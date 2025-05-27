<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('songs', function (Blueprint $table) {
            $table->id(); // INT, オートインクリメント
            $table->string('title', 255); // 例: FLOWER【SP】
            $table->string('jiriki_rank', 20); // 地力S+, A, B など
            $table->timestamps(); // created_at / updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('songs');
    }
};

