<?php

namespace App\Console\Commands;

use App\Services\ShopSyncNotifier;
use App\Services\ShopSyncSentryReporter;
use Illuminate\Console\Command;
use Throwable;

class TestShopSyncNotification extends Command
{
    protected $signature = 'shops:test-sync-notification';

    protected $description = '店舗情報を変更せず、Sentryに通知設定の確認用イベントを送信する';

    public function handle(ShopSyncNotifier $notifier, ShopSyncSentryReporter $reporter): int
    {
        if (! $notifier->isConfigured()) {
            $this->error('SHOP_SYNC_SENTRY_DSNを設定してください。');

            return self::FAILURE;
        }

        try {
            $reporter->sendTest();
        } catch (Throwable) {
            $this->error('Sentryへのテスト通知の送信に失敗しました。');

            return self::FAILURE;
        }

        $this->info('テスト通知をSentryへ送信しました。Sentryのイベントとアラートメールを確認してください。');

        return self::SUCCESS;
    }
}
