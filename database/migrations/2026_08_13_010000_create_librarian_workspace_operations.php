<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'tasks.view', 'tasks.manage_own', 'tasks.assign',
        'acquisitions.view', 'edd.view', 'edd.manage',
        'periodicals.view', 'periodicals.manage', 'calendar.view',
    ];

    public function up(): void
    {
        Schema::create('library_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('type', 64)->default('general');
            $table->string('related_entity_type', 120)->nullable();
            $table->string('related_entity_id', 80)->nullable();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->string('priority', 16)->default('normal');
            $table->timestampTz('due_at')->nullable();
            $table->string('status', 24)->default('open');
            $table->text('comment')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
            $table->index(['assigned_to', 'status', 'due_at'], 'library_tasks_assignee_queue_idx');
            $table->index(['status', 'priority', 'due_at'], 'library_tasks_priority_queue_idx');
        });

        Schema::create('acquisition_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 64)->unique();
            $table->string('supplier')->nullable();
            $table->string('status', 24)->default('requested');
            $table->date('ordered_at')->nullable();
            $table->date('expected_at')->nullable();
            $table->date('received_at')->nullable();
            $table->char('currency', 3)->default('KZT');
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('created_by')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->cascadeOnUpdate()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'expected_at'], 'acquisition_orders_queue_idx');
        });

        Schema::create('acquisition_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('acquisition_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bibliographic_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title_snapshot');
            $table->unsignedInteger('quantity_ordered')->default(1);
            $table->unsignedInteger('quantity_received')->default(0);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->timestampsTz();
            $table->index(['acquisition_order_id', 'bibliographic_record_id'], 'acquisition_items_record_idx');
        });

        Schema::create('document_delivery_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_number', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('requested_document');
            $table->string('source')->nullable();
            $table->string('status', 24)->default('requested');
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('due_at')->nullable();
            $table->text('result')->nullable();
            $table->text('rights_restrictions')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'responsible_id', 'due_at'], 'document_delivery_queue_idx');
        });

        Schema::create('periodical_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bibliographic_record_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title_snapshot');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('expected_issues')->default(0);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fund_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shelf')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestampsTz();
            $table->unique(['bibliographic_record_id', 'year', 'branch_id'], 'periodical_subscription_scope_unique');
            $table->index(['status', 'year'], 'periodical_subscriptions_status_year_idx');
        });

        Schema::create('periodical_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('periodical_subscription_id')->constrained()->cascadeOnDelete();
            $table->string('issue_number', 64);
            $table->date('expected_at')->nullable();
            $table->date('received_at')->nullable();
            $table->string('status', 24)->default('expected');
            $table->text('notes')->nullable();
            $table->timestampsTz();
            $table->unique(['periodical_subscription_id', 'issue_number'], 'periodical_issue_number_unique');
            $table->index(['status', 'expected_at'], 'periodical_issues_receipt_queue_idx');
        });

        $this->installPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('periodical_issues');
        Schema::dropIfExists('periodical_subscriptions');
        Schema::dropIfExists('document_delivery_requests');
        Schema::dropIfExists('acquisition_order_items');
        Schema::dropIfExists('acquisition_orders');
        Schema::dropIfExists('library_tasks');
    }

    private function installPermissions(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles') || ! Schema::hasTable('role_has_permissions')) {
            return;
        }

        $now = now('UTC');
        foreach (self::PERMISSIONS as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }

        $grants = [
            'librarian' => ['tasks.view', 'tasks.manage_own', 'edd.view', 'edd.manage', 'periodicals.view', 'calendar.view'],
            'senior_librarian' => self::PERMISSIONS,
            'acquisitions' => ['tasks.view', 'tasks.manage_own', 'acquisitions.view', 'periodicals.view', 'periodicals.manage', 'calendar.view'],
            'bibliographer' => ['tasks.view', 'tasks.manage_own', 'edd.view', 'edd.manage', 'periodicals.view', 'calendar.view'],
            'director' => ['tasks.view', 'tasks.assign', 'acquisitions.view', 'edd.view', 'periodicals.view', 'calendar.view'],
            'admin' => self::PERMISSIONS,
        ];
        foreach ($grants as $role => $permissions) {
            $roleId = DB::table('roles')->where(['name' => $role, 'guard_name' => 'web'])->value('id');
            if ($roleId === null) {
                continue;
            }
            $ids = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $permissions)->pluck('id');
            foreach ($ids as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
