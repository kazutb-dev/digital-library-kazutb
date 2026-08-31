<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class LibraryOperationSettingsTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_senior_librarian_can_view_and_update_settings_with_an_audit_trail(): void
    {
        $senior = $this->makeControlPlaneUser('senior_librarian');
        $index = route('librarian.settings.library-operations.index');

        $this->signInToLibraryAs($senior)
            ->get($index)
            ->assertOk()
            ->assertSee(__('library_settings.title'))
            ->assertSee('data-settings-destination="library-operations"', false)
            ->assertDontSee('data-settings-destination="admin"', false);

        $this->signInToLibraryAs($senior)
            ->from($index)
            ->patch(route('librarian.settings.library-operations.update'), $this->validSettings([
                'max_active_loans' => 8,
            ]))
            ->assertRedirect($index)
            ->assertSessionHas('success', __('library_settings.saved'));

        $setting = Setting::query()->where('key', 'max_active_loans')->firstOrFail();
        $this->assertSame(8, $setting->value);

        $audit = ActivityLog::query()
            ->where('actor_id', $senior->getKey())
            ->where('action_type', 'update')
            ->where('entity_type', 'setting')
            ->where('entity_id', (string) $setting->getKey())
            ->sole();

        $this->assertSame(['key' => 'max_active_loans', 'value' => 5], $audit->old_values);
        $this->assertSame(['key' => 'max_active_loans', 'value' => 8], $audit->new_values);
        $this->assertSame('system', $audit->scope);
    }

    public function test_librarian_cannot_view_or_update_library_operation_settings(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');

        $this->signInToLibraryAs($librarian)
            ->get(route('librarian.settings.library-operations.index'))
            ->assertForbidden();

        $this->signInToLibraryAs($librarian)
            ->patch(route('librarian.settings.library-operations.update'), $this->validSettings())
            ->assertForbidden();
    }

    public function test_admin_can_use_library_operation_settings_but_sidebar_prefers_admin_settings(): void
    {
        $index = route('librarian.settings.library-operations.index');

        $this->signInToLibraryAs($this->adminUser)
            ->get($index)
            ->assertOk()
            ->assertSee('data-settings-destination="admin"', false)
            ->assertDontSee('data-settings-destination="library-operations"', false);

        $this->signInToLibraryAs($this->adminUser)
            ->from($index)
            ->patch(route('librarian.settings.library-operations.update'), $this->validSettings([
                'default_service_point' => 'Main desk',
            ]))
            ->assertRedirect($index);

        $this->assertSame('Main desk', Setting::query()->where('key', 'default_service_point')->value('value'));
    }

    /**
     * @param  array<string, int|string>  $overrides
     * @return array<string, int|string>
     */
    private function validSettings(array $overrides = []): array
    {
        return array_merge([
            'max_active_reservations' => 3,
            'reservation_hold_days' => 1,
            'max_active_loans' => 5,
            'standard_loan_period_days' => 14,
            'renewal_allowed' => '1',
            'renewal_period_days' => 14,
            'max_renewals' => 1,
            'fine_per_overdue_day' => 100,
            'inventory_batch_scan_limit' => 5000,
            'inventory_numbering_enabled' => '1',
            'inventory_number_prefix' => 'INV',
            'barcode_generation_enabled' => '1',
            'barcode_prefix' => 'KAZUTB',
            'ksu_numbering_enabled' => '1',
            'ksu_yearly_reset' => '1',
            'default_service_point' => '',
            'default_sigla' => '',
        ], $overrides);
    }
}
