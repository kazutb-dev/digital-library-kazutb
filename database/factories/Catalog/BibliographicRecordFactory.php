<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\BibliographicRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BibliographicRecord>
 */
class BibliographicRecordFactory extends Factory
{
    protected $model = BibliographicRecord::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'primary_author' => $this->faker->name(),
            'additional_authors' => [],
            'publisher' => $this->faker->company(),
            'publication_year' => $this->faker->numberBetween(1990, 2026),
            'language' => $this->faker->randomElement(['ru', 'kk', 'en']),
            'udc_code' => $this->faker->randomElement(['004', '33', '54', '62', '93/94']),
            'author_mark' => mb_substr($this->faker->lastName(), 0, 1).$this->faker->numberBetween(10, 99),
            'category' => $this->faker->randomElement(['economics', 'technology', 'science', 'history']),
            'annotation' => $this->faker->paragraph(),
            'keywords' => $this->faker->words(4),
            'isbn' => $this->faker->unique()->isbn13(),
            'resource_type' => 'book',
            'is_draft' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'is_draft' => true,
            'primary_author' => null,
            'annotation' => null,
            'udc_code' => null,
        ]);
    }
}
