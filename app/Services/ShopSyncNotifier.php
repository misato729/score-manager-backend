<?php

namespace App\Services;

use App\Models\ShopSyncNotification;
use Illuminate\Support\Facades\DB;

class ShopSyncNotifier
{
    public function __construct(private ShopSyncSentryReporter $reporter) {}

    public function record(array $shops): void
    {
        if ($shops !== []) {
            // 店舗の非公開化と同じトランザクションで保存する。
            ShopSyncNotification::create(['shops' => $shops]);
        }
    }

    public function isConfigured(): bool
    {
        return filled(config('shop-sync.sentry_dsn'));
    }

    public function pendingCount(): int
    {
        return ShopSyncNotification::whereNull('sent_at')->count();
    }

    public function sendPending(): int
    {
        if (! $this->isConfigured()) {
            return 0;
        }

        $sent = 0;
        $ids = ShopSyncNotification::whereNull('sent_at')->orderBy('created_at')->pluck('id');

        foreach ($ids as $id) {
            $sent += DB::transaction(function () use ($id) {
                // 通知行だけをロックし、同時実行による重複送信を防ぐ。
                $notification = ShopSyncNotification::whereKey($id)->lockForUpdate()->first();

                if ($notification === null || $notification->sent_at !== null) {
                    return 0;
                }

                $this->reporter->send($notification);
                $notification->update(['sent_at' => now()]);

                return 1;
            });
        }

        return $sent;
    }
}
