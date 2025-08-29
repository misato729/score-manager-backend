<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShopFactory extends Factory
{
    protected $model = Shop::class;

    public function definition(): array
    {
        return [
            'name'               => $this->faker->company(),
            'address'            => $this->faker->address(),
            'lat'                => $this->faker->latitude(),
            'lng'                => $this->faker->longitude(),
            'price'              => 100,
            'number_of_machine'  => $this->faker->numberBetween(1, 10),
            'description'        => $this->faker->optional()->sentence(),
            'is_deleted'         => 0,
        ];
    }
}
