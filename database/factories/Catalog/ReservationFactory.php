<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bibliographic_record_id' => BibliographicRecord::factory(),
            'status' => 'pending',
        ];
    }
}
