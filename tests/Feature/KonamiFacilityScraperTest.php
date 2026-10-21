<?php

namespace Tests\Feature;

use App\Services\KonamiFacilityScraper;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class KonamiFacilityScraperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_fetches_official_html_and_returns_all_47_prefectures_including_empty_regions(): void
    {
        $this->fakeSource($this->manifest([13]), [1 => $this->fixture('tokyo.html')]);

        $snapshot = app(KonamiFacilityScraper::class)->fetch();

        $this->assertSame(range(1, 47), array_keys($snapshot));
        $this->assertSame(['タイトーステーション池袋ロサ店', 'ウェアハウス入谷店'], $snapshot[13]);
        $this->assertSame([], $snapshot[5]);
        $this->assertSame([], $snapshot[47]);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/p/list.html?')
            && $request['gkey'] === 'REFLECC' && $request['paselif'] === 'false'
            && $request['area'] === 'AR-02' && $request['pref'] === 'JP-13'
            && $request['finder'] === 'area');
    }

    public function test_accepts_an_entirely_empty_valid_manifest(): void
    {
        $this->fakeSource($this->manifest(), []);

        $this->assertSame(array_fill(1, 47, []), app(KonamiFacilityScraper::class)->fetch());
        Http::assertSentCount(1);
    }

    public function test_accepts_an_explicit_empty_search_result(): void
    {
        $this->fakeSource($this->manifest([5]), [1 => $this->fixture('empty.html')]);

        $this->assertSame([], app(KonamiFacilityScraper::class)->fetch()[5]);
        Http::assertSentCount(2);
    }

    public function test_decodes_html_entities_in_shop_names_without_changing_kana(): void
    {
        $html = str_replace('タイトーステーション池袋ロサ店', 'Ａ＆Ｂ &quot;新館&quot; カナ店', $this->fixture('tokyo.html'));
        $this->fakeSource($this->manifest([13]), [1 => $html]);

        $this->assertSame('Ａ＆Ｂ "新館" カナ店', app(KonamiFacilityScraper::class)->fetch()[13][0]);
    }

    public function test_normalizes_fullwidth_ascii_and_whitespace_without_loosening_other_characters(): void
    {
        $this->assertSame('ABC123&- 新館 店', KonamiFacilityScraper::normalizeShopName(" \tＡＢＣ１２３＆－　新館\n\t店 \r\n"));
        $this->assertNotSame(
            KonamiFacilityScraper::normalizeShopName('カナ店'),
            KonamiFacilityScraper::normalizeShopName('ｶﾅ店')
        );
    }

    #[DataProvider('invalidManifestCases')]
    public function test_rejects_incomplete_or_unexpected_manifests(string $case): void
    {
        $manifest = $this->manifest();
        switch ($case) {
            case 'status': $manifest['preflist']['status'] = 'error';
                break;
            case 'game': $manifest['preflist']['modelname'] = 'beatmania IIDX';
                break;
            case 'filtered': $manifest['preflist']['prefid'] = 'JP-13';
                break;
            case 'missing': array_shift($manifest['preflist']['prefinfo']);
                break;
            case 'duplicate': $manifest['preflist']['prefinfo'][] = $manifest['preflist']['prefinfo'][0];
                break;
            case 'name': $manifest['preflist']['prefinfo'][0]['name'] = '東京都';
                break;
            case 'area': $manifest['preflist']['prefinfo'][0]['area_id'] = 'AR-07';
                break;
            case 'boolean': $manifest['preflist']['prefinfo'][0]['exist'] = false;
                break;
            case 'missing_area': array_shift($manifest['arealist']['areainfo']);
                break;
            case 'inconsistent_area': $manifest['preflist']['prefinfo'][0]['exist'] = 'true';
                break;
        }
        $this->fakeSource($manifest, []);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    public static function invalidManifestCases(): array
    {
        return array_combine(
            $cases = ['status', 'game', 'filtered', 'missing', 'duplicate', 'name', 'area', 'boolean', 'missing_area', 'inconsistent_area'],
            array_map(fn ($case) => [$case], $cases)
        );
    }

    public function test_does_not_treat_non_json_response_as_empty_data(): void
    {
        Http::fake(['*' => Http::response('<html>Maintenance</html>')]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    #[DataProvider('invalidResultCases')]
    public function test_rejects_incomplete_or_filtered_search_results(string $case): void
    {
        $html = $this->fixture('tokyo.html');
        $html = match ($case) {
            'challenge' => '<html>Please enable JavaScript</html>',
            'prefecture' => str_replace('id="id_pref" type="hidden" value="JP-13"', 'id="id_pref" type="hidden" value="JP-14"', $html),
            'paseli' => str_replace('id="id_paselif"', 'checked id="id_paselif"', $html),
            'keyword' => str_replace('id="id_keyword_hdn" type="hidden" value=""', 'id="id_keyword_hdn" type="hidden" value="東京"', $html),
            'count' => str_replace('2件の店舗が見つかりました。', '3件の店舗が見つかりました。', $html),
            'duplicate' => str_replace('data-fdesc="21ca8bbf5d3d7cf8c40a9b07e4a82ecf"', 'data-fdesc="757b54872be03931177cfed81f47f637"', $html),
            'empty_name' => str_replace('data-name="ウェアハウス入谷店"', 'data-name=""', $html),
            'missing_count' => str_replace('cl_search_result', 'unknown_result', $html),
        };
        $this->fakeSource($this->manifest([13]), [1 => $html]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    public static function invalidResultCases(): array
    {
        return array_combine(
            $cases = ['challenge', 'prefecture', 'paseli', 'keyword', 'count', 'duplicate', 'empty_name', 'missing_count'],
            array_map(fn ($case) => [$case], $cases)
        );
    }

    public function test_requires_an_explicit_empty_message_for_a_zero_result_page(): void
    {
        $html = str_replace('店舗が見つかりませんでした｡', 'メンテナンス中です。', $this->fixture('empty.html'));
        $this->fakeSource($this->manifest([5]), [1 => $html]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    public function test_collects_every_page_including_pages_reached_from_later_pagers(): void
    {
        $this->fakeSource($this->manifest([13]), [
            1 => $this->page(1, 'first', '店舗1', [2]),
            2 => $this->page(2, 'second', '店舗2', [1, 3]),
            3 => $this->page(3, 'third', '店舗3', [1, 2]),
        ]);

        $this->assertSame(['店舗1', '店舗2', '店舗3'], app(KonamiFacilityScraper::class)->fetch()[13]);
        Http::assertSentCount(4);
    }

    public function test_rejects_duplicate_results_across_pages(): void
    {
        $this->fakeSource($this->manifest([13]), [
            1 => $this->page(1, 'first', '店舗1', [2]),
            2 => $this->page(2, 'first', '店舗1', [3]),
        ]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    public function test_rejects_changed_total_counts_between_pages(): void
    {
        $this->fakeSource($this->manifest([13]), [
            1 => $this->page(1, 'first', '店舗1', [2]),
            2 => str_replace('3件の店舗', '2件の店舗', $this->page(2, 'second', '店舗2', [])),
        ]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    public function test_rejects_replayed_first_page_when_a_later_page_was_requested(): void
    {
        $this->fakeSource($this->manifest([13]), [
            1 => $this->page(1, 'first', '店舗1', [2]),
            2 => $this->page(1, 'second', '店舗2', []),
        ]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    #[DataProvider('invalidPagerCases')]
    public function test_rejects_changed_pager_filters_and_out_of_bound_pages(string $replacement): void
    {
        $html = str_replace('page=2', $replacement, $this->page(1, 'first', '店舗1', [2]));
        $this->fakeSource($this->manifest([13]), [1 => $html]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    public static function invalidPagerCases(): array
    {
        return [['page=101'], ['page=0'], ['page=2&keyword=東京'], ['page[]=2'], ['page=2&pref=JP-14']];
    }

    public function test_http_errors_abort_fetch_instead_of_returning_empty_results(): void
    {
        Http::fake(['*' => Http::response('Service unavailable', 503)]);

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    public function test_connection_errors_abort_fetch_instead_of_returning_empty_results(): void
    {
        Http::fake(fn () => throw new ConnectionException('Timed out'));

        $this->expectException(RuntimeException::class);
        app(KonamiFacilityScraper::class)->fetch();
    }

    private function manifest(array $existing = []): array
    {
        $manifest = json_decode($this->fixture('areas.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['preflist']['prefinfo'] as &$prefecture) {
            $prefecture['exist'] = in_array((int) substr($prefecture['id'], 3), $existing, true) ? 'true' : 'false';
        }
        unset($prefecture);
        foreach ($manifest['arealist']['areainfo'] as &$area) {
            $area['exist'] = collect($manifest['preflist']['prefinfo'])->contains(
                fn ($prefecture) => $prefecture['area_id'] === $area['id'] && $prefecture['exist'] === 'true'
            ) ? 'true' : 'false';
        }

        return $manifest;
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/Fixtures/konami/'.$name));
    }

    private function fakeSource(array $manifest, array $pages): void
    {
        Http::fake(function (Request $request) use ($manifest, $pages) {
            if (str_contains($request->url(), '/json/facilityarealist.html?')) {
                return Http::response($manifest);
            }

            return Http::response($pages[(int) ($request['page'] ?? 1)] ?? 'Missing fixture', 200);
        });
    }

    private function page(int $number, string $id, string $name, array $links): string
    {
        $html = '<html><body><div id="id_page_body"><div class="cl_game_title">REFLEC BEAT</div>'
            .'<input id="id_paselif" type="checkbox"><input id="id_keyword_hdn" value="">'
            .'<div class="cl_search_result">3件の店舗が見つかりました。</div>'
            .'<div class="cl_shop_bloc" data-fdesc="'.$id.'" data-name="'.$name.'"></div>'
            .'<input id="id_gkey" value="REFLECC"><input id="id_area" value="AR-02">'
            .'<input id="id_pref" value="JP-13"><input id="id_finder" value="area">'
            .'<input id="id_page" value="'.($number === 1 ? '' : $number).'">'
            .'<div id="id_pager_bloc">';
        foreach ($links as $link) {
            $html .= '<a href="/game/facility/search/p/list.html?gkey=REFLECC&amp;paselif=false&amp;area=AR-02&amp;pref=JP-13&amp;finder=area&page='.$link.'">'.$link.'</a>';
        }

        return $html.'</div></div></body></html>';
    }
}
