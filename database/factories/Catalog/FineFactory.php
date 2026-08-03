<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Fine;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fine>
 */
class FineFactory extends Factory
{
    protected $model = Fine::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'reason' => 'overdue',
            'status' => 'pending',
            'charged_at' => now(),
        ];
    }
}
