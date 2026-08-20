<?php

namespace App\Providers;

use App\Directory\ActiveDirectoryClientInterface;
use App\Directory\LdapActiveDirectoryClient;
use App\Models\Branch;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\RepositoryItem;
use App\Models\ContactMessage;
use App\Models\ExternalResource;
use App\Models\Fund;
use App\Models\Library\CirculationLoan;
use App\Models\Library\DigitalMaterial;
use App\Models\Library\Document;
use App\Models\LiteratureDraft;
use App\Models\News;
use App\Models\OfficialReportSnapshot;
use App\Models\ReportExportJob;
use App\Models\Setting;
use App\Models\User;
use App\Policies\CatalogPolicy;
use App\Policies\CirculationPolicy;
use App\Policies\DigitalMaterialPolicy;
use App\Policies\OfficialReportSnapshotPolicy;
use App\Policies\RepositoryPolicy;
use App\Policies\ReservationPolicy;
use App\Services\AuditLogger;
use App\Support\DestructiveDatabaseCommandGuard;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ActiveDirectoryClientInterface::class, LdapActiveDirectoryClient::class);
    }

    public function boot(): void
    {
        // The application APIs are session-aware. Keep their CORS contract
        // same-origin even when a stale framework default config is cached.
        $applicationOrigin = rtrim((string) config('app.url'), '/');
        config([
            'cors.paths' => ['api/*', 'sanctum/csrf-cookie'],
            'cors.allowed_methods' => ['*'],
            'cors.allowed_origins' => [$applicationOrigin],
            'cors.allowed_origins_patterns' => [],
            'cors.allowed_headers' => ['*'],
            'cors.exposed_headers' => [],
            'cors.max_age' => 600,
            'cors.supports_credentials' => true,
        ]);

        Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
            $requestedConnection = $event->input->getParameterOption('--database', null, true);
            $connection = is_string($requestedConnection) && trim($requestedConnection) !== ''
                ? trim($requestedConnection)
                : (string) config('database.default');
            $database = (string) config("database.connections.{$connection}.database");

            DestructiveDatabaseCommandGuard::assertSafe(
                $event->command,
                (string) app()->environment(),
                $connection,
                $database,
            );
        });

        $locale = in_array(app()->getLocale(), ['ru', 'kk', 'en'], true) ? app()->getLocale() : 'kk';

        app()->setLocale($locale);
        View::share('pageLang', $locale);

        Relation::morphMap([
            'user' => User::class,
            'news' => News::class,
            'contact_message' => ContactMessage::class,
            'setting' => Setting::class,
            'branch' => Branch::class,
            'fund' => Fund::class,
            'external_resource' => ExternalResource::class,
        ]);

        $this->registerAuthorization();

        // Login rate limiter — prevents brute force on /api/login
        $loginLimit = (int) env('LOGIN_RATE_LIMIT', 5);
        RateLimiter::for('login', function (Request $request) use ($loginLimit) {
            $rawIdentifier = $request->input('login') ?? $request->input('email');
            if ($rawIdentifier === null || trim((string) $rawIdentifier) === '') {
                $demoRole = (string) ($request->input('role') ?? $request->route('role') ?? '');
                $rawIdentifier = array_key_exists($demoRole, (array) config('demo_users.identities'))
                    ? 'demo:'.$demoRole
                    : 'anonymous';
            }
            $identifier = mb_strtolower(trim((string) $rawIdentifier));
            $key = 'login:'.hash('sha256', $identifier).'|'.$request->ip();

            return Limit::perMinute($loginLimit)
                ->by($key)
                ->response(function (Request $request, array $headers) use ($identifier) {
                    app(AuditLogger::class)->logRequired(
                        actionType: 'login.throttled',
                        entityType: 'authentication',
                        entityId: $identifier !== '' ? $identifier : 'anonymous',
                        newValues: ['reason' => 'rate_limited'],
                        scope: 'security',
                        metadata: ['limiter' => 'login'],
                        actor: ['name' => $identifier !== '' ? $identifier : 'anonymous', 'role' => 'guest'],
                        request: $request,
                    );

                    return response()->json([
                        'message' => __('auth.messages.too_many_attempts', [
                            'seconds' => (int) ($headers['Retry-After'] ?? 60),
                        ]),
                    ], 429, $headers);
                });
        });

        // CRM integration rate limits — per client reference (bearer token hash)
        // Configurable via env: INTEGRATION_RATE_LIMIT (default 120),
        // INTEGRATION_MUTATE_RATE_LIMIT (default 30)
        $globalLimit = (int) env('INTEGRATION_RATE_LIMIT', 120);
        $mutateLimit = (int) env('INTEGRATION_MUTATE_RATE_LIMIT', 30);

        RateLimiter::for('integration', function (Request $request) use ($globalLimit) {
            $clientRef = $request->attributes->get('integration.authenticated_client_ref', 'unknown');

            return Limit::perMinute($globalLimit)->by('integration:'.$clientRef);
        });

        $externalResourceLimit = max(10, min(
            600,
            (int) env('EXTERNAL_RESOURCE_PUBLIC_RATE_LIMIT', 120),
        ));
        RateLimiter::for('external-resources', function (Request $request) use ($externalResourceLimit) {
            $actor = $request->user()?->getKey();
            $key = $actor !== null
                ? 'user:'.$actor
                : 'guest:'.hash('sha256', (string) $request->ip());

            return Limit::perMinute($externalResourceLimit)->by('external-resources:'.$key);
        });

        $this->registerIntegrationLimiters($mutateLimit);

        $repositoryReadLimit = max(10, (int) env('REPOSITORY_READ_RATE_LIMIT', 120));
        RateLimiter::for('repository-read', function (Request $request) use ($repositoryReadLimit) {
            $identity = $request->user()?->getAuthIdentifier();
            $key = $identity === null
                ? 'guest:'.hash('sha256', (string) $request->ip())
                : 'user:'.$identity;

            return Limit::perMinute($repositoryReadLimit)->by('repository:'.$key);
        });
    }

    /**
     * Wires the RBAC policies.
     *
     * Model-backed domains are registered the usual way. Reservations have no
     * Eloquent model — the reservation service returns arrays from the CRM — so
     * ReservationPolicy is exposed as explicit gates instead. Those gate names
     * use a `reservation:` prefix rather than the `reservation.` permission
     * prefix: Spatie resolves permissions through a Gate::before callback, and
     * identical names would make a permission silently shadow the policy.
     */
    private function registerAuthorization(): void
    {
        Gate::policy(Document::class, CatalogPolicy::class);
        Gate::policy(CirculationLoan::class, CirculationPolicy::class);
        Gate::policy(DigitalMaterial::class, DigitalMaterialPolicy::class);
        Gate::policy(ElectronicMaterial::class, DigitalMaterialPolicy::class);
        Gate::policy(LiteratureDraft::class, RepositoryPolicy::class);
        Gate::policy(RepositoryItem::class, RepositoryPolicy::class);
        Gate::policy(OfficialReportSnapshot::class, OfficialReportSnapshotPolicy::class);
        Gate::policy(ReportExportJob::class, OfficialReportSnapshotPolicy::class);

        Gate::define('reservation:create', [ReservationPolicy::class, 'create']);
        Gate::define('reservation:cancel', [ReservationPolicy::class, 'cancel']);
        Gate::define('reservation:confirm', [ReservationPolicy::class, 'confirm']);
        Gate::define('reservation:override-limits', [ReservationPolicy::class, 'overrideLimits']);
    }

    private function registerIntegrationLimiters(int $mutateLimit): void
    {
        // Stricter limiter for mutation (write) endpoints
        RateLimiter::for('integration-mutate', function (Request $request) use ($mutateLimit) {
            $clientRef = $request->attributes->get('integration.authenticated_client_ref', 'unknown');

            return Limit::perMinute($mutateLimit)
                ->by('integration:mutate:'.$clientRef)
                ->response(function () use ($mutateLimit) {
                    return response()->json([
                        'error' => [
                            'error_code' => 'rate_limit_exceeded',
                            'reason_code' => 'mutation_rate_exceeded',
                            'message' => "Mutation rate limit exceeded. Max {$mutateLimit} write operations per minute.",
                        ],
                    ], 429);
                });
        });
    }
}
