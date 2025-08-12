<?php
namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;

class AdminShopController extends Controller
{
    public function index()
    {
        // ショップ一覧を取得
        $shops = Shop::all();

        // ビューにデータを渡して表示
        return view('shops.index', compact('shops'));
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
