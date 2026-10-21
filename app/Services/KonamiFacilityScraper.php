<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class KonamiFacilityScraper
{
    private const BASE_URL = 'https://p.eagate.573.jp';

    private const LIST_PATH = '/game/facility/search/p/list.html';

    private const MAX_PAGES = 100;

    /** @return array<int, list<string>> */
    public function fetch(): array
    {
        $manifest = $this->request('/game/facility/search/json/facilityarealist.html', [
            'gkey' => 'REFLECC', 'paselif' => 'false',
        ]);
        $prefectures = $this->parseManifest($manifest);
        $snapshot = [];

        foreach ($prefectures as $code => $prefecture) {
            $snapshot[$code] = $prefecture['exist'] === 'true'
                ? $this->fetchPrefecture($prefecture)
                : [];
        }

        return $snapshot;
    }

    public static function normalizeShopName(string $name): string
    {
        $name = mb_convert_kana($name, 'as', 'UTF-8');

        return trim(preg_replace('/[\s\p{Z}]+/u', ' ', $name) ?? $name);
    }

    private function request(string $path, array $query): string
    {
        if (! app()->runningUnitTests()) {
            usleep(250000);
        }

        try {
            return Http::connectTimeout(10)->timeout(30)->retry(3, 250)
                ->get(self::BASE_URL.$path, $query)->throw()->body();
        } catch (Throwable $exception) {
            throw new RuntimeException('KONAMI設置店舗情報の取得に失敗しました。', 0, $exception);
        }
    }

    private function parseManifest(string $body): array
    {
        try {
            $manifest = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw new RuntimeException('KONAMI都道府県一覧が正しいJSONではありません。', 0, $exception);
        }

        foreach (['arealist', 'preflist'] as $key) {
            if (($manifest[$key]['status'] ?? null) !== 'ok'
                || ($manifest[$key]['modelname'] ?? null) !== 'REFLEC BEAT'
                || ($manifest[$key]['areaid'] ?? null) !== '') {
                throw new RuntimeException('KONAMI都道府県一覧の検索条件を確認できません。');
            }
        }

        if (($manifest['preflist']['prefid'] ?? null) !== ''
            || ! is_array($manifest['preflist']['prefinfo'] ?? null)
            || ! is_array($manifest['arealist']['areainfo'] ?? null)) {
            throw new RuntimeException('KONAMI都道府県一覧の形式が変わっています。');
        }

        $expectedAreas = [
            'AR-01' => '北海道・東北', 'AR-02' => '関東', 'AR-03' => '中部', 'AR-04' => '近畿',
            'AR-05' => '中国', 'AR-06' => '四国', 'AR-07' => '九州・沖縄',
        ];
        $areas = [];
        foreach ($manifest['arealist']['areainfo'] as $area) {
            if (is_array($area) && $area == ['id' => '', 'name' => '', 'exist' => 'false']) {
                continue;
            }
            $id = is_array($area) ? ($area['id'] ?? null) : null;
            if (! is_string($id) || ! isset($expectedAreas[$id]) || isset($areas[$id])
                || ($area['name'] ?? null) !== $expectedAreas[$id]
                || ! in_array($area['exist'] ?? null, ['true', 'false'], true)) {
                throw new RuntimeException('KONAMI地域一覧が不正です。');
            }
            $areas[$id] = $area['exist'];
        }
        if (count($areas) !== count($expectedAreas)) {
            throw new RuntimeException('KONAMI地域一覧が揃っていません。');
        }

        $prefectures = [];
        foreach ($manifest['preflist']['prefinfo'] as $prefecture) {
            if (is_array($prefecture) && $prefecture == ['area_id' => '', 'id' => '', 'name' => '', 'exist' => 'false']) {
                continue;
            }

            if (! is_array($prefecture) || ! is_string($prefecture['id'] ?? null)
                || ! preg_match('/^JP-(0[1-9]|[1-3][0-9]|4[0-7])$/D', $prefecture['id'])) {
                throw new RuntimeException('KONAMI都道府県一覧に不正な都道府県があります。');
            }

            $code = (int) substr($prefecture['id'], 3);
            $area = match (true) {
                $code <= 7 => 'AR-01', $code <= 14 => 'AR-02', $code <= 24 => 'AR-03',
                $code <= 30 => 'AR-04', $code <= 35 => 'AR-05', $code <= 39 => 'AR-06',
                default => 'AR-07',
            };

            if (isset($prefectures[$code])
                || ($prefecture['name'] ?? null) !== config("prefectures.$code")
                || ($prefecture['area_id'] ?? null) !== $area
                || (($prefecture['exist'] ?? null) === 'true' && $areas[$area] !== 'true')
                || ! in_array($prefecture['exist'] ?? null, ['true', 'false'], true)) {
                throw new RuntimeException('KONAMI都道府県一覧に重複または不正な情報があります。');
            }

            $prefectures[$code] = $prefecture;
        }

        ksort($prefectures);
        if (array_keys($prefectures) !== range(1, 47)) {
            throw new RuntimeException('KONAMI都道府県一覧が47都道府県分揃っていません。');
        }

        return $prefectures;
    }

    private function fetchPrefecture(array $prefecture): array
    {
        $query = [
            'gkey' => 'REFLECC', 'paselif' => 'false', 'area' => $prefecture['area_id'],
            'pref' => $prefecture['id'], 'finder' => 'area',
        ];
        $pending = [1];
        $visited = [];
        $shops = [];
        $expectedTotal = null;

        while ($pending !== []) {
            sort($pending);
            $page = array_shift($pending);
            if (isset($visited[$page])) {
                continue;
            }
            $visited[$page] = true;
            $html = $this->request(self::LIST_PATH, $query + ($page === 1 ? [] : ['page' => $page]));
            [$total, $pageShops, $pages] = $this->parsePage($html, $query, $page);

            if ($expectedTotal !== null && $total !== $expectedTotal) {
                throw new RuntimeException('KONAMI店舗件数が取得途中で変化しました。');
            }
            $expectedTotal = $total;
            foreach ($pageShops as $id => $name) {
                if (isset($shops[$id])) {
                    throw new RuntimeException('KONAMI店舗一覧に重複した店舗またはページがあります。');
                }
                $shops[$id] = $name;
            }
            foreach ($pages as $nextPage) {
                if (! isset($visited[$nextPage]) && ! in_array($nextPage, $pending, true)) {
                    $pending[] = $nextPage;
                }
            }
        }

        if (count($shops) !== $expectedTotal) {
            throw new RuntimeException('KONAMI店舗一覧の全ページを取得できませんでした。');
        }

        return array_values($shops);
    }

    private function parsePage(string $html, array $query, int $page): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (! $loaded || ! mb_check_encoding($html, 'UTF-8')) {
            throw new RuntimeException('KONAMI店舗一覧が正しいHTMLではありません。');
        }
        $xpath = new DOMXPath($document);
        $bodies = $xpath->query('//*[@id="id_page_body"]');
        $titles = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " cl_game_title ")]');
        $paseli = $xpath->query('//input[@id="id_paselif"]');
        $keyword = $xpath->query('//input[@id="id_keyword_hdn"]');
        if ($bodies->length !== 1 || $titles->length !== 1 || trim($titles->item(0)->textContent) !== 'REFLEC BEAT'
            || $paseli->length !== 1 || $paseli->item(0)->hasAttribute('checked')
            || $keyword->length !== 1 || $keyword->item(0)->getAttribute('value') !== '') {
            throw new RuntimeException('KONAMI店舗一覧の検索画面を確認できません。');
        }
        foreach (['gkey', 'area', 'pref', 'finder'] as $key) {
            $inputs = $xpath->query('//input[@id="id_'.$key.'"]');
            if ($inputs->length !== 1 || $inputs->item(0)->getAttribute('value') !== $query[$key]) {
                throw new RuntimeException('KONAMI店舗一覧の検索条件が一致しません。');
            }
        }
        $pageInput = $xpath->query('//input[@id="id_page"]');
        if ($pageInput->length !== 1
            || ! in_array($pageInput->item(0)->getAttribute('value'), $page === 1 ? ['', '1'] : [(string) $page], true)) {
            throw new RuntimeException('KONAMI店舗一覧のページ番号が一致しません。');
        }

        $results = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " cl_search_result ")]');
        $nodes = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " cl_shop_bloc ")]');
        $links = $xpath->query('//*[@id="id_pager_bloc"]//a');
        if ($results->length === 0 && $nodes->length === 0 && $links->length === 0 && $page === 1
            && str_contains($xpath->evaluate('string(//*[@id="id_page_body"])'), '店舗が見つかりませんでした｡')) {
            return [0, [], []];
        }
        if ($results->length !== 1
            || ! preg_match('/^([0-9]+)件の店舗が見つかりました。$/u', trim($results->item(0)->textContent), $matches)
            || (int) $matches[1] < 1 || $nodes->length < 1) {
            throw new RuntimeException('KONAMI店舗一覧の検索結果を確認できません。');
        }

        $shops = [];
        foreach ($nodes as $node) {
            $id = $node->getAttribute('data-fdesc');
            $name = $node->getAttribute('data-name');
            if ($id === '' || self::normalizeShopName($name) === '' || isset($shops[$id])) {
                throw new RuntimeException('KONAMI店舗一覧の店舗情報が不正です。');
            }
            $shops[$id] = $name;
        }

        $pages = [];
        foreach ($links as $link) {
            $pages[] = $this->parsePageLink($link, $query);
        }

        return [(int) $matches[1], $shops, array_unique($pages)];
    }

    private function parsePageLink(DOMElement $link, array $expectedQuery): int
    {
        $url = parse_url($link->getAttribute('href'));
        if ($url === false || (isset($url['host']) && $url['host'] !== 'p.eagate.573.jp')
            || (isset($url['scheme']) && $url['scheme'] !== 'https')
            || ! in_array($url['path'] ?? '', [self::LIST_PATH, 'list.html', ''], true)) {
            throw new RuntimeException('KONAMI店舗一覧のページリンクが不正です。');
        }
        parse_str($url['query'] ?? '', $query);
        foreach ($expectedQuery as $key => $value) {
            if (($query[$key] ?? null) !== $value) {
                throw new RuntimeException('KONAMI店舗一覧のページリンクの検索条件が一致しません。');
            }
        }
        if (array_diff(array_keys($query), [...array_keys($expectedQuery), 'page']) !== []
            || ! is_string($query['page'] ?? null) || ! ctype_digit($query['page'])
            || (int) $query['page'] < 1 || (int) $query['page'] > self::MAX_PAGES) {
            throw new RuntimeException('KONAMI店舗一覧のページリンクの番号が不正です。');
        }

        return (int) $query['page'];
    }
}
