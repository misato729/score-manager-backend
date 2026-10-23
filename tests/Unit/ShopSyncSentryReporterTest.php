<?php

namespace Tests\Unit;

use App\Models\ShopSyncNotification;
use App\Services\ShopSyncSentryReporter;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Sentry\Event;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportInterface;
use Tests\TestCase;

class ShopSyncSentryReporterTest extends TestCase
{
    public function test_sends_a_warning_with_the_complete_persisted_shop_snapshot_and_review_links(): void
    {
        config([
            'app.env' => 'production',
            'shop-sync.sentry_dsn' => 'private-dsn-must-not-appear-in-event',
            'shop-sync.github_run_url' => 'https://github.com/example/repo/actions/runs/123',
        ]);
        $notification = $this->notification();
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(function (Event $event) use ($notification) {
            $this->assertSame('warning', (string) $event->getLevel());
            $this->assertSame('production', $event->getEnvironment());
            $this->assertSame('shop_sync', $event->getLogger());
            $this->assertEqualsWithDelta(microtime(true), $event->getTimestamp(), 2);
            $this->assertSame(['shop-sync-hidden', $notification->id], $event->getFingerprint());
            $this->assertSame([
                'operation' => 'shop_sync', 'event_type' => 'shops_hidden', 'is_test' => 'false',
            ], $event->getTags());

            foreach ([
                '公式検索で5日連続の未掲載を確認し、2店舗を非公開にしました', '2026-10-21 17:15:00', '件数: 2店舗',
                '東京ゲーム店', '東京都', 'ID: 13', '東京都豊島区', 'https://example.test/admin/shops/13/edit',
                '北海道ゲーム店', '北海道', 'ID: 1', '北海道札幌市', 'https://example.test/admin/shops/1/edit',
                '一時的な未掲載', '店舗名の変更', '同じ都道府県・店舗名で公式検索に復帰した場合は、自動で再公開',
                '公式名称に修正して手動で再公開', ShopSyncSentryReporter::OFFICIAL_SEARCH_URL,
                'https://github.com/example/repo/actions/runs/123',
            ] as $text) {
                $this->assertStringContainsString($text, $event->getMessage());
            }
            $extra = $event->getExtra();
            $this->assertSame($notification->id, $extra['notification_id']);
            $this->assertSame('2026-10-21T17:15:00+09:00', $extra['hidden_at']);
            $this->assertSame(2, $extra['hidden_shop_count']);
            $this->assertCount(2, $extra['shops']);
            $this->assertSame('東京ゲーム店', $extra['shops'][0]['name']);
            $this->assertSame(5, $extra['shops'][0]['consecutive_missing_days']);
            $this->assertSame('2026-10-21', $extra['shops'][0]['last_checked_on']);
            $this->assertSame(6, $extra['shops'][1]['consecutive_missing_days']);
            $this->assertArrayNotHasKey('unrelated_private_field', $extra['shops'][0]);
            $this->assertArrayNotHasKey('recipient', $extra);
            $this->assertStringNotContainsString('private-dsn-must-not-appear-in-event', json_encode($extra));
            $this->assertNull($event->getUser());
            $this->assertSame([], $event->getRequest());
            $this->assertSame([], $event->getBreadcrumbs());

            return new Result(ResultStatus::success(), $event);
        });

        (new ShopSyncSentryReporter($transport))->send($notification);
    }

    #[DataProvider('snapshotsWithoutFiveConfirmedDays')]
    public function test_does_not_infer_five_days_or_automatic_republication_from_older_or_incomplete_snapshots(array $missingDays): void
    {
        $notification = $this->notification();
        $notification->shops = array_map(function (array $shop, ?int $days) {
            if ($days === null) {
                unset($shop['consecutive_missing_days'], $shop['last_checked_on']);
            } else {
                $shop['consecutive_missing_days'] = $days;
            }

            return $shop;
        }, $notification->shops, $missingDays);
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(function (Event $event) {
            $this->assertStringStartsWith('設置店舗の自動確認で2店舗を非公開にしました。', $event->getMessage());
            $this->assertStringNotContainsString('5日連続', $event->getMessage());
            $this->assertStringNotContainsString('自動で再公開', $event->getMessage());
            $this->assertStringContainsString('営業・稼働状況を確認し、必要に応じて管理画面から手動で再公開してください。', $event->getMessage());
            $this->assertStringContainsString('公式名称に修正して手動で再公開してください。', $event->getMessage());

            return new Result(ResultStatus::success(), $event);
        });

        (new ShopSyncSentryReporter($transport))->send($notification);
    }

    public static function snapshotsWithoutFiveConfirmedDays(): array
    {
        return [
            'legacy_snapshot' => [[null, null]],
            'mixed_snapshot' => [[5, null]],
            'incomplete_confirmation' => [[5, 4]],
        ];
    }

    public function test_retries_reuse_the_event_id_and_fingerprint_but_each_new_batch_gets_a_new_issue(): void
    {
        config(['shop-sync.github_run_url' => null]);
        $events = [];
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->exactly(3))->method('send')->willReturnCallback(function (Event $event) use (&$events) {
            $events[] = $event;

            return new Result(ResultStatus::success(), $event);
        });
        $reporter = new ShopSyncSentryReporter($transport);
        $first = $this->notification();
        $second = $this->notification('01a15d75-9bd5-7770-b519-5999ae771110');

        $reporter->send($first);
        $reporter->send($first);
        $reporter->send($second);

        $this->assertSame(str_replace('-', '', $first->id), (string) $events[0]->getId());
        $this->assertSame((string) $events[0]->getId(), (string) $events[1]->getId());
        $this->assertSame($events[0]->getFingerprint(), $events[1]->getFingerprint());
        $this->assertSame($events[0]->getMessage(), $events[1]->getMessage());
        $this->assertNotSame((string) $events[0]->getId(), (string) $events[2]->getId());
        $this->assertNotSame($events[0]->getFingerprint(), $events[2]->getFingerprint());
        $this->assertArrayNotHasKey('github_run_url', $events[0]->getExtra());
        $this->assertStringNotContainsString('実行ログ:', $events[0]->getMessage());
    }

    #[DataProvider('unsuccessfulStatuses')]
    public function test_only_a_successful_transport_acknowledgement_counts_as_sent(ResultStatus $status): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(new Result($status));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Sentryへの店舗非公開通知の送信に失敗しました。');
        (new ShopSyncSentryReporter($transport))->send($this->notification());
    }

    public static function unsuccessfulStatuses(): array
    {
        return [
            'rate_limited_429' => [ResultStatus::createFromHttpStatusCode(429)],
            'server_failure_500' => [ResultStatus::createFromHttpStatusCode(500)],
            'invalid_request_400' => [ResultStatus::createFromHttpStatusCode(400)],
            'payload_too_large_413' => [ResultStatus::createFromHttpStatusCode(413)],
            'skipped' => [ResultStatus::skipped()],
            'unknown' => [ResultStatus::unknown()],
        ];
    }

    public function test_transport_exceptions_fail_without_exposing_secrets_in_exception_messages_or_chains(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')
            ->willThrowException(new RuntimeException('Failed https://private-token@example.test/1'));

        try {
            (new ShopSyncSentryReporter($transport))->send($this->notification());
            $this->fail('Transport exceptions must fail the notification.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sentryへの店舗非公開通知の送信に失敗しました。', $exception->getMessage());
            $this->assertStringNotContainsString('private-token', (string) $exception);
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_invalid_sdk_configuration_fails_without_exposing_the_dsn(): void
    {
        config(['shop-sync.sentry_dsn' => 'not-a-dsn-private-token']);

        try {
            (new ShopSyncSentryReporter)->send($this->notification());
            $this->fail('Invalid configuration must fail the notification.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Sentryへの店舗非公開通知の送信に失敗しました。', $exception->getMessage());
            $this->assertStringNotContainsString('private-token', (string) $exception);
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_sends_clearly_marked_test_events_without_shop_records_and_with_unique_ids(): void
    {
        $events = [];
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->exactly(2))->method('send')->willReturnCallback(function (Event $event) use (&$events) {
            $events[] = $event;

            return new Result(ResultStatus::success(), $event);
        });
        $reporter = new ShopSyncSentryReporter($transport);

        $reporter->sendTest();
        $reporter->sendTest();

        foreach ($events as $event) {
            $this->assertStringStartsWith('[テスト通知]', $event->getMessage());
            $this->assertStringContainsString('通知設定の確認です。店舗の公開状態は変更していません。', $event->getMessage());
            $this->assertSame('warning', (string) $event->getLevel());
            $this->assertSame('shop_sync', $event->getTags()['operation']);
            $this->assertSame('shops_hidden', $event->getTags()['event_type']);
            $this->assertSame('true', $event->getTags()['is_test']);
            $this->assertTrue($event->getExtra()['is_test']);
            $this->assertArrayNotHasKey('shops', $event->getExtra());
            $this->assertArrayNotHasKey('notification_id', $event->getExtra());
            $this->assertNull($event->getUser());
        }
        $this->assertNotSame((string) $events[0]->getId(), (string) $events[1]->getId());
        $this->assertNotSame($events[0]->getFingerprint(), $events[1]->getFingerprint());
    }

    public function test_test_notifications_also_require_transport_acceptance(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(new Result(ResultStatus::rateLimit()));

        $this->expectException(RuntimeException::class);
        (new ShopSyncSentryReporter($transport))->sendTest();
    }

    private function notification(string $id = '01a15d75-9bd5-7770-b519-5999ae771109'): ShopSyncNotification
    {
        $notification = new ShopSyncNotification([
            'shops' => [
                [
                    'id' => 13, 'name' => '東京ゲーム店', 'prefecture' => '東京都', 'address' => '東京都豊島区',
                    'edit_url' => 'https://example.test/admin/shops/13/edit',
                    'consecutive_missing_days' => 5, 'last_checked_on' => '2026-10-21',
                    'unrelated_private_field' => 'must not be sent',
                ],
                [
                    'id' => 1, 'name' => '北海道ゲーム店', 'prefecture' => '北海道', 'address' => '北海道札幌市',
                    'edit_url' => 'https://example.test/admin/shops/1/edit',
                    'consecutive_missing_days' => 6, 'last_checked_on' => '2026-10-21',
                ],
            ],
        ]);
        $notification->id = $id;
        $notification->created_at = '2026-10-21 08:15:00';

        return $notification;
    }
}
