<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->unsignedTinyInteger('prefecture_code')->nullable()->after('address');
            $table->index(['prefecture_code', 'id']);
        });

        foreach (config('prefectures') as $code => $prefecture) {
            DB::table('shops')
                ->where('address', 'like', "{$prefecture}%")
                ->update(['prefecture_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['prefecture_code', 'id']);
            $table->dropColumn('prefecture_code');
        });
    }
};
