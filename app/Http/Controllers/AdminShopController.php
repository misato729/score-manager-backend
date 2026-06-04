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
        $base = Shop::query()
            ->when($keyword, function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('name', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%");
                });
            });

        // 件数（同一条件で集計）
        $totalCount = (clone $base)->count();                           // 全 ○ 件
        $publishedCount = (clone $base)->where('is_deleted', false)->count(); // 公開中 ○ 件

        // 一覧本体（現状どおり全件取得）
        $shops = (clone $base)
            ->orderBy('prefecture_code', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return view('shops.index', compact('shops', 'totalCount', 'publishedCount'));
    }

    public function create()
    {
        return view('shops.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateShop($request);
        $data['prefecture_code'] = ($data['prefecture_code'] ?? null) ?: $this->prefectureCodeFor($data['address']);
        $data['is_deleted'] = $request->has('is_deleted');

        Shop::create($data);

        return redirect()->route('shops.index')->with('success', 'Shop created successfully.');
    }

    public function edit($id)
    {
        $shop = Shop::findOrFail($id);

        return view('shops.edit', compact('shop'));
    }

    public function update(Request $request, $id)
    {
        $data = $this->validateShop($request);
        $data['prefecture_code'] = ($data['prefecture_code'] ?? null) ?: $this->prefectureCodeFor($data['address']);

        // 論理削除の値を設定（チェックされていない場合はfalse）
        $data['is_deleted'] = $request->has('is_deleted');

        $shop = Shop::findOrFail($id);
        $shop->update($data);

        return redirect()->route('shops.index')->with('success', 'Shop updated successfully.');
    }

    private function validateShop(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'prefecture_code' => 'nullable|integer|min:1|max:47',
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'price' => 'nullable|numeric',
            'number_of_machine' => 'nullable|integer',
            'description' => 'nullable|string|max:1000',
        ]);
    }

    private function prefectureCodeFor(string $address): ?int
    {
        foreach (config('prefectures') as $code => $prefecture) {
            if (str_starts_with($address, $prefecture)) {
                return $code;
            }
        }

        return null;
    }
}
