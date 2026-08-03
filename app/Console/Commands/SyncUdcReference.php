<?php

namespace App\Console\Commands;

use App\Models\Catalog\UdcCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncUdcReference extends Command
{
    protected $signature = 'udc:sync-reference {--dry-run : Show changes without writing them}';

    protected $description = 'Add UDC codes found in bibliographic records to the editable reference tree';

    /** @var array<string, string> */
    private const DEPARTMENTS = [
        '004' => 'Информационные технологии',
        '005' => 'Бизнес и менеджмент',
        '159.9' => 'Психология',
        '33' => 'Экономика',
        '34' => 'Право',
        '61' => 'Медицина',
        '62' => 'Инженерия',
        '65' => 'Бизнес и менеджмент',
        '72' => 'Архитектура',
        '8' => 'Филология',
        '9' => 'История',
    ];

    public function handle(): int
    {
        $sourceCodes = DB::table('bibliographic_records')
            ->whereNull('deleted_at')
            ->whereNotNull('udc_code')
            ->whereRaw("TRIM(udc_code) <> ''")
            ->selectRaw('TRIM(udc_code) AS code')
            ->distinct()
            ->pluck('code')
            ->uniqueStrict()
            ->sortBy(fn (string $code): string => sprintf('%04d:%s', strlen($code), $code))
            ->values();

        $known = UdcCode::query()->pluck('id', 'code');
        $missing = $sourceCodes->reject(fn (string $code): bool => $known->has($code));

        $this->info(sprintf(
            'Found %d unique catalog codes; %d already known; %d missing.',
            $sourceCodes->count(),
            $sourceCodes->count() - $missing->count(),
            $missing->count(),
        ));

        if ($this->option('dry-run')) {
            $missing->each(fn (string $code) => $this->line($code));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($missing): void {
            foreach ($missing as $code) {
                UdcCode::query()->create([
                    'code' => $code,
                    'description' => 'Раздел '.$code,
                    'description_kk' => null,
                    'description_en' => null,
                    'is_verified' => false,
                    'department' => $this->departmentFor($code),
                ]);
            }

            $all = UdcCode::query()->orderByRaw('LENGTH(code)')->get()->keyBy('code');
            foreach ($all as $code => $model) {
                $parent = $all
                    ->filter(fn (UdcCode $candidate, string $candidateCode): bool => $candidateCode !== $code
                        && strlen($candidateCode) < strlen($code)
                        && str_starts_with($code, $candidateCode))
                    ->sortByDesc(fn (UdcCode $candidate): int => strlen($candidate->code))
                    ->first();

                $model->forceFill([
                    'parent_id' => $parent?->getKey(),
                    'department' => $model->department ?: $this->departmentFor($model->code),
                ])->save();
            }
        });

        $this->info(sprintf('UDC reference now contains %d rows.', UdcCode::query()->count()));

        return self::SUCCESS;
    }

    private function departmentFor(string $code): ?string
    {
        foreach (self::DEPARTMENTS as $prefix => $department) {
            if (str_starts_with($code, $prefix)) {
                return $department;
            }
        }

        return null;
    }
}
