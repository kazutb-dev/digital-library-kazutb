<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BookCopy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarcodeMarkingService
{
    public const BATCH_LIMIT = 100;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DataQualityScanner $scanner,
    ) {}

    public function assign(BookCopy $copy, User $actor, ?string $requested = null): BookCopy
    {
        try {
            $copy = DB::transaction(function () use ($copy, $actor, $requested): BookCopy {
                /** @var BookCopy $locked */
                $locked = BookCopy::query()->lockForUpdate()->findOrFail($copy->getKey());

                if (filled($locked->barcode)) {
                    throw ValidationException::withMessages([
                        'barcode' => __('librarian.copies.marking.already_assigned', ['barcode' => $locked->barcode]),
                    ]);
                }

                if (in_array($locked->status, ['lost', 'written_off'], true)) {
                    throw ValidationException::withMessages([
                        'barcode' => __('librarian.copies.marking.inactive_copy'),
                    ]);
                }

                $barcode = $requested === null || trim($requested) === ''
                    ? $this->generatedValue($locked)
                    : trim($requested);

                $this->assertAvailable($barcode, $locked);
                $locked->update(['barcode' => $barcode]);
                $locked->recordHistory('barcode_assigned', null, $actor->getKey(), null, [
                    'barcode' => $barcode,
                    'inventory_number' => $locked->inventory_number,
                    'source' => $requested === null || trim($requested) === '' ? 'generated' : 'scanned',
                ]);
                $this->audit->logRequired(
                    actionType: 'copies.barcode_assign',
                    entityType: 'book_copy',
                    entityId: $locked->getKey(),
                    oldValues: ['barcode' => null],
                    newValues: ['barcode' => $barcode, 'inventory_number' => $locked->inventory_number],
                    scope: 'operational',
                    actor: $actor,
                );

                return $locked;
            });
        } catch (QueryException $exception) {
            if (in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true)) {
                $barcode = $requested === null || trim($requested) === '' ? $this->generatedValue($copy) : trim($requested);
                $existing = BookCopy::query()->where('barcode', $barcode)->first();
                throw ValidationException::withMessages([
                    'barcode' => __('librarian.copies.marking.barcode_taken', ['inventory' => $existing?->inventory_number ?? '—']),
                ]);
            }
            throw $exception;
        }

        $this->scanner->scanModel($copy->fresh(), 'book_copy');

        return $copy->fresh();
    }

    /** @param Collection<int, BookCopy> $copies */
    public function prepareBatch(Collection $copies, User $actor): array
    {
        $ready = [];
        $skipped = [];
        $batchId = (string) str()->uuid();

        foreach ($copies->take(self::BATCH_LIMIT) as $copy) {
            if (filled($copy->barcode)) {
                $skipped[$copy->getKey()] = 'already_marked';

                continue;
            }
            if (blank($copy->inventory_number)) {
                $skipped[$copy->getKey()] = 'missing_inventory';

                continue;
            }
            if (in_array($copy->status, ['lost', 'written_off'], true)) {
                $skipped[$copy->getKey()] = 'inactive';

                continue;
            }

            try {
                $assigned = $this->assign($copy, $actor);
                $assigned->recordHistory('barcode_batch_prepared', null, $actor->getKey(), null, ['batch_id' => $batchId]);
                $ready[] = $assigned->getKey();
            } catch (ValidationException) {
                $skipped[$copy->getKey()] = 'conflict';
            }
        }

        return ['batch_id' => $batchId, 'ready_ids' => $ready, 'skipped' => $skipped];
    }

    public function confirm(BookCopy $copy, User $actor, string $scanned): void
    {
        $scanned = trim($scanned);
        if (blank($copy->barcode) || ! hash_equals((string) $copy->barcode, $scanned)) {
            throw ValidationException::withMessages([
                'scanned_barcode' => __('librarian.copies.marking.confirm_mismatch'),
            ]);
        }

        DB::transaction(function () use ($copy, $actor): void {
            $copy->recordHistory('barcode_confirmed', null, $actor->getKey(), null, [
                'barcode' => $copy->barcode,
                'inventory_number' => $copy->inventory_number,
            ]);
            $this->audit->logRequired(
                actionType: 'copies.barcode_confirm',
                entityType: 'book_copy',
                entityId: $copy->getKey(),
                newValues: ['barcode' => $copy->barcode, 'inventory_number' => $copy->inventory_number],
                scope: 'operational',
                actor: $actor,
            );
        });
    }

    /** @param Collection<int, BookCopy> $copies */
    public function markPrinted(Collection $copies, User $actor): void
    {
        DB::transaction(function () use ($copies, $actor): void {
            foreach ($copies as $copy) {
                if (blank($copy->barcode)) {
                    continue;
                }
                $copy->recordHistory('barcode_label_printed', null, $actor->getKey(), null, ['barcode' => $copy->barcode]);
                $this->audit->logRequired(
                    actionType: 'copies.barcode_label_printed',
                    entityType: 'book_copy',
                    entityId: $copy->getKey(),
                    newValues: ['barcode' => $copy->barcode, 'inventory_number' => $copy->inventory_number],
                    scope: 'operational',
                    actor: $actor,
                );
            }
        });
    }

    public function state(BookCopy $copy): string
    {
        if (blank($copy->barcode)) {
            return 'unmarked';
        }

        $events = $copy->relationLoaded('history')
            ? $copy->history->pluck('event_type')
            : $copy->history()->pluck('event_type');

        if ($events->contains('barcode_confirmed')) {
            return 'confirmed';
        }
        if ($events->contains('barcode_label_printed')) {
            return 'printed';
        }

        return 'prepared';
    }

    private function generatedValue(BookCopy $copy): string
    {
        return 'KUTB'.str_pad((string) $copy->getKey(), 8, '0', STR_PAD_LEFT);
    }

    private function assertAvailable(string $barcode, BookCopy $copy): void
    {
        if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', $barcode) !== 1) {
            throw ValidationException::withMessages([
                'barcode' => __('librarian.copies.marking.invalid_barcode'),
            ]);
        }

        $existing = BookCopy::query()->where('barcode', $barcode)->whereKeyNot($copy->getKey())->first();
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'barcode' => __('librarian.copies.marking.barcode_taken', ['inventory' => $existing->inventory_number]),
            ]);
        }
    }
}
