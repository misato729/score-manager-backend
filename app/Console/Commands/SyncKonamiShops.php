<?php

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\KonamiFacilityScraper;
use App\Services\ShopSyncNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SyncKonamiShops extends Command
{
    protected $signature = 'shops:sync-konami {--dry-run : 変更候補を表示し、店舗情報を更新しない}';

    protected $description = 'KONAMI公式検索から消えた公開店舗を非公開にする';

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

        [$candidates, $skipped, $updated] = DB::transaction(function () use ($officialNames, $notifier) {
            $candidates = [];
            $notificationShops = [];
            $skipped = [];
            $ids = [];

            // 取得中の管理画面での編集も反映し、更新中の競合を防ぐ。
            $shops = Shop::where('is_deleted', false)->orderBy('id')->lockForUpdate()->get();

            foreach ($shops as $shop) {
                $code = $this->prefectureCodeFor($shop);
                $name = KonamiFacilityScraper::normalizeShopName($shop->name);

                if ($code === null || $name === '' || ! array_key_exists($code, $officialNames)) {
                    $skipped[] = [$shop->id, $shop->name];

                    continue;
                }

                if (! isset($officialNames[$code][$name])) {
                    $ids[] = $shop->id;
                    $candidates[] = [$shop->id, config("prefectures.{$code}"), $shop->name];
                    $notificationShops[] = [
                        'id' => $shop->id,
                        'name' => $shop->name,
                        'prefecture' => config("prefectures.{$code}"),
                        'address' => $shop->address,
                        'edit_url' => rtrim(config('shop-sync.admin_url'), '/')."/admin/shops/{$shop->id}/edit",
                    ];
                }
            }

            $updated = $this->option('dry-run') || $ids === []
                ? 0
                : Shop::whereKey($ids)->where('is_deleted', false)->update(['is_deleted' => true]);

            if ($updated > 0) {
                $notifier->record($notificationShops);
            }

            return [$candidates, $skipped, $updated];
        });

        $this->info(sprintf('公式検索: %d都道府県 / %d店舗', count($snapshot), array_sum(array_map('count', $snapshot))));

        if ($candidates !== []) {
            $this->table(['ID', '都道府県', '非公開化対象の店舗名'], $candidates);
        }

        if ($skipped !== []) {
            $this->warn('都道府県または店舗名を判定できないため、'.count($skipped).'件をスキップしました。');
            $this->table(['ID', 'スキップした店舗名'], $skipped);
        }

        if ($this->option('dry-run')) {
            $this->info('確認のみ: 非公開化候補 '.count($candidates).'件。店舗情報は変更していません。');
        } else {
            $this->info($updated.'件を非公開にしました。');

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
