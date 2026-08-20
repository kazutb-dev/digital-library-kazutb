<?php

use App\Models\Catalog\RepositoryApproval;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\RepositoryItemVersion;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained('repository_items')->cascadeOnDelete();
            $table->foreignId('repository_item_version_id')->constrained('repository_item_versions')->restrictOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('approver_role_snapshot', 64);
            $table->string('checksum_sha256', 64);
            $table->string('metadata_fingerprint', 64);
            $table->timestampTz('approved_at');
            $table->timestampsTz();

            // The same PDF may be approved again after corrected metadata. Each
            // decision remains a separate immutable audit record.
            $table->index(['repository_item_id', 'repository_item_version_id'], 'repository_approval_version_idx');
            $table->index(['repository_item_id', 'approved_at'], 'repository_approval_item_date_idx');
        });

        Schema::table('repository_items', function (Blueprint $table): void {
            $table->foreignId('active_approval_id')->nullable()
                ->after('approved_by')
                ->constrained('repository_approvals')
                ->nullOnDelete();
            $table->string('post_embargo_access_policy', 64)->nullable()->after('embargo_until');
        });

        // This is intentionally additive: the historical 2026-08-11 migration
        // already made new records public-by-default. A new intake has not had
        // its rights reviewed yet, so the hardened default is metadata-only;
        // full-public access remains an explicit, director-approved decision.
        Schema::table('repository_items', function (Blueprint $table): void {
            $table->string('access_policy', 64)->default('metadata_only')->change();
        });

        $this->backfillVerifiableApprovals();

        Schema::create('repository_usage_daily', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained('repository_items')->cascadeOnDelete();
            $table->date('occurred_on');
            $table->string('event_type', 32);
            $table->string('role_name', 96)->default('guest');
            $table->string('locale', 8)->default('ru');
            $table->unsignedBigInteger('event_count')->default(0);

            $table->unique(
                ['repository_item_id', 'occurred_on', 'event_type', 'role_name', 'locale'],
                'repository_usage_daily_dimension_unique',
            );
            $table->index(['occurred_on', 'event_type'], 'repository_usage_daily_date_type_idx');
            $table->index(['role_name', 'occurred_on'], 'repository_usage_daily_role_date_idx');
        });

        // Preserve historical totals while irreversibly removing reader IDs and
        // precise timestamps. Role/locale/date are the only reporting dimensions.
        if (Schema::hasTable('repository_access_events')) {
            DB::table('repository_access_events')
                ->selectRaw('repository_item_id, occurred_on, event_type, role_name, locale, COUNT(*) AS aggregate')
                ->groupBy('repository_item_id', 'occurred_on', 'event_type', 'role_name', 'locale')
                ->orderBy('repository_item_id')
                ->each(function (object $row): void {
                    DB::table('repository_usage_daily')->insert([
                        'repository_item_id' => $row->repository_item_id,
                        'occurred_on' => $row->occurred_on,
                        'event_type' => $row->event_type,
                        'role_name' => $row->role_name ?: 'guest',
                        'locale' => $row->locale ?: 'ru',
                        'event_count' => (int) $row->aggregate,
                    ]);
                });

            Schema::drop('repository_access_events');
        }
    }

    public function down(): void
    {
        Schema::create('repository_access_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained('repository_items')->cascadeOnDelete();
            $table->string('event_type', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('role_name', 96)->default('guest');
            $table->string('locale', 8)->default('ru');
            $table->date('occurred_on');
            $table->timestampsTz();
        });

        Schema::dropIfExists('repository_usage_daily');

        Schema::table('repository_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('active_approval_id');
            $table->dropColumn('post_embargo_access_policy');
            $table->string('access_policy', 64)->default('full_public')->change();
        });

        Schema::dropIfExists('repository_approvals');
    }

    /**
     * Convert only legacy approvals for which the database already contains
     * director, review and exact-version evidence. Ambiguous legacy rows stay
     * private until a director reviews them again; the migration never invents
     * an approval snapshot.
     */
    private function backfillVerifiableApprovals(): void
    {
        if (! Schema::hasTable('repository_reviews')
            || ! Schema::hasTable('repository_item_versions')
            || ! Schema::hasTable('roles')
            || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        RepositoryItem::query()
            ->whereIn('status', ['approved', 'scheduled', 'published', 'embargoed', 'withdrawn'])
            ->whereNotNull('approved_by')
            ->orderBy('id')
            ->chunkById(100, function ($items): void {
                foreach ($items as $item) {
                    if ((int) $item->uploaded_by === (int) $item->approved_by
                        || ! $item->rightsPermitPublication()) {
                        continue;
                    }

                    $approver = User::query()->find($item->approved_by);
                    if ($approver === null || ! $approver->hasRole('director')) {
                        continue;
                    }

                    $review = DB::table('repository_reviews')
                        ->where('repository_item_id', $item->getKey())
                        ->where('reviewer_id', $approver->getKey())
                        ->where('decision', 'approved')
                        ->latest('id')
                        ->first();
                    if ($review === null) {
                        continue;
                    }

                    $version = RepositoryItemVersion::query()
                        ->where('repository_item_id', $item->getKey())
                        ->where('version_number', $item->version_number)
                        ->where('is_active', true)
                        ->first();
                    if (! $this->versionMatchesItem($version, $item)) {
                        continue;
                    }

                    $approval = RepositoryApproval::query()->create([
                        'repository_item_id' => $item->getKey(),
                        'repository_item_version_id' => $version->getKey(),
                        'approver_id' => $approver->getKey(),
                        'approver_role_snapshot' => 'director',
                        'checksum_sha256' => $version->checksum_sha256,
                        'metadata_fingerprint' => $item->approvalFingerprint($version),
                        'approved_at' => $review->created_at ?? $item->updated_at ?? now('UTC'),
                    ]);

                    DB::table('repository_items')->where('id', $item->getKey())->update([
                        'active_approval_id' => $approval->getKey(),
                    ]);
                }
            });
    }

    private function versionMatchesItem(?RepositoryItemVersion $version, RepositoryItem $item): bool
    {
        return $version !== null
            && filled($version->checksum_sha256)
            && filled($item->checksum_sha256)
            && hash_equals((string) $version->checksum_sha256, (string) $item->checksum_sha256)
            && (string) $version->file_path === (string) $item->file_path
            && (int) $version->version_number === (int) $item->version_number;
    }
};
