<?php

namespace Tests\Unit\Support;

use App\Support\StoredUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StoredUploadTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_failed_storage_write_is_never_accepted_as_a_path(): void
    {
        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('store')
            ->once()
            ->with('news-covers', 'public')
            ->andReturn(false);

        $this->expectException(RuntimeException::class);

        StoredUpload::put($file, 'news-covers', 'public');
    }

    public function test_transaction_compensation_removes_a_stored_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('news-covers/probe.jpg', 'probe');

        StoredUpload::deleteOrReport('news-covers/probe.jpg', 'public');

        Storage::disk('public')->assertMissing('news-covers/probe.jpg');
    }
}
