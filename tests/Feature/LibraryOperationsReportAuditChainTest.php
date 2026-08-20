<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\DigitalMaterialAccessLog;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\LibraryVisit;
use App\Models\Catalog\ReaderProfile;
use App\Models\DataQualityIssue;
use App\Models\ExternalResource;
use App\Models\ExternalResourceEvent;
use App\Models\Fund;
use App\Services\AuditLogger;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\ReservationQueueService;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\Reports\LibraryReportService;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class LibraryOperationsReportAuditChainTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_linked_transactions_reconcile_to_every_relevant_report_and_audit_trace(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 09:00:00', 'UTC'));

        $branch = Branch::query()->create([
            'code' => 'OPS-CHAIN',
            'name' => 'Operations chain branch',
            'type' => 'library',
            'is_active' => true,
        ]);
        $fund = Fund::query()->create([
            'branch_id' => $branch->getKey(),
            'code' => 'OPS-CHAIN-FUND',
            'name' => 'Operations chain fund',
            'fund_type' => 'main',
            'institutional_scope' => 'general',
            'is_active' => true,
        ]);

        $firstReader = $this->makeControlPlaneUser('member');
        $secondReader = $this->makeControlPlaneUser('member');
        $visitOnlyReader = $this->makeControlPlaneUser('member');
        foreach ([$firstReader, $secondReader, $visitOnlyReader] as $reader) {
            ReaderProfile::forUser($reader)->update([
                'category' => 'doctoral',
                'preferred_branch_id' => $branch->getKey(),
            ]);
        }

        $record = BibliographicRecord::factory()->create([
            'title' => 'Қазақ   кітапханасының операциялық тізбегі',
            'primary_author' => 'Әбілова А.',
            'isbn' => '9780306406157',
            'publication_year' => 2026,
            'publisher' => 'Test isolated publisher',
            'language' => 'kk',
            'udc_code' => '02',
            'author_mark' => 'Ә14',
            'resource_type' => 'book',
        ]);
        $copies = collect([
            ['inventory_number' => 'OPS-INV-001', 'barcode' => 'OPS000001', 'price' => 100.00],
            ['inventory_number' => 'OPS-INV-002', 'barcode' => 'OPS000002', 'price' => 200.00],
            ['inventory_number' => 'OPS-INV-003', 'barcode' => 'OPS000003', 'price' => 300.00],
        ])->map(fn (array $copy): BookCopy => BookCopy::query()->create($copy + [
            'bibliographic_record_id' => $record->getKey(),
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
            'room' => 'Hall A',
            'section' => 'KZ',
            'shelf_location' => 'KZ-02',
            'registration_date' => today(),
            'acquisition_date' => today(),
            'acquisition_source' => 'purchase',
            'supplier_name' => 'Isolated supplier',
            'condition' => 'new',
            'status' => 'available',
            'access_restriction' => 'free',
        ]));

        $circulation = app(CirculationService::class);
        $firstLoan = $circulation->issue($firstReader, $copies[0], $this->adminUser);
        $secondLoan = $circulation->issue($secondReader, $copies[1], $this->adminUser);
        app(ReservationQueueService::class)->create(
            $firstReader,
            $record,
            $this->adminUser,
            $branch->getKey(),
            'desk',
        );

        Carbon::setTestNow(Carbon::parse('2026-08-19 12:00:00', 'UTC'));
        $circulation->returnCopy($copies[0], $this->adminUser, 'unchanged');
        $secondLoan->update(['due_at' => now()->subHour()]);
        $this->assertSame(['overdue' => 1, 'due_soon' => 0], $circulation->sweepOverdue());

        LibraryVisit::query()->create([
            'user_id' => $visitOnlyReader->getKey(),
            'branch_id' => $branch->getKey(),
            'scanned_at' => now(),
            'source' => 'desk',
        ]);
        app(DataQualityScanner::class)->scanModel($record->fresh(), 'bibliographic_record');
        $this->assertGreaterThan(0, DataQualityIssue::query()->count());

        $material = ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $record->getKey(),
            'title' => 'Operations chain digital material',
            'file_type' => 'pdf',
            'material_type' => 'book_pdf',
            'language' => 'kk',
            'access_level' => 'authenticated',
            'allow_download' => true,
            'is_active' => true,
            'workflow_status' => 'published',
            'download_policy' => 'allowed',
        ]);
        DigitalMaterialAccessLog::query()->create([
            'electronic_material_id' => $material->getKey(),
            'user_id' => $firstReader->getKey(),
            'action' => 'download',
            'allowed' => true,
            'created_at' => now(),
        ]);
        $external = ExternalResource::query()->create([
            'slug' => 'operations-chain-resource',
            'title' => 'Operations chain external resource',
            'resource_type' => 'licensed',
            'description' => 'Isolated report fixture',
            'available_roles' => ['member'],
            'is_active' => true,
            'url' => 'https://example.test/operations-chain',
            'access_type' => 'remote_auth',
            'category' => 'research_database',
            'publication_status' => 'published',
        ]);
        foreach ([
            [$firstReader->getKey(), 'outbound_click'],
            [$secondReader->getKey(), 'access_denied'],
        ] as [$userId, $eventType]) {
            ExternalResourceEvent::query()->create([
                'external_resource_id' => $external->getKey(),
                'user_id' => $userId,
                'event_type' => $eventType,
                'role_name' => 'doctoral',
                'created_at' => now(),
            ]);
        }

        app(AuditLogger::class)->logRequired(
            actionType: 'operations.trace_probe',
            entityType: 'library_operation_chain',
            entityId: 'isolated-1',
            oldValues: ['status' => 'before', 'password' => 'audit-plain-password'],
            newValues: ['status' => 'after', 'integration' => ['api_token' => 'audit-plain-token']],
            metadata: ['authorization' => 'Bearer audit-plain-secret'],
            scope: 'operational',
            actor: $this->adminUser,
        );

        $all = $this->filters();
        $fundFilters = $this->filters(['branch_id' => $branch->getKey(), 'fund_id' => $fund->getKey()]);
        $readerFilters = $this->filters(['branch_id' => $branch->getKey(), 'user_segment' => 'doctoral']);
        $electronicFilters = $this->filters(['user_segment' => 'doctoral']);
        $reports = app(LibraryReportService::class);

        $this->assertSame(3, DB::table('book_copies')
            ->where('branch_id', $branch->getKey())
            ->where('fund_id', $fund->getKey())
            ->whereDate('registration_date', '2026-08-19')
            ->count());
        $this->assertSame('2026-08-18', $fundFilters->toArray()['from']);
        $this->assertSame('2026-08-20', $fundFilters->toArray()['to']);

        $acquisitions = $reports->dataset('acquisitions', $fundFilters);
        $this->assertSame(3, $this->metric($acquisitions, 'copies'));
        $this->assertSame(1, $this->metric($acquisitions, 'records'));
        $this->assertSame(600.0, (float) $this->metric($acquisitions, 'value'));
        $this->assertSame(3, (int) collect($acquisitions['rows'])->sum('copies'));
        $this->assertSame(600.0, (float) collect($acquisitions['rows'])->sum('total_value'));

        $fundUsage = $reports->dataset('fund-usage', $fundFilters);
        foreach (['copies' => 3, 'issued' => 2, 'returned' => 1, 'reservations' => 1] as $key => $expected) {
            $this->assertSame($expected, $this->metric($fundUsage, $key), "Fund report mismatch for {$key}.");
            $this->assertSame($expected, (int) collect($fundUsage['rows'])->sum($key));
        }

        $users = $reports->dataset('users', $readerFilters);
        foreach (['total' => 3, 'active_users' => 3, 'unique_users' => 3, 'visits' => 1, 'issued' => 2, 'returned' => 1] as $key => $expected) {
            $this->assertSame($expected, $this->metric($users, $key), "User report mismatch for {$key}.");
        }
        foreach ($users['rows'] as $row) {
            $this->assertArrayNotHasKey('user_ids', $row, 'Internal identity sets must not leave the report service.');
        }

        $electronic = $reports->dataset('electronic-resources', $electronicFilters);
        foreach (['total' => 3, 'downloads' => 1, 'logins' => 1, 'denied' => 1, 'failures' => 0, 'unique_users' => 2] as $key => $expected) {
            $this->assertSame($expected, $this->metric($electronic, $key), "Electronic report mismatch for {$key}.");
        }
        $this->assertSame(3, (int) collect($electronic['rows'])->sum('total'));

        $sourceTotals = [
            'loans' => DB::table('loans')->whereBetween('issued_at', [$all->from, $all->to])->count(),
            'returns' => DB::table('loans')->whereNotNull('returned_at')->whereBetween('returned_at', [$all->from, $all->to])->count(),
            'overdue' => DB::table('loans')->where('status', 'overdue')->whereBetween('due_at', [$all->from, $all->to])->count(),
            'reservations' => DB::table('reservations')->whereBetween('created_at', [$all->from, $all->to])->count(),
            'queue' => DB::table('reservations')->whereIn('status', ['pending', 'queued', 'confirmed', 'in_transit', 'ready_for_pickup'])
                ->whereBetween('created_at', [$all->from, $all->to])->count(),
            'data-quality' => DB::table('data_quality_issues')->whereBetween('first_detected_at', [$all->from, $all->to])->count(),
            'external-resources' => DB::table('external_resource_events')->whereBetween('created_at', [$all->from, $all->to])->count(),
            'electronic-materials' => DB::table('digital_material_access_logs')->whereBetween('created_at', [$all->from, $all->to])->count(),
            'fund-movement' => DB::table('copy_history')->whereBetween('occurred_at', [$all->from, $all->to])->count(),
            'new-acquisitions' => DB::table('book_copies')
                ->where('branch_id', $branch->getKey())
                ->where('fund_id', $fund->getKey())
                ->whereBetween('registration_date', ['2026-08-18', '2026-08-20'])
                ->count(),
            'audit-summary' => DB::table('activity_logs')->whereBetween('occurred_at', [$all->from, $all->to])->count(),
        ];
        foreach ($sourceTotals as $report => $sourceTotal) {
            $dataset = $reports->dataset($report, $report === 'new-acquisitions' ? $fundFilters : $all);
            $this->assertSame((int) $sourceTotal, $this->metric($dataset, 'total'), "{$report} total differs from its transaction source.");
            $this->assertSame((int) $sourceTotal, (int) collect($dataset['rows'])->sum('total'), "{$report} rows do not reconcile.");
        }

        $this->assertSame('returned', $firstLoan->fresh()->status);
        $this->assertSame('overdue', $secondLoan->fresh()->status);
        $this->assertSame(3, DB::table('book_copies')->where('fund_id', $fund->getKey())->count());

        foreach ([
            'circulation.issue', 'circulation.return', 'circulation.overdue_marked',
            'reservation.create', 'reservation.ready',
        ] as $action) {
            $entries = ActivityLog::query()->where('action_type', $action)->get();
            $this->assertNotEmpty($entries, "Missing audit action {$action}.");
            foreach ($entries as $entry) {
                $this->assertNotEmpty($entry->actor_name);
                $this->assertNotNull($entry->occurred_at);
                $this->assertNotEmpty($entry->entity_type);
                $this->assertNotEmpty($entry->entity_id);
                $this->assertNotNull($entry->old_values);
                $this->assertNotNull($entry->new_values);
                $this->assertSame('operational', $entry->scope);
            }
        }

        $auditViewer = $this->makeControlPlaneUser('librarian');
        $this->assertTrue(app(AuditLogger::class)->visibleQuery($auditViewer)
            ->where('action_type', 'circulation.overdue_marked')
            ->where('entity_id', (string) $secondLoan->getKey())
            ->exists(), 'A scheduler circulation trace must remain visible in the operational audit stream.');

        $probe = ActivityLog::query()->where('action_type', 'operations.trace_probe')->firstOrFail();
        $this->assertSame('[REDACTED]', $probe->old_values['password']);
        $this->assertSame('[REDACTED]', $probe->new_values['integration']['api_token']);
        $this->assertSame('[REDACTED]', $probe->metadata['authorization']);
        $serializedAudit = ActivityLog::query()->get()->toJson(JSON_UNESCAPED_UNICODE);
        foreach (['audit-plain-password', 'audit-plain-token', 'audit-plain-secret'] as $secret) {
            $this->assertStringNotContainsString($secret, $serializedAudit);
        }
    }

    /** @param array<string, scalar> $extra */
    private function filters(array $extra = []): ReportFilters
    {
        $from = now('Asia/Almaty')->subDay()->toDateString();
        $to = now('Asia/Almaty')->addDay()->toDateString();

        return ReportFilters::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'custom',
            'from' => $from,
            'to' => $to,
            ...$extra,
        ]));
    }

    /** @param array<string, mixed> $dataset */
    private function metric(array $dataset, string $key): int|float
    {
        $metric = collect($dataset['metrics'])->firstWhere('key', $key);
        $this->assertNotNull($metric, "Missing report metric {$key}.");

        return $metric['value'];
    }
}
