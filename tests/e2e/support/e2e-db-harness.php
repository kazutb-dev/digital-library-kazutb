<?php

declare(strict_types=1);

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\RepositoryItem;
use App\Models\ExternalResource;
use App\Models\ExternalResourceNotificationOutbox;
use App\Models\LibraryTask;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$expectedDatabase = trim((string) getenv('PLAYWRIGHT_E2E_DATABASE'));
$connection = (string) config('database.default');
$actualDatabase = trim((string) config("database.connections.{$connection}.database"));
$databaseHost = trim((string) config("database.connections.{$connection}.host"));
$databasePort = (int) config("database.connections.{$connection}.port");
$databaseUsername = trim((string) config("database.connections.{$connection}.username"));
$databasePassword = (string) config("database.connections.{$connection}.password");
$expectedStorage = rtrim(trim((string) getenv('LARAVEL_STORAGE_PATH')), '/');
$actualStorage = rtrim(storage_path(), '/');
$filesystemDisk = (string) config('filesystems.default');
$localRoot = rtrim((string) config('filesystems.disks.local.root'), '/');
$publicRoot = rtrim((string) config('filesystems.disks.public.root'), '/');

if (! app()->environment('testing')
    || $connection !== 'pgsql'
    || ! in_array($databaseHost, ['127.0.0.1', '::1', 'localhost'], true)
    || $databasePort < 1024
    || $databasePort > 65535
    || $databaseUsername === ''
    || $databasePassword === ''
    || preg_match('/^[A-Za-z_][A-Za-z0-9_]*_test$/i', $expectedDatabase) !== 1
    || ! hash_equals($expectedDatabase, $actualDatabase)
    || preg_match('#^/tmp/kazutb-library-playwright/[A-Za-z0-9_-]+$#', $expectedStorage) !== 1
    || ! hash_equals($expectedStorage, $actualStorage)
    || $filesystemDisk !== 'local'
    || ! hash_equals($actualStorage.'/app/private', $localRoot)
    || ! hash_equals($actualStorage.'/app/public', $publicRoot)) {
    fwrite(STDERR, sprintf(
        "Unsafe E2E runtime refused: env=%s connection=%s host=%s port=%d expected=%s actual=%s storage=%s\n",
        app()->environment(),
        $connection,
        $databaseHost,
        $databasePort,
        $expectedDatabase !== '' ? $expectedDatabase : '[empty]',
        $actualDatabase !== '' ? $actualDatabase : '[empty]',
        $actualStorage !== '' ? $actualStorage : '[empty]',
    ));
    exit(64);
}

$connectedDatabase = (string) DB::selectOne('select current_database() as name')->name;
if (! hash_equals($expectedDatabase, $connectedDatabase)) {
    fwrite(STDERR, "Connected PostgreSQL database does not match PLAYWRIGHT_E2E_DATABASE.\n");
    exit(64);
}

$action = (string) ($argv[1] ?? '');
$result = match ($action) {
    'assert-runtime' => [
        'ok' => true,
        'environment' => app()->environment(),
        'connection' => $connection,
        'host' => $databaseHost,
        'port' => $databasePort,
        'database' => $connectedDatabase,
        'storage' => $actualStorage,
        'filesystem' => $filesystemDisk,
    ],
    'expire-external' => expireExternal((int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')),
    'external-notification-state' => externalNotificationState((int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')),
    'cleanup-external' => cleanupExternal((int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')),
    'cleanup-repository' => cleanupRepository((int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')),
    'cleanup-catalog' => cleanupCatalog((int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')),
    'create-executive-alert' => createExecutiveAlert((string) ($argv[2] ?? '')),
    'cleanup-executive-alert' => cleanupExecutiveAlert((int) ($argv[2] ?? 0), (string) ($argv[3] ?? '')),
    default => throw new RuntimeException('Unsupported E2E harness action.'),
};

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

/** @return array<string, mixed> */
function expireExternal(int $id, string $expectedTitle): array
{
    $resource = exactExternalFixture($id, $expectedTitle);
    if ($resource->publication_status !== 'published' || ! $resource->is_active) {
        throw new RuntimeException('Only the exact published E2E fixture may be expired.');
    }

    $date = today('UTC')->subDay()->toDateString();
    DB::transaction(function () use ($resource, $date): void {
        ExternalResource::query()->whereKey($resource->getKey())->lockForUpdate()->firstOrFail();
        DB::table('external_resources')->where('id', $resource->getKey())->update([
            'license_expires_at' => $date,
            'contract_ends_at' => $date,
            'updated_at' => now('UTC'),
        ]);
    });

    return ['id' => $id, 'expired_on' => $date, 'status' => $resource->fresh()->accessStatus()];
}

/** @return array<string, mixed> */
function externalNotificationState(int $id, string $expectedTitle): array
{
    exactExternalFixture($id, $expectedTitle);
    $outboxes = ExternalResourceNotificationOutbox::query()
        ->where('external_resource_id', $id)
        ->where('notification_type', 'licence_expiry');
    $directorIds = DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('roles.name', 'director')
        ->where('model_has_roles.model_type', (new User)->getMorphClass())
        ->pluck('model_has_roles.model_id');
    $notifications = ReaderNotification::query()
        ->whereIn('user_id', $directorIds)
        ->where('event_type', 'external_resource_licence')
        ->where('payload->external_resource_id', $id);

    return [
        'outbox_total' => (clone $outboxes)->count(),
        'outbox_delivered' => (clone $outboxes)->where('status', 'delivered')->whereNotNull('processed_at')->count(),
        'director_notifications' => $notifications->count(),
    ];
}

/** @return array<string, mixed> */
function cleanupExternal(int $id, string $expectedTitle): array
{
    $resource = ExternalResource::withTrashed()->find($id);
    if ($resource === null) {
        return ['id' => $id, 'removed' => true, 'already_missing' => true];
    }
    assertFixtureTitle((string) $resource->title, $expectedTitle);

    $privatePaths = DB::table('external_resource_contract_versions')
        ->where('external_resource_id', $id)->pluck('licence_file_path')->filter()->all();
    if ($resource->licence_file_path) {
        $privatePaths[] = $resource->licence_file_path;
    }
    $logoPath = $resource->logo_path;

    DB::transaction(function () use ($id): void {
        if (DB::getSchemaBuilder()->hasTable('reader_notifications')) {
            DB::table('reader_notifications')
                ->where('event_type', 'external_resource_licence')
                ->where('payload->external_resource_id', $id)
                ->delete();
        }
        foreach (['external_resource_notification_outboxes', 'external_resource_events', 'external_resource_contract_versions'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->where('external_resource_id', $id)->delete();
            }
        }
        ExternalResource::withTrashed()->whereKey($id)->forceDelete();
    });

    foreach (array_unique($privatePaths) as $path) {
        if (is_string($path) && str_starts_with($path, 'external-resource-contracts/')) {
            Storage::disk('local')->delete($path);
        }
    }
    if (is_string($logoPath) && str_starts_with($logoPath, 'external-resource-logos/')) {
        Storage::disk('public')->delete($logoPath);
    }

    return ['id' => $id, 'removed' => ! ExternalResource::withTrashed()->whereKey($id)->exists()];
}

/** @return array<string, mixed> */
function cleanupRepository(int $id, string $expectedTitle): array
{
    $item = RepositoryItem::query()->find($id);
    if ($item === null) {
        return ['id' => $id, 'removed' => true, 'already_missing' => true];
    }
    assertFixtureTitle((string) $item->title, $expectedTitle);
    $publicId = (string) $item->public_id;
    $paths = $item->versions()->pluck('file_path')->filter()->all();
    if ($item->file_path) {
        $paths[] = $item->file_path;
    }

    DB::transaction(function () use ($id): void {
        DB::table('repository_items')->where('id', $id)->update(['active_approval_id' => null]);
        if (DB::getSchemaBuilder()->hasTable('repository_approvals')) {
            DB::table('repository_approvals')->where('repository_item_id', $id)->delete();
        }
        if (DB::getSchemaBuilder()->hasTable('repository_item_versions')) {
            DB::table('repository_item_versions')->where('repository_item_id', $id)->delete();
        }
        RepositoryItem::query()->whereKey($id)->delete();
    });

    $prefix = "repository/{$publicId}/";
    foreach (array_unique($paths) as $path) {
        if (is_string($path) && str_starts_with($path, $prefix)) {
            Storage::disk('local')->delete($path);
        }
    }

    return ['id' => $id, 'removed' => ! RepositoryItem::query()->whereKey($id)->exists()];
}

/** @return array<string, mixed> */
function cleanupCatalog(int $id, string $expectedTitle): array
{
    $record = BibliographicRecord::withTrashed()->find($id);
    if ($record === null) {
        return ['id' => $id, 'removed' => true, 'already_missing' => true];
    }
    assertFixtureTitle((string) $record->title, $expectedTitle);
    if ($record->copies()->exists() || $record->electronicMaterials()->exists()) {
        throw new RuntimeException('Refusing to remove an E2E catalogue fixture with holdings.');
    }
    if (DB::getSchemaBuilder()->hasTable('data_quality_issues')) {
        DB::table('data_quality_issues')->where('entity_type', 'bibliographic_record')->where('entity_id', (string) $id)->delete();
    }
    $record->forceDelete();

    return ['id' => $id, 'removed' => ! BibliographicRecord::withTrashed()->whereKey($id)->exists()];
}

/** @return array<string, mixed> */
function createExecutiveAlert(string $expectedTitle): array
{
    assertFixtureTitle($expectedTitle, $expectedTitle);
    $reader = User::query()->where('ad_login', 'demo_student')->firstOrFail();
    $director = User::query()->where('ad_login', 'demo_director')->firstOrFail();

    return DB::transaction(function () use ($expectedTitle, $reader, $director): array {
        $record = BibliographicRecord::query()->create([
            'title' => $expectedTitle,
            'primary_author' => '[E2E] Test author',
            'publisher' => '[E2E] Test publisher',
            'publication_year' => 2026,
            'language' => 'ru',
            'udc_code' => '004',
            'annotation' => '[E2E] Isolated overdue alert fixture.',
            'keywords' => ['e2e'],
            'resource_type' => 'book',
            'is_draft' => false,
        ]);
        $copy = BookCopy::query()->create([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'E2E-INV-'.$record->getKey(),
            'barcode' => 'E2E-BC-'.$record->getKey(),
            'status' => 'overdue',
            'condition' => 'good',
            'access_restriction' => 'free',
            'issue_count' => 1,
        ]);
        $loan = Loan::query()->create([
            'user_id' => $reader->getKey(),
            'copy_id' => $copy->getKey(),
            'status' => 'overdue',
            'issued_at' => now('UTC')->subDays(30),
            'due_at' => now('UTC')->subDays(7),
            'issued_by' => $director->getKey(),
            'notes' => '[E2E] isolated executive alert',
        ]);

        return ['record_id' => $record->getKey(), 'copy_id' => $copy->getKey(), 'loan_id' => $loan->getKey()];
    });
}

/** @return array<string, mixed> */
function cleanupExecutiveAlert(int $recordId, string $expectedTitle): array
{
    $record = BibliographicRecord::withTrashed()->find($recordId);
    if ($record === null) {
        return ['id' => $recordId, 'removed' => true, 'already_missing' => true];
    }
    assertFixtureTitle((string) $record->title, $expectedTitle);

    DB::transaction(function () use ($recordId): void {
        $copyIds = BookCopy::query()->where('bibliographic_record_id', $recordId)->pluck('id');
        Loan::query()->whereIn('copy_id', $copyIds)->where('notes', '[E2E] isolated executive alert')->delete();
        BookCopy::query()->whereIn('id', $copyIds)->delete();
        BibliographicRecord::withTrashed()->whereKey($recordId)->forceDelete();
    });
    LibraryTask::query()->where('related_entity_type', 'executive_alert')->where('comment', 'like', '[E2E]%')->delete();

    return ['id' => $recordId, 'removed' => ! BibliographicRecord::withTrashed()->whereKey($recordId)->exists()];
}

function exactExternalFixture(int $id, string $expectedTitle): ExternalResource
{
    $resource = ExternalResource::withTrashed()->findOrFail($id);
    assertFixtureTitle((string) $resource->title, $expectedTitle);

    return $resource;
}

function assertFixtureTitle(string $actual, string $expected): void
{
    if (! str_starts_with($expected, '[E2E]') || ! hash_equals($expected, $actual)) {
        throw new RuntimeException('Refusing to touch a record that is not the exact [E2E] fixture.');
    }
}
