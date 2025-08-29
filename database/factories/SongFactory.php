<?php

namespace Database\Factories;

use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

class SongFactory extends Factory
{
    protected $model = Song::class;

    public function definition(): array
    {
        return [
            'title'       => $this->faker->unique()->sentence(3),
            'jiriki_rank' => $this->faker->randomElement(['S', 'AAA', 'AA', 'A', 'B']),
        ];
    }
}
