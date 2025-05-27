<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SongsTableSeeder extends Seeder
{
    public function run(): void
    {
        $csvPath = database_path('seeders/data/songsTable.csv');

        if (!File::exists($csvPath)) {
            throw new \Exception("songsTable.csv not found at $csvPath");
        }

        $csv = array_map('str_getcsv', file($csvPath));
        $header = array_map('trim', array_shift($csv)); // ヘッダー取り出し

        foreach ($csv as $row) {
            $data = array_combine($header, $row);

            DB::table('songs')->insert([
                'title' => $data['title'],
                'jiriki_rank' => $data['jiriki_rank'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
