<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class AdminShopController extends Controller
{
    public function index(Request $request)
{
    $keyword = $request->string('keyword')->toString();

    // 共通クエリ（検索条件はそのまま）
    $base = \App\Models\Shop::query()
        ->when($keyword, function ($query) use ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('address', 'like', "%{$keyword}%");
            });
        });

    // 件数（同一条件で集計）
    $totalCount     = (clone $base)->count();                           // 全 ○ 件
    $publishedCount = (clone $base)->where('is_deleted', false)->count(); // 公開中 ○ 件

    // 一覧本体（現状どおり全件取得）
    $shops = (clone $base)
        ->orderBy('id', 'asc')
        ->get();

    return view('shops.index', compact('shops', 'totalCount', 'publishedCount'));
}

    public function edit($id)
    {
        $shop = Shop::findOrFail($id);
        return view('shops.edit', compact('shop'));
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'price' => 'nullable|numeric',
            'number_of_machine' => 'nullable|integer',
            'description' => 'nullable|string|max:1000',
        ]);

        // 論理削除の値を設定（チェックされていない場合はfalse）
        $data['is_deleted'] = $request->has('is_deleted') ? true : false;

        $shop = Shop::findOrFail($id);
        $shop->update($data);

        return redirect()->route('shops.index')->with('success', 'Shop updated successfully.');
    }

}
?>
