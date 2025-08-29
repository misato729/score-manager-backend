<?php

namespace Database\Factories;

use App\Models\UserVisit;
use App\Models\User;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserVisitFactory extends Factory
{
    protected $model = UserVisit::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'shop_id' => Shop::factory(),
            // created_at/updated_at は自動
        ];
    }
}
