<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReaderProfile>
 */
class ReaderProfileFactory extends Factory
{
    protected $model = ReaderProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticket_number' => 'KUTB-'.now()->format('Y').'-'.$this->faker->unique()->numberBetween(1000, 9999),
            'category' => 'student',
            'status' => 'active',
        ];
    }

    public function blocked(string $reason = 'Manual block'): static
    {
        return $this->state(fn (): array => ['status' => 'blocked', 'block_reason' => $reason]);
    }
}
