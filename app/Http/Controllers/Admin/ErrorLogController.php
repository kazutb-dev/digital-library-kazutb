<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\LaravelLogReader;
use App\Support\DatabaseSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * System error log — exceptions and failed queue jobs, distinct from the
 * user-action audit log. Strictly read-only: nothing is ever written to the
 * log from here, and credential-looking values are masked before rendering.
 */
class ErrorLogController extends Controller
{
    public function index(Request $request, LaravelLogReader $reader): View
    {
        $filters = $request->validate([
            'level' => ['nullable', Rule::in(['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'])],
        ]);

        $logPath = storage_path('logs/laravel.log');
        $level = $filters['level'] ?? null;

        $failedJobsAvailable = DatabaseSchema::hasTable('failed_jobs');
        $failedJobs = $failedJobsAvailable
            ? DB::table('failed_jobs')->orderByDesc('failed_at')->limit(50)->get()
                ->map(static function (object $job) use ($reader): object {
                    $job->exception = $reader->maskSecrets((string) $job->exception);

                    return $job;
                })
            : collect();

        return view('admin.logs.errors', [
            'entries' => $reader->entries($logPath, $level),
            'levelCounts' => $reader->levelCounts($logPath),
            'logAvailable' => is_readable($logPath),
            'level' => $level,
            'failedJobs' => $failedJobs,
            'failedJobsAvailable' => $failedJobsAvailable,
        ]);
    }
}
