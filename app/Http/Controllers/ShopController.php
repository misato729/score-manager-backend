<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $keyword   = $request->string('keyword')->toString();
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        // 一覧の共通クエリ（検索条件を反映）
        $base = Shop::query()
            ->select([
                'id','name','address','lat','lng','price',
                'number_of_machine','description','is_deleted', // ← バッジ表示用
            ])
            ->when($keyword, function ($q) use ($keyword) {
                $q->where(function ($w) use ($keyword) {
                    $w->where('name', 'like', "%{$keyword}%")
                      ->orWhere('address', 'like', "%{$keyword}%");
                });
            });

        // 件数（同一条件で集計）
        $totalCount     = (clone $base)->count();                          // 全○件
        $publishedCount = (clone $base)->where('is_deleted', false)->count(); // 公開中○件

        // 一覧本体（同条件＋並び順＋ページネーション）
        $shops = (clone $base)
            ->orderBy('id', $direction)
            ->paginate(20)
            ->withQueryString();

        return view('shops.index', compact('shops', 'totalCount', 'publishedCount'));
    }
}
