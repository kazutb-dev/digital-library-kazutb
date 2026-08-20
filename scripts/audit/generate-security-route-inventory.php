<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Route;

$root = dirname(__DIR__, 2);
$cachePrefix = sys_get_temp_dir().'/kazutb-security-route-inventory-'.bin2hex(random_bytes(6));

foreach ([
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $cachePrefix.'-config.php',
    'APP_EVENTS_CACHE' => $cachePrefix.'-events.php',
    'APP_PACKAGES_CACHE' => $cachePrefix.'-packages.php',
    'APP_ROUTES_CACHE' => $cachePrefix.'-routes.php',
    'APP_SERVICES_CACHE' => $cachePrefix.'-services.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$router = $app->make('router');

$stateMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
$unsafeGetPattern = '/(?:delete|remove|assign|approve|publish|archive|activate|deactivate|logout|change)/i';
$browserWrites = [];
$api = [];
$unsafeGets = [];

foreach ($router->getRoutes() as $route) {
    if (! $route instanceof Route) {
        continue;
    }

    $methods = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
    $uri = $route->uri();
    $resolvedMiddleware = array_values($router->gatherRouteMiddleware($route));
    $stateChanging = array_intersect($methods, $stateMethods) !== [];
    $sessionBased = collect($resolvedMiddleware)->contains(
        fn (string $middleware): bool => str_contains($middleware, 'StartSession')
            || str_contains($middleware, 'EnsureAuthenticatedReader')
            || str_contains($middleware, 'EnsureInternalCirculationStaff')
    );
    $csrf = collect($resolvedMiddleware)->contains(
        fn (string $middleware): bool => is_a($middleware, PreventRequestForgery::class, true)
    );
    $permissions = array_values(array_map(
        static fn (string $middleware): string => substr($middleware, strpos($middleware, ':') + 1),
        array_filter($resolvedMiddleware, static fn (string $middleware): bool => str_contains($middleware, 'PermissionMiddleware:')),
    ));
    $rateLimits = array_values(array_map(
        static fn (string $middleware): string => substr($middleware, strpos($middleware, ':') + 1),
        array_filter($resolvedMiddleware, static fn (string $middleware): bool => str_contains($middleware, 'ThrottleRequests:')),
    ));
    $auth = array_values(array_filter($resolvedMiddleware, static fn (string $middleware): bool => (
        str_contains($middleware, 'Authenticate')
        || str_contains($middleware, 'EnsureAuthenticatedReader')
        || str_contains($middleware, 'EnsureInternalCirculationStaff')
        || str_contains($middleware, 'EnsureIntegrationBoundary')
        || str_contains($middleware, 'EnsureControlPlaneAccess')
        || str_contains($middleware, 'EnsureLibrarianStaff')
    )));

    $entry = [
        'method' => implode('|', $methods),
        'route' => '/'.$uri,
        'name' => $route->getName(),
        'action' => $route->getActionName(),
        'auth' => $auth,
        'permissions' => $permissions,
        'rate_limits' => $rateLimits,
        'csrf' => $csrf,
        'session_based' => $sessionBased,
        'state_change' => $stateChanging,
        'policy_middleware' => collect($resolvedMiddleware)->contains(
            fn (string $middleware): bool => str_contains($middleware, 'Authorize:')
        ),
    ];

    if ($stateChanging && $sessionBased) {
        $browserWrites[] = $entry;
    }
    if (str_starts_with($uri, 'api/')) {
        $api[] = $entry;
    }
    if (in_array('GET', $methods, true) && preg_match($unsafeGetPattern, $uri)) {
        $unsafeGets[] = $entry;
    }
}

$artifact = [
    'generated_at_utc' => gmdate(DATE_ATOM),
    'source' => 'Laravel runtime route collection with isolated testing configuration',
    'summary' => [
        'browser_state_changing_routes' => count($browserWrites),
        'browser_state_changing_csrf_gaps' => count(array_filter($browserWrites, fn (array $route): bool => ! $route['csrf'])),
        'api_routes' => count($api),
        'unsafe_get_name_candidates' => count($unsafeGets),
    ],
    'browser_state_changing_routes' => $browserWrites,
    'unsafe_get_name_candidates' => $unsafeGets,
    'api_routes' => $api,
];

$output = $root.'/docs/runtime/SECURITY-ROUTE-INVENTORY.json';
if (file_put_contents($output, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n") === false) {
    throw new RuntimeException('Unable to write '.$output);
}

fwrite(STDOUT, json_encode(['output' => $output, 'summary' => $artifact['summary']], JSON_UNESCAPED_SLASHES)."\n");
