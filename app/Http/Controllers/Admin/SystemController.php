<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\VerifiedBackupService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class SystemController extends Controller
{
    public function index(VerifiedBackupService $backups): View
    {
        $dbOk = false;
        try {
            DB::select('select 1');
            $dbOk = true;
        } catch (\Throwable) {
        }

        return view('admin.system.index', [
            'health' => [
                'application' => true,
                'database' => $dbOk,
                'storage' => is_writable(storage_path('app')),
                'queue_pending' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0,
                'queue_failed' => Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0,
                'migrations' => Schema::hasTable('migrations') ? DB::table('migrations')->count() : 0,
                'filesystem_free' => disk_free_space(storage_path()) ?: 0,
            ],
            'backups' => $backups->backups(),
        ]);
    }

    public function createBackup(Request $request, VerifiedBackupService $backups, AuditLogger $audit): RedirectResponse
    {
        $backup = $backups->create();
        $audit->logRequired('backup.create', 'database_backup', $backup['name'], newValues: ['sha256' => $backup['sha256'], 'size' => $backup['size']], scope: 'system', request: $request);

        return back()->with('success', 'Backup created and TOC verified.');
    }

    public function restoreTest(Request $request, string $backup, VerifiedBackupService $backups, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', 'in:RESTORE TO TEST']]);
        $result = $backups->restoreToTest($backup);
        $audit->logRequired('backup.restore_test', 'database_backup', $backup, newValues: $result, scope: 'system', request: $request);

        return back()->with('success', 'Restore test verified in '.$result['database'].'.');
    }
}
