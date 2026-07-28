<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\BridgeController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\DemoAuthController;
use App\Http\Controllers\Api\DigitalMaterialController;
use App\Http\Controllers\Api\ExternalResourceController;
use App\Http\Controllers\Api\ShortlistController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\InternalAiAssistantController;
use App\Http\Controllers\Api\InternalCopyReadController;
use App\Http\Controllers\Api\InternalCopyWriteController;
use App\Http\Controllers\Api\InternalCirculationController;
use App\Http\Controllers\Api\InternalEnrichmentController;
use App\Http\Controllers\Api\InternalReaderContactController;
use App\Http\Controllers\Api\InternalReviewController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\LandingController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\Integration\DocumentManagementController;
use App\Http\Controllers\Api\Integration\ReservationReadController;
use App\Http\Controllers\Api\Integration\ReservationMutateController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::middleware('web')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // Demo/dev quick-login — gated by config('demo_auth.enabled').
    Route::get('/demo-auth/identities', [DemoAuthController::class, 'identities']);
    Route::post('/demo-auth/login', [DemoAuthController::class, 'login']);

    // Reader-authenticated routes — middleware enforces library.user session check.
    Route::middleware('library.auth')->group(function (): void {
        Route::get('/v1/account/summary', [AccountController::class, 'summary']);
        Route::get('/v1/account/loans', [AccountController::class, 'loans']);
        Route::get('/v1/account/loans/summary', [AccountController::class, 'loanSummary']);
        Route::post('/v1/account/loans/{loanId}/renew', [AccountController::class, 'renewLoan']);
        Route::get('/v1/account/reservations', [AccountController::class, 'reservations']);
        Route::post('/v1/account/reservations', [AccountController::class, 'createReservation']);
        Route::post('/v1/account/reservations/{id}/cancel', [AccountController::class, 'cancelReservation']);
        Route::get('/v1/account/reservations/check', [AccountController::class, 'checkReservation']);
        Route::get('/v1/me', [AuthController::class, 'me']);
        Route::post('/v1/logout', [AuthController::class, 'logout']);
    });

    // Shortlist routes — session-based, available to all session holders.
    Route::prefix('v1/shortlist')->group(function (): void {
        Route::get('/', [ShortlistController::class, 'index']);
        Route::get('/export', [ShortlistController::class, 'export']);
        Route::get('/summary', [ShortlistController::class, 'summary']);
        Route::patch('/draft', [ShortlistController::class, 'updateDraft']);
        Route::post('/', [ShortlistController::class, 'store']);
        Route::delete('/{identifier}', [ShortlistController::class, 'destroy'])->where('identifier', '.+');
        Route::post('/clear', [ShortlistController::class, 'clear']);
        Route::post('/check', [ShortlistController::class, 'check']);
    });
});

Route::prefix('v1')->group(function (): void {
    Route::prefix('internal/circulation')
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            'internal.circulation.staff',
        ])
        ->group(function (): void {
        Route::get('/loans/{loanId}', [InternalCirculationController::class, 'showLoan']);
        Route::get('/copies/{copyId}/active-loan', [InternalCirculationController::class, 'showActiveLoanForCopy']);
        Route::get('/readers/{readerId}/loans', [InternalCirculationController::class, 'listReaderLoans']);
        Route::post('/checkouts', [InternalCirculationController::class, 'checkout']);
        Route::post('/returns', [InternalCirculationController::class, 'returnCopy']);
        Route::post('/loans/{loanId}/renew', [InternalCirculationController::class, 'renewLoan']);
    });

    Route::prefix('internal')
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            'internal.circulation.staff',
        ])
        ->group(function (): void {
            Route::get('/copies/{copyId}', [InternalCopyReadController::class, 'show']);
            Route::get('/documents/{documentId}/copies', [InternalCopyReadController::class, 'listByDocument']);
            Route::post('/copies', [InternalCopyWriteController::class, 'store']);
            Route::patch('/copies/{copyId}', [InternalCopyWriteController::class, 'patch']);
            Route::post('/copies/{copyId}/retire', [InternalCopyWriteController::class, 'retire']);
            Route::get('/review/copies', [InternalReviewController::class, 'copyQueue']);
            Route::get('/review/copies-summary', [InternalReviewController::class, 'copySummary']);
            Route::post('/review/copies/{copyId}/resolve', [InternalReviewController::class, 'resolveCopy']);
            Route::post('/review/copies/bulk-resolve', [InternalReviewController::class, 'bulkResolveCopies']);
            Route::get('/review/documents', [InternalReviewController::class, 'documentQueue']);
            Route::get('/review/documents-summary', [InternalReviewController::class, 'documentSummary']);
            Route::post('/review/documents/{documentId}/flag', [InternalReviewController::class, 'flagDocument']);
            Route::post('/review/documents/{documentId}/resolve', [InternalReviewController::class, 'resolveDocument']);
            Route::post('/review/documents/bulk-flag', [InternalReviewController::class, 'bulkFlagDocuments']);
            Route::post('/review/documents/bulk-resolve', [InternalReviewController::class, 'bulkResolveDocuments']);
            Route::get('/review/readers', [InternalReviewController::class, 'readerQueue']);
            Route::get('/review/readers-summary', [InternalReviewController::class, 'readerSummary']);
            Route::post('/review/readers/{readerId}/resolve', [InternalReviewController::class, 'resolveReader']);
            Route::post('/review/readers/bulk-resolve', [InternalReviewController::class, 'bulkResolveReaders']);
            Route::get('/review/triage-summary', [InternalReviewController::class, 'triageSummary']);
            Route::get('/review/triage-reason-codes', [InternalReviewController::class, 'triageReasonCodes']);
            Route::get('/review/stewardship-metrics', [InternalReviewController::class, 'stewardshipMetrics']);
            Route::get('/enrichment/stats', [InternalEnrichmentController::class, 'stats']);
            Route::post('/enrichment/validate-isbn', [InternalEnrichmentController::class, 'validateIsbn']);
            Route::post('/enrichment/bulk-validate', [InternalEnrichmentController::class, 'bulkValidate']);
            Route::get('/enrichment/lookup/{documentId}', [InternalEnrichmentController::class, 'lookup']);
            Route::post('/enrichment/apply/{documentId}', [InternalEnrichmentController::class, 'apply']);
            Route::post('/enrichment/check-isbn', [InternalEnrichmentController::class, 'checkIsbn']);
            Route::get('/reader-contacts/stats', [InternalReaderContactController::class, 'stats']);
            Route::get('/reader-contacts/{readerId}', [InternalReaderContactController::class, 'contacts']);
            Route::put('/reader-contacts/{contactId}/update', [InternalReaderContactController::class, 'update']);
            Route::post('/reader-contacts/{readerId}/add', [InternalReaderContactController::class, 'add']);
            Route::post('/reader-contacts/bulk-normalize', [InternalReaderContactController::class, 'bulkNormalize']);
            Route::post('/reader-contacts/validate', [InternalReaderContactController::class, 'validate']);
        });

    Route::get('/bridge/summary', [BridgeController::class, 'summary']);
    Route::get('/bridge/users', [BridgeController::class, 'users']);
    Route::get('/bridge/copies', [BridgeController::class, 'copies']);
    Route::get('/bridge/books', [BridgeController::class, 'books']);

    // Canonical public catalog APIs (WS1 converged).
    Route::get('/book-db/{isbn}', [BookController::class, 'dbShow']);
    Route::get('/catalog-db', [CatalogController::class, 'dbIndex']);
    Route::get('/subjects', [SubjectController::class, 'index']);

    // External licensed resources (public, config-backed).
    Route::get('/external-resources', [ExternalResourceController::class, 'index']);
    Route::get('/external-resources/{slug}', [ExternalResourceController::class, 'show']);

    // Digital materials: controlled viewer access.
    Route::get('/documents/{documentId}/digital-materials', [DigitalMaterialController::class, 'forDocument']);
    Route::get('/digital-materials/{id}/stream', [DigitalMaterialController::class, 'stream']);

    Route::get('/library/health-summary', [LibraryController::class, 'healthSummary']);
    Route::get('/review/issues', [ReviewController::class, 'issues']);
    Route::get('/review/issues-summary', [ReviewController::class, 'issuesSummary']);

    // WS1 convergence freeze:
    // Legacy /v1/catalog and /v1/catalog/{isbn} routes removed (delete-after-confirmation wave).
    // Canonical public catalog/detail APIs: /v1/catalog-db, /v1/book-db/{isbn}.
    // Transitional external proxy kept only for reader fallback compatibility.
    Route::get('/catalog-external', [CatalogController::class, 'proxy']); // [WS1-FROZEN][TRANSITIONAL-EXTERNAL] reader fallback only

    Route::get('/landing', [LandingController::class, 'index']);

    Route::prefix('internal/ai-assistant')
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            'internal.circulation.staff',
        ])
        ->group(function (): void {
            Route::post('/token', [InternalAiAssistantController::class, 'token']);
            Route::post('/session', [InternalAiAssistantController::class, 'session']);
            Route::post('/thread', [InternalAiAssistantController::class, 'thread']);
        });
});

Route::prefix('integration/v1')
    ->middleware(['integration.boundary', 'integration.log', 'throttle:integration'])
    ->group(function (): void {
        // Technical boundary endpoint for infrastructure verification only.
        Route::get('/_boundary/ping', function (Request $request) {
            return response()->json([
                'ok' => true,
                'context' => [
                    'authenticated_client_ref' => $request->attributes->get('integration.authenticated_client_ref'),
                    'source_system' => $request->attributes->get('integration.source_system'),
                    'request_id' => $request->attributes->get('integration.request_id'),
                    'correlation_id' => $request->attributes->get('integration.correlation_id'),
                ],
            ]);
        });

        // Future external integration endpoints (reservation shell v1):
        // Read-only external reservation endpoints (shell v1, phase 1):
        Route::get('/reservations', [ReservationReadController::class, 'index']);
        Route::get('/reservations/{id}', [ReservationReadController::class, 'show']);

        // Mutate endpoints (shell v1, phase 2): approve / reject only.
        Route::middleware('throttle:integration-mutate')->group(function (): void {
            Route::post('/reservations/{id}/approve', [ReservationMutateController::class, 'approve']);
            Route::post('/reservations/{id}/reject', [ReservationMutateController::class, 'reject']);
        });

        // Book management v1 phase 1 (document metadata only).
        Route::get('/documents', [DocumentManagementController::class, 'index']);
        Route::get('/documents/{id}', [DocumentManagementController::class, 'show']);
        Route::middleware('throttle:integration-mutate')->group(function (): void {
            Route::post('/documents', [DocumentManagementController::class, 'store']);
            Route::patch('/documents/{id}', [DocumentManagementController::class, 'patch']);
            Route::post('/documents/{id}/archive', [DocumentManagementController::class, 'archive']);
        });
    });

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
