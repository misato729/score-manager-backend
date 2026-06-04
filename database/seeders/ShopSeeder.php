<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

class ShopSeeder extends Seeder
{
    public function run(): void
    {
        // 全削除
        DB::table('shops')->truncate();

        // CSV 読み込み
        $csvPath = base_path('database/seeders/data/shops.csv'); // CSVはこの場所に保存
        $csv = array_map('str_getcsv', file($csvPath));
        $header = array_map('trim', $csv[0]);

        foreach (array_slice($csv, 1) as $row) {
            $data = array_combine($header, $row);
            Shop::create([
                'name' => $data['name'],
                'address' => $data['address'],
                'prefecture_code' => $data['prefecture_code'] ?: $this->prefectureCodeFor($data['address']),
                'lat' => $data['lat'],
                'lng' => $data['lng'],
                'price' => $data['price'],
                'number_of_machine' => $data['number_of_machine'],
                'description' => $data['description'],
                'is_deleted' => false, // デフォルトで false
            ]);
        }
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
