<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Models\ShopSyncState;
use App\Services\KonamiFacilityScraper;
use App\Services\ShopSyncNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncKonamiShops extends Command
{
    private const MISSING_DAYS_BEFORE_HIDING = 5;

    protected $signature = 'shops:sync-konami {--dry-run : 変更候補を表示し、店舗情報を更新しない}';

    protected $description = 'KONAMI公式検索で5日連続見つからない店舗を非公開にし、掲載復帰時に再公開する';

    public function handle(KonamiFacilityScraper $scraper, ShopSyncNotifier $notifier): int
    {
        if (Schema::hasTable('shop_sync_notifications')) {
            $this->info('通知テーブル: 準備済み。');
        } elseif ($this->option('dry-run')) {
            $this->warn('通知テーブルが未作成です。通常の同期前にマイグレーションを実行してください。今回は変更候補のみ確認します。');
        } else {
            $this->error('通知テーブルが未作成です。マイグレーションを実行してください。店舗情報は変更していません。');

            return self::FAILURE;
        }

        $hasStateTable = Schema::hasTable('shop_sync_states');
        if ($hasStateTable) {
            $this->info('同期履歴テーブル: 準備済み。');
        } elseif ($this->option('dry-run')) {
            $this->warn('同期履歴テーブルが未作成です。今回は未掲載の確認を初日として候補を表示します。');
        } else {
            $this->error('同期履歴テーブルが未作成です。マイグレーションを実行してください。店舗情報は変更していません。');

            return self::FAILURE;
        }

        try {
            // 取得途中の失敗を0件と誤認しないよう、全県の取得後にDBに触れる。
            $snapshot = $scraper->fetch();
        } catch (Throwable $exception) {
            $this->error('公式検索の取得に失敗しました。店舗情報は変更していません。');
            $this->error($exception->getMessage());

            if (! $this->option('dry-run')) {
                $this->sendNotifications($notifier);
            }

            return self::FAILURE;
        }

        $officialNames = [];
        foreach ($snapshot as $code => $names) {
            $officialNames[$code] = array_fill_keys(array_map(
                KonamiFacilityScraper::normalizeShopName(...), $names
            ), true);
        }

        $checkedAt = CarbonImmutable::now('Asia/Tokyo');
        $today = $checkedAt->toDateString();
        $yesterday = $checkedAt->subDay()->toDateString();

        [$candidates, $restorationCandidates, $watching, $skipped, $updated, $republished] = DB::transaction(function () use ($officialNames, $notifier, $hasStateTable, $today, $yesterday) {
            $candidates = [];
            $restorationCandidates = [];
            $watching = [];
            $notificationShops = [];
            $skipped = [];
            $updated = 0;
            $republished = 0;

            // 取得中の管理画面での編集も反映し、更新中の競合を防ぐ。
            $query = Shop::query();
            if ($hasStateTable) {
                $query->where(function ($query) {
                    $query->where('is_deleted', false)
                        ->orWhereHas('syncState', fn ($state) => $state->whereNotNull('hidden_at'));
                })->with('syncState');
            } else {
                $query->where('is_deleted', false);
            }
            $shops = $query->orderBy('id')->lockForUpdate()->get();

            foreach ($shops as $shop) {
                $code = $this->prefectureCodeFor($shop);
                $name = KonamiFacilityScraper::normalizeShopName($shop->name);

                if ($code === null || $name === '' || ! array_key_exists($code, $officialNames)) {
                    $skipped[] = [$shop->id, $shop->name];

                    continue;
                }

                $state = ($hasStateTable ? $shop->syncState : null) ?? new ShopSyncState([
                    'shop_id' => $shop->id,
                    'consecutive_missing_days' => 0,
                ]);
                $row = [$shop->id, config("prefectures.{$code}"), $shop->name];

                if (isset($officialNames[$code][$name])) {
                    if ($state->hidden_at !== null && (bool) $shop->is_deleted) {
                        $restorationCandidates[] = $row;
                        if (! $this->option('dry-run')) {
                            $shop->update(['is_deleted' => false]);
                            $republished++;
                        }
                    }
                    $state->consecutive_missing_days = 0;
                    $state->hidden_at = null;
                } else {
                    $lastCheckedOn = $state->last_checked_on?->toDateString();
                    $previousDays = min(self::MISSING_DAYS_BEFORE_HIDING, max(0, $state->consecutive_missing_days));
                    // 再実行は同じ日の確認として扱い、未観測の日を不在とは推測しない。
                    $state->consecutive_missing_days = match ($lastCheckedOn) {
                        $today => max(1, $previousDays),
                        $yesterday => min(self::MISSING_DAYS_BEFORE_HIDING, $previousDays + 1),
                        default => 1,
                    };

                    if (! (bool) $shop->is_deleted) {
                        if ($state->consecutive_missing_days >= self::MISSING_DAYS_BEFORE_HIDING) {
                            $candidates[] = $row;
                            if (! $this->option('dry-run')) {
                                $shop->update(['is_deleted' => true]);
                                $state->hidden_at = now();
                                $updated++;
                                $notificationShops[] = [
                                    'id' => $shop->id,
                                    'name' => $shop->name,
                                    'prefecture' => config("prefectures.{$code}"),
                                    'address' => $shop->address,
                                    'edit_url' => rtrim(config('shop-sync.admin_url'), '/')."/admin/shops/{$shop->id}/edit",
                                    'consecutive_missing_days' => $state->consecutive_missing_days,
                                    'last_checked_on' => $today,
                                ];
                            }
                        } else {
                            $watching[] = [...$row, $state->consecutive_missing_days.'/'.self::MISSING_DAYS_BEFORE_HIDING];
                        }
                    }
                }

                $state->last_checked_on = $today;
                if (! $this->option('dry-run') && (! $state->exists || $state->isDirty())) {
                    $state->save();
                }
            }

            if ($updated > 0) {
                $notifier->record($notificationShops);
            }

            return [$candidates, $restorationCandidates, $watching, $skipped, $updated, $republished];
        });

        $this->info(sprintf('公式検索: %d都道府県 / %d店舗', count($snapshot), array_sum(array_map('count', $snapshot))));

        if ($candidates !== []) {
            $this->table(['ID', '都道府県', '非公開化対象の店舗名'], $candidates);
        }

        if ($restorationCandidates !== []) {
            $this->table(['ID', '都道府県', '再公開対象の店舗名'], $restorationCandidates);
        }

        if ($watching !== []) {
            $this->info('非公開化待ち '.count($watching).'件（5日連続の未掲載を確認中）。');
            $this->table(['ID', '都道府県', '確認中の店舗名', '連続未掲載日数'], $watching);
        }

        if ($skipped !== []) {
            $this->warn('都道府県または店舗名を判定できないため、'.count($skipped).'件をスキップしました。');
            $this->table(['ID', 'スキップした店舗名'], $skipped);
        }

        if ($this->option('dry-run')) {
            $this->info('確認のみ: 非公開化候補 '.count($candidates).'件。店舗情報は変更していません。');
            $this->info('確認のみ: 再公開候補 '.count($restorationCandidates).'件。店舗情報は変更していません。');
        } else {
            $this->info($updated.'件を非公開にしました。');
            $this->info($republished.'件を再公開しました。');

            if (! $this->sendNotifications($notifier)) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function sendNotifications(ShopSyncNotifier $notifier): bool
    {
        try {
            $sent = $notifier->sendPending();

            if ($sent > 0) {
                $this->info($sent.'件の通知をSentryへ送信しました。');
            }

            if (! $notifier->isConfigured() && $notifier->pendingCount() > 0) {
                $this->warn('SentryのDSNが未設定のため、通知を保存しました。設定後の実行時に送信します。');
            }

            return true;
        } catch (Throwable) {
            $this->error('非公開化の通知をSentryへ送信できませんでした。通知は保存されているため、次回再送します。');

            return false;
        }
    }

    private function prefectureCodeFor(Shop $shop): ?int
    {
        if ($shop->prefecture_code !== null && config("prefectures.{$shop->prefecture_code}") !== null) {
            return (int) $shop->prefecture_code;
        }

        foreach (config('prefectures') as $code => $name) {
            if (str_starts_with($shop->address, $name)) {
                return $code;
            }
        }

        return null;
    }
}
