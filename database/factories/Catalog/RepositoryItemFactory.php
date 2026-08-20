<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\RepositoryApproval;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\RepositoryItemVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use LogicException;

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
            'work_type' => $this->faker->randomElement(RepositoryItem::WORK_TYPES),
            'year' => $this->faker->numberBetween(2015, 2026),
            'department' => $this->faker->randomElement(['Экономика', 'Инжиниринг и ИТ', 'Технологии']),
            'abstract' => $this->faker->paragraph(),
            'keywords' => $this->faker->words(4),
            'language' => 'ru',
            'status' => 'draft',
            'access_policy' => 'metadata_only',
            'copyright_status' => 'unknown',
            'version_number' => 1,
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Build a genuinely publishable fixture with immutable director evidence.
     * A published factory record cannot silently bypass the production rules.
     */
    public function published(User $director): static
    {
        if (! $director->is_active || ! $director->hasRole('director')) {
            throw new LogicException('A published repository fixture requires an active director.');
        }

        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now(),
            'approved_by' => $director->getKey(),
            'access_policy' => 'full_public',
            'embargo_until' => null,
            'copyright_status' => 'university_owned',
            'rights_holder' => 'KazUTB',
            'source' => 'Institutional repository intake',
        ])->afterCreating(function (RepositoryItem $item) use ($director): void {
            if ((int) $item->uploaded_by === (int) $director->getKey()) {
                throw new LogicException('A repository uploader cannot approve their own fixture.');
            }

            $pdf = "%PDF-1.4\n% factory repository record {$item->getKey()}\n%%EOF\n";
            $path = "repository/factory/{$item->public_id}/v1/work.pdf";
            if (! Storage::disk('local')->put($path, $pdf)) {
                throw new LogicException('Unable to store repository fixture PDF.');
            }

            $checksum = hash('sha256', $pdf);
            $item->forceFill([
                'file_path' => $path,
                'file_name' => 'work.pdf',
                'file_size' => strlen($pdf),
                'checksum_sha256' => $checksum,
                'version_number' => 1,
            ])->save();

            $version = RepositoryItemVersion::query()->create([
                'repository_item_id' => $item->getKey(),
                'version_number' => 1,
                'storage_disk' => 'local',
                'file_path' => $path,
                'file_name' => 'work.pdf',
                'file_size' => strlen($pdf),
                'mime_type' => 'application/pdf',
                'checksum_sha256' => $checksum,
                'change_reason' => 'Factory fixture creation.',
                'created_by' => $item->uploaded_by,
                'is_active' => true,
            ]);
            $approval = RepositoryApproval::query()->create([
                'repository_item_id' => $item->getKey(),
                'repository_item_version_id' => $version->getKey(),
                'approver_id' => $director->getKey(),
                'approver_role_snapshot' => 'director',
                'checksum_sha256' => $checksum,
                'metadata_fingerprint' => $item->approvalFingerprint($version),
                'approved_at' => now('UTC'),
            ]);
            $item->forceFill(['active_approval_id' => $approval->getKey()])->save();
        });
    }

    /** Attach the director whose business approval made the state publishable. */
    public function approvedBy(User $director): static
    {
        return $this->state(fn (): array => ['approved_by' => $director->getKey()]);
    }
}
