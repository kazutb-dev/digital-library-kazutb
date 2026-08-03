<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\RepositoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepositoryItem>
 */
class RepositoryItemFactory extends Factory
{
    protected $model = RepositoryItem::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'authors' => [$this->faker->name()],
            'work_type' => $this->faker->randomElement(['thesis', 'article', 'master_thesis']),
            'year' => $this->faker->numberBetween(2015, 2026),
            'department' => $this->faker->randomElement(['Экономика', 'Инжиниринг и ИТ', 'Технологии']),
            'abstract' => $this->faker->paragraph(),
            'keywords' => $this->faker->words(4),
            'language' => 'ru',
            'status' => 'draft',
            'uploaded_by' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => 'published', 'published_at' => now()]);
    }
}
