<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'copy_id' => BookCopy::factory()->issued(),
            'status' => 'active',
            'issued_at' => now()->subDays(3),
            'due_at' => now()->addDays(11),
            'renewal_count' => 0,
        ];
    }

    public function overdue(int $days = 5): static
    {
        return $this->state(fn (): array => [
            'status' => 'overdue',
            'issued_at' => now()->subDays($days + 14),
            'due_at' => now()->subDays($days),
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (): array => [
            'status' => 'returned',
            'returned_at' => now(),
        ]);
    }
}
