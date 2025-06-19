<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id(); // id (BIGINT, AUTO_INCREMENT)
            $table->string('name'); // 店舗名
            $table->string('address'); // 住所
            $table->decimal('lat', 10, 7); // 緯度（例: 35.6894871）
            $table->decimal('lng', 10, 7); // 経度（例: 139.6917064）
            $table->integer('price')->nullable(); // 1プレイ料金（円）
            $table->unsignedTinyInteger('number_of_machine')->nullable(); // 設置台数
            $table->text('description')->nullable(); // 備考・メモ
            $table->boolean('is_deleted')->default(false); // 表示用の論理削除フラグ
            $table->softDeletes(); // deleted_at TIMESTAMP NULL

            $table->timestamps(); // created_at / updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
