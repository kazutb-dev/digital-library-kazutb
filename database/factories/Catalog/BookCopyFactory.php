<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookCopy>
 */
class BookCopyFactory extends Factory
{
    protected $model = BookCopy::class;

    public function definition(): array
    {
        $inventory = 'INV-'.$this->faker->unique()->numberBetween(100000, 999999);

        return [
            'bibliographic_record_id' => BibliographicRecord::factory(),
            'inventory_number' => $inventory,
            'barcode' => 'BC'.$this->faker->unique()->numberBetween(10000000, 99999999),
            'accounting_type' => 'individual',
            'storage_sigla' => 'НБ',
            'shelf_location' => $this->faker->numberBetween(1, 20).'-'.$this->faker->numberBetween(1, 8),
            'price' => $this->faker->randomFloat(2, 1500, 25000),
            'acquisition_source' => $this->faker->randomElement(['purchase', 'donation', 'exchange']),
            'acquisition_date' => $this->faker->dateTimeBetween('-5 years'),
            'registration_date' => $this->faker->dateTimeBetween('-5 years'),
            'condition' => 'good',
            'status' => 'available',
            'access_restriction' => 'free',
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => ['status' => 'issued']);
    }

    public function readingRoomOnly(): static
    {
        return $this->state(fn (): array => ['access_restriction' => 'reading_room']);
    }
}
