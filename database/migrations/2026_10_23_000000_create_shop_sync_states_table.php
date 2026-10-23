<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_sync_states', function (Blueprint $table) {
            $table->foreignId('shop_id')->primary()->constrained('shops')->cascadeOnDelete();
            $table->date('last_checked_on')->nullable();
            $table->unsignedSmallInteger('consecutive_missing_days')->default(0);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_sync_states');
    }
};
