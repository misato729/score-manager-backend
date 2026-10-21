<?php

namespace App\Services;

use App\Models\ShopSyncNotification;
use Illuminate\Support\Str;
use RuntimeException;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventId;
use Sentry\Severity;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Throwable;

class ShopSyncSentryReporter
{
    public const OFFICIAL_SEARCH_URL = 'https://p.eagate.573.jp/game/facility/search/p/index.html?gkey=REFLECC';

    public function __construct(private ?TransportInterface $transport = null) {}

    public function send(ShopSyncNotification $notification): void
    {
        $shops = array_map(fn (array $shop) => array_intersect_key($shop, array_flip([
            'id', 'name', 'prefecture', 'address', 'edit_url',
        ])), $notification->shops);
        $date = ($notification->created_at ?? now())->copy()->setTimezone('Asia/Tokyo');
        $lines = [
            '設置店舗の自動確認で'.count($shops).'店舗を非公開にしました。',
            '日時: '.$date->format('Y-m-d H:i:s').'（日本時間）',
            '件数: '.count($shops).'店舗',
            '',
        ];

        foreach ($shops as $index => $shop) {
            $lines[] = ($index + 1).'. '.$shop['name'].'（'.$shop['prefecture'].' / ID: '.$shop['id'].'）';
            $lines[] = '住所: '.$shop['address'];
            $lines[] = '店舗編集: '.$shop['edit_url'];
            $lines[] = '';
        }

        $lines[] = '公式検索から消えた理由は、撤去のほか店舗名の変更である可能性があります。';
        $lines[] = '店舗名が変わった場合は、公式名称に修正して手動で再公開してください。';
        $lines[] = 'KONAMI公式検索: '.self::OFFICIAL_SEARCH_URL;
        $extra = [
            'notification_id' => (string) $notification->getKey(),
            'hidden_at' => $date->toIso8601String(),
            'hidden_shop_count' => count($shops),
            'shops' => $shops,
            'official_search_url' => self::OFFICIAL_SEARCH_URL,
        ];
        $this->addRunUrl($lines, $extra);

        $this->sendEvent((string) $notification->getKey(), implode("\n", $lines), $extra, false);
    }

    public function sendTest(): void
    {
        $date = now('Asia/Tokyo');
        $lines = [
            '[テスト通知] 設置店舗の非公開通知',
            '通知設定の確認です。店舗の公開状態は変更していません。',
            '日時: '.$date->format('Y-m-d H:i:s').'（日本時間）',
            'KONAMI公式検索: '.self::OFFICIAL_SEARCH_URL,
        ];
        $extra = ['is_test' => true, 'official_search_url' => self::OFFICIAL_SEARCH_URL];
        $this->addRunUrl($lines, $extra);

        $this->sendEvent((string) Str::uuid(), implode("\n", $lines), $extra, true);
    }

    private function addRunUrl(array &$lines, array &$extra): void
    {
        $runUrl = config('shop-sync.github_run_url');
        if (is_string($runUrl) && $runUrl !== '') {
            $lines[] = '実行ログ: '.$runUrl;
            $extra['github_run_url'] = $runUrl;
        }
    }

    private function sendEvent(string $id, string $message, array $extra, bool $isTest): void
    {
        try {
            $event = Event::createEvent(new EventId(str_replace('-', '', $id)))
                ->setLevel(Severity::warning())
                ->setEnvironment(config('app.env'))
                ->setLogger('shop_sync')
                ->setMessage($message)
                ->setFingerprint(['shop-sync-hidden', $id])
                ->setTags([
                    'operation' => 'shop_sync',
                    'event_type' => 'shops_hidden',
                    'is_test' => $isTest ? 'true' : 'false',
                ])
                ->setExtra($extra);
            $transport = $this->transport ?? ClientBuilder::create([
                'dsn' => config('shop-sync.sentry_dsn'),
                'default_integrations' => false,
                'send_default_pii' => false,
                'http_timeout' => 10,
                'http_connect_timeout' => 5,
            ])->getTransport();

            if ($transport->send($event)->getStatus() !== ResultStatus::success()) {
                throw new RuntimeException('Sentry did not accept the notification.');
            }
        } catch (Throwable) {
            // SDK and transport exceptions can contain DSNs; keep command logs free of credentials.
            throw new RuntimeException('Sentryへの店舗非公開通知の送信に失敗しました。');
        }
    }
}
