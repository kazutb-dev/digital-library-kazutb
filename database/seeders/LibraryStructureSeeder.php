<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Fund;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LibraryStructureSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $branches = [
            [
                'code' => 'SCIENTIFIC-LIBRARY',
                'name' => 'Научная библиотека',
                'type' => 'library',
                'description' => 'Основное подразделение научной библиотеки университета.',
                'sort_order' => 10,
            ],
            [
                'code' => 'ECONOMICS-DESK',
                'name' => 'Абонемент экономического факультета',
                'type' => 'circulation_desk',
                'description' => 'Пункт выдачи литературы экономического факультета.',
                'address' => '1/203',
                'sort_order' => 20,
            ],
            [
                'code' => 'TECHNOLOGY-DESK',
                'name' => 'Абонемент технологического факультета',
                'type' => 'circulation_desk',
                'description' => 'Пункт выдачи литературы технологического факультета.',
                'address' => '1/200',
                'sort_order' => 30,
            ],
            [
                'code' => 'ENGINEERING-IT-DESK',
                'name' => 'Абонемент факультета инжиниринга и ИТ',
                'type' => 'circulation_desk',
                'description' => 'Пункт выдачи литературы факультета инжиниринга и информационных технологий.',
                'sort_order' => 40,
            ],
            [
                'code' => 'READING-ROOM',
                'name' => 'Читальный зал',
                'type' => 'reading_room',
                'description' => 'Читальный зал для работы с библиотечными материалами.',
                'sort_order' => 50,
            ],
        ];

        $branchIds = [];

        foreach ($branches as $branchData) {
            $branch = Branch::withTrashed()->firstOrCreate(
                ['code' => $branchData['code']],
                $branchData + ['is_active' => true]
            );

            // A repeated seed must not silently undo an audited admin delete.
            $branchIds[$branchData['code']] = $branch->trashed()
                ? null
                : $branch->getKey();
        }

        $funds = [
            [
                'code' => 'MAIN',
                'name' => 'Основной фонд',
                'fund_type' => 'main',
                'institutional_scope' => 'general',
                'branch_id' => $branchIds['SCIENTIFIC-LIBRARY'],
                'description' => 'Основной универсальный фонд печатных изданий.',
                'sort_order' => 10,
            ],
            [
                'code' => 'EDUCATIONAL',
                'name' => 'Учебный фонд',
                'fund_type' => 'educational',
                'institutional_scope' => 'general',
                'branch_id' => $branchIds['SCIENTIFIC-LIBRARY'],
                'description' => 'Учебная литература по образовательным программам университета.',
                'sort_order' => 20,
            ],
            [
                'code' => 'RESEARCH',
                'name' => 'Научный фонд',
                'fund_type' => 'research',
                'institutional_scope' => 'general',
                'branch_id' => $branchIds['SCIENTIFIC-LIBRARY'],
                'description' => 'Монографии, научные труды и исследовательские издания.',
                'sort_order' => 30,
            ],
            [
                'code' => 'PERIODICALS',
                'name' => 'Фонд периодических изданий',
                'fund_type' => 'periodicals',
                'institutional_scope' => 'general',
                'branch_id' => $branchIds['SCIENTIFIC-LIBRARY'],
                'description' => 'Газеты, журналы и продолжающиеся издания.',
                'sort_order' => 40,
            ],
            [
                'code' => 'ELECTRONIC',
                'name' => 'Электронный фонд',
                'fund_type' => 'electronic',
                'institutional_scope' => 'general',
                'branch_id' => $branchIds['SCIENTIFIC-LIBRARY'],
                'description' => 'Электронные издания и цифровые библиотечные материалы.',
                'sort_order' => 50,
            ],
            [
                'code' => 'COLLEGE',
                'name' => 'Фонд колледжа',
                'fund_type' => 'main',
                'institutional_scope' => 'college',
                'description' => 'Обособленный фонд колледжа.',
                'location' => '1/202',
                'sort_order' => 60,
            ],
            [
                'code' => 'UNIVERSITY-ECONOMIC',
                'name' => 'Экономический фонд университета',
                'fund_type' => 'main',
                'institutional_scope' => 'university_economic',
                'branch_id' => $branchIds['ECONOMICS-DESK'],
                'description' => 'Профильный фонд экономических дисциплин.',
                'location' => '1/203',
                'sort_order' => 70,
            ],
            [
                'code' => 'UNIVERSITY-TECHNOLOGY',
                'name' => 'Технологический фонд университета',
                'fund_type' => 'main',
                'institutional_scope' => 'university_technology',
                'branch_id' => $branchIds['TECHNOLOGY-DESK'],
                'description' => 'Профильный фонд технологических дисциплин.',
                'location' => '1/200',
                'sort_order' => 80,
            ],
        ];

        foreach ($funds as $fundData) {
            // If an administrator removed the canonical parent branch, do not
            // recreate its dependent fund as an unassigned record.
            if (array_key_exists('branch_id', $fundData) && $fundData['branch_id'] === null) {
                continue;
            }

            Fund::withTrashed()->firstOrCreate(
                ['code' => $fundData['code']],
                $fundData + ['is_active' => true]
            );
        }
    }
}
