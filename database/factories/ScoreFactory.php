<?php

namespace Database\Factories;

use App\Models\Score;
use App\Models\User;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScoreFactory extends Factory
{
    protected $model = Score::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'song_id' => Song::factory(),
            'rank'    => $this->faker->randomElement(['AAA', 'AA', 'A', 'B']),
            'fc'      => $this->faker->boolean(), // true/false
        ];
    }

    public function fc(): self
    {
        return $this->state(fn () => ['fc' => true]);
    }
}
