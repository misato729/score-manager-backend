<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_scores_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('song_id')->constrained('songs')->onDelete('cascade');
            $table->string('rank', 10);
            $table->boolean('fc')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'song_id']); // 1ユーザーが1曲に1スコアまで
        });
    }

    public function down(): void {
        Schema::dropIfExists('scores');
    }
};
