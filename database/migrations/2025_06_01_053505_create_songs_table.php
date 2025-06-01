<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_songs_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('songs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->string('jiriki_rank', 20);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('songs');
    }
};
