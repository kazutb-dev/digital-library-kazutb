<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

trait UsesIsolatedLocalStorage
{
    protected function fakeIsolatedLocalStorage(): void
    {
        $root = storage_path('framework/testing-isolated/'.sha1(static::class));

        File::deleteDirectory($root);
        File::ensureDirectoryExists($root);

        $this->beforeApplicationDestroyed(static function () use ($root): void {
            File::deleteDirectory($root);
        });

        Storage::set('local', Storage::build([
            'driver' => 'local',
            'root' => $root,
            'throw' => false,
        ]));
    }
}
