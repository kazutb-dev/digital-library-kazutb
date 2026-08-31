<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ErrorLogController;
use App\Http\Controllers\Admin\ExternalResourceController as AdminExternalResourceController;
use App\Http\Controllers\Admin\FundController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\IntegrationController;
use App\Http\Controllers\Admin\LibraryDataRecoveryController;
use App\Http\Controllers\Admin\NewsController as AdminNewsController;
use App\Http\Controllers\Admin\ProfileController as AdminProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ContactMessageSubmissionController;
use App\Http\Controllers\DigitalViewerController;
use App\Http\Controllers\DiscoverController;
use App\Http\Controllers\ExternalResourceRedirectController;
use App\Http\Controllers\Librarian\AcquisitionBatchController as LibrarianAcquisitionBatchController;
use App\Http\Controllers\Librarian\AnnualContentPlanController as LibrarianAnnualContentPlanController;
use App\Http\Controllers\Librarian\CatalogAttachmentController as LibrarianCatalogAttachmentController;
use App\Http\Controllers\Librarian\CatalogController as LibrarianCatalogController;
use App\Http\Controllers\Librarian\CirculationController as LibrarianCirculationController;
use App\Http\Controllers\Librarian\CopyController as LibrarianCopyController;
use App\Http\Controllers\Librarian\DashboardController as LibrarianDashboardController;
use App\Http\Controllers\Librarian\DataCleanupController as LibrarianDataCleanupController;
use App\Http\Controllers\Librarian\DataQualityController as LibrarianDataQualityController;
use App\Http\Controllers\Librarian\DigitalMaterialController as LibrarianDigitalMaterialController;
use App\Http\Controllers\Librarian\DirectoryReaderController as LibrarianDirectoryReaderController;
use App\Http\Controllers\Librarian\ExecutiveDashboardController as LibrarianExecutiveDashboardController;
use App\Http\Controllers\Librarian\FineController as LibrarianFineController;
use App\Http\Controllers\Librarian\IncidentController as LibrarianIncidentController;
use App\Http\Controllers\Librarian\InventoryController as LibrarianInventoryController;
use App\Http\Controllers\Librarian\KsuRegisterController as LibrarianKsuRegisterController;
use App\Http\Controllers\Librarian\LibraryOperationSettingController as LibrarianLibraryOperationSettingController;
use App\Http\Controllers\Librarian\MessageController as LibrarianMessageController;
use App\Http\Controllers\Librarian\NewsController as LibrarianNewsController;
use App\Http\Controllers\Librarian\OfficialReportController as LibrarianOfficialReportController;
use App\Http\Controllers\Librarian\ProfileController as LibrarianProfileController;
use App\Http\Controllers\Librarian\RecoveryQualityController as LibrarianRecoveryQualityController;
use App\Http\Controllers\Librarian\ReportController as LibrarianReportController;
use App\Http\Controllers\Librarian\RepositoryController as LibrarianRepositoryController;
use App\Http\Controllers\Librarian\ReservationController as LibrarianReservationController;
use App\Http\Controllers\Librarian\UdcReferenceController as LibrarianUdcReferenceController;
use App\Http\Controllers\Librarian\VisitController as LibrarianVisitController;
use App\Http\Controllers\Librarian\WorkspaceController as LibrarianWorkspaceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Member\CabinetController as MemberCabinetController;
use App\Http\Controllers\Member\CollectionController as MemberCollectionController;
use App\Http\Controllers\Member\IncidentController as MemberIncidentController;
use App\Http\Controllers\Member\NotificationController as MemberNotificationController;
use App\Http\Controllers\Member\PortalController as MemberPortalController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\PublicExternalResourceController;
use App\Http\Controllers\RepositoryController as PublicRepositoryController;
use App\Http\Controllers\WebAuthController;
use App\Models\Catalog\RepositoryItem;
use App\Models\News;
use App\Models\NewsCategory;
use App\Models\NewsSlugRedirect;
use App\Models\Setting;
use App\Services\ExternalResourceService;
use App\Services\Library\BookDetailReadService;
use App\Services\Library\CatalogReadService;
use App\Services\Library\PublicPortalStatistics;
use App\Services\News\NewsAnalyticsService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Route changes are intentionally tracked by the vault hook automation.
// This file doubles as the final validation target for route-aware vault logging.
// Route-aware state snapshots are now part of the vault workflow.

$internalStaffView = static function (Request $request, string $view) {
    $user = $request->session()->get('library.user');
    $role = is_array($user) ? mb_strtolower(trim((string) ($user['role'] ?? ''))) : '';

    // Belt-and-braces: route group also has library.auth applied at the group level.
    abort_unless(in_array($role, ['librarian', 'admin'], true), 403);

    return view($view, [
        'internalStaffUser' => $user,
    ]);
};

// Role-based post-login landing destination.
// Canonical page map (PROJECT_CONTEXT 30) expects: admin -> /admin,
// librarian -> /librarian, member -> /dashboard.
// Wave 1: /account is no longer a primary surface; member readers land on
// /dashboard. The legacy /account route is retained ONLY as a hidden
// backward-compatibility surface for bookmarks and existing tests.
$postLoginDestination = static function (array $user): string {
    $role = mb_strtolower(trim((string) ($user['role'] ?? '')));

    return match ($role) {
        'admin' => '/admin',
        'librarian' => '/librarian',
        default => '/dashboard',
    };
};

$librarianView = static function (Request $request, string $view, array $data = []) {
    return view($view, array_merge([
        'librarianStaffUser' => $request->session()->get('library.user'),
    ], $data));
};

$memberView = static function (Request $request, string $view, array $data = []) {
    return view($view, array_merge([
        'memberReader' => $request->session()->get('library.user'),
    ], $data));
};

$publicHttpUrl = static function (mixed $value): ?string {
    $url = trim((string) $value);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }

    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
        ? $url
        : null;
};

$newsModelToPublicArticle = static function ($record) use ($publicHttpUrl): array {
    $publishedAt = $record->published_at ?? $record->publish_at ?? $record->updated_at ?? $record->created_at ?? now();
    $publishedAt = $publishedAt instanceof CarbonInterface ? $publishedAt : now();
    $requestedLocale = in_array(app()->getLocale(), ['ru', 'kk', 'en'], true) ? app()->getLocale() : 'kk';
    $content = method_exists($record, 'localized') ? $record->localized('content', $requestedLocale) : trim((string) ($record->body ?? $record->content ?? ''));
    $paragraphs = preg_split('/\R{2,}/', $content) ?: [];
    $paragraphs = array_values(array_filter(array_map(static fn ($value) => trim((string) $value), $paragraphs)));

    if ($paragraphs === [] && $content !== '') {
        $paragraphs = [$content];
    }

    $blocks = [];
    foreach ($paragraphs as $index => $paragraph) {
        $blocks[] = [
            'type' => $index === 0 ? 'lead' : 'p',
            'text' => $paragraph,
        ];
    }

    $language = $requestedLocale;
    $categoryKey = 'news.types.'.(string) ($record->type ?? $record->category ?? 'announcement');
    $categoryLabel = Lang::has($categoryKey, $language)
        ? trans($categoryKey, [], $language)
        : Str::of((string) ($record->category ?? 'announcement'))
            ->replace('-', ' ')
            ->replace('_', ' ')
            ->title()
            ->toString();
    $localizedExcerpt = method_exists($record, 'localized') ? $record->localized('excerpt', $language) : (string) $record->excerpt;
    $excerpt = trim((string) ($localizedExcerpt ?: Str::limit(preg_replace('/\s+/', ' ', strip_tags($content)), 220)));
    $localizedTitle = method_exists($record, 'localized') ? $record->localized('title', $language) : (string) $record->title;
    $localizedSlug = method_exists($record, 'localizedSlug') ? $record->localizedSlug($language) : (string) $record->slug;
    $repositoryWork = null;
    if ($record instanceof News
        && Schema::hasColumn('news', 'repository_item_id')
        && filled($record->repository_item_id)) {
        try {
            $repositoryWork = RepositoryItem::query()
                ->publicMetadata()
                ->whereKey($record->repository_item_id)
                ->first(['id', 'title', 'year']);
        } catch (Throwable $exception) {
            // The news surface remains available during a rolling repository
            // migration, while the link fails closed instead of exposing an
            // unapproved repository identifier.
            report($exception);
        }
    }

    return [
        'id' => (string) $record->id,
        'slug' => $localizedSlug,
        'topic' => in_array((string) ($record->type ?? $record->category), ['event', 'schedule'], true) ? 'events' : 'research',
        'featured' => (bool) (($record->show_on_homepage ?? false) || ($record->is_featured ?? false)),
        'language' => $language,
        'published_at' => $publishedAt->toDateString(),
        'published_display' => [
            'ru' => $publishedAt->format('d.m.Y'),
            'kk' => $publishedAt->format('d.m.Y'),
            'en' => $publishedAt->format('F j, Y'),
        ],
        'category' => [$language => $categoryLabel],
        'title' => [$language => $localizedTitle],
        'excerpt' => [$language => $excerpt],
        'hero' => [
            'image' => ! empty($record->cover_image) ? 'storage/'.$record->cover_image : null,
            'alt' => [$language => (method_exists($record, 'localized') ? ($record->localized('image_alt', $language) ?: $localizedTitle) : $localizedTitle)],
        ],
        'body' => [$language => $blocks],
        'cta' => null,
        'repository' => $repositoryWork ? [
            'id' => $repositoryWork->getKey(),
            'title' => $repositoryWork->title,
            'year' => $repositoryWork->year,
        ] : null,
        'event' => [
            'starts_at' => $record->starts_at?->toIso8601String(),
            'ends_at' => $record->ends_at?->toIso8601String(),
            'starts_display' => $record->starts_at?->timezone($record->timezone ?: 'Asia/Almaty')->translatedFormat('d F Y, H:i'),
            'ends_display' => $record->ends_at?->timezone($record->timezone ?: 'Asia/Almaty')->translatedFormat('d F Y, H:i'),
            'venue' => method_exists($record, 'localized') ? trim((string) $record->localized('venue', $language)) ?: null : null,
            'online_url' => $publicHttpUrl($record->online_url),
            'registration_url' => $publicHttpUrl($record->registration_url),
            'registration_required' => (bool) $record->registration_required,
            'organizer' => $record->organizer,
            'contact_name' => $record->contact_name,
        ],
        'schema_type' => in_array((string) ($record->type ?? ''), ['event', 'schedule'], true) ? 'Event' : 'NewsArticle',
    ];
};

$resolveCrmUserId = static function (array $sessionUser): ?string {
    $sessionId = trim((string) ($sessionUser['id'] ?? ''));

    // Session user ID from CRM is the authoritative CRM user identifier.
    // No DB lookup needed; if it's a valid UUID, use it directly.
    if ($sessionId !== '' && Str::isUuid($sessionId)) {
        return $sessionId;
    }

    return null;
};

$memberReservationsFeed = static function (string $crmUserId): array {
    return DB::connection('pgsql')
        ->table('public.Reservation as r')
        ->leftJoin('public.Book as b', 'b.id', '=', 'r.bookId')
        ->where('r.userId', $crmUserId)
        ->select([
            'r.id',
            'r.status',
            'r.reservedAt',
            'r.expiresAt',
            'r.processedAt',
            'r.notes',
            'r.copyId',
            'r.createdAt',
            'b.title as bookTitle',
            'b.isbn as bookIsbn',
            'b.publishYear as bookPublishYear',
        ])
        ->orderByDesc('r.reservedAt')
        ->limit(100)
        ->get()
        ->map(function (object $row): array {
            $notes = null;

            if (! empty($row->notes)) {
                $decoded = json_decode($row->notes, true);
                $notes = is_array($decoded) ? $decoded : null;
            }

            return [
                'id' => $row->id,
                'status' => $row->status,
                'reservedAt' => $row->reservedAt,
                'expiresAt' => $row->expiresAt,
                'processedAt' => $row->processedAt,
                'copyId' => $row->copyId,
                'cancelOrigin' => $notes['cancel_origin'] ?? null,
                'cancelReasonCode' => $notes['cancel_reason_code'] ?? null,
                'book' => [
                    'title' => $row->bookTitle,
                    'isbn' => $row->bookIsbn,
                    'publishYear' => $row->bookPublishYear,
                ],
            ];
        })
        ->all();
};

$memberHistoryFeed = static function (string $crmUserId): array {
    return DB::connection('pgsql')
        ->table('app.circulation_loans as cl')
        ->leftJoin('app.book_copies as bc', 'bc.id', '=', 'cl.copy_id')
        ->leftJoin('app.documents as d', 'd.id', '=', 'bc.document_id')
        ->where('cl.reader_id', $crmUserId)
        ->select([
            'cl.id',
            'cl.issued_at',
            'cl.due_at',
            'cl.returned_at',
            'cl.status',
            'd.title_display',
            'd.title_raw',
        ])
        ->orderByDesc('cl.issued_at')
        ->limit(200)
        ->get()
        ->map(function (object $row): array {
            $issuedAt = $row->issued_at !== null ? Carbon::parse($row->issued_at) : null;
            $dueAt = $row->due_at !== null ? Carbon::parse($row->due_at) : null;
            $returnedAt = $row->returned_at !== null ? Carbon::parse($row->returned_at) : null;

            $status = 'returned';
            $statusLabel = 'Returned';
            if ($returnedAt === null) {
                if ($dueAt !== null && $dueAt->isPast()) {
                    $status = 'overdue';
                    $statusLabel = 'Overdue';
                } else {
                    $status = 'active';
                    $statusLabel = 'Currently on loan';
                }
            }

            return [
                'id' => (string) $row->id,
                'title' => trim((string) ($row->title_display ?? $row->title_raw ?? '')) ?: 'Untitled item',
                'status' => $status,
                'status_label' => $statusLabel,
                'issued_at' => $issuedAt?->format('M d, Y'),
                'due_at' => $dueAt?->format('M d, Y'),
                'returned_at' => $returnedAt?->format('M d, Y'),
                'term' => $issuedAt?->format('F Y') ?? 'Unknown period',
            ];
        })
        ->all();
};

// Phase 3.3 — seeded public news catalog.
// Representative content for the canonical /news index + /news/{slug} detail.
// The structure is deliberately DB-replaceable later: an array of article
// records keyed by `slug`, each carrying trilingual copy, metadata, and an
// optional long-form body for the detail route. Ordering of the index is
// controlled by $orderedSlugs (most recent first).
$newsSeedProvider = static function (): array {
    $articles = [
        'global-symposium-archival-integrity' => [
            'slug' => 'global-symposium-archival-integrity',
            'featured' => true,
            'published_at' => '2026-04-14',
            'published_display' => [
                'ru' => '14 апреля 2026',
                'kk' => '2026 жылғы 14 сәуір',
                'en' => 'April 14, 2026',
            ],
            'category' => [
                'ru' => 'Главный материал',
                'kk' => 'Басты материал',
                'en' => 'Featured Report',
            ],
            'title' => [
                'ru' => 'Международный симпозиум по целостности архивов прошёл в Астане',
                'kk' => 'Астанада мұрағат тұтастығы бойынша халықаралық симпозиум өтті',
                'en' => 'Global Symposium on Archival Integrity Concludes in Astana',
            ],
            'excerpt' => [
                'ru' => 'Исследователи и специалисты по цифровому сохранению обсудили задачи обеспечения исторической непрерывности в эпоху быстро меняющихся цифровых форматов.',
                'kk' => 'Зерттеушілер мен цифрлық сақтау мамандары жылдам өзгеретін цифрлық форматтар дәуіріндегі тарихи сабақтастықты қамтамасыз ету мәселесін талқылады.',
                'en' => 'Researchers and digital preservation specialists addressed the challenge of maintaining historical continuity in an era of rapidly evolving digital formats.',
            ],
            'hero' => [
                'image' => 'images/news/campus-library.jpg',
                'alt' => [
                    'ru' => 'Университетский читальный зал с участниками симпозиума',
                    'kk' => 'Университеттің оқу залы, симпозиум қатысушылары',
                    'en' => 'University reading room during the symposium',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Казахский университет технологии и бизнеса имени К. Кулажанова провёл международный симпозиум по целостности архивов — площадку для обсуждения практик долговременного сохранения цифровых и гибридных коллекций в университетских библиотеках.'],
                    ['type' => 'h2', 'text' => 'Темы программы'],
                    ['type' => 'p', 'text' => 'Программа объединила библиотекарей, архивистов и исследователей. В центре обсуждения — связность метаданных, устойчивость форматов и контролируемый доступ к институциональному архиву.'],
                    ['type' => 'list', 'items' => [
                        ['term' => 'Метаданные и провенанс', 'text' => 'Сохранение контекста и источников при переводе коллекций в цифровую среду.'],
                        ['term' => 'Устойчивость форматов', 'text' => 'Долговременное хранение материалов и планирование миграций.'],
                        ['term' => 'Контролируемый доступ', 'text' => 'Политики читательского доступа к закрытым и лицензируемым материалам.'],
                    ]],
                    ['type' => 'h2', 'text' => 'Итоги и следующие шаги'],
                    ['type' => 'p', 'text' => 'По итогам симпозиума библиотека публикует методические рекомендации по работе с институциональным архивом и расширяет программу проверенных поступлений в научном репозитории университета.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті университет кітапханаларындағы цифрлық және гибридті жинақтарды ұзақ мерзімді сақтау тәжірибесін талқылауға арналған мұрағат тұтастығы жөніндегі халықаралық симпозиумды өткізді.'],
                    ['type' => 'h2', 'text' => 'Бағдарлама тақырыптары'],
                    ['type' => 'p', 'text' => 'Бағдарлама кітапханашылар, архивистер мен зерттеушілерді біріктірді. Негізгі тақырыптар: метадерек байланыстылығы, формат тұрақтылығы және институционалдық мұрағатқа бақыланатын қолжетімділік.'],
                    ['type' => 'list', 'items' => [
                        ['term' => 'Метадерек және провенанс', 'text' => 'Жинақтарды цифрлық ортаға көшіру кезіндегі контекст пен дереккөздерді сақтау.'],
                        ['term' => 'Формат тұрақтылығы', 'text' => 'Материалдарды ұзақ мерзімде сақтау және миграцияны жоспарлау.'],
                        ['term' => 'Бақыланатын қолжетімділік', 'text' => 'Шектеулі және лицензияланған материалдарға оқырман қолжетімділігінің саясаты.'],
                    ]],
                    ['type' => 'h2', 'text' => 'Қорытындылар және келесі қадамдар'],
                    ['type' => 'p', 'text' => 'Симпозиум қорытындысы бойынша кітапхана институционалдық мұрағатпен жұмысқа арналған әдістемелік ұсынымдарды жариялайды және университеттің ғылыми репозиторийіндегі тексерілген түсімдер бағдарламасын кеңейтеді.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'Kazakh University of Technology and Business named after K. Kulazhanov hosted an international symposium on archival integrity — a working space to discuss long-term preservation practice for digital and hybrid collections in university libraries.'],
                    ['type' => 'h2', 'text' => 'Programme themes'],
                    ['type' => 'p', 'text' => 'The programme brought together librarians, archivists, and researchers. Central themes were metadata continuity, format resilience, and controlled access to the institutional archive.'],
                    ['type' => 'list', 'items' => [
                        ['term' => 'Metadata and provenance', 'text' => 'Preserving context and sources as collections move into digital environments.'],
                        ['term' => 'Format resilience', 'text' => 'Long-term preservation of materials and planned migrations across formats.'],
                        ['term' => 'Controlled access', 'text' => 'Reader access policies for restricted and licensed materials.'],
                    ]],
                    ['type' => 'h2', 'text' => 'Outcomes and next steps'],
                    ['type' => 'p', 'text' => 'Following the symposium the library is publishing guidance for working with the institutional archive and is expanding the reviewed-intake programme of the university scholarly repository.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Продолжить работу', 'body' => 'Перейдите в научный репозиторий Казахский университет технологии и бизнеса имени К. Кулажанова, чтобы ознакомиться с проверенными публикациями.', 'label' => 'Открыть репозиторий', 'href' => '/repository'],
                'kk' => ['heading' => 'Жұмысты жалғастыру', 'body' => 'Тексерілген жарияланымдармен танысу үшін Қ. Құлажанов атындағы Қазақ технология және бизнес университеті ғылыми репозиторийіне өтіңіз.', 'label' => 'Репозиторийді ашу', 'href' => '/repository'],
                'en' => ['heading' => 'Continue from here', 'body' => 'Open the Kazakh University of Technology and Business named after K. Kulazhanov scholarly repository to browse reviewed publications.', 'label' => 'Open the repository', 'href' => '/repository'],
            ],
        ],
        'catalog-assistance-pilot-2026' => [
            'slug' => 'catalog-assistance-pilot-2026',
            'featured' => false,
            'published_at' => '2026-04-22',
            'published_display' => [
                'ru' => '22 апреля 2026',
                'kk' => '2026 жылғы 22 сәуір',
                'en' => 'April 22, 2026',
            ],
            'category' => [
                'ru' => 'Цифровые сервисы',
                'kk' => 'Цифрлық сервистер',
                'en' => 'Digital Services',
            ],
            'title' => [
                'ru' => 'Библиотека запустила пилот ассистента для навигации по каталогу',
                'kk' => 'Кітапхана каталог бойынша навигацияға арналған көмекші пилотын іске қосты',
                'en' => 'Library Launches a Pilot Catalog Navigation Assistant',
            ],
            'excerpt' => [
                'ru' => 'Обновлённый интерфейс помогает читателям быстрее находить книги, ориентироваться в фильтрах и переходить к связанным материалам без лишних кликов.',
                'kk' => 'Жаңартылған интерфейс оқырмандарға кітаптарды тезірек табуға, сүзгілерді түсінуге және байланысты материалдарға артық қадамсыз өтуге көмектеседі.',
                'en' => 'The updated interface helps readers find books faster, understand filters, and move to related materials with fewer clicks.',
            ],
            'hero' => [
                'image' => 'images/news/ai-workshop.jpg',
                'alt' => [
                    'ru' => 'Рабочий стол с цифровым интерфейсом каталога',
                    'kk' => 'Каталогтың цифрлық интерфейсі көрсетілген жұмыс орны',
                    'en' => 'Workstation with a digital catalog interface',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Пилотный ассистент каталога Казахский университет технологии и бизнеса имени К. Кулажанова показывает подсказки по поиску, подборкам и фильтрам в едином потоке.'],
                    ['type' => 'p', 'text' => 'Обновлённый режим объединяет ключевые сценарии поиска. Команда анализирует, какие элементы навигации помогают быстрее находить книги и работать с подборками.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті каталогының пилоттық көмекшісі іздеу, подборка және сүзгілер бойынша кеңестерді бір сценарийде көрсетеді.'],
                    ['type' => 'p', 'text' => 'Жаңартылған режим негізгі іздеу сценарийлерін біріктіреді. Команда кітаптарды жылдам табуға және жинақтармен жұмыс істеуге көмектесетін навигация элементтерін талдайды.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'The Kazakh University of Technology and Business named after K. Kulazhanov pilot catalog assistant surfaces search tips, shortlist actions, and filter guidance in one flow.'],
                    ['type' => 'p', 'text' => 'The updated experience brings the core search journeys together. The team reviews which navigation elements help readers find books and use reading lists efficiently.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Открыть каталог', 'body' => 'Попробуйте обновлённый поиск и быстрые сценарии работы с подборкой в публичном каталоге.', 'label' => 'Перейти в каталог', 'href' => '/catalog'],
                'kk' => ['heading' => 'Каталогты ашу', 'body' => 'Ашық каталогтағы жаңартылған іздеу мен подборкамен жұмыс істеудің жедел сценарийлерін көріңіз.', 'label' => 'Каталогқа өту', 'href' => '/catalog'],
                'en' => ['heading' => 'Open the catalog', 'body' => 'Try the refreshed search flow and the quick shortlist actions in the public catalog.', 'label' => 'Go to catalog', 'href' => '/catalog'],
            ],
        ],
        'student-research-showcase-2026' => [
            'slug' => 'student-research-showcase-2026',
            'featured' => false,
            'published_at' => '2026-04-18',
            'published_display' => [
                'ru' => '18 апреля 2026',
                'kk' => '2026 жылғы 18 сәуір',
                'en' => 'April 18, 2026',
            ],
            'category' => [
                'ru' => 'События кампуса',
                'kk' => 'Кампус оқиғалары',
                'en' => 'Campus Updates',
            ],
            'title' => [
                'ru' => 'Студенческая исследовательская витрина открыла весенний цикл постеров',
                'kk' => 'Студенттік зерттеу витринасы көктемгі постерлер циклін ашты',
                'en' => 'Student Research Showcase Opens the Spring Poster Cycle',
            ],
            'excerpt' => [
                'ru' => 'На открытой витрине собраны лучшие постеры магистрантов и проектные работы, которые можно быстро просмотреть по темам и направлениям.',
                'kk' => 'Ашық витринаға магистранттардың үздік постерлері мен жобалық жұмыстары жиналды, оларды тақырыптар мен бағыттар бойынша жылдам қарауға болады.',
                'en' => 'The public showcase gathers the best master’s posters and project works, all ready to browse quickly by topic and discipline.',
            ],
            'hero' => [
                'image' => 'images/news/classics-event.jpg',
                'alt' => [
                    'ru' => 'Выставка постеров в светлом читальном пространстве',
                    'kk' => 'Жарық оқу кеңістігіндегі постер көрмесі',
                    'en' => 'Poster showcase in a bright reading space',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Весенняя витрина показывает, как студенческие исследования превращаются в понятные и доступные публичные материалы.'],
                    ['type' => 'p', 'text' => 'Команда библиотеки собрала постеры, короткие аннотации и тематические подборки, чтобы читатели могли быстро переходить от одного проекта к другому.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Көктемгі витрина студенттік зерттеулердің түсінікті әрі қолжетімді жария материалдарға қалай айналатынын көрсетеді.'],
                    ['type' => 'p', 'text' => 'Кітапхана командасы постерлерді, қысқа аннотацияларды және тақырыптық жинақтарды біріктіріп, оқырмандардың бір жобадан екіншісіне тез өтуіне жағдай жасады.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'The spring showcase shows how student research can be turned into clear and accessible public material.'],
                    ['type' => 'p', 'text' => 'The library team assembled posters, short abstracts, and topic bundles so readers can move quickly from one project to the next.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Смотреть коллекцию', 'body' => 'Откройте подборку материалов и изучайте новые темы в каталоге.', 'label' => 'Открыть подборку', 'href' => '/catalog'],
                'kk' => ['heading' => 'Жинақты көру', 'body' => 'Материалдар жинағын ашып, каталогтағы жаңа тақырыптарды зерттеңіз.', 'label' => 'Жинақты ашу', 'href' => '/catalog'],
                'en' => ['heading' => 'View the collection', 'body' => 'Open the collection and explore new topics in the catalog.', 'label' => 'Open the collection', 'href' => '/catalog'],
            ],
        ],
        'eurasian-manuscripts-integration' => [
            'slug' => 'eurasian-manuscripts-integration',
            'featured' => false,
            'published_at' => '2026-04-10',
            'published_display' => [
                'ru' => '10 апреля 2026',
                'kk' => '2026 жылғы 10 сәуір',
                'en' => 'April 10, 2026',
            ],
            'category' => [
                'ru' => 'Обновления фонда',
                'kk' => 'Қор жаңартулары',
                'en' => 'Collection Updates',
            ],
            'title' => [
                'ru' => 'Цифровая интеграция евразийских рукописей XIX века',
                'kk' => 'XIX ғасырдың еуразиялық қолжазбаларының цифрлық интеграциясы',
                'en' => 'Integration of the 19th-Century Eurasian Manuscripts',
            ],
            'excerpt' => [
                'ru' => 'Более четырёх тысяч оцифрованных рукописей из центральных степных архивов индексированы и доступны для академического поиска в каталоге.',
                'kk' => 'Орталық дала мұрағаттарынан алынған төрт мыңнан астам цифрландырылған қолжазба академиялық іздеуге дайын және каталогта қолжетімді.',
                'en' => 'More than four thousand digitised manuscripts from the central steppe archives are indexed and available for academic search in the catalog.',
            ],
            'hero' => [
                'image' => 'images/news/classics-event.jpg',
                'alt' => [
                    'ru' => 'Стеллажи с архивными материалами',
                    'kk' => 'Мұрағат материалдарының сөрелері',
                    'en' => 'Shelves of archival materials',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Библиотека завершила базовый этап цифровой интеграции коллекции евразийских рукописей XIX века в институциональный архив Казахский университет технологии и бизнеса имени К. Кулажанова.'],
                    ['type' => 'p', 'text' => 'Материалы прошли проверку метаданных, получили устойчивые идентификаторы и связаны с каталогом. Читатели и преподаватели могут обращаться к коллекции через обычный академический поиск.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Кітапхана XIX ғасырдың еуразиялық қолжазбалар жинағын Қ. Құлажанов атындағы Қазақ технология және бизнес университеті институционалдық мұрағатына цифрлық интеграциялаудың базалық кезеңін аяқтады.'],
                    ['type' => 'p', 'text' => 'Материалдар метадеректер тексеруінен өтті, тұрақты идентификаторларды алды және каталогпен байланыстырылды. Оқырмандар мен оқытушылар жинаққа қалыпты академиялық іздеу арқылы өте алады.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'The library has completed the foundation stage of digital integration for the 19th-century Eurasian manuscript collection into the Kazakh University of Technology and Business named after K. Kulazhanov institutional archive.'],
                    ['type' => 'p', 'text' => 'Materials have been validated against metadata standards, assigned stable identifiers, and linked to the catalog. Readers and faculty can now reach the collection through ordinary academic search.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Открыть коллекцию', 'body' => 'Каталог Казахский университет технологии и бизнеса имени К. Кулажанова содержит проиндексированные материалы — используйте тематическую навигацию по УДК.', 'label' => 'Открыть каталог', 'href' => '/catalog'],
                'kk' => ['heading' => 'Жинақты ашу', 'body' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті каталогында индекстелген материалдар бар — ӘОЖ бойынша тақырыптық навигацияны пайдаланыңыз.', 'label' => 'Каталогты ашу', 'href' => '/catalog'],
                'en' => ['heading' => 'Open the collection', 'body' => 'The Kazakh University of Technology and Business named after K. Kulazhanov catalog contains the indexed materials — use UDC subject navigation to explore.', 'label' => 'Open the catalog', 'href' => '/catalog'],
            ],
        ],
        'digital-access-partner-institutions' => [
            'slug' => 'digital-access-partner-institutions',
            'featured' => false,
            'published_at' => '2026-04-05',
            'published_display' => [
                'ru' => '5 апреля 2026',
                'kk' => '2026 жылғы 5 сәуір',
                'en' => 'April 5, 2026',
            ],
            'category' => [
                'ru' => 'Цифровой доступ',
                'kk' => 'Цифрлық қолжетімділік',
                'en' => 'Digital Access',
            ],
            'title' => [
                'ru' => 'Расширение цифрового доступа для внешних академических партнёров',
                'kk' => 'Сыртқы академиялық серіктестер үшін цифрлық қолжетімділікті кеңейту',
                'en' => 'Expanded Digital Access for External Academic Partners',
            ],
            'excerpt' => [
                'ru' => 'Обновлены механизмы контролируемого доступа для партнёрских университетов — снижены барьеры для международных исследователей без ослабления политик библиотеки.',
                'kk' => 'Серіктес университеттер үшін бақыланатын қолжетімділік механизмдері жаңартылды — халықаралық зерттеушілер үшін тосқауылдар азайды, бірақ саясат босаңсымайды.',
                'en' => 'Controlled-access mechanisms for partner universities have been updated — lower the barrier for international researchers without weakening library policy.',
            ],
            'hero' => [
                'image' => 'images/news/author-visit.jpg',
                'alt' => [
                    'ru' => 'Читатель работает с цифровыми материалами',
                    'kk' => 'Оқырман цифрлық материалдармен жұмыс істеуде',
                    'en' => 'Reader working with digital materials',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Библиотека расширила программу цифрового доступа для партнёрских университетов. Политика доступа и журнал обращений остаются под полным контролем Казахский университет технологии и бизнеса имени К. Кулажанова.'],
                    ['type' => 'p', 'text' => 'Обновлённые потоки доступа поддерживают существующие уровни контроля и работают только в рамках подтверждённых академических соглашений.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Кітапхана серіктес университеттер үшін цифрлық қолжетімділік бағдарламасын кеңейтті. Қолжетімділік саясаты мен өтініш журналы толығымен Қ. Құлажанов атындағы Қазақ технология және бизнес университеті бақылауында қалады.'],
                    ['type' => 'p', 'text' => 'Жаңартылған қолжетімділік ағындары қолданыстағы бақылау деңгейлерін қолдайды және тек расталған академиялық келісімдер шеңберінде жұмыс істейді.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'The library has expanded its digital-access programme for partner universities. Access policy and the request journal remain fully under Kazakh University of Technology and Business named after K. Kulazhanov control.'],
                    ['type' => 'p', 'text' => 'Updated access flows respect existing control levels and operate only within confirmed academic agreements.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Проверить доступ', 'body' => 'Если вы представляете партнёрскую организацию, свяжитесь с библиотекой, чтобы уточнить маршруты доступа.', 'label' => 'Связаться с библиотекой', 'href' => '/contacts'],
                'kk' => ['heading' => 'Қолжетімділікті тексеру', 'body' => 'Серіктес ұйымның өкілі болсаңыз, қолжетімділік маршруттарын нақтылау үшін кітапханаға хабарласыңыз.', 'label' => 'Кітапханамен байланысу', 'href' => '/contacts'],
                'en' => ['heading' => 'Confirm your access', 'body' => 'If you represent a partner institution, contact the library to confirm the right access routes for your team.', 'label' => 'Contact the library', 'href' => '/contacts'],
            ],
        ],
        'restoration-lab-acquisition-ledgers' => [
            'slug' => 'restoration-lab-acquisition-ledgers',
            'featured' => false,
            'published_at' => '2026-03-29',
            'published_display' => [
                'ru' => '29 марта 2026',
                'kk' => '2026 жылғы 29 наурыз',
                'en' => 'March 29, 2026',
            ],
            'category' => [
                'ru' => 'Приобретения',
                'kk' => 'Толықтырулар',
                'en' => 'Acquisitions',
            ],
            'title' => [
                'ru' => 'В фонд реставрационной лаборатории переданы журналы книжных поступлений',
                'kk' => 'Қалпына келтіру зертханасына кітап түсімдерінің журналдары берілді',
                'en' => 'Restoration Lab Receives Historical Acquisition Ledgers',
            ],
            'excerpt' => [
                'ru' => 'Новая партия инвентарных журналов и карточек поступила в библиотеку для консервации, описания и дальнейшего включения в цифровой архив.',
                'kk' => 'Түгендеу журналдары мен карточкалардың жаңа топтамасы консервациялау, сипаттау және кейін цифрлық мұрағатқа енгізу үшін кітапханаға берілді.',
                'en' => 'A newly transferred set of accession ledgers and inventory cards has entered the library for conservation, description, and later inclusion in the digital archive.',
            ],
            'hero' => [
                'image' => 'images/news/default-library.jpg',
                'alt' => [
                    'ru' => 'Исторические книги и журналы на полке фонда',
                    'kk' => 'Қор сөресіндегі тарихи кітаптар мен журналдар',
                    'en' => 'Historical books and ledgers on a collection shelf',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Фонд реставрационной лаборатории Казахский университет технологии и бизнеса имени К. Кулажанова пополнился серией исторических журналов поступлений и вспомогательных карточек учёта.'],
                    ['type' => 'p', 'text' => 'Материалы проходят первичную консервацию, атрибуцию и подготовку к оцифровке. После завершения обработки описания будут связаны с публичным каталогом и новостным архивом библиотеки.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті қалпына келтіру зертханасының қоры тарихи түсім журналдары мен қосалқы есеп карточкаларымен толықты.'],
                    ['type' => 'p', 'text' => 'Материалдар бастапқы консервациядан, атрибуциядан және цифрландыруға дайындаудан өтуде. Өңдеу аяқталғаннан кейін сипаттамалар ашық каталогпен және кітапхананың жаңалықтар мұрағатымен байланысады.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'The Kazakh University of Technology and Business named after K. Kulazhanov restoration lab has received a set of historical acquisition ledgers and supporting catalog cards.'],
                    ['type' => 'p', 'text' => 'The materials are undergoing initial conservation, attribution, and digitisation preparation. Once processed, their descriptions will be linked into the public catalog and the library news archive.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Посмотреть каталог фонда', 'body' => 'Следите за обновлениями коллекций и проверенными поступлениями в публичном каталоге библиотеки.', 'label' => 'Открыть каталог', 'href' => '/catalog'],
                'kk' => ['heading' => 'Қор каталогын қарау', 'body' => 'Кітапхананың ашық каталогындағы коллекция жаңартулары мен тексерілген түсімдерді бақылаңыз.', 'label' => 'Каталогты ашу', 'href' => '/catalog'],
                'en' => ['heading' => 'Browse the collection catalog', 'body' => 'Track collection updates and reviewed accessions in the public library catalog.', 'label' => 'Open the catalog', 'href' => '/catalog'],
            ],
        ],
        'interlibrary-loan-governance-update' => [
            'slug' => 'interlibrary-loan-governance-update',
            'featured' => false,
            'published_at' => '2026-03-21',
            'published_display' => [
                'ru' => '21 марта 2026',
                'kk' => '2026 жылғы 21 наурыз',
                'en' => 'March 21, 2026',
            ],
            'category' => [
                'ru' => 'Политики',
                'kk' => 'Саясаттар',
                'en' => 'Policy',
            ],
            'title' => [
                'ru' => 'Обновлены правила межбиблиотечного обмена и международных запросов',
                'kk' => 'Кітапханааралық алмасу және халықаралық сұраным ережелері жаңартылды',
                'en' => 'Interlibrary Loan Governance Updated for International Requests',
            ],
            'excerpt' => [
                'ru' => 'Библиотека уточнила сроки, роли и требования к маршрутизации запросов, чтобы ускорить обмен материалами между академическими партнёрами.',
                'kk' => 'Кітапхана академиялық серіктестер арасындағы материал алмасуды жеделдету үшін сұранымдарды бағыттау мерзімдерін, рөлдерін және талаптарын нақтылады.',
                'en' => 'The library has clarified timelines, roles, and routing requirements to accelerate material exchange with academic partners.',
            ],
            'hero' => [
                'image' => 'images/news/ai-workshop.jpg',
                'alt' => [
                    'ru' => 'Документы и формы межбиблиотечного обмена на рабочем столе',
                    'kk' => 'Жұмыс үстеліндегі кітапханааралық алмасу құжаттары мен нысандары',
                    'en' => 'Interlibrary loan forms and documents on a work table',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Казахский университет технологии и бизнеса имени К. Кулажанова обновил регламент межбиблиотечного обмена для международных и межвузовских запросов.'],
                    ['type' => 'p', 'text' => 'Новая редакция фиксирует единые сроки подтверждения, перечень ответственных сотрудников и требования к сопровождению цифровых копий. Это снижает задержки и делает обслуживание предсказуемее для партнёрских учреждений.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті халықаралық және жоғары оқу орындары арасындағы сұранымдар үшін кітапханааралық алмасу регламентін жаңартты.'],
                    ['type' => 'p', 'text' => 'Жаңа редакция растаудың бірыңғай мерзімдерін, жауапты қызметкерлер тізімін және цифрлық көшірмелерді сүйемелдеу талаптарын бекітеді. Бұл кідірістерді азайтып, серіктес ұйымдар үшін қызмет көрсетуді болжамды етеді.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'Kazakh University of Technology and Business named after K. Kulazhanov has updated its interlibrary loan governance for international and inter-university requests.'],
                    ['type' => 'p', 'text' => 'The revised guidance defines confirmation timelines, accountable roles, and requirements for handling digital copies. This reduces delays and makes service expectations more predictable for partner institutions.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Уточнить правила обмена', 'body' => 'Если вашей организации нужен доступ к межбиблиотечному обмену, свяжитесь с библиотекой для согласования маршрута.', 'label' => 'Связаться с библиотекой', 'href' => '/contacts'],
                'kk' => ['heading' => 'Алмасу ережелерін нақтылау', 'body' => 'Ұйымыңызға кітапханааралық алмасу қажет болса, бағытты келісу үшін кітапханаға хабарласыңыз.', 'label' => 'Кітапханамен байланысу', 'href' => '/contacts'],
                'en' => ['heading' => 'Confirm exchange guidance', 'body' => 'If your institution needs interlibrary loan access, contact the library to confirm the correct request route.', 'label' => 'Contact the library', 'href' => '/contacts'],
            ],
        ],
        'catalog-usability-lab-findings-2026' => [
            'slug' => 'catalog-usability-lab-findings-2026',
            'featured' => false,
            'published_at' => '2026-03-14',
            'published_display' => [
                'ru' => '14 марта 2026',
                'kk' => '2026 жылғы 14 наурыз',
                'en' => 'March 14, 2026',
            ],
            'category' => [
                'ru' => 'Исследования',
                'kk' => 'Зерттеулер',
                'en' => 'Research',
            ],
            'title' => [
                'ru' => 'Лаборатория UX-каталога опубликовала результаты весеннего цикла',
                'kk' => 'Каталог UX-зертханасы көктемгі цикл нәтижелерін жариялады',
                'en' => 'Catalog UX Lab Publishes Spring Findings',
            ],
            'excerpt' => [
                'ru' => 'Команда библиотеки провела серию тестов навигации и поиска, чтобы сократить время доступа к научным материалам и повысить точность выдачи.',
                'kk' => 'Кітапхана командасы ғылыми материалдарға қолжетімділік уақытын қысқарту және іздеу нәтижесінің дәлдігін арттыру үшін навигация мен іздеу тесттерін өткізді.',
                'en' => 'The library team ran navigation and search tests to reduce access time to scholarly materials and improve retrieval precision.',
            ],
            'hero' => [
                'image' => 'images/news/ai-workshop.jpg',
                'alt' => [
                    'ru' => 'Дашборд исследования пользовательских сценариев каталога',
                    'kk' => 'Каталогтың пайдаланушы сценарийлерін зерттеу дашборды',
                    'en' => 'Dashboard from the catalog user-scenario research cycle',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Казахский университет технологии и бизнеса имени К. Кулажанова завершила весенний цикл исследований пользовательского опыта каталога.'],
                    ['type' => 'p', 'text' => 'Команда проанализировала пути поиска по ключевым исследовательским сценариям и обновила приоритеты улучшений для интерфейсов фильтрации, выдачи и перехода к связанным материалам.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті каталог пайдаланушы тәжірибесін зерттеудің көктемгі циклін аяқтады.'],
                    ['type' => 'p', 'text' => 'Команда зерттеу сценарийлері бойынша іздеу маршруттарын талдап, сүзгілеу, нәтиже беру және байланысты материалдарға өту интерфейстерін жетілдіру басымдықтарын жаңартты.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'Kazakh University of Technology and Business named after K. Kulazhanov has completed its spring cycle of catalog user-experience research.'],
                    ['type' => 'p', 'text' => 'The team analysed search journeys across core research scenarios and refreshed improvement priorities for filtering, result ranking, and related-material navigation interfaces.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Открыть каталог', 'body' => 'Проверьте обновленные сценарии поиска в публичном каталоге библиотеки.', 'label' => 'Перейти в каталог', 'href' => '/catalog'],
                'kk' => ['heading' => 'Каталогты ашу', 'body' => 'Кітапхананың ашық каталогындағы жаңартылған іздеу сценарийлерін тексеріңіз.', 'label' => 'Каталогқа өту', 'href' => '/catalog'],
                'en' => ['heading' => 'Open the catalog', 'body' => 'Review the updated search journeys in the public library catalog.', 'label' => 'Go to catalog', 'href' => '/catalog'],
            ],
        ],
        'institutional-repository-intake-window-2026' => [
            'slug' => 'institutional-repository-intake-window-2026',
            'featured' => false,
            'published_at' => '2026-03-07',
            'published_display' => [
                'ru' => '7 марта 2026',
                'kk' => '2026 жылғы 7 наурыз',
                'en' => 'March 7, 2026',
            ],
            'category' => [
                'ru' => 'Репозиторий',
                'kk' => 'Репозиторий',
                'en' => 'Repository',
            ],
            'title' => [
                'ru' => 'Открыто окно приёма материалов в институциональный репозиторий',
                'kk' => 'Институционалдық репозиторийге материал қабылдау терезесі ашылды',
                'en' => 'Institutional Repository Intake Window Opens',
            ],
            'excerpt' => [
                'ru' => 'Библиотека объявила весенний цикл приёма диссертаций, статей и отчетов подразделений для экспертной модерации и публикации в репозитории.',
                'kk' => 'Кітапхана диссертациялар, мақалалар және бөлімшелер есептері үшін көктемгі қабылдау циклін жариялады, материалдар сараптамалық модерациядан кейін репозиторийде жарияланады.',
                'en' => 'The library announced a spring intake cycle for theses, articles, and institutional reports for expert moderation and repository publication.',
            ],
            'hero' => [
                'image' => 'images/news/default-library.jpg',
                'alt' => [
                    'ru' => 'Сотрудник репозитория проверяет пакет академических материалов',
                    'kk' => 'Репозиторий қызметкері академиялық материалдар пакетін тексеруде',
                    'en' => 'Repository officer reviewing an academic submission package',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Казахский университет технологии и бизнеса имени К. Кулажанова начала новый цикл приёма материалов в институциональный репозиторий.'],
                    ['type' => 'p', 'text' => 'Материалы проходят проверку метаданных, правового статуса и качества описания. После модерации записи публикуются с устойчивыми идентификаторами и маршрутом к защищенному просмотру.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті институционалдық репозиторийге материал қабылдаудың жаңа циклін бастады.'],
                    ['type' => 'p', 'text' => 'Материалдар метадерек, құқықтық мәртебе және сипаттама сапасы бойынша тексеріледі. Модерациядан кейін жазбалар тұрақты идентификаторлармен және қорғалған оқу маршрутымен жарияланады.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'Kazakh University of Technology and Business named after K. Kulazhanov has launched a new institutional repository intake cycle.'],
                    ['type' => 'p', 'text' => 'Submissions are reviewed for metadata quality, rights status, and descriptive completeness. After moderation, records are published with persistent identifiers and a controlled-reading route.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Перейти в репозиторий', 'body' => 'Проверьте условия приёма и текущие опубликованные записи репозитория.', 'label' => 'Открыть репозиторий', 'href' => '/repository'],
                'kk' => ['heading' => 'Репозиторийге өту', 'body' => 'Қабылдау шарттары мен репозиторийдегі ағымдағы жарияланған жазбаларды тексеріңіз.', 'label' => 'Репозиторийді ашу', 'href' => '/repository'],
                'en' => ['heading' => 'Go to repository', 'body' => 'Review intake conditions and current published repository records.', 'label' => 'Open repository', 'href' => '/repository'],
            ],
        ],
        'library-hours-extended-finals-2026' => [
            'slug' => 'library-hours-extended-finals-2026',
            'featured' => false,
            'published_at' => '2026-02-27',
            'published_display' => [
                'ru' => '27 февраля 2026',
                'kk' => '2026 жылғы 27 ақпан',
                'en' => 'February 27, 2026',
            ],
            'category' => [
                'ru' => 'Сервисы',
                'kk' => 'Сервистер',
                'en' => 'Services',
            ],
            'title' => [
                'ru' => 'В период финальной аттестации библиотека продлевает режим работы',
                'kk' => 'Қорытынды аттестация кезеңінде кітапхана жұмыс уақыты ұзартылды',
                'en' => 'Library Extends Operating Hours for Final Assessment Period',
            ],
            'excerpt' => [
                'ru' => 'Для поддержки учебной нагрузки библиотека расширяет вечерний график и усиливает консультационные окна по работе с источниками и подписными базами данных.',
                'kk' => 'Оқу жүктемесін қолдау үшін кітапхана кешкі кестені ұзартып, дереккөздер мен жазылымдық дерекқорлар бойынша консультация терезелерін көбейтеді.',
                'en' => 'To support peak academic workloads, the library is extending evening access and increasing consultation windows for source work and subscribed databases.',
            ],
            'hero' => [
                'image' => 'images/news/campus-library.jpg',
                'alt' => [
                    'ru' => 'Вечерний режим работы в читальном зале',
                    'kk' => 'Оқу залындағы кешкі жұмыс режимі',
                    'en' => 'Extended evening service in the main reading room',
                ],
            ],
            'body' => [
                'ru' => [
                    ['type' => 'lead', 'text' => 'Библиотека вводит расширенный график обслуживания на период финальной аттестации.'],
                    ['type' => 'p', 'text' => 'Дополнительные вечерние часы и консультационные окна направлены на поддержку студентов и преподавателей в работе с учебной и научной литературой.'],
                ],
                'kk' => [
                    ['type' => 'lead', 'text' => 'Кітапхана қорытынды аттестация кезеңіне арналған кеңейтілген қызмет көрсету кестесін енгізеді.'],
                    ['type' => 'p', 'text' => 'Қосымша кешкі сағаттар мен консультация терезелері студенттер мен оқытушылардың оқу және ғылыми әдебиетпен жұмысын қолдауға бағытталған.'],
                ],
                'en' => [
                    ['type' => 'lead', 'text' => 'The library is introducing an extended service schedule for the final assessment period.'],
                    ['type' => 'p', 'text' => 'Additional evening hours and consultation windows are intended to support students and faculty working with course and research literature.'],
                ],
            ],
            'cta' => [
                'ru' => ['heading' => 'Связаться с библиотекой', 'body' => 'Уточните расписание консультаций и вечерних сервисов через официальные контакты.', 'label' => 'Открыть контакты', 'href' => '/contacts'],
                'kk' => ['heading' => 'Кітапханамен байланысу', 'body' => 'Кеңес беру кестесі мен кешкі сервистерді ресми байланыс арналары арқылы нақтылаңыз.', 'label' => 'Байланыстарды ашу', 'href' => '/contacts'],
                'en' => ['heading' => 'Contact the library', 'body' => 'Confirm consultation and evening-service schedules through official contact channels.', 'label' => 'Open contacts', 'href' => '/contacts'],
            ],
        ],
    ];

    $orderedSlugs = [
        'global-symposium-archival-integrity',
        'catalog-assistance-pilot-2026',
        'student-research-showcase-2026',
        'eurasian-manuscripts-integration',
        'digital-access-partner-institutions',
        'restoration-lab-acquisition-ledgers',
        'interlibrary-loan-governance-update',
        'catalog-usability-lab-findings-2026',
        'institutional-repository-intake-window-2026',
        'library-hours-extended-finals-2026',
    ];

    return ['articles' => $articles, 'ordered' => $orderedSlugs];
};

// Public leadership content for /leadership. Profiles are published only
// when confirmed by an official university source.
$leadershipPublicProvider = static function (): array {
    $header = [
        'ru' => [
            'eyebrow' => 'Руководство библиотеки',
            'headline' => 'Руководство библиотеки',
            'lede' => 'На странице указаны сведения, подтверждённые официальной страницей научной библиотеки университета. Для обращений используйте официальные контакты.',
        ],
        'kk' => [
            'eyebrow' => 'Кітапхана басшылығы',
            'headline' => 'Кітапхана басшылығы',
            'lede' => 'Бұл бетте университеттің ғылыми кітапханасының ресми бетімен расталған мәліметтер көрсетілген. Өтініштер үшін ресми байланыс арналарын пайдаланыңыз.',
        ],
        'en' => [
            'eyebrow' => 'Library Leadership',
            'headline' => 'Library leadership',
            'lede' => 'This page shows information confirmed by the university’s official scientific-library page. Use the official contact routes for inquiries.',
        ],
    ];

    $mandate = [
        'ru' => [
            'eyebrow' => null,
            'title' => null,
            'paragraph' => null,
            'reports_to_label' => null,
            'reports_to_value' => null,
        ],
        'kk' => [
            'eyebrow' => null,
            'title' => null,
            'paragraph' => null,
            'reports_to_label' => null,
            'reports_to_value' => null,
        ],
        'en' => [
            'eyebrow' => null,
            'title' => null,
            'paragraph' => null,
            'reports_to_label' => null,
            'reports_to_value' => null,
        ],
    ];

    // Confirmed by the university's official scientific-library page.
    $profiles = [
        [
            'slug' => 'director',
            'order' => 1,
            'portrait' => null,
            'full_name' => [
                'ru' => 'Панкей Ж.',
                'kk' => 'Панкей Ж.',
                'en' => 'Панкей Ж.',
            ],
            'portrait_initials' => [
                'ru' => 'ПЖ',
                'kk' => 'ПЖ',
                'en' => 'ПЖ',
            ],
            'role_title' => [
                'ru' => 'Директор библиотеки',
                'kk' => 'Кітапхана директоры',
                'en' => 'Library Director',
            ],
            'role_scope_line' => [
                'ru' => null,
                'kk' => null,
                'en' => null,
            ],
            'role_description' => [
                'ru' => null,
                'kk' => null,
                'en' => null,
            ],
            'source_url' => 'https://www.kaztbu.edu.kz/biblioteka',
            'source_label' => [
                'ru' => 'Официальный источник',
                'kk' => 'Ресми дереккөз',
                'en' => 'Official source',
            ],
            'email' => 'zh.pankey@kaztbu.edu.kz',
            'extension' => '112',
        ],
    ];

    $supportCta = [
        'ru' => [
            'eyebrow' => 'Связаться с библиотекой',
            'heading' => 'Общие обращения и академические запросы',
            'body' => 'Для общих вопросов, обращений преподавателей и внешних академических запросов используйте официальные контакты библиотеки.',
            'label' => 'Перейти к контактам',
            'href' => '/contacts',
        ],
        'kk' => [
            'eyebrow' => 'Кітапханамен байланысу',
            'heading' => 'Жалпы өтініштер мен академиялық сұраулар',
            'body' => 'Жалпы сұрақтар, оқытушылардың өтініштері және сыртқы академиялық сұраулар бойынша кітапхананың ресми байланыс арналарын пайдаланыңыз.',
            'label' => 'Байланыс бетіне өту',
            'href' => '/contacts',
        ],
        'en' => [
            'eyebrow' => 'Contact the library',
            'heading' => 'General inquiries and academic requests',
            'body' => 'For general questions, faculty inquiries, and external academic requests, please use the library’s official contact routes.',
            'label' => 'Open contacts',
            'href' => '/contacts',
        ],
    ];

    return [
        'header' => $header,
        'mandate' => $mandate,
        'profiles' => $profiles,
        'support_cta' => $supportCta,
        'last_reviewed_at' => null,
    ];
};

// Official library rules published by the university. The five stable section
// anchors remain available for inbound links; the eleven source rules are
// grouped by topic without changing their meaning.
$rulesPublicProvider = static function (): array {
    $header = [
        'ru' => [
            'eyebrow' => 'Пользование библиотекой',
            'headline' => 'Правила пользования библиотекой',
            'subtitle_secondary_lang' => 'Library Usage Rules',
            'preamble' => 'Настоящие Правила определяют порядок организации деятельности научной библиотеки университета и условия пользования библиотечным фондом.',
            'effective_label' => null,
            'effective_date' => null,
            'reviewed_label' => null,
        ],
        'kk' => [
            'eyebrow' => 'Кітапхананы пайдалану',
            'headline' => 'Кітапхананы пайдалану ережелері',
            'subtitle_secondary_lang' => 'Library Usage Rules',
            'preamble' => 'Осы Ережелер университеттің ғылыми кітапханасы қызметін ұйымдастыру тәртібін және кітапхана қорын пайдалану шарттарын айқындайды.',
            'effective_label' => null,
            'effective_date' => null,
            'reviewed_label' => null,
        ],
        'en' => [
            'eyebrow' => 'Using the library',
            'headline' => 'Library Usage Rules',
            'subtitle_secondary_lang' => 'Правила пользования библиотекой',
            'preamble' => 'These Rules establish how the university Scientific Library operates and the conditions for using its collection.',
            'effective_label' => null,
            'effective_date' => null,
            'reviewed_label' => null,
        ],
    ];

    $toc = [
        'ru' => [
            'label' => 'Содержание',
            'items' => [
                ['href' => '#general', 'label' => '1. Общие положения'],
                ['href' => '#borrowing', 'label' => '2. Выдача и возврат'],
                ['href' => '#digital', 'label' => '3. Работа в читальном зале'],
                ['href' => '#conduct', 'label' => '4. Обязанности читателей'],
                ['href' => '#penalties', 'label' => '5. Ответственность'],
            ],
        ],
        'kk' => [
            'label' => 'Мазмұны',
            'items' => [
                ['href' => '#general', 'label' => '1. Жалпы ережелер'],
                ['href' => '#borrowing', 'label' => '2. Беру және қайтару'],
                ['href' => '#digital', 'label' => '3. Оқу залында пайдалану'],
                ['href' => '#conduct', 'label' => '4. Оқырмандардың міндеттері'],
                ['href' => '#penalties', 'label' => '5. Жауапкершілік'],
            ],
        ],
        'en' => [
            'label' => 'Contents',
            'items' => [
                ['href' => '#general', 'label' => '1. General provisions'],
                ['href' => '#borrowing', 'label' => '2. Borrowing and returns'],
                ['href' => '#digital', 'label' => '3. Reading-room use'],
                ['href' => '#conduct', 'label' => '4. Reader responsibilities'],
                ['href' => '#penalties', 'label' => '5. Responsibility'],
            ],
        ],
    ];

    $general = [
        'ru' => [
            'number' => '1',
            'eyebrow' => 'Раздел 1',
            'title' => 'Общие положения',
            'lede' => 'Кто может пользоваться научной библиотекой и как пройти регистрацию.',
            'items' => [
                'Право пользования научной библиотекой предоставляется студентам, магистрантам, докторантам и профессорско-преподавательскому составу университета.',
                'Регистрация осуществляется на основании удостоверения личности или студенческого билета. Читателю выдается читательский билет.',
            ],
        ],
        'kk' => [
            'number' => '1',
            'eyebrow' => '1-бөлім',
            'title' => 'Жалпы ережелер',
            'lede' => 'Ғылыми кітапхананы кімдер пайдалана алады және тіркелу тәртібі.',
            'items' => [
                'Ғылыми кітапхананы пайдалану құқығы университеттің студенттеріне, магистранттарына, докторанттарына және профессор-оқытушылар құрамына беріледі.',
                'Ғылыми кітапханаға тіркеу жеке куәлік немесе студенттік билет негізінде жүзеге асырылады. Оқырманға оқырман билеті беріледі.',
            ],
        ],
        'en' => [
            'number' => '1',
            'eyebrow' => 'Section 1',
            'title' => 'General provisions',
            'lede' => 'Who may use the Scientific Library and how registration works.',
            'items' => [
                'The Scientific Library is available to university students, master’s students, doctoral students, and faculty.',
                'Registration requires an identity document or student ID. The reader receives a library card.',
            ],
        ],
    ];

    $borrowing = [
        'ru' => [
            'number' => '2',
            'eyebrow' => 'Раздел 2',
            'title' => 'Выдача и возврат',
            'lede' => 'Для каждой выдачи система показывает фактический срок возврата и доступные читателю действия.',
            'groups' => [
                [
                    'audience' => 'Текущая выдача',
                    'icon' => 'menu_book',
                    'rows' => [
                        ['label' => 'Срок возврата', 'value' => 'В личном кабинете'],
                        ['label' => 'Продление', 'value' => 'Если действие доступно'],
                        ['label' => 'Бронирование', 'value' => 'По фактическому наличию'],
                    ],
                ],
            ],
            'notes' => [
                'Наличие экземпляров проверяйте в каталоге непосредственно перед обращением.',
                'Условия конкретного экземпляра могут отличаться; интерфейс показывает только доступные для него действия.',
                'По вопросам выдачи и возврата используйте официальные контакты библиотеки.',
            ],
        ],
        'kk' => [
            'number' => '2',
            'eyebrow' => '2-бөлім',
            'title' => 'Беру және қайтару',
            'lede' => 'Әр берілім үшін жүйе нақты қайтару мерзімін және оқырманға қолжетімді әрекеттерді көрсетеді.',
            'groups' => [
                [
                    'audience' => 'Ағымдағы берілім',
                    'icon' => 'menu_book',
                    'rows' => [
                        ['label' => 'Қайтару мерзімі', 'value' => 'Жеке кабинетте'],
                        ['label' => 'Ұзарту', 'value' => 'Әрекет қолжетімді болса'],
                        ['label' => 'Брондау', 'value' => 'Нақты қолжетімділік бойынша'],
                    ],
                ],
            ],
            'notes' => [
                'Кітапханаға жүгінер алдында даналардың бар-жоғын каталогтан тексеріңіз.',
                'Нақты дананың шарттары өзгеше болуы мүмкін; интерфейс сол дана үшін қолжетімді әрекеттерді ғана көрсетеді.',
                'Беру және қайтару туралы сұрақтар бойынша кітапхананың ресми байланыс арналарын пайдаланыңыз.',
            ],
        ],
        'en' => [
            'number' => '2',
            'eyebrow' => 'Section 2',
            'title' => 'Borrowing and returns',
            'lede' => 'For each loan, the system shows the actual due date and the actions available to the reader.',
            'groups' => [
                [
                    'audience' => 'Current loan',
                    'icon' => 'menu_book',
                    'rows' => [
                        ['label' => 'Due date', 'value' => 'In the reader account'],
                        ['label' => 'Renewal', 'value' => 'When the action is available'],
                        ['label' => 'Reservation', 'value' => 'Based on current availability'],
                    ],
                ],
            ],
            'notes' => [
                'Check current copy availability in the catalogue before contacting the library.',
                'Conditions may differ for a specific copy; the interface shows only the actions available for it.',
                'Use the library’s official contacts for questions about borrowing and returns.',
            ],
        ],
    ];

    $digital = [
        'ru' => [
            'number' => '3',
            'eyebrow' => 'Раздел 3',
            'title' => 'Электронный доступ',
            'lede' => 'Способ доступа определяется для каждого опубликованного электронного материала или внешнего ресурса отдельно.',
            'items' => [
                'Карточка материала показывает доступное действие: чтение, переход к источнику или запрос доступа.',
                'Карточка внешнего ресурса показывает подтверждённые условия доступа и авторизации.',
                'Если ресурс недоступен по результатам проверки, переход к нему блокируется.',
                'Не передавайте данные своей учётной записи другим людям.',
            ],
        ],
        'kk' => [
            'number' => '3',
            'eyebrow' => '3-бөлім',
            'title' => 'Электрондық қолжетімділік',
            'lede' => 'Қолжетімділік тәсілі әр жарияланған электрондық материал немесе сыртқы ресурс үшін жеке анықталады.',
            'items' => [
                'Материал карточкасында қолжетімді әрекет көрсетіледі: оқу, дереккөзге өту немесе қолжетімділік сұрау.',
                'Сыртқы ресурс карточкасында расталған қолжетімділік және авторизация шарттары көрсетіледі.',
                'Тексеру нәтижесі бойынша ресурс қолжетімсіз болса, оған өту бұғатталады.',
                'Есептік жазбаңыздың деректерін басқа адамдарға бермеңіз.',
            ],
        ],
        'en' => [
            'number' => '3',
            'eyebrow' => 'Section 3',
            'title' => 'Digital access',
            'lede' => 'The access method is determined separately for each published digital material or external resource.',
            'items' => [
                'The material card shows the available action: read, open the source, or request access.',
                'An external resource card shows its confirmed access and authentication conditions.',
                'If a resource is unavailable according to its latest check, the outbound action is blocked.',
                'Do not share your account credentials with other people.',
            ],
        ],
    ];

    $conduct = [
        'ru' => [
            'number' => '4',
            'eyebrow' => 'Раздел 4',
            'title' => 'Правила поведения',
            'lede' => 'При работе с фондом сохраняйте материалы и учитывайте указания сотрудников в конкретной зоне обслуживания.',
            'items' => [
                'Обращайтесь с библиотечными материалами бережно и сообщайте сотруднику о замеченных повреждениях.',
                'Не делайте пометок и не повреждайте печатные издания.',
                'Соблюдайте спокойную рабочую обстановку и уважайте других посетителей.',
                'Уточняйте правила конкретного помещения или пункта обслуживания у сотрудника библиотеки.',
            ],
        ],
        'kk' => [
            'number' => '4',
            'eyebrow' => '4-бөлім',
            'title' => 'Мінез-құлық ережелері',
            'lede' => 'Кітапхана қорымен жұмыс істегенде материалдарды сақтаңыз және нақты қызмет көрсету аймағындағы қызметкерлердің нұсқауларын ескеріңіз.',
            'items' => [
                'Кітапхана материалдарын ұқыпты пайдаланыңыз және байқалған зақым туралы қызметкерге хабарлаңыз.',
                'Баспа басылымдарына белгі қоймаңыз және оларды зақымдамаңыз.',
                'Тыныш жұмыс ортасын сақтап, басқа келушілерге құрметпен қараңыз.',
                'Нақты бөлме немесе қызмет көрсету орнының тәртібін кітапхана қызметкерінен нақтылаңыз.',
            ],
        ],
        'en' => [
            'number' => '4',
            'eyebrow' => 'Section 4',
            'title' => 'Code of conduct',
            'lede' => 'When using the collection, protect the materials and follow staff guidance for the specific service area.',
            'items' => [
                'Handle library materials carefully and tell a staff member about any damage you notice.',
                'Do not mark or damage printed items.',
                'Maintain a calm working environment and respect other visitors.',
                'Ask a library staff member about the rules for a specific room or service point.',
            ],
        ],
    ];

    $penalties = [
        'ru' => [
            'number' => '5',
            'eyebrow' => 'Раздел 5',
            'title' => 'Вопросы и поддержка',
            'lede' => 'Точный порядок решения вопроса зависит от конкретной выдачи, материала или ресурса и уточняется библиотекой.',
            'items' => [
                'По просроченной выдаче ориентируйтесь на фактический срок в личном кабинете и обратитесь в библиотеку.',
                'При утрате или повреждении издания согласуйте дальнейшие действия с сотрудником библиотеки.',
                'По вопросам доступа к электронному материалу или внешнему ресурсу сообщите название и адрес его карточки.',
            ],
            'suspension_ladder_label' => '',
            'suspension_ladder' => [],
            'appeal_label' => 'Обратная связь',
            'appeal_text' => 'Используйте официальные контакты библиотеки; для авторизованных читателей также доступен канал обращений в личном кабинете.',
        ],
        'kk' => [
            'number' => '5',
            'eyebrow' => '5-бөлім',
            'title' => 'Сұрақтар мен қолдау',
            'lede' => 'Мәселені шешудің нақты тәртібі тиісті берілімге, материалға немесе ресурсқа байланысты және кітапханадан нақтыланады.',
            'items' => [
                'Мерзімі өткен берілім бойынша жеке кабинеттегі нақты мерзімді тексеріп, кітапханаға хабарласыңыз.',
                'Басылым жоғалған немесе зақымдалған жағдайда кейінгі әрекеттерді кітапхана қызметкерімен келісіңіз.',
                'Электрондық материалға немесе сыртқы ресурсқа қолжетімділік туралы сұрақта оның атауы мен карточка мекенжайын көрсетіңіз.',
            ],
            'suspension_ladder_label' => '',
            'suspension_ladder' => [],
            'appeal_label' => 'Кері байланыс',
            'appeal_text' => 'Кітапхананың ресми байланыс арналарын пайдаланыңыз; авторизацияланған оқырмандар өтінішті жеке кабинет арқылы да жібере алады.',
        ],
        'en' => [
            'number' => '5',
            'eyebrow' => 'Section 5',
            'title' => 'Questions and support',
            'lede' => 'The exact way to resolve an issue depends on the specific loan, material, or resource and is confirmed by the library.',
            'items' => [
                'For an overdue loan, use the actual due date in the reader account and contact the library.',
                'If an item is lost or damaged, agree on the next steps with a library staff member.',
                'For a digital material or external resource access question, include its title and card address.',
            ],
            'suspension_ladder_label' => '',
            'suspension_ladder' => [],
            'appeal_label' => 'Feedback',
            'appeal_text' => 'Use the library’s official contacts; signed-in readers can also use the message channel in their account.',
        ],
    ];

    $footerMeta = [
        'ru' => [
            'eyebrow' => 'Связь с библиотекой',
            'heading' => 'Нужно уточнить условие?',
            'body' => 'Для точного ответа укажите издание, материал или ресурс и опишите нужное действие.',
            'contacts_label' => 'Перейти к контактам',
            'contacts_href' => '/contacts',
            'leadership_label' => 'Руководство библиотеки',
            'leadership_href' => '/leadership',
            'version_label' => null,
            'version_value' => null,
        ],
        'kk' => [
            'eyebrow' => 'Кітапханамен байланыс',
            'heading' => 'Шартты нақтылау керек пе?',
            'body' => 'Нақты жауап алу үшін басылымды, материалды немесе ресурсты көрсетіп, қажетті әрекетті сипаттаңыз.',
            'contacts_label' => 'Байланыс бетіне өту',
            'contacts_href' => '/contacts',
            'leadership_label' => 'Кітапхана басшылығы',
            'leadership_href' => '/leadership',
            'version_label' => null,
            'version_value' => null,
        ],
        'en' => [
            'eyebrow' => 'Contact the library',
            'heading' => 'Need to confirm a condition?',
            'body' => 'For an exact answer, identify the title, material, or resource and describe the action you need.',
            'contacts_label' => 'Open contacts',
            'contacts_href' => '/contacts',
            'leadership_label' => 'Library leadership',
            'leadership_href' => '/leadership',
            'version_label' => null,
            'version_value' => null,
        ],
    ];

    // The official page publishes eleven numbered rules. They are arranged
    // under the page's stable topic anchors below, with no additional policy
    // claims, loan limits, or penalties inferred by this application.
    $borrowing = [
        'ru' => [
            'number' => '2', 'eyebrow' => 'Раздел 2', 'title' => 'Выдача и возврат',
            'lede' => 'Порядок временного пользования материалами научной библиотеки.',
            'groups' => [],
            'notes' => [
                'Книги и учебно-методические материалы выдаются для временного пользования через абонементный отдел.',
                'Литература, полученная по абонементу, должна быть возвращена в научную библиотеку в установленные сроки.',
            ],
        ],
        'kk' => [
            'number' => '2', 'eyebrow' => '2-бөлім', 'title' => 'Беру және қайтару',
            'lede' => 'Ғылыми кітапхана материалдарын уақытша пайдалану тәртібі.',
            'groups' => [],
            'notes' => [
                'Кітаптар мен оқу-әдістемелік материалдар ғылыми кітапхананың абонемент бөлімі арқылы уақытша пайдалануға беріледі.',
                'Абонемент бойынша алынған әдебиет белгіленген мерзімде ғылыми кітапханаға қайтарылуға тиіс.',
            ],
        ],
        'en' => [
            'number' => '2', 'eyebrow' => 'Section 2', 'title' => 'Borrowing and returns',
            'lede' => 'The procedure for temporary use of Scientific Library materials.',
            'groups' => [],
            'notes' => [
                'Books and teaching materials are issued for temporary use through the Scientific Library lending department.',
                'Items borrowed through the lending department must be returned to the Scientific Library by the established due date.',
            ],
        ],
    ];

    $digital = [
        'ru' => ['number' => '3', 'eyebrow' => 'Раздел 3', 'title' => 'Работа в читальном зале', 'lede' => 'Материалы, которые не выдаются на абонемент.', 'items' => ['Издания редкого фонда, диссертации, справочная литература и периодические издания на абонемент не выдаются и используются только в читальном зале.']],
        'kk' => ['number' => '3', 'eyebrow' => '3-бөлім', 'title' => 'Оқу залында пайдалану', 'lede' => 'Абонементке берілмейтін материалдар.', 'items' => ['Сирек қор басылымдары, диссертациялар, анықтамалық әдебиет және мерзімді басылымдар абонементке берілмейді және тек ғылыми кітапхананың оқу залында пайдаланылады.']],
        'en' => ['number' => '3', 'eyebrow' => 'Section 3', 'title' => 'Reading-room use', 'lede' => 'Materials that are not available through the lending department.', 'items' => ['Rare-collection editions, dissertations, reference works, and periodicals are not loaned and may be used only in the Scientific Library reading room.']],
    ];

    $conduct = [
        'ru' => ['number' => '4', 'eyebrow' => 'Раздел 4', 'title' => 'Обязанности читателей', 'lede' => 'Бережное отношение к фонду и порядок в библиотеке.', 'items' => ['Читатели обязаны бережно относиться к библиотечным материалам, не допускать их порчи, не делать в них записи и не рвать страницы.', 'В научной библиотеке необходимо соблюдать общественный порядок, тишину и выполнять законные требования сотрудников.']],
        'kk' => ['number' => '4', 'eyebrow' => '4-бөлім', 'title' => 'Оқырмандардың міндеттері', 'lede' => 'Қорға ұқыпты қарау және кітапханадағы тәртіп.', 'items' => ['Оқырмандар кітапхана материалдарына ұқыпты қарауға, оларды бүлдірмеуге, оларға жазба жазбауға және беттерін жыртпауға міндетті.', 'Ғылыми кітапханада қоғамдық тәртіпті, тыныштықты сақтау және кітапхана қызметкерлерінің заңды талаптарын орындау қажет.']],
        'en' => ['number' => '4', 'eyebrow' => 'Section 4', 'title' => 'Reader responsibilities', 'lede' => 'Care for the collection and maintain order in the library.', 'items' => ['Readers must handle library materials carefully, avoid damaging them, refrain from writing in them, and not tear pages.', 'Readers must maintain public order and quiet in the Scientific Library and comply with lawful staff instructions.']],
    ];

    $penalties = [
        'ru' => ['number' => '5', 'eyebrow' => 'Раздел 5', 'title' => 'Ответственность', 'lede' => 'Меры при нарушении сроков, утрате или повреждении материалов и нарушении Правил.', 'items' => ['При нарушении сроков возврата к читателю применяются меры ответственности в соответствии с Правилами.', 'В случае утраты или приведения полученных материалов в непригодное состояние читатель обязан возместить их стоимость.', 'За нарушение Правил библиотечное обслуживание может быть временно ограничено или приостановлено.'], 'suspension_ladder_label' => '', 'suspension_ladder' => [], 'appeal_label' => 'Источник', 'appeal_text' => 'Правила опубликованы на официальной странице научной библиотеки.'],
        'kk' => ['number' => '5', 'eyebrow' => '5-бөлім', 'title' => 'Жауапкершілік', 'lede' => 'Қайтару мерзімі бұзылғанда, материалдар жоғалған немесе бүлінген және Ережелер бұзылған кездегі шаралар.', 'items' => ['Қайтару мерзімі бұзылған жағдайда оқырманға осы Ережелерге сәйкес жауапкершілік шаралары қолданылады.', 'Алынған материалдар жоғалған немесе жарамсыз күйге келтірілген жағдайда оқырман олардың құнын өтеуге міндетті.', 'Осы Ережелерді бұзғаны үшін оқырманға кітапханалық қызмет көрсетуді уақытша шектеу немесе тоқтата тұру шаралары қолданылуы мүмкін.'], 'suspension_ladder_label' => '', 'suspension_ladder' => [], 'appeal_label' => 'Дереккөз', 'appeal_text' => 'Ережелер ғылыми кітапхананың ресми бетінде жарияланған.'],
        'en' => ['number' => '5', 'eyebrow' => 'Section 5', 'title' => 'Responsibility', 'lede' => 'Measures for overdue returns, lost or damaged materials, and violations of the Rules.', 'items' => ['If a return deadline is missed, responsibility measures are applied under these Rules.', 'If borrowed materials are lost or rendered unusable, the reader must reimburse their value.', 'Library service may be temporarily restricted or suspended for violations of these Rules.'], 'suspension_ladder_label' => '', 'suspension_ladder' => [], 'appeal_label' => 'Source', 'appeal_text' => 'The Rules are published on the official Scientific Library page.'],
    ];

    return [
        'header' => $header,
        'toc' => $toc,
        'general' => $general,
        'borrowing' => $borrowing,
        'digital' => $digital,
        'conduct' => $conduct,
        'penalties' => $penalties,
        'footer_meta' => $footerMeta,
        'last_reviewed_at' => null,
    ];
};

// Public homepage (PROJECT_CONTEXT 17).
//
// Supplies the data-backed homepage sections: the new-additions rail comes
// from CatalogReadService, and the per-UDC counts shown on the faculty and
// collection cards are read from the same service. Counts are cached because
// they are one query per index; when the catalog schema is absent the map
// stays empty and the cards render without a count rather than inventing one.
Route::get('/', function (CatalogReadService $catalogReadService, PublicPortalStatistics $publicPortalStatistics) {
    $newArrivals = [];
    $homepageNews = collect();
    $latestRepositoryWorks = collect();
    $publicStats = [];
    try {
        $publicStats = $publicPortalStatistics->snapshot();
    } catch (Throwable) {
        // A missing source suppresses its public figure; it never triggers a
        // configured or marketing fallback.
    }
    try {
        // Include the total so the same canonical read path drives the rail in
        // every environment; an unavailable source still yields an empty rail.
        $result = Cache::remember('homepage.new_arrivals', now()->addMinutes(5), fn (): array => $catalogReadService->search(
            limit: 10,
            sort: 'recently_added',
            includeTotal: true,
            includeLocations: false,
            completeOnly: true,
        ));
        $newArrivals = is_array($result['data'] ?? null) ? $result['data'] : [];
    } catch (Throwable) {
        // The homepage must render even with the catalog schema absent.
    }

    $udcCounts = Cache::remember(
        'homepage.udc_counts',
        now()->addMinutes(15),
        static function () use ($catalogReadService): array {
            $codes = collect(array_merge(
                config('homepage_sections.faculties', []),
                config('homepage_sections.collections', []),
            ))->pluck('udc')->unique()->values();

            $counts = [];
            foreach ($codes as $code) {
                try {
                    $result = $catalogReadService->search(
                        udc: $code,
                        limit: 1,
                        includeTotal: true,
                        includeLocations: false,
                    );
                    $total = (int) ($result['meta']['total'] ?? 0);
                    if ($total > 0) {
                        $counts[$code] = $total;
                    }
                } catch (Throwable) {
                    // A missing catalog means no counts at all; stop probing.
                    return [];
                }
            }

            return $counts;
        },
    );

    $facultyDeskDefinitions = [
        'econ' => 'economic_library',
        'tech' => 'technology_library',
        'engit' => 'college_library',
    ];
    $facultyBooks = [];
    $facultyStats = [];
    foreach ($facultyDeskDefinitions as $deskKey => $institution) {
        try {
            $books = Cache::remember(
                'homepage.faculty_books.'.$institution,
                now()->addMinutes(15),
                fn (): array => $catalogReadService->popularByInstitution($institution),
            );
            $tones = ['sand', 'ink', 'clay', 'forest', 'sage'];
            $facultyBooks[$deskKey] = collect($books)
                ->values()
                ->map(function (array $book, int $index) use ($tones): array {
                    $book['tone'] = $tones[$index % count($tones)];

                    return $book;
                })
                ->all();
            $facultyStats[$deskKey] = Cache::remember(
                'homepage.faculty_copies.'.$institution,
                now()->addMinutes(15),
                fn (): int => $catalogReadService->institutionCopiesCount($institution),
            );
        } catch (Throwable) {
            $facultyBooks[$deskKey] = [];
            $facultyStats[$deskKey] = 0;
        }
    }

    try {
        if (Schema::hasTable('news')) {
            $homepageNews = News::query()
                ->published()
                ->homepage()
                ->when(Schema::hasColumn('news', 'visibility'), fn ($query) => $query->where('visibility', 'public'))
                ->when(Schema::hasColumn('news', 'type'), fn ($query) => $query->whereNotIn('type', ['event', 'schedule']))
                ->when(Schema::hasColumn('news', 'homepage_until'), fn ($query) => $query->where(fn ($nested) => $nested->whereNull('homepage_until')->orWhere('homepage_until', '>', now('UTC'))))
                ->when(Schema::hasColumn('news', 'is_pinned'), fn ($query) => $query->orderByDesc('is_pinned')->orderByDesc('homepage_priority'))
                ->latest(Schema::hasColumn('news', 'published_at') ? 'published_at' : 'publish_at')
                ->limit(6)
                ->get();
        }
    } catch (Throwable) {
        // A content-store outage must not make the public homepage unavailable.
    }

    try {
        if (Schema::hasTable('repository_items')) {
            $latestRepositoryWorks = RepositoryItem::query()
                ->published()
                ->publicMetadata()
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(4)
                ->get();
        }
    } catch (Throwable) {
        // Repository readiness must not become a homepage availability risk.
    }

    return view('welcome', [
        'newArrivals' => $newArrivals,
        'udcCounts' => $udcCounts,
        'facultyBooks' => $facultyBooks,
        'facultyStats' => $facultyStats,
        'homepageNews' => $homepageNews,
        'latestRepositoryWorks' => $latestRepositoryWorks,
        'publicStats' => $publicStats,
    ]);
});

Route::get('/catalog', function (Request $request, CatalogReadService $catalogReadService) {
    $uiSort = (string) $request->query('sort', 'popular');
    $materialType = (string) $request->query('material_type', 'all');
    $yearBounds = ['min' => (int) date('Y'), 'max' => (int) date('Y')];
    try {
        $yearBounds = $catalogReadService->yearBounds();
    } catch (Throwable) {
        // With no catalogue source, keep a neutral one-year control range.
    }
    $apiSortMap = [
        'title' => 'title',
        'year_desc' => 'newest',
        'year_asc' => 'oldest',
        'popular' => 'popular',
        'newest' => 'newest',
        'author' => 'author',
    ];

    $catalogBootstrap = $catalogReadService->search(
        query: trim((string) $request->query('q', '')),
        title: $request->filled('title') ? trim((string) $request->query('title')) : null,
        author: $request->filled('author') ? trim((string) $request->query('author')) : null,
        publisher: $request->filled('publisher') ? trim((string) $request->query('publisher')) : null,
        isbn: $request->filled('isbn') ? trim((string) $request->query('isbn')) : null,
        subject: $request->filled('subject') ? trim((string) $request->query('subject')) : null,
        udc: $request->filled('udc') ? trim((string) $request->query('udc')) : null,
        language: $request->filled('language') ? (string) $request->query('language') : null,
        page: max((int) $request->query('page', 1), 1),
        limit: Setting::catalogPageSize(),
        sort: $apiSortMap[$uiSort] ?? 'popular',
        yearFrom: $request->filled('year_from') && is_numeric((string) $request->query('year_from')) ? (int) $request->query('year_from') : null,
        yearTo: $request->filled('year_to') && is_numeric((string) $request->query('year_to')) ? (int) $request->query('year_to') : null,
        availableOnly: $request->boolean('available_only'),
        physicalOnly: $request->boolean('physical_only'),
        materialType: $materialType,
        institution: $request->filled('institution') ? (string) $request->query('institution') : null,
        resourceType: $request->filled('resource_type') ? (string) $request->query('resource_type') : null,
        fund: $request->filled('fund') ? (string) $request->query('fund') : null,
        branch: $request->filled('branch') ? (string) $request->query('branch') : null,
        category: $request->filled('category') ? (string) $request->query('category') : null,
        availability: $request->filled('availability') ? (string) $request->query('availability') : null,
        format: $request->filled('format') ? (string) $request->query('format') : null,
        includeUdcCode: $request->user() !== null,
    );

    return view('catalog', [
        'catalogBootstrap' => $catalogBootstrap,
        'catalogYearBounds' => $yearBounds,
        // Live filter axes: the sidebar renders only values that exist in the
        // collection, with their real counts.
        'catalogFacets' => $catalogReadService->facets(),
        'catalogPageSize' => Setting::catalogPageSize(),
    ]);
});

Route::get('/book/{isbn}', function (Request $request, string $isbn, BookDetailReadService $bookDetailReadService) {
    try {
        $bookBootstrap = $bookDetailReadService->findByIdentifier($isbn, $request->user());
    } catch (Throwable $exception) {
        report($exception);
        abort(503, 'The catalogue is temporarily unavailable.');
    }
    abort_if($bookBootstrap === null, 404);

    return view('book', [
        'bookIsbn' => $isbn,
        'bookBootstrap' => $bookBootstrap,
    ]);
});

Route::get('/digital-viewer/{materialId}', [DigitalViewerController::class, 'show']);

// Wave 1 — /account retained as a hidden backward-compatibility route only.
// The shell (navbar/footer/login) no longer surfaces /account; canonical user
// landing is /dashboard. This route remains for legacy bookmarks and for the
// existing API test harness that uses /account as a vehicle to assert
// authenticated reader behaviour. Do NOT add new shell links to /account.
Route::get('/account', function (Request $request) {
    $user = $request->session()->get('library.user');
    $lang = app()->getLocale();
    $lang = in_array($lang, ['ru', 'kk', 'en'], true) ? $lang : 'kk';
    $accountPath = $lang === 'kk' ? '/account' : ('/account?lang='.$lang);

    if (! is_array($user)) {
        $loginPath = '/login?redirect='.urlencode($accountPath);
        if ($lang !== 'kk') {
            $loginPath .= '&lang='.$lang;
        }

        return redirect($loginPath);
    }

    return view('account', ['sessionUser' => $user]);
});

Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:login');

// Forced password change after an admin reset (must_change_password flag).
// RequirePasswordChange redirects every other authenticated page here.
Route::middleware('auth')->group(function (): void {
    Route::get('/password/change', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/password/change', [PasswordChangeController::class, 'update'])->name('password.change.update');
});
Route::post('/locale', [LocaleController::class, 'update'])->name('locale.update');

// Session-based sign-out used by the member shell (form POST + CSRF).
// Admin and librarian shells use `fetch('/api/v1/logout')` for their JS-driven
// header buttons; this web route exists for the member shell's form control and
// keeps sign-out working without JavaScript. Accepts both POST (canonical form
// submit) and GET (safety net for accidental navigation) — always clears the
// library session keys and redirects to /login.
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');

// Keep unknown browser URLs inside the web middleware stack so session/cookie
// locale resolution also applies to the localized 404 response.
Route::fallback(static fn () => abort(404))->name('web.fallback');

Route::get('/login', function (Request $request) use ($postLoginDestination) {
    $lang = app()->getLocale();
    $lang = in_array($lang, ['ru', 'kk', 'en'], true) ? $lang : 'kk';

    if (is_array($request->session()->get('library.user'))) {
        $destination = $postLoginDestination($request->session()->get('library.user'));

        return redirect($lang === 'kk' ? $destination : ($destination.'?lang='.$lang));
    }

    $copy = [
        'ru' => [
            'title' => 'Вход — Казахский университет технологии и бизнеса имени К. Кулажанова',
            'brand' => 'Казахский университет технологии и бизнеса имени К. Кулажанова',
            'legacyHero' => 'Вход в библиотечную систему',
            'displayHeadline' => 'Сохраняем знания, поддерживаем исследования.',
            'lead' => 'Единая точка входа в цифровую библиотеку Казахского университета технологии и бизнеса: каталог, электронные коллекции и научный репозиторий.',
            'accessHeading' => 'Защищённый доступ',
            'accessValue' => 'Вход доступен только с корпоративной учётной записью университета. Действия в системе регистрируются в журнале безопасности.',
            'supportHeading' => 'Поддержка',
            'supportValue' => 'Возникли сложности со входом? Напишите в службу поддержки: support@kazutb.edu.kz.',
            'formTitle' => 'Добро пожаловать',
            'formSubtitle' => 'Введите корпоративный логин и пароль университета, чтобы продолжить.',
            'loginLabel' => 'Корпоративный логин',
            'loginPlaceholder' => 'Имя пользователя или университетский email',
            'passwordLabel' => 'Пароль',
            'passwordPlaceholder' => '••••••••••••',
            'forgot' => 'Забыли пароль?',
            'keepSigned' => 'Оставаться в системе 30 дней',
            'submit' => 'Войти',
            'securityNotice' => 'Несанкционированный доступ запрещён. Действия в системе фиксируются в журнале безопасности согласно политике университета.',
            'footerLegal' => ' '.date('Y').' Казахский университет технологии и бизнеса имени К. Кулажанова. Все права защищены.',
            'footerLinks' => [
                ['label' => 'О библиотеке', 'href' => '/about'],
                ['label' => 'Правила библиотеки', 'href' => '/rules'],
                ['label' => 'Контакты', 'href' => '/contacts'],
                ['label' => 'На главную', 'href' => '/'],
            ],
        ],
        'kk' => [
            'title' => 'Кіру — Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
            'brand' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
            'legacyHero' => 'Кітапхана жүйесіне кіру',
            'displayHeadline' => 'Білімді сақтаймыз, зерттеуді қолдаймыз.',
            'lead' => 'Қазақ технология және бизнес университетінің цифрлық кітапханасына бірыңғай кіру нүктесі: каталог, электрондық жинақтар және ғылыми репозиторий.',
            'accessHeading' => 'Қорғалған қолжетімділік',
            'accessValue' => 'Кіру тек университеттің корпоративтік есептік жазбасымен қолжетімді. Жүйедегі әрекеттер қауіпсіздік журналында тіркеледі.',
            'supportHeading' => 'Қолдау',
            'supportValue' => 'Кіру кезінде қиындық туындады ма? Қолдау қызметіне жазыңыз: support@kazutb.edu.kz.',
            'formTitle' => 'Қош келдіңіз',
            'formSubtitle' => 'Жалғастыру үшін университеттің корпоративтік логині мен құпиясөзін енгізіңіз.',
            'loginLabel' => 'Корпоративтік логин',
            'loginPlaceholder' => 'Пайдаланушы аты немесе университеттік email',
            'passwordLabel' => 'Құпиясөз',
            'passwordPlaceholder' => '••••••••••••',
            'forgot' => 'Құпиясөзді ұмыттыңыз ба?',
            'keepSigned' => 'Жүйеде 30 күн қалу',
            'submit' => 'Кіру',
            'securityNotice' => 'Рұқсатсыз кіруге тыйым салынады. Жүйедегі әрекеттер университет саясатына сәйкес қауіпсіздік журналында тіркеледі.',
            'footerLegal' => ' '.date('Y').' Қ. Құлажанов атындағы Қазақ технология және бизнес университеті. Барлық құқықтар қорғалған.',
            'footerLinks' => [
                ['label' => 'Кітапхана туралы', 'href' => '/about'],
                ['label' => 'Кітапхана ережелері', 'href' => '/rules'],
                ['label' => 'Байланыс', 'href' => '/contacts'],
                ['label' => 'Басты бетке', 'href' => '/'],
            ],
        ],
        'en' => [
            'title' => 'Sign in — Kazakh University of Technology and Business named after K. Kulazhanov',
            'brand' => 'Kazakh University of Technology and Business named after K. Kulazhanov',
            'legacyHero' => 'Sign in to the library system',
            'displayHeadline' => 'Preserving Knowledge, Empowering Research.',
            'lead' => 'A single entry point to the digital library of the Kazakh University of Technology and Business: catalog, digital collections, and the scholarly repository.',
            'accessHeading' => 'Secure Access',
            'accessValue' => 'Sign-in is available only with a university corporate account. Activity is recorded in the security audit log.',
            'supportHeading' => 'Support',
            'supportValue' => 'Having trouble signing in? Contact the help desk at support@kazutb.edu.kz.',
            'formTitle' => 'Welcome back',
            'formSubtitle' => 'Enter your university login and password to continue.',
            'loginLabel' => 'Corporate login',
            'loginPlaceholder' => 'Username or university email',
            'passwordLabel' => 'Password',
            'passwordPlaceholder' => '••••••••••••',
            'forgot' => 'Forgot password?',
            'keepSigned' => 'Keep me signed in for 30 days',
            'submit' => 'Sign in',
            'securityNotice' => 'Unauthorized access is prohibited. All activity is logged for security and auditing purposes as per institutional policy.',
            'footerLegal' => ' '.date('Y').' Kazakh University of Technology and Business named after K. Kulazhanov. All rights reserved.',
            'footerLinks' => [
                ['label' => 'About the Library', 'href' => '/about'],
                ['label' => 'Library Rules', 'href' => '/rules'],
                ['label' => 'Contacts', 'href' => '/contacts'],
                ['label' => 'Back to Home', 'href' => '/'],
            ],
        ],
    ];

    return response()->view('auth', [
        'copy' => $copy,
        'lang' => $lang,
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
})->name('login');

// Consolidated pages: /services removed — redirect to prevent 404s.
// Phase 3.3 — /news reinstated as the canonical public news index.
Route::get('/services', fn () => redirect('/', 301));

// Phase 3 Cluster C.1 — seeded public events content for /events.
//
// Scoped strictly to this page; structure mirrors $newsSeedProvider and
// $contactsSeedProvider so a future backend phase can replace the closure
// with a DB-backed events source (Phase 3 Cluster C.2 — event detail).
// The /events/{slug} detail surface is NOT in scope for Cluster C.1;
// the slugs defined here establish the future public route contract.
$eventsSeedProvider = static function (): array {
    $chrome = [
        'ru' => [
            'title' => 'События — Казахский университет технологии и бизнеса имени К. Кулажанова',
            'hero_eyebrow' => 'Публичная программа',
            'hero_title' => 'Календарь событий',
            'hero_body' => 'Расписание симпозиумов, семинаров и книжных выставок Казахский университет технологии и бизнеса имени К. Кулажанова. Присоединяйтесь к академическому сообществу университета.',
            'event_details_cta' => 'Подробнее о событии',
            'page_status' => 'Страница :page из :last · всего событий: :total',
            'previous_page' => 'Предыдущая страница',
            'next_page' => 'Следующая страница',
            'load_more' => 'Показать больше событий',
        ],
        'kk' => [
            'title' => 'Іс-шаралар — Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
            'hero_eyebrow' => 'Көпшілік бағдарламасы',
            'hero_title' => 'Іс-шаралар күнтізбесі',
            'hero_body' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті ұйымдастыратын симпозиумдар, семинарлар мен кітап көрмелерінің кестесі. Университеттің академиялық қауымдастығына қосылыңыз.',
            'event_details_cta' => 'Іс-шара туралы толығырақ',
            'page_status' => ':total іс-шара · :last беттің :page-беті',
            'previous_page' => 'Алдыңғы бет',
            'next_page' => 'Келесі бет',
            'load_more' => 'Қосымша іс-шараларды көрсету',
        ],
        'en' => [
            'title' => 'Events — Kazakh University of Technology and Business named after K. Kulazhanov',
            'hero_eyebrow' => 'Public Programme',
            'hero_title' => 'Public Events Index',
            'hero_body' => 'A curated calendar of upcoming symposiums, seminars, and book exhibits hosted by the Kazakh University of Technology and Business named after K. Kulazhanov. Join our academic community.',
            'event_details_cta' => 'Event details',
            'page_status' => 'Page :page of :last · :total events total',
            'previous_page' => 'Previous page',
            'next_page' => 'Next page',
            'load_more' => 'Load more events',
        ],
    ];

    $items = [
        [
            'slug' => 'digital-preservation-symposium-2026',
            'featured' => true,
            'category_slug' => 'symposium',
            'iso_date' => '2026-05-14',
            'i18n' => [
                'ru' => [
                    'category' => 'Симпозиум',
                    'date_month_day' => '14 мая',
                    'date_year_time' => '2026 · 10:00',
                    'title' => 'Цифровое сохранение фондов в академических библиотеках',
                    'description' => 'Открытая сессия для преподавателей и исследователей: методологии цифрового сохранения научных материалов, работа с метаданными и политика долгосрочного хранения.',
                    'venue' => 'Главный читальный зал, корпус 1',
                ],
                'kk' => [
                    'category' => 'Симпозиум',
                    'date_month_day' => '14 мамыр',
                    'date_year_time' => '2026 · 10:00',
                    'title' => 'Академиялық кітапханалардағы қорларды цифрлық сақтау',
                    'description' => 'Оқытушылар мен зерттеушілерге арналған ашық сессия: ғылыми материалдарды цифрлық сақтау әдіснамасы, метадеректермен жұмыс және ұзақ мерзімді сақтау саясаты.',
                    'venue' => 'Басты оқу залы, 1-корпус',
                ],
                'en' => [
                    'category' => 'Symposium',
                    'date_month_day' => 'May 14',
                    'date_year_time' => '2026 · 10:00',
                    'title' => 'Digital Preservation of Collections in Academic Libraries',
                    'description' => 'An open session for faculty and researchers: methodologies for digital preservation of scholarly materials, metadata workflows, and long-term retention policy.',
                    'venue' => 'Main Reading Room, Building 1',
                ],
            ],
        ],
        [
            'slug' => 'open-access-publishing-seminar-2026',
            'featured' => false,
            'category_slug' => 'seminar',
            'iso_date' => '2026-05-28',
            'i18n' => [
                'ru' => [
                    'category' => 'Семинар',
                    'date_month_day' => '28 мая',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Открытый доступ и академическая публикация',
                    'description' => 'Практический семинар для магистрантов и докторантов: выбор журналов, идентификация хищнических изданий, требования к Open Access и оформление авторских прав.',
                    'venue' => 'Семинарский зал B, корпус 1',
                ],
                'kk' => [
                    'category' => 'Семинар',
                    'date_month_day' => '28 мамыр',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Ашық қолжетімділік және академиялық жариялау',
                    'description' => 'Магистранттар мен докторанттарға арналған тәжірибелік семинар: журналдарды таңдау, жыртқыш басылымдарды анықтау, Open Access талаптары және авторлық құқықты рәсімдеу.',
                    'venue' => 'B семинар залы, 1-корпус',
                ],
                'en' => [
                    'category' => 'Seminar',
                    'date_month_day' => 'May 28',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Open Access and Academic Publishing',
                    'description' => 'A practical seminar for master\'s and doctoral candidates: journal selection, identifying predatory publishers, Open Access requirements, and copyright handling.',
                    'venue' => 'Seminar Hall B, Building 1',
                ],
            ],
        ],
        [
            'slug' => 'rare-collections-exhibit-2026',
            'featured' => false,
            'category_slug' => 'book-exhibit',
            'iso_date' => '2026-06-10',
            'i18n' => [
                'ru' => [
                    'category' => 'Книжная выставка',
                    'date_month_day' => '10 июня',
                    'date_year_time' => '2026 · весь день',
                    'title' => 'Редкие издания и научное наследие фонда',
                    'description' => 'Куратирская экспозиция редких изданий и отреставрированных научных материалов — приоритетные направления: ранняя инженерная литература и региональная история.',
                    'venue' => 'Зал 1/200, технологический фонд',
                ],
                'kk' => [
                    'category' => 'Кітап көрмесі',
                    'date_month_day' => '10 маусым',
                    'date_year_time' => '2026 · толық күн',
                    'title' => 'Сирек басылымдар және қордың ғылыми мұрасы',
                    'description' => 'Сирек басылымдар мен қалпына келтірілген ғылыми материалдардың кураторлық экспозициясы — басым бағыттар: ерте инженерлік әдебиет пен өңірлік тарих.',
                    'venue' => '1/200 залы, технологиялық қор',
                ],
                'en' => [
                    'category' => 'Book Exhibit',
                    'date_month_day' => 'Jun 10',
                    'date_year_time' => '2026 · All day',
                    'title' => 'Rare Editions and the Scholarly Heritage of the Collection',
                    'description' => 'A curated exhibition of rare editions and restored scholarly materials — priority areas: early engineering literature and regional history.',
                    'venue' => 'Room 1/200, Technology Fund',
                ],
            ],
        ],
        [
            'slug' => 'research-workshop-thesis-citations-2026',
            'featured' => false,
            'category_slug' => 'workshop',
            'iso_date' => '2026-06-24',
            'i18n' => [
                'ru' => [
                    'category' => 'Мастер-класс',
                    'date_month_day' => '24 июня',
                    'date_year_time' => '2026 · 15:00',
                    'title' => 'Работа с источниками и оформление библиографий',
                    'description' => 'Практикум для студентов старших курсов и магистрантов: работа с подписными базами данных, корректное цитирование и подготовка библиографического списка.',
                    'venue' => 'Зал 1/202, фонд колледжа',
                ],
                'kk' => [
                    'category' => 'Мастер-класс',
                    'date_month_day' => '24 маусым',
                    'date_year_time' => '2026 · 15:00',
                    'title' => 'Дереккөздермен жұмыс және библиографияны рәсімдеу',
                    'description' => 'Жоғары курс студенттері мен магистранттарға арналған практикум: жазылымдық дерекқорлармен жұмыс, дұрыс сілтеме жасау және библиографиялық тізімді дайындау.',
                    'venue' => '1/202 залы, колледж қоры',
                ],
                'en' => [
                    'category' => 'Workshop',
                    'date_month_day' => 'Jun 24',
                    'date_year_time' => '2026 · 15:00',
                    'title' => 'Working with Sources and Preparing Bibliographies',
                    'description' => 'A practical session for senior students and master\'s candidates: working with subscribed databases, correct citation, and preparation of a bibliographic list.',
                    'venue' => 'Room 1/202, College Fund',
                ],
            ],
        ],
        [
            'slug' => 'doctoral-writing-clinic-2026',
            'featured' => false,
            'category_slug' => 'clinic',
            'iso_date' => '2026-07-02',
            'i18n' => [
                'ru' => [
                    'category' => 'Консультационная сессия',
                    'date_month_day' => '2 июля',
                    'date_year_time' => '2026 · 11:00',
                    'title' => 'Клиника академического письма для докторантов',
                    'description' => 'Индивидуальные и групповые консультации по структуре статьи, академическому стилю, работе с отзывами рецензентов и этике публикации.',
                    'venue' => 'Исследовательская студия, зал 1/204',
                ],
                'kk' => [
                    'category' => 'Кеңес беру сессиясы',
                    'date_month_day' => '2 шілде',
                    'date_year_time' => '2026 · 11:00',
                    'title' => 'Докторанттарға арналған академиялық жазу клиникасы',
                    'description' => 'Мақала құрылымы, академиялық стиль, рецензент пікірлерімен жұмыс және жариялау этикасы бойынша жеке және топтық кеңестер.',
                    'venue' => 'Зерттеу студиясы, 1/204 залы',
                ],
                'en' => [
                    'category' => 'Writing Clinic',
                    'date_month_day' => 'Jul 02',
                    'date_year_time' => '2026 · 11:00',
                    'title' => 'Academic Writing Clinic for Doctoral Candidates',
                    'description' => 'Individual and group consultations on article structure, academic style, reviewer feedback, and publication ethics.',
                    'venue' => 'Research Studio, Room 1/204',
                ],
            ],
        ],
        [
            'slug' => 'data-literacy-bootcamp-2026',
            'featured' => false,
            'category_slug' => 'bootcamp',
            'iso_date' => '2026-07-09',
            'i18n' => [
                'ru' => [
                    'category' => 'Интенсив',
                    'date_month_day' => '9 июля',
                    'date_year_time' => '2026 · 09:30',
                    'title' => 'Интенсив по информационной и дата-грамотности',
                    'description' => 'Однодневная программа по поиску исследовательских наборов данных, оценке качества источников и базовой подготовке данных для учебных проектов.',
                    'venue' => 'Цифровая лаборатория, корпус 2',
                ],
                'kk' => [
                    'category' => 'Интенсив',
                    'date_month_day' => '9 шілде',
                    'date_year_time' => '2026 · 09:30',
                    'title' => 'Ақпараттық және дата-сауаттылық интенсиві',
                    'description' => 'Зерттеу деректер жиынтықтарын іздеу, дереккөз сапасын бағалау және оқу жобалары үшін деректерді бастапқы дайындау бойынша бір күндік бағдарлама.',
                    'venue' => 'Цифрлық зертхана, 2-корпус',
                ],
                'en' => [
                    'category' => 'Bootcamp',
                    'date_month_day' => 'Jul 09',
                    'date_year_time' => '2026 · 09:30',
                    'title' => 'Information and Data Literacy Bootcamp',
                    'description' => 'A one-day programme on locating research datasets, evaluating source quality, and preparing basic data for coursework projects.',
                    'venue' => 'Digital Lab, Building 2',
                ],
            ],
        ],
        [
            'slug' => 'heritage-metadata-roundtable-2026',
            'featured' => false,
            'category_slug' => 'roundtable',
            'iso_date' => '2026-07-16',
            'i18n' => [
                'ru' => [
                    'category' => 'Круглый стол',
                    'date_month_day' => '16 июля',
                    'date_year_time' => '2026 · 16:00',
                    'title' => 'Круглый стол по метаданным культурного наследия',
                    'description' => 'Обсуждение стандартов описания, межинституционального обмена записями и устойчивых идентификаторов для редких коллекций и архивов.',
                    'venue' => 'Медиа-зал, корпус 1',
                ],
                'kk' => [
                    'category' => 'Дөңгелек үстел',
                    'date_month_day' => '16 шілде',
                    'date_year_time' => '2026 · 16:00',
                    'title' => 'Мәдени мұра метадеректері бойынша дөңгелек үстел',
                    'description' => 'Сирек қорлар мен мұрағаттарға арналған сипаттау стандарттары, институтаралық жазба алмасу және тұрақты идентификаторлар талқыланады.',
                    'venue' => 'Медиа-зал, 1-корпус',
                ],
                'en' => [
                    'category' => 'Roundtable',
                    'date_month_day' => 'Jul 16',
                    'date_year_time' => '2026 · 16:00',
                    'title' => 'Heritage Metadata Roundtable',
                    'description' => 'A discussion on description standards, inter-institutional record exchange, and persistent identifiers for rare collections and archives.',
                    'venue' => 'Media Hall, Building 1',
                ],
            ],
        ],
        [
            'slug' => 'freshers-library-orientation-2026',
            'featured' => false,
            'category_slug' => 'orientation',
            'iso_date' => '2026-08-27',
            'i18n' => [
                'ru' => [
                    'category' => 'Ориентация',
                    'date_month_day' => '27 августа',
                    'date_year_time' => '2026 · 13:00',
                    'title' => 'Ориентация первокурсников по сервисам библиотеки',
                    'description' => 'Вводная сессия по читательскому кабинету, цифровым ресурсам, правилам пользования фондом и маршрутам обращения за академической поддержкой.',
                    'venue' => 'Актовый зал библиотеки',
                ],
                'kk' => [
                    'category' => 'Бейімдеу сессиясы',
                    'date_month_day' => '27 тамыз',
                    'date_year_time' => '2026 · 13:00',
                    'title' => 'Бірінші курс студенттеріне арналған кітапхана сервистері бойынша ориентация',
                    'description' => 'Оқырман кабинеті, цифрлық ресурстар, қорды пайдалану ережелері және академиялық қолдау маршруттары бойынша кіріспе сессия.',
                    'venue' => 'Кітапхана мәжіліс залы',
                ],
                'en' => [
                    'category' => 'Orientation',
                    'date_month_day' => 'Aug 27',
                    'date_year_time' => '2026 · 13:00',
                    'title' => 'Freshers Orientation to Library Services',
                    'description' => 'An introductory session on the reader dashboard, digital resources, collection rules, and routes for academic support.',
                    'venue' => 'Library Assembly Hall',
                ],
            ],
        ],
        [
            'slug' => 'research-data-workshop-2026',
            'featured' => false,
            'category_slug' => 'workshop',
            'iso_date' => '2026-09-04',
            'i18n' => [
                'ru' => [
                    'category' => 'Мастер-класс',
                    'date_month_day' => '4 сентября',
                    'date_year_time' => '2026 · 10:30',
                    'title' => 'Работа с исследовательскими данными и таблицами',
                    'description' => 'Практический воркшоп по сбору, очистке и краткому описанию исследовательских данных для курсовых и магистерских проектов.',
                    'venue' => 'Цифровая лаборатория, корпус 2',
                ],
                'kk' => [
                    'category' => 'Мастер-класс',
                    'date_month_day' => '4 қыркүйек',
                    'date_year_time' => '2026 · 10:30',
                    'title' => 'Зерттеу деректерімен және кестелермен жұмыс',
                    'description' => 'Курстық және магистрлік жобалар үшін зерттеу деректерін жинау, тазалау және қысқаша сипаттау бойынша практикалық воркшоп.',
                    'venue' => 'Цифрлық зертхана, 2-корпус',
                ],
                'en' => [
                    'category' => 'Workshop',
                    'date_month_day' => 'Sep 04',
                    'date_year_time' => '2026 · 10:30',
                    'title' => 'Working with Research Data and Tables',
                    'description' => 'A practical workshop on collecting, cleaning, and summarising research data for course and master’s projects.',
                    'venue' => 'Digital Lab, Building 2',
                ],
            ],
        ],
        [
            'slug' => 'scholarly-communication-seminar-2026',
            'featured' => false,
            'category_slug' => 'seminar',
            'iso_date' => '2026-09-18',
            'i18n' => [
                'ru' => [
                    'category' => 'Семинар',
                    'date_month_day' => '18 сентября',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Семинар по академической коммуникации и публикациям',
                    'description' => 'Разговор о структуре научной статьи, выборе журнала, подготовке аннотации и взаимодействии с редакциями.',
                    'venue' => 'Семинарский зал A, корпус 1',
                ],
                'kk' => [
                    'category' => 'Семинар',
                    'date_month_day' => '18 қыркүйек',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Академиялық коммуникация және жариялау семинары',
                    'description' => 'Ғылыми мақаланың құрылымы, журнал таңдау, аннотация дайындау және редакциялармен жұмыс туралы әңгіме.',
                    'venue' => 'A семинар залы, 1-корпус',
                ],
                'en' => [
                    'category' => 'Seminar',
                    'date_month_day' => 'Sep 18',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Scholarly Communication and Publishing Seminar',
                    'description' => 'A session on article structure, journal selection, abstract writing, and working with editorial teams.',
                    'venue' => 'Seminar Hall A, Building 1',
                ],
            ],
        ],
        [
            'slug' => 'new-semester-library-orientation-2026',
            'featured' => false,
            'category_slug' => 'orientation',
            'iso_date' => '2026-10-02',
            'i18n' => [
                'ru' => [
                    'category' => 'Ориентация',
                    'date_month_day' => '2 октября',
                    'date_year_time' => '2026 · 13:30',
                    'title' => 'Ориентация по библиотеке в новом семестре',
                    'description' => 'Повторная вводная встреча для студентов: сервисы, читательский кабинет, поиск книг и быстрые действия с подборками.',
                    'venue' => 'Актовый зал библиотеки',
                ],
                'kk' => [
                    'category' => 'Бейімдеу сессиясы',
                    'date_month_day' => '2 қазан',
                    'date_year_time' => '2026 · 13:30',
                    'title' => 'Жаңа семестрдегі кітапханаға ориентация',
                    'description' => 'Студенттерге арналған қайталама кіріспе кездесуі: сервистер, оқырман кабинеті, кітап іздеу және подборкамен жылдам жұмыс.',
                    'venue' => 'Кітапхана мәжіліс залы',
                ],
                'en' => [
                    'category' => 'Orientation',
                    'date_month_day' => 'Oct 02',
                    'date_year_time' => '2026 · 13:30',
                    'title' => 'New Semester Library Orientation',
                    'description' => 'A refreshed introduction for students: services, the reader dashboard, book search, and quick shortlist actions.',
                    'venue' => 'Library Assembly Hall',
                ],
            ],
        ],
        [
            'slug' => 'library-search-basics-2026',
            'featured' => false,
            'category_slug' => 'seminar',
            'iso_date' => '2026-08-29',
            'i18n' => [
                'ru' => [
                    'category' => 'Семинар',
                    'date_month_day' => '29 августа',
                    'date_year_time' => '2026 · 10:00',
                    'title' => 'Базовый семинар по поиску книг и статей',
                    'description' => 'Разбираем быстрый поиск, уточнение запросов, фильтры каталога и переход к сохранённым подборкам.',
                    'venue' => 'Зал учебных консультаций, корпус 1',
                ],
                'kk' => [
                    'category' => 'Семинар',
                    'date_month_day' => '29 тамыз',
                    'date_year_time' => '2026 · 10:00',
                    'title' => 'Кітаптар мен мақалаларды іздеу бойынша базалық семинар',
                    'description' => 'Жылдам іздеу, сұрауды нақтылау, каталог сүзгілері және сақталған подборкаларға өту қарастырылады.',
                    'venue' => 'Оқу консультациялары залы, 1-корпус',
                ],
                'en' => [
                    'category' => 'Seminar',
                    'date_month_day' => 'Aug 29',
                    'date_year_time' => '2026 · 10:00',
                    'title' => 'Basic Seminar on Finding Books and Articles',
                    'description' => 'We cover fast search, query refinement, catalog filters, and jumping to saved shortlists.',
                    'venue' => 'Study Consultations Room, Building 1',
                ],
            ],
        ],
        [
            'slug' => 'citation-tools-clinic-2026',
            'featured' => false,
            'category_slug' => 'clinic',
            'iso_date' => '2026-09-08',
            'i18n' => [
                'ru' => [
                    'category' => 'Клиника',
                    'date_month_day' => '8 сентября',
                    'date_year_time' => '2026 · 11:00',
                    'title' => 'Клиника по цитированию и библиографическим стилям',
                    'description' => 'Практика оформления ссылок, библиографий и ссылочного менеджмента для курсовых, статей и диссертаций.',
                    'venue' => 'Исследовательская студия, зал 1/203',
                ],
                'kk' => [
                    'category' => 'Клиника',
                    'date_month_day' => '8 қыркүйек',
                    'date_year_time' => '2026 · 11:00',
                    'title' => 'Дәйексөз және библиографиялық стильдер клиникасы',
                    'description' => 'Курстық, мақала және диссертациялар үшін сілтемелерді, библиографияны және reference manager құралдарын рәсімдеу практикасы.',
                    'venue' => 'Зерттеу студиясы, 1/203 залы',
                ],
                'en' => [
                    'category' => 'Clinic',
                    'date_month_day' => 'Sep 08',
                    'date_year_time' => '2026 · 11:00',
                    'title' => 'Citation and Bibliographic Styles Clinic',
                    'description' => 'Hands-on practice with references, bibliographies, and reference managers for papers, articles, and dissertations.',
                    'venue' => 'Research Studio, Room 1/203',
                ],
            ],
        ],
        [
            'slug' => 'repository-introduction-2026',
            'featured' => false,
            'category_slug' => 'workshop',
            'iso_date' => '2026-09-19',
            'i18n' => [
                'ru' => [
                    'category' => 'Мастер-класс',
                    'date_month_day' => '19 сентября',
                    'date_year_time' => '2026 · 14:30',
                    'title' => 'Как отправлять материалы в научный репозиторий',
                    'description' => 'Пошаговый мастер-класс по загрузке статей, проверке метаданных и подготовке материалов к публикации.',
                    'venue' => 'Медиа-зал, корпус 2',
                ],
                'kk' => [
                    'category' => 'Мастер-класс',
                    'date_month_day' => '19 қыркүйек',
                    'date_year_time' => '2026 · 14:30',
                    'title' => 'Ғылыми репозиторийге материал жіберу жолы',
                    'description' => 'Мақалаларды жүктеу, метадеректерді тексеру және материалды жариялауға дайындау бойынша қадамдық мастер-класс.',
                    'venue' => 'Медиа-зал, 2-корпус',
                ],
                'en' => [
                    'category' => 'Workshop',
                    'date_month_day' => 'Sep 19',
                    'date_year_time' => '2026 · 14:30',
                    'title' => 'How to Submit Materials to the Scholarly Repository',
                    'description' => 'A step-by-step workshop on uploading articles, checking metadata, and preparing items for publication.',
                    'venue' => 'Media Hall, Building 2',
                ],
            ],
        ],
        [
            'slug' => 'reading-club-launch-2026',
            'featured' => false,
            'category_slug' => 'book-exhibit',
            'iso_date' => '2026-10-10',
            'i18n' => [
                'ru' => [
                    'category' => 'Книжная выставка',
                    'date_month_day' => '10 октября',
                    'date_year_time' => '2026 · весь день',
                    'title' => 'Запуск читательского клуба и тематической витрины',
                    'description' => 'Открываем клуб чтения с подборкой новых книг, которые можно сразу добавлять в shortlist и обсуждать по темам.',
                    'venue' => 'Главный холл библиотеки',
                ],
                'kk' => [
                    'category' => 'Кітап көрмесі',
                    'date_month_day' => '10 қазан',
                    'date_year_time' => '2026 · толық күн',
                    'title' => 'Оқырман клубы мен тақырыптық витринаның іске қосылуы',
                    'description' => 'Жаңа кітаптар жинағымен оқырман клубын ашамыз, оларды бірден shortlist-ке қосып, тақырыптар бойынша талқылауға болады.',
                    'venue' => 'Кітапхананың басты холы',
                ],
                'en' => [
                    'category' => 'Book Exhibit',
                    'date_month_day' => 'Oct 10',
                    'date_year_time' => '2026 · All day',
                    'title' => 'Reading Club Launch and Themed Showcase',
                    'description' => 'We are launching a reading club with a new-book showcase that readers can instantly add to shortlist and discuss by topic.',
                    'venue' => 'Main Library Hall',
                ],
            ],
        ],
        [
            'slug' => 'digital-exhibitions-tour-2026',
            'featured' => false,
            'category_slug' => 'roundtable',
            'iso_date' => '2026-10-24',
            'i18n' => [
                'ru' => [
                    'category' => 'Круглый стол',
                    'date_month_day' => '24 октября',
                    'date_year_time' => '2026 · 15:00',
                    'title' => 'Экскурсия по цифровым выставкам и архивным витринам',
                    'description' => 'Показываем, как библиотека оформляет цифровые выставки, карточки материалов и связанные архивные подборки.',
                    'venue' => 'Онлайн и в медиазале',
                ],
                'kk' => [
                    'category' => 'Дөңгелек үстел',
                    'date_month_day' => '24 қазан',
                    'date_year_time' => '2026 · 15:00',
                    'title' => 'Цифрлық көрмелер мен архивтік витриналарға шолу',
                    'description' => 'Кітапхана цифрлық көрмелерді, материал карточкаларын және байланысты архивтік жинақтарды қалай рәсімдейтінін көрсетеді.',
                    'venue' => 'Онлайн және медиа-залда',
                ],
                'en' => [
                    'category' => 'Roundtable',
                    'date_month_day' => 'Oct 24',
                    'date_year_time' => '2026 · 15:00',
                    'title' => 'Tour of Digital Exhibitions and Archive Displays',
                    'description' => 'We show how the library prepares digital exhibitions, item cards, and linked archival bundles.',
                    'venue' => 'Online and in the media hall',
                ],
            ],
        ],
        [
            'slug' => 'exam-support-clinic-2026',
            'featured' => false,
            'category_slug' => 'clinic',
            'iso_date' => '2026-11-05',
            'i18n' => [
                'ru' => [
                    'category' => 'Клиника',
                    'date_month_day' => '5 ноября',
                    'date_year_time' => '2026 · 12:00',
                    'title' => 'Клиника поддержки перед экзаменами и дедлайнами',
                    'description' => 'Короткие консультации по поиску материалов, управлению временем и подбору источников для финальных работ.',
                    'venue' => 'Зал консультаций, корпус 1',
                ],
                'kk' => [
                    'category' => 'Клиника',
                    'date_month_day' => '5 қараша',
                    'date_year_time' => '2026 · 12:00',
                    'title' => 'Емтихан және дедлайн алдында қолдау клиникасы',
                    'description' => 'Материал іздеу, уақытты басқару және қорытынды жұмыстар үшін дереккөздерді таңдау бойынша қысқа консультациялар.',
                    'venue' => 'Кеңес беру залы, 1-корпус',
                ],
                'en' => [
                    'category' => 'Clinic',
                    'date_month_day' => 'Nov 05',
                    'date_year_time' => '2026 · 12:00',
                    'title' => 'Pre-Exam and Deadline Support Clinic',
                    'description' => 'Short consultations on finding materials, managing time, and selecting sources for final assignments.',
                    'venue' => 'Consultation Room, Building 1',
                ],
            ],
        ],
        [
            'slug' => 'library-innovation-lab-2026',
            'featured' => false,
            'category_slug' => 'roundtable',
            'iso_date' => '2026-11-18',
            'i18n' => [
                'ru' => [
                    'category' => 'Круглый стол',
                    'date_month_day' => '18 ноября',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Лаборатория библиотечных инноваций и новых сервисов',
                    'description' => 'Обсуждаем новые сценарии работы с каталогом, shortlist и цифровыми витринами для следующего цикла улучшений.',
                    'venue' => 'Медиа-зал, корпус 1',
                ],
                'kk' => [
                    'category' => 'Дөңгелек үстел',
                    'date_month_day' => '18 қараша',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Кітапхана инновациялары мен жаңа сервистер зертханасы',
                    'description' => 'Келесі жетілдіру циклі үшін каталог, shortlist және цифрлық витриналармен жұмыс істеудің жаңа сценарийлерін талқылаймыз.',
                    'venue' => 'Медиа-зал, 1-корпус',
                ],
                'en' => [
                    'category' => 'Roundtable',
                    'date_month_day' => 'Nov 18',
                    'date_year_time' => '2026 · 14:00',
                    'title' => 'Library Innovation Lab and New Services',
                    'description' => 'We discuss new workflows for the catalog, shortlist, and digital showcases for the next improvement cycle.',
                    'venue' => 'Media Hall, Building 1',
                ],
            ],
        ],
    ];

    return [
        'chrome' => $chrome,
        'items' => $items,
    ];
};

Route::get('/news', function (Request $request) use ($newsModelToPublicArticle) {
    $topic = (string) $request->query('topic', 'all');
    $topic = in_array($topic, ['all', 'research'], true) ? $topic : 'all';
    $page = max(1, (int) $request->query('page', 1));
    $newsTypes = array_values(array_diff(News::TYPES, ['event', 'schedule']));
    $requestedType = (string) $request->query('type', '');
    $requestedType = in_array($requestedType, $newsTypes, true) ? $requestedType : null;
    $search = mb_substr(trim((string) $request->query('q', '')), 0, 200);
    $categoryFilter = preg_match('/^[a-z0-9-]{1,100}$/', (string) $request->query('category', '')) ? (string) $request->query('category') : null;
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('from', '')) ? (string) $request->query('from') : null;
    $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query('to', '')) ? (string) $request->query('to') : null;
    $perPage = 5;
    $databaseArticles = collect();
    $filterCategories = collect();
    try {
        if (Schema::hasTable('news')) {
            // Once the managed table exists it is authoritative, including
            // when it is intentionally empty.
            if (Schema::hasTable('news_categories')) {
                $filterCategories = NewsCategory::query()->where('active', true)->orderBy('sort_order')->get();
            }
            $visibilities = ['public'];
            if ($request->user()) {
                $visibilities[] = 'members';
            }
            if ($request->user()?->can('news.view_internal')) {
                $visibilities[] = 'staff';
            }
            $databaseArticles = News::query()
                ->published()
                ->when(Schema::hasColumn('news', 'visibility'), fn ($query) => $query->whereIn('visibility', $visibilities))
                ->when(Schema::hasColumn('news', 'type'), fn ($query) => $query->whereNotIn('type', ['event', 'schedule']))
                ->when($requestedType, fn ($query) => $query->where('type', $requestedType))
                ->when($categoryFilter, fn ($query) => $query->whereHas('newsCategory', fn ($category) => $category->where('slug', $categoryFilter)))
                ->when($search !== '', fn ($query) => $query->search($search))
                ->when($from, fn ($query) => $query->whereDate(DB::raw('COALESCE(starts_at, published_at)'), '>=', $from))
                ->when($to, fn ($query) => $query->whereDate(DB::raw('COALESCE(starts_at, published_at)'), '<=', $to))
                ->latest(Schema::hasColumn('news', 'published_at') ? 'published_at' : 'publish_at')
                ->get()
                ->map($newsModelToPublicArticle);
        }
    } catch (Throwable $exception) {
        report($exception);
        abort(503, 'News is temporarily unavailable.');
    }

    // A missing or intentionally empty content store is an empty public news
    // list. Demo fixtures must never become production publications.
    $articles = $databaseArticles->values()->all();
    if ($topic !== 'all') {
        $articles = array_values(array_filter(
            $articles,
            static fn (array $article): bool => (string) ($article['topic'] ?? (
                data_get($article, 'category.en') === 'Event' ? 'events' : 'research'
            )) === $topic,
        ));
    }

    $total = count($articles);
    $lastPage = max(1, (int) ceil(max(1, $total) / $perPage));
    $page = min($page, $lastPage);
    $offset = ($page - 1) * $perPage;
    $articles = array_slice($articles, $offset, $perPage);

    return view('news.index', [
        'activePage' => 'news',
        'newsArticles' => $articles,
        'currentPage' => $page,
        'lastPage' => $lastPage,
        'newsTotal' => $total,
        'showCanonicalHero' => $page === 1,
        'filterCategories' => $filterCategories,
        'newsTypes' => $newsTypes,
    ]);
});

Route::get('/news/{slug}', function (Request $request, string $slug) use ($newsModelToPublicArticle) {
    $databaseArticle = null;
    $databaseRecord = null;
    $visibilities = ['public'];
    if ($request->user()) {
        $visibilities[] = 'members';
    }
    if ($request->user()?->can('news.view_internal')) {
        $visibilities[] = 'staff';
    }

    try {
        if (Schema::hasTable('news')) {
            // The database becomes the sole source as soon as the migration
            // exists; deleted or unknown articles must not fall back to seeds.
            $locale = in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'kk';
            $databaseRecord = News::query()->published()->when(Schema::hasColumn('news', 'visibility'), fn ($query) => $query->whereIn('visibility', $visibilities))->where(function ($query) use ($slug): void {
                $query->where('slug', $slug);
                if (Schema::hasColumn('news', 'slug_kk')) {
                    $query->orWhere('slug_kk', $slug)->orWhere('slug_ru', $slug)->orWhere('slug_en', $slug);
                }
            })->first();
            if (! $databaseRecord && Schema::hasTable('news_slug_redirects')) {
                $redirect = NewsSlugRedirect::query()->with('news')->where('locale', $locale)->where('old_slug', $slug)->first();
                $redirectRecord = $redirect?->news;
                $redirectIsVisible = $redirectRecord
                    && News::query()->published()->whereKey($redirectRecord->getKey())
                        ->when(Schema::hasColumn('news', 'visibility'), fn ($query) => $query->whereIn('visibility', $visibilities))
                        ->exists();
                if ($redirectIsVisible) {
                    $section = in_array((string) $redirectRecord->type, ['event', 'schedule'], true) ? 'events' : 'news';
                    $target = '/'.$section.'/'.rawurlencode($redirectRecord->localizedSlug($locale));
                    if ($locale !== 'kk') {
                        $target .= '?'.http_build_query(['lang' => $locale]);
                    }

                    return redirect($target, 301);
                }
            }
            if ($databaseRecord && in_array((string) $databaseRecord->type, ['event', 'schedule'], true)) {
                $target = '/events/'.rawurlencode($databaseRecord->localizedSlug($locale));
                if ($locale !== 'kk') {
                    $target .= '?'.http_build_query(['lang' => $locale]);
                }

                return redirect($target, 301);
            }
            $databaseArticle = $databaseRecord ? $newsModelToPublicArticle($databaseRecord) : null;
        }
    } catch (Throwable $exception) {
        report($exception);
        abort(503, 'News is temporarily unavailable.');
    }
    abort_unless($databaseArticle !== null && $databaseRecord !== null, 404);

    $article = $databaseArticle;
    app(NewsAnalyticsService::class)->recordView($databaseRecord, $request);
    $related = News::query()
        ->published()
        ->whereKeyNot($databaseRecord->getKey())
        ->when(Schema::hasColumn('news', 'visibility'), fn ($query) => $query->whereIn('visibility', $visibilities))
        ->when(Schema::hasColumn('news', 'type'), fn ($query) => $query->whereNotIn('type', ['event', 'schedule']))
        ->latest(Schema::hasColumn('news', 'published_at') ? 'published_at' : 'publish_at')
        ->limit(3)
        ->get()
        ->map($newsModelToPublicArticle)
        ->all();

    return view('news.show', [
        'activePage' => 'news',
        'article' => $article,
        'relatedArticles' => $related,
    ]);
})->where('slug', '[a-z0-9-]+')->name('news.show');

Route::post('/news/{news}/registration-click', function (Request $request, News $news) use ($publicHttpUrl) {
    $visibilities = ['public'];
    if ($request->user()) {
        $visibilities[] = 'members';
    }
    if ($request->user()?->can('news.view_internal')) {
        $visibilities[] = 'staff';
    }

    $isPublished = News::query()->published()->whereKey($news->getKey())->exists();
    $isVisible = ! Schema::hasColumn('news', 'visibility')
        || in_array((string) ($news->visibility ?: 'public'), $visibilities, true);
    $registrationUrl = $publicHttpUrl($news->registration_url);

    abort_unless(
        $isPublished
        && $isVisible
        && in_array((string) $news->type, ['event', 'schedule'], true)
        && $registrationUrl !== null,
        404,
    );
    app(NewsAnalyticsService::class)->recordRegistrationClick($news);

    return redirect()->away($registrationUrl);
})->middleware('throttle:30,1')->name('news.registration-click');

// Editorial contact copy backed by the university's official public pages.
// Values that the primary source does not confirm are deliberately omitted.
$contactsProvider = static function (): array {
    /*
     * Reading-room staff shown on /contacts.
     *
     * Personal names are deliberately NOT translated or transliterated per
     * locale — only the job title is — so a colleague's name is spelled exactly
     * one way across the whole site.
     *
     * `photo` is a path under public/. The card falls back to initials while the
     * file is absent, so publishing a portrait is a matter of dropping the file
     * in place — no template or data change needed.
     */
    $staffMembers = [
        [
            // Also profiled on /leadership, where the strategy remit and full
            // description live. This card is the short "who to ask" entry; the
            // two surfaces are kept deliberately different in depth.
            'slug' => 'pankey-zh',
            'name' => 'Панкей Ж.',
            'initials' => 'ПЖ',
            'photo' => 'images/staff/pankey-zh.jpg',
            'role' => [
                'ru' => 'Директор библиотеки',
                'kk' => 'Кітапхана директоры',
                'en' => 'Library Director',
            ],
            'responsibilities' => [
                'ru' => 'Руководитель научной библиотеки',
                'kk' => 'Ғылыми кітапхана басшысы',
                'en' => 'Head of the Scientific Library',
            ],
            'email' => 'zh.pankey@kaztbu.edu.kz',
            'extension' => '112',
        ],
        [
            'slug' => 'aikebayeva-shu',
            'name' => 'Айкебаева Ш.У.',
            'initials' => 'АШ',
            'photo' => null,
            'role' => ['ru' => 'Предметный библиотекарь', 'kk' => 'Пәндік кітапханашы', 'en' => 'Subject Librarian'],
            'responsibilities' => [
                'ru' => '«Туризм и сервис», «Учет и финансы», «Социально-гуманитарные дисциплины»',
                'kk' => '«Туризм және сервис», «Есеп және қаржы», «Әлеуметтік-гуманитарлық пәндер»',
                'en' => 'Tourism and Service; Accounting and Finance; Social and Humanitarian Disciplines',
            ],
        ],
        [
            'slug' => 'sailaubek-ab',
            'name' => 'Сайлаубек А.Б.',
            'initials' => 'СА',
            'photo' => null,
            'role' => ['ru' => 'Предметный библиотекарь', 'kk' => 'Пәндік кітапханашы', 'en' => 'Subject Librarian'],
            'responsibilities' => [
                'ru' => '«Экономика и управление», «Государственные и иностранные языки», «Физическое воспитание»',
                'kk' => '«Экономика және басқару», «Мемлекеттік және шет тілдері», «Дене тәрбиесі»',
                'en' => 'Economics and Management; State and Foreign Languages; Physical Education',
            ],
        ],
        [
            'slug' => 'raimkulova-na',
            'name' => 'Раимкулова Н.А.',
            'initials' => 'РН',
            'photo' => null,
            'role' => ['ru' => 'Предметный библиотекарь', 'kk' => 'Пәндік кітапханашы', 'en' => 'Subject Librarian'],
            'responsibilities' => [
                'ru' => '«Технология и стандартизация», «Технология легкой промышленности и дизайн»',
                'kk' => '«Технология және стандарттау», «Жеңіл өнеркәсіп технологиясы және дизайн»',
                'en' => 'Technology and Standardization; Light Industry Technology and Design',
            ],
        ],
        [
            'slug' => 'korpeshova-em',
            'name' => 'Корпешова Э.М.',
            'initials' => 'КЭ',
            'photo' => null,
            'role' => ['ru' => 'Предметный библиотекарь', 'kk' => 'Пәндік кітапханашы', 'en' => 'Subject Librarian'],
            'responsibilities' => [
                'ru' => '«Информационные технологии», «Компьютерная инженерия и автоматизация»',
                'kk' => '«Ақпараттық технологиялар», «Компьютерлік инженерия және автоматтандыру»',
                'en' => 'Information Technology; Computer Engineering and Automation',
            ],
        ],
        [
            'slug' => 'yermaganbetova-ma',
            'name' => 'Ермаганбетова М.А.',
            'initials' => 'ЕМ',
            'photo' => null,
            'role' => ['ru' => 'Предметный библиотекарь', 'kk' => 'Пәндік кітапханашы', 'en' => 'Subject Librarian'],
            'responsibilities' => [
                'ru' => '«Химия, химическая технология и экология»',
                'kk' => '«Химия, химиялық технология және экология»',
                'en' => 'Chemistry, Chemical Technology and Ecology',
            ],
        ],
    ];

    $staffFor = static function (string $lang) use ($staffMembers): array {
        return array_map(static fn (array $member): array => [
            'slug' => $member['slug'],
            'name' => $member['name'],
            'initials' => $member['initials'],
            'photo' => $member['photo'],
            'role' => $member['role'][$lang] ?? $member['role']['ru'],
            'responsibilities' => $member['responsibilities'][$lang] ?? $member['responsibilities']['ru'],
            'email' => $member['email'] ?? null,
            'extension' => $member['extension'] ?? null,
        ], $staffMembers);
    };

    $supportChannels = [
        'ru' => [
            [
                'slug' => 'library',
                'icon' => 'local_library',
                'title' => 'Научная библиотека',
                'body' => 'Вопросы о фонде, поиске изданий, выдаче и опубликованных электронных ресурсах.',
                'email' => 'zh.pankey@kaztbu.edu.kz',
                'phone' => null,
            ],
            [
                'slug' => 'general',
                'icon' => 'contact_support',
                'title' => 'Общий контакт университета',
                'body' => 'Для вопроса о фонде, электронном ресурсе или доступе укажите тему обращения, название материала и, при наличии, текст ошибки.',
                'email' => 'info@kaztbu.edu.kz',
                'phone' => '+7 (7172) 69-70-60',
            ],
        ],
        'kk' => [
            [
                'slug' => 'library',
                'icon' => 'local_library',
                'title' => 'Ғылыми кітапхана',
                'body' => 'Қор, басылымдарды іздеу, беру және жарияланған электрондық ресурстар туралы сұрақтар.',
                'email' => 'zh.pankey@kaztbu.edu.kz',
                'phone' => null,
            ],
            [
                'slug' => 'general',
                'icon' => 'contact_support',
                'title' => 'Университеттің жалпы байланыс арнасы',
                'body' => 'Қор, электрондық ресурс немесе қолжетімділік туралы сұрақта өтініш тақырыбын, материал атауын және бар болса қате мәтінін көрсетіңіз.',
                'email' => 'info@kaztbu.edu.kz',
                'phone' => '+7 (7172) 69-70-60',
            ],
        ],
        'en' => [
            [
                'slug' => 'library',
                'icon' => 'local_library',
                'title' => 'Scientific Library',
                'body' => 'Questions about the collection, finding and borrowing editions, and published electronic resources.',
                'email' => 'zh.pankey@kaztbu.edu.kz',
                'phone' => null,
            ],
            [
                'slug' => 'general',
                'icon' => 'contact_support',
                'title' => 'General university contact',
                'body' => 'For a collection, electronic-resource, or access question, include the subject, material title, and any error message.',
                'email' => 'info@kaztbu.edu.kz',
                'phone' => '+7 (7172) 69-70-60',
            ],
        ],
    ];

    // Kept verbatim from the university's official library page. The source
    // currently mentions Saturday in both the working-hours and days-off rows;
    // reproducing both rows avoids silently inventing a correction.
    $hoursRows = [
        'ru' => [
            ['days' => 'Понедельник – суббота', 'hours' => '08:30 – 17:30'],
            ['days' => 'Суббота, воскресенье', 'hours' => 'Выходной'],
            ['days' => 'Последний рабочий день месяца', 'hours' => 'Санитарный день'],
        ],
        'kk' => [
            ['days' => 'Дүйсенбі – сенбі', 'hours' => '08:30 – 17:30'],
            ['days' => 'Сенбі, жексенбі', 'hours' => 'Демалыс'],
            ['days' => 'Әр айдың соңғы жұмыс күні', 'hours' => 'Санитарлық күн'],
        ],
        'en' => [
            ['days' => 'Monday – Saturday', 'hours' => '08:30 – 17:30'],
            ['days' => 'Saturday, Sunday', 'hours' => 'Closed'],
            ['days' => 'Last working day of each month', 'hours' => 'Sanitary day'],
        ],
    ];

    return [
        'ru' => [
            'hero_eyebrow' => 'Связаться с библиотекой',
            'hero_title_a' => 'Официальные контакты',
            'hero_title_b' => 'университета.',
            'hero_body' => 'Используйте подтверждённые контактные данные. В обращении укажите тему, название материала или ресурса и, при наличии, текст ошибки.',
            'support_heading' => 'Контактные каналы',
            'support_channels' => $supportChannels['ru'],
            'location_title' => 'Физический адрес',
            'location_address_line_a' => 'Астана, ул. Кайыма Мухамедханова, 37A',
            'location_address_line_b' => 'Главный учебный корпус Казахского университета технологии и бизнеса имени К. Кулажанова.',
            'location_phone' => '+7 (7172) 69-70-60',
            'location_mobile' => '+7 (775) 232-22-66',
            'location_email' => 'info@kaztbu.edu.kz',
            'instagram_url' => 'https://www.instagram.com/library_kazutb/',
            'instagram_handle' => '@library_kazutb',
            'location_directions_cta' => 'Открыть в Google Maps',
            'hours_label' => 'Режим работы',
            'hours_rows' => $hoursRows['ru'],
            'hours_source_label' => 'Официальная страница научной библиотеки',
            'hours_source_url' => 'https://www.kaztbu.edu.kz/biblioteka',
            'fund_rooms' => [],
            'visit_title' => 'Перед визитом',
            'visit_body' => 'Перед посещением ознакомьтесь с общими условиями пользования и уточните рабочий день по официальному источнику.',
            'visit_link_rules_title' => 'Правила библиотеки',
            'visit_link_rules_body' => 'Общие ориентиры по выдаче, работе с фондом и электронному доступу.',
            'visit_link_leadership_title' => 'Руководство библиотеки',
            'visit_link_leadership_body' => 'Подтверждённые сведения о руководителе библиотеки.',
            'staff_heading' => 'Сотрудники библиотеки',
            'staff_note' => 'Обратитесь к сотруднику зала за помощью в поиске издания, работе с каталогом и оформлении выдачи.',
            'staff' => $staffFor('ru'),
        ],
        'kk' => [
            'hero_eyebrow' => 'Кітапханамен байланысу',
            'hero_title_a' => 'Университеттің',
            'hero_title_b' => 'ресми байланыс арналары.',
            'hero_body' => 'Расталған байланыс деректерін пайдаланыңыз. Өтініште тақырыпты, материалдың немесе ресурстың атауын және бар болса қате мәтінін көрсетіңіз.',
            'support_heading' => 'Байланыс арналары',
            'support_channels' => $supportChannels['kk'],
            'location_title' => 'Физикалық мекенжай',
            'location_address_line_a' => 'Астана, Қайым Мұхамедханов көшесі, 37A',
            'location_address_line_b' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университетінің бас оқу корпусы.',
            'location_phone' => '+7 (7172) 69-70-60',
            'location_mobile' => '+7 (775) 232-22-66',
            'location_email' => 'info@kaztbu.edu.kz',
            'instagram_url' => 'https://www.instagram.com/library_kazutb/',
            'instagram_handle' => '@library_kazutb',
            'location_directions_cta' => 'Google Maps-те ашу',
            'hours_label' => 'Жұмыс режимі',
            'hours_rows' => $hoursRows['kk'],
            'hours_source_label' => 'Ғылыми кітапхананың ресми беті',
            'hours_source_url' => 'https://www.kaztbu.edu.kz/biblioteka',
            'fund_rooms' => [],
            'visit_title' => 'Келмес бұрын',
            'visit_body' => 'Келмес бұрын жалпы пайдалану шарттарымен танысып, жұмыс күнін ресми дереккөзден нақтылаңыз.',
            'visit_link_rules_title' => 'Кітапхана ережелері',
            'visit_link_rules_body' => 'Беру, қорды пайдалану және электрондық қолжетімділік жөніндегі жалпы нұсқаулар.',
            'visit_link_leadership_title' => 'Кітапхана басшылығы',
            'visit_link_leadership_body' => 'Кітапхана басшысы туралы расталған мәліметтер.',
            'staff_heading' => 'Кітапхана қызметкерлері',
            'staff_note' => 'Басылымды іздеу, каталогпен жұмыс және беруді рәсімдеу бойынша көмек алу үшін зал қызметкеріне хабарласыңыз.',
            'staff' => $staffFor('kk'),
        ],
        'en' => [
            'hero_eyebrow' => 'Contact the library',
            'hero_title_a' => 'Official University',
            'hero_title_b' => 'Contact Details.',
            'hero_body' => 'Use the confirmed contact details. Include the subject, material or resource title, and any error message in your inquiry.',
            'support_heading' => 'Contact channels',
            'support_channels' => $supportChannels['en'],
            'location_title' => 'Physical Location',
            'location_address_line_a' => '37A Kayym Mukhamedkhanov Street, Astana',
            'location_address_line_b' => 'Main academic building of the Kazakh University of Technology and Business named after K. Kulazhanov.',
            'location_phone' => '+7 (7172) 69-70-60',
            'location_mobile' => '+7 (775) 232-22-66',
            'location_email' => 'info@kaztbu.edu.kz',
            'instagram_url' => 'https://www.instagram.com/library_kazutb/',
            'instagram_handle' => '@library_kazutb',
            'location_directions_cta' => 'Open in Google Maps',
            'hours_label' => 'Opening hours',
            'hours_rows' => $hoursRows['en'],
            'hours_source_label' => 'Official Scientific Library page',
            'hours_source_url' => 'https://www.kaztbu.edu.kz/biblioteka',
            'fund_rooms' => [],
            'visit_title' => 'Before you visit',
            'visit_body' => 'Before visiting, review the general usage guidance and confirm the working day through the official source.',
            'visit_link_rules_title' => 'Library usage rules',
            'visit_link_rules_body' => 'General guidance on loans, collection use, and digital access.',
            'visit_link_leadership_title' => 'Library leadership',
            'visit_link_leadership_body' => 'Confirmed information about the library director.',
            'staff_heading' => 'Library staff',
            'staff_note' => 'Ask a library staff member for help finding an edition, working with the catalogue, and arranging a loan.',
            'staff' => $staffFor('en'),
        ],
    ];
};

Route::get('/about', function () {
    return view('about', ['activePage' => 'about']);
});

Route::get('/contacts', function () use ($contactsProvider) {
    return view('contacts', [
        'activePage' => 'contacts',
        'contacts' => $contactsProvider(),
    ]);
});

// Phase 3 Cluster C.1 — standalone public events index surface.
// Layout copy remains trilingual, while every public event card comes only
// from the managed publication store.
Route::get('/events', function (Request $request) use ($eventsSeedProvider, $publicHttpUrl) {
    $events = $eventsSeedProvider();
    $events['items'] = [];

    try {
        if (Schema::hasTable('news') && Schema::hasColumn('news', 'type')) {
            $locale = in_array(app()->getLocale(), ['kk', 'ru', 'en'], true) ? app()->getLocale() : 'kk';
            $query = mb_substr(trim((string) $request->query('q', '')), 0, 120);
            $category = preg_replace('/[^a-z0-9_-]/', '', mb_strtolower((string) $request->query('category', '')));
            $now = now('UTC');

            $events['items'] = News::query()
                ->published()
                ->when(Schema::hasColumn('news', 'visibility'), fn ($builder) => $builder->where('visibility', 'public'))
                ->whereIn('type', ['event', 'schedule'])
                ->where(function ($builder) use ($now): void {
                    $builder
                        ->where('ends_at', '>=', $now)
                        ->orWhere(function ($withoutEnd) use ($now): void {
                            $withoutEnd->whereNull('ends_at')->where('starts_at', '>=', $now);
                        });
                })
                ->when($category !== '', fn ($builder) => $builder->whereHas('newsCategory', fn ($categoryQuery) => $categoryQuery->where('slug', $category)))
                ->when($query !== '', fn ($builder) => $builder->search($query))
                ->orderBy('starts_at')
                ->get()
                ->map(function (News $item) use ($locale, $publicHttpUrl): array {
                    $start = $item->starts_at ?? $item->published_at ?? now('UTC');
                    $venue = trim((string) $item->localized('venue', $locale));
                    $onlineUrl = $publicHttpUrl($item->online_url);

                    return ['slug' => $item->localizedSlug($locale), 'iso_date' => $start->toDateString(), 'featured' => (bool) $item->is_featured, 'category_slug' => $item->newsCategory?->slug ?? $item->type, 'i18n' => [$locale => ['category' => __('news.types.'.$item->type), 'date_month_day' => $start->translatedFormat('d F'), 'date_year_time' => $start->translatedFormat('Y · H:i'), 'title' => $item->localized('title', $locale), 'description' => $item->localized('excerpt', $locale), 'venue' => $venue !== '' ? $venue : ($onlineUrl !== null ? __('news.public.online') : null)]]];
                })
                ->all();
        }
    } catch (Throwable $exception) {
        report($exception);
        abort(503, 'Events are temporarily unavailable.');
    }

    return view('events.index', [
        'activePage' => 'events',
        'events' => $events,
    ]);
});

// Phase 3 Cluster C.2 — seeded public event detail payload keyed by slug.
//
// Extends the Cluster C.1 index seed with per-event rich content:
// subtitle, secondary category, date/time range, capacity, about body
// paragraphs, agenda rows, featured speaker, and preparatory materials.
// Structure mirrors $newsSeedProvider's per-article shape so a future
// backend phase can replace this closure with a DB-backed source.
$eventDetailProvider = static function (): array {
    $chrome = [
        'ru' => [
            'breadcrumb_discovery' => 'Библиотека',
            'breadcrumb_events' => 'События и лекции',
            'back_to_events' => 'Вернуться к событиям',
            'section_about' => 'О событии',
            'section_agenda' => 'Программа',
            'section_speaker' => 'Спикер',
            'section_materials' => 'Материалы для подготовки',
            'section_share' => 'Поделиться событием',
            'section_related' => 'Связанные события',
            'view_all_events' => 'Все события',
            'meta_datetime' => 'Дата и время',
            'meta_venue' => 'Место проведения',
            'meta_capacity' => 'Аудитория',
            'cta_register' => 'Записаться',
            'cta_save' => 'Сохранить',
            'view_map' => 'Показать на карте',
        ],
        'kk' => [
            'breadcrumb_discovery' => 'Кітапхана',
            'breadcrumb_events' => 'Іс-шаралар мен дәрістер',
            'back_to_events' => 'Іс-шараларға оралу',
            'section_about' => 'Іс-шара туралы',
            'section_agenda' => 'Бағдарлама',
            'section_speaker' => 'Спикер',
            'section_materials' => 'Дайындалуға арналған материалдар',
            'section_share' => 'Іс-шарамен бөлісу',
            'section_related' => 'Қатысты іс-шаралар',
            'view_all_events' => 'Барлық іс-шаралар',
            'meta_datetime' => 'Күні мен уақыты',
            'meta_venue' => 'Өткізілу орны',
            'meta_capacity' => 'Аудитория',
            'cta_register' => 'Тіркелу',
            'cta_save' => 'Сақтау',
            'view_map' => 'Картадан көру',
        ],
        'en' => [
            'breadcrumb_discovery' => 'Library',
            'breadcrumb_events' => 'Events & Lectures',
            'back_to_events' => 'Back to events',
            'section_about' => 'About the Event',
            'section_agenda' => 'Agenda',
            'section_speaker' => 'Featured Speaker',
            'section_materials' => 'Preparatory Materials',
            'section_share' => 'Share this event',
            'section_related' => 'Related Events',
            'view_all_events' => 'View all events',
            'meta_datetime' => 'Date & Time',
            'meta_venue' => 'Venue',
            'meta_capacity' => 'Audience',
            'cta_register' => 'Register Attendance',
            'cta_save' => 'Save',
            'view_map' => 'View Map',
        ],
    ];

    $details = [
        'digital-preservation-symposium-2026' => [
            'secondary_category_slug' => 'archival-science',
            'date_time_range' => '10:00 – 13:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Ответ академической библиотеки на вызовы долгосрочного сохранения цифровых научных материалов.',
                    'secondary_category' => 'Архивистика',
                    'date_time_range' => '10:00 – 13:30 (Астана)',
                    'capacity_label' => '120 мест',
                    'capacity_note' => 'Открыто для преподавателей и исследователей',
                    'about' => [
                        'Симпозиум объединяет научные библиотеки и исследовательские центры вокруг практических подходов к цифровому сохранению. Сессия посвящена тому, как академическая библиотека обеспечивает целостность и доступность цифровых научных материалов в долгосрочной перспективе.',
                        'Участники рассмотрят методологии миграции форматов, политики долгосрочного хранения, работу с метаданными и этические аспекты сохранения рожденного-цифровым контента — от институциональных репозиториев до массивов научных данных.',
                        'Также будут представлены текущие инициативы Казахский университет технологии и бизнеса имени К. Кулажанова по выстраиванию надежного цифрового хранилища, чтобы изменчивость цифровых носителей не приводила к потере научного наследия университета.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Приветственное слово', 'note' => 'Руководство библиотеки Казахский университет технологии и бизнеса имени К. Кулажанова'],
                        ['time' => '10:15', 'title' => 'Ключевой доклад: хрупкость цифрового', 'note' => 'Обзор текущего состояния цифрового сохранения в академических библиотеках и переход от пассивного хранения к активной кураторской работе.'],
                        ['time' => '11:30', 'title' => 'Кофе-брейк и обсуждение', 'note' => 'Главный читальный зал, корпус 1'],
                        ['time' => '11:45', 'title' => 'Панельная дискуссия и вопросы', 'note' => 'Модератор — руководитель научной библиотеки'],
                    ],
                    'speaker' => [
                        'name' => 'Профессор кафедры информационных наук',
                        'role' => 'Главный спикер · Академическая библиотечная сеть',
                        'bio' => 'Ведущий специалист по методологиям цифрового архивирования и автор научных работ о долгосрочном сохранении электронных ресурсов в академических библиотеках.',
                    ],
                    'materials' => [
                        ['title' => 'Вводные тезисы: цифровое сохранение в академической среде', 'meta' => 'PDF · 2.4 МБ'],
                        ['title' => 'Политики долгосрочного хранения: сравнительный обзор', 'meta' => 'PDF · 3.1 МБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Академиялық кітапхананың цифрлық ғылыми материалдарды ұзақ мерзімде сақтау сын-қатерлеріне жауабы.',
                    'secondary_category' => 'Архивтану',
                    'date_time_range' => '10:00 – 13:30 (Астана)',
                    'capacity_label' => '120 орын',
                    'capacity_note' => 'Оқытушылар мен зерттеушілерге ашық',
                    'about' => [
                        'Симпозиум ғылыми кітапханалар мен зерттеу орталықтарын цифрлық сақтаудың тәжірибелік тәсілдері төңірегінде біріктіреді. Сессия академиялық кітапхананың цифрлық ғылыми материалдардың тұтастығы мен қолжетімділігін ұзақ мерзімде қалай қамтамасыз ететініне арналған.',
                        'Қатысушылар форматтарды көшіру әдіснамасын, ұзақ мерзімді сақтау саясатын, метадеректермен жұмысты және цифрлық мазмұнды сақтаудың этикалық аспектілерін — институционалдық репозиторийлерден бастап ғылыми деректер массивтеріне дейін — қарастырады.',
                        'Сондай-ақ Қ. Құлажанов атындағы Қазақ технология және бизнес университеті-дің сенімді цифрлық қоймасын құру жөніндегі ағымдағы бастамалары ұсынылады, бұл цифрлық тасымалдағыштардың өзгермелілігі университеттің ғылыми мұрасын жоғалтуға әкелмеуі үшін жасалады.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Сәлемдеу сөзі', 'note' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті кітапханасының басшылығы'],
                        ['time' => '10:15', 'title' => 'Негізгі баяндама: цифрлықтың сынғыштығы', 'note' => 'Академиялық кітапханалардағы цифрлық сақтаудың қазіргі жағдайына шолу және пассивті сақтаудан белсенді кураторлық жұмысқа өту.'],
                        ['time' => '11:30', 'title' => 'Кофе-брейк және талқылау', 'note' => 'Басты оқу залы, 1-корпус'],
                        ['time' => '11:45', 'title' => 'Панельдік талқылау және сұрақтар', 'note' => 'Модератор — ғылыми кітапхана басшысы'],
                    ],
                    'speaker' => [
                        'name' => 'Ақпараттық ғылымдар кафедрасының профессоры',
                        'role' => 'Басты спикер · Академиялық кітапхана желісі',
                        'bio' => 'Цифрлық мұрағаттау әдіснамасы саласындағы жетекші маман, академиялық кітапханаларда электрондық ресурстарды ұзақ мерзімді сақтау туралы ғылыми жұмыстардың авторы.',
                    ],
                    'materials' => [
                        ['title' => 'Кіріспе тезистер: академиялық ортадағы цифрлық сақтау', 'meta' => 'PDF · 2.4 МБ'],
                        ['title' => 'Ұзақ мерзімді сақтау саясаты: салыстырмалы шолу', 'meta' => 'PDF · 3.1 МБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'An academic library response to the long-term preservation of born-digital scholarly materials.',
                    'secondary_category' => 'Archival Science',
                    'date_time_range' => '10:00 – 13:30 (Astana)',
                    'capacity_label' => '120 seats',
                    'capacity_note' => 'Open to faculty and researchers',
                    'about' => [
                        'This symposium brings research libraries and scholarly centres together around practical approaches to digital preservation. The session focuses on how an academic library maintains the integrity and accessibility of born-digital scholarly materials over the long term.',
                        'Participants will examine format migration methodologies, long-term retention policy, metadata workflows, and the ethical considerations of preserving born-digital content — from institutional repositories to research data sets.',
                        'The Kazakh University of Technology and Business named after K. Kulazhanov will also present its ongoing initiatives to build a robust digital vault, so that the volatility of digital carriers does not lead to the loss of the university\'s scholarly record.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Opening remarks', 'note' => 'Kazakh University of Technology and Business named after K. Kulazhanov Library leadership'],
                        ['time' => '10:15', 'title' => 'Keynote: The fragility of the digital', 'note' => 'An overview of the current state of digital preservation in academic libraries and the shift from passive storage to active curation.'],
                        ['time' => '11:30', 'title' => 'Coffee break & networking', 'note' => 'Main Reading Room, Building 1'],
                        ['time' => '11:45', 'title' => 'Panel discussion & Q&A', 'note' => 'Moderated by the Head of Research Library'],
                    ],
                    'speaker' => [
                        'name' => 'Professor, Department of Information Science',
                        'role' => 'Featured speaker · Academic Library Network',
                        'bio' => 'A leading specialist in digital archiving methodologies and author of scholarly work on the long-term preservation of electronic resources in academic libraries.',
                    ],
                    'materials' => [
                        ['title' => 'Opening brief: digital preservation in the academic setting', 'meta' => 'PDF · 2.4 MB'],
                        ['title' => 'Long-term retention policy: a comparative overview', 'meta' => 'PDF · 3.1 MB'],
                    ],
                ],
            ],
        ],
        'open-access-publishing-seminar-2026' => [
            'secondary_category_slug' => 'publishing',
            'date_time_range' => '14:00 – 16:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Ориентирование в академическом публикационном ландшафте для магистрантов и докторантов.',
                    'secondary_category' => 'Академическая публикация',
                    'date_time_range' => '14:00 – 16:00 (Астана)',
                    'capacity_label' => '40 мест',
                    'capacity_note' => 'Приоритет — магистрантам и докторантам',
                    'about' => [
                        'Практический семинар, посвященный ориентированию в современном академическом публикационном ландшафте. Основное внимание уделяется работе с журналами открытого доступа и оценке их научной репутации.',
                        'Рассматриваются выбор журналов под профиль исследования, идентификация хищнических изданий, требования Open Access к рукописям и правильное оформление авторских прав и лицензий.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Вводная часть: академическая экосистема публикаций', 'note' => 'Ведущий библиотекарь-консультант'],
                        ['time' => '14:45', 'title' => 'Практикум: анализ журнала', 'note' => 'Работа в малых группах с подписными базами данных университета'],
                        ['time' => '15:30', 'title' => 'Вопросы и индивидуальные консультации', 'note' => 'Семинарский зал B, корпус 1'],
                    ],
                    'speaker' => [
                        'name' => 'Координатор научных коммуникаций',
                        'role' => 'Отдел научных коммуникаций · Казахский университет технологии и бизнеса имени К. Кулажанова',
                        'bio' => 'Курирует работу с подписными базами данных, поддержку авторов университета и политику публикационной этики.',
                    ],
                    'materials' => [
                        ['title' => 'Чек-лист: выбор журнала', 'meta' => 'PDF · 420 КБ'],
                        ['title' => 'Руководство по оформлению лицензий Creative Commons', 'meta' => 'PDF · 680 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Магистранттар мен докторанттар үшін академиялық жариялау ландшафтында бағдарлау.',
                    'secondary_category' => 'Академиялық жариялау',
                    'date_time_range' => '14:00 – 16:00 (Астана)',
                    'capacity_label' => '40 орын',
                    'capacity_note' => 'Басымдық — магистранттар мен докторанттарға',
                    'about' => [
                        'Заманауи академиялық жариялау ландшафтында бағдарлауға арналған тәжірибелік семинар. Негізгі назар ашық қолжетімділік журналдарымен жұмысқа және олардың ғылыми беделін бағалауға аударылады.',
                        'Зерттеу бейініне сәйкес журналдарды таңдау, жыртқыш басылымдарды анықтау, қолжазбаларға Open Access талаптары мен авторлық құқық пен лицензияларды дұрыс рәсімдеу қарастырылады.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Кіріспе: академиялық жариялау экожүйесі', 'note' => 'Жетекші кітапханашы-кеңесші'],
                        ['time' => '14:45', 'title' => 'Практикум: журналды талдау', 'note' => 'Университеттің жазылымдық дерекқорларымен шағын топта жұмыс'],
                        ['time' => '15:30', 'title' => 'Сұрақтар және жеке кеңестер', 'note' => 'B семинар залы, 1-корпус'],
                    ],
                    'speaker' => [
                        'name' => 'Ғылыми коммуникациялар үйлестірушісі',
                        'role' => 'Ғылыми коммуникациялар бөлімі · Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
                        'bio' => 'Жазылымдық дерекқорлармен жұмысты, университет авторларына қолдауды және жариялау этикасы саясатын үйлестіреді.',
                    ],
                    'materials' => [
                        ['title' => 'Тексеру парағы: журналды таңдау', 'meta' => 'PDF · 420 КБ'],
                        ['title' => 'Creative Commons лицензияларын рәсімдеу бойынша нұсқаулық', 'meta' => 'PDF · 680 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'Navigating the academic publishing landscape for master\'s and doctoral candidates.',
                    'secondary_category' => 'Academic Publishing',
                    'date_time_range' => '14:00 – 16:00 (Astana)',
                    'capacity_label' => '40 seats',
                    'capacity_note' => 'Priority for master\'s and doctoral candidates',
                    'about' => [
                        'A practical seminar on orienting authors in the modern academic publishing landscape. The focus is on working with open-access journals and assessing their scholarly standing.',
                        'Topics include selecting journals aligned with the research profile, identifying predatory publishers, Open Access manuscript requirements, and the correct handling of copyright and licensing.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Opening: the academic publishing ecosystem', 'note' => 'Lead librarian consultant'],
                        ['time' => '14:45', 'title' => 'Workshop: analysing a journal', 'note' => 'Small-group work with the university\'s subscribed databases'],
                        ['time' => '15:30', 'title' => 'Questions and one-on-one consultations', 'note' => 'Seminar Hall B, Building 1'],
                    ],
                    'speaker' => [
                        'name' => 'Scholarly Communications Coordinator',
                        'role' => 'Scholarly Communications Office · Kazakh University of Technology and Business named after K. Kulazhanov',
                        'bio' => 'Leads work with subscribed databases, author support for the university, and publication ethics policy.',
                    ],
                    'materials' => [
                        ['title' => 'Checklist: choosing a journal', 'meta' => 'PDF · 420 KB'],
                        ['title' => 'Guide to Creative Commons licensing', 'meta' => 'PDF · 680 KB'],
                    ],
                ],
            ],
        ],
        'rare-collections-exhibit-2026' => [
            'secondary_category_slug' => 'heritage',
            'date_time_range' => 'All day',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Кураторская экспозиция редких изданий и отреставрированных научных материалов фонда Казахский университет технологии и бизнеса имени К. Кулажанова.',
                    'secondary_category' => 'Наследие',
                    'date_time_range' => 'Весь день · 10:00 – 17:00',
                    'capacity_label' => 'Без предварительной записи',
                    'capacity_note' => 'Открыто для всех читателей университета',
                    'about' => [
                        'Экспозиция объединяет редкие издания, ценные научные коллекции и отреставрированные материалы, которые формируют историческое ядро технологического фонда Казахский университет технологии и бизнеса имени К. Кулажанова.',
                        'Приоритетные направления: ранняя инженерная литература, учебные пособия по прикладным наукам и региональная история. Для каждого раздела предусмотрено отдельное кураторское сопровождение.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Открытие экспозиции', 'note' => 'Зал 1/200, технологический фонд'],
                        ['time' => '12:00', 'title' => 'Кураторская экскурсия', 'note' => 'Ранняя инженерная литература'],
                        ['time' => '15:00', 'title' => 'Кураторская экскурсия', 'note' => 'Региональная история и научное наследие'],
                    ],
                    'speaker' => [
                        'name' => 'Куратор технологического фонда',
                        'role' => 'Отдел редких изданий · Казахский университет технологии и бизнеса имени К. Кулажанова',
                        'bio' => 'Сопровождает работу с редкими изданиями, реставрацию и описание научного наследия университета.',
                    ],
                    'materials' => [
                        ['title' => 'Каталог экспозиции', 'meta' => 'PDF · 5.8 МБ'],
                        ['title' => 'Описание ключевых коллекций', 'meta' => 'PDF · 1.9 МБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті қорындағы сирек басылымдар мен қалпына келтірілген ғылыми материалдардың кураторлық экспозициясы.',
                    'secondary_category' => 'Мұра',
                    'date_time_range' => 'Толық күн · 10:00 – 17:00',
                    'capacity_label' => 'Алдын ала жазылусыз',
                    'capacity_note' => 'Университеттің барлық оқырмандарына ашық',
                    'about' => [
                        'Экспозиция Қ. Құлажанов атындағы Қазақ технология және бизнес университеті технологиялық қорының тарихи өзегін құрайтын сирек басылымдарды, құнды ғылыми жинақтарды және қалпына келтірілген материалдарды біріктіреді.',
                        'Басым бағыттар: ерте инженерлік әдебиет, қолданбалы ғылымдар бойынша оқу құралдары және өңірлік тарих. Әр бөлімге бөлек кураторлық сүйемелдеу көзделген.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Экспозицияның ашылуы', 'note' => '1/200 залы, технологиялық қор'],
                        ['time' => '12:00', 'title' => 'Кураторлық экскурсия', 'note' => 'Ерте инженерлік әдебиет'],
                        ['time' => '15:00', 'title' => 'Кураторлық экскурсия', 'note' => 'Өңірлік тарих және ғылыми мұра'],
                    ],
                    'speaker' => [
                        'name' => 'Технологиялық қордың кураторы',
                        'role' => 'Сирек басылымдар бөлімі · Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
                        'bio' => 'Сирек басылымдармен жұмысты, қалпына келтіруді және университет ғылыми мұрасын сипаттауды сүйемелдейді.',
                    ],
                    'materials' => [
                        ['title' => 'Экспозиция каталогы', 'meta' => 'PDF · 5.8 МБ'],
                        ['title' => 'Негізгі жинақтардың сипаттамасы', 'meta' => 'PDF · 1.9 МБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A curated exhibition of rare editions and restored scholarly materials from the Kazakh University of Technology and Business named after K. Kulazhanov collections.',
                    'secondary_category' => 'Heritage',
                    'date_time_range' => 'All day · 10:00 – 17:00',
                    'capacity_label' => 'No prior registration',
                    'capacity_note' => 'Open to all university readers',
                    'about' => [
                        'The exhibition brings together rare editions, valuable scholarly collections, and restored materials that form the historical core of the Kazakh University of Technology and Business named after K. Kulazhanov Technology Fund.',
                        'Priority areas: early engineering literature, applied-science textbooks, and regional history. Each section has dedicated curatorial support.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Exhibition opening', 'note' => 'Room 1/200, Technology Fund'],
                        ['time' => '12:00', 'title' => 'Curator-led tour', 'note' => 'Early engineering literature'],
                        ['time' => '15:00', 'title' => 'Curator-led tour', 'note' => 'Regional history and scholarly heritage'],
                    ],
                    'speaker' => [
                        'name' => 'Curator, Technology Fund',
                        'role' => 'Rare Editions Department · Kazakh University of Technology and Business named after K. Kulazhanov',
                        'bio' => 'Supports work with rare editions, restoration, and descriptive cataloguing of the university\'s scholarly heritage.',
                    ],
                    'materials' => [
                        ['title' => 'Exhibition catalogue', 'meta' => 'PDF · 5.8 MB'],
                        ['title' => 'Key collections: descriptive notes', 'meta' => 'PDF · 1.9 MB'],
                    ],
                ],
            ],
        ],
        'research-workshop-thesis-citations-2026' => [
            'secondary_category_slug' => 'research-skills',
            'date_time_range' => '15:00 – 17:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Практикум по работе с источниками и подготовке библиографического списка для итоговых работ.',
                    'secondary_category' => 'Исследовательские навыки',
                    'date_time_range' => '15:00 – 17:00 (Астана)',
                    'capacity_label' => '30 мест',
                    'capacity_note' => 'Приоритет — студенты старших курсов и магистранты',
                    'about' => [
                        'Мастер-класс для студентов старших курсов и магистрантов Казахский университет технологии и бизнеса имени К. Кулажанова, которые готовят дипломные работы и диссертации. Фокус — на корректной работе с источниками и цитированием.',
                        'Участники научатся эффективно использовать подписные базы данных университета, различать первичные и вторичные источники, корректно оформлять ссылки и подготавливать итоговый библиографический список.',
                    ],
                    'agenda' => [
                        ['time' => '15:00', 'title' => 'Введение: источники в академической работе', 'note' => 'Куратор практикума'],
                        ['time' => '15:30', 'title' => 'Работа с подписными базами данных', 'note' => 'Совместный практикум в зале 1/202'],
                        ['time' => '16:30', 'title' => 'Оформление библиографического списка', 'note' => 'Разбор типовых ошибок и индивидуальные вопросы'],
                    ],
                    'speaker' => [
                        'name' => 'Библиотекарь-методист',
                        'role' => 'Фонд колледжа · Казахский университет технологии и бизнеса имени К. Кулажанова',
                        'bio' => 'Отвечает за информационную грамотность и поддержку студентов при подготовке итоговых работ.',
                    ],
                    'materials' => [
                        ['title' => 'Шаблон: структура библиографического списка', 'meta' => 'PDF · 540 КБ'],
                        ['title' => 'Правила цитирования и оформления ссылок', 'meta' => 'PDF · 720 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Қорытынды жұмыстар үшін дереккөздермен жұмыс істеу және библиографиялық тізім даярлау бойынша практикум.',
                    'secondary_category' => 'Зерттеу дағдылары',
                    'date_time_range' => '15:00 – 17:00 (Астана)',
                    'capacity_label' => '30 орын',
                    'capacity_note' => 'Басымдық — жоғары курс студенттері мен магистранттарға',
                    'about' => [
                        'Диплом жұмыстары мен диссертация дайындайтын Қ. Құлажанов атындағы Қазақ технология және бизнес университеті жоғары курс студенттері мен магистранттарына арналған мастер-класс. Негізгі назар — дереккөздермен және сілтемелермен дұрыс жұмыс істеуге.',
                        'Қатысушылар университеттің жазылымдық дерекқорларын тиімді пайдалануды, бастапқы және қосымша дереккөздерді ажыратуды, сілтемелерді дұрыс рәсімдеуді және қорытынды библиографиялық тізімді дайындауды үйренеді.',
                    ],
                    'agenda' => [
                        ['time' => '15:00', 'title' => 'Кіріспе: академиялық жұмыстағы дереккөздер', 'note' => 'Практикум кураторы'],
                        ['time' => '15:30', 'title' => 'Жазылымдық дерекқорлармен жұмыс', 'note' => '1/202 залындағы бірлескен практикум'],
                        ['time' => '16:30', 'title' => 'Библиографиялық тізімді рәсімдеу', 'note' => 'Типтік қателерді талдау және жеке сұрақтар'],
                    ],
                    'speaker' => [
                        'name' => 'Кітапханашы-әдіскер',
                        'role' => 'Колледж қоры · Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
                        'bio' => 'Ақпараттық сауаттылыққа және қорытынды жұмыстарды дайындау кезінде студенттерді қолдауға жауапты.',
                    ],
                    'materials' => [
                        ['title' => 'Үлгі: библиографиялық тізімнің құрылымы', 'meta' => 'PDF · 540 КБ'],
                        ['title' => 'Сілтемелерді рәсімдеу ережелері', 'meta' => 'PDF · 720 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A practicum on working with sources and preparing a bibliography for graduating projects.',
                    'secondary_category' => 'Research Skills',
                    'date_time_range' => '15:00 – 17:00 (Astana)',
                    'capacity_label' => '30 seats',
                    'capacity_note' => 'Priority for senior and master\'s candidates',
                    'about' => [
                        'A workshop for senior and master\'s candidates at Kazakh University of Technology and Business named after K. Kulazhanov preparing graduating projects and theses. The focus is on working correctly with sources and references.',
                        'Participants will learn how to use the university\'s subscribed databases effectively, distinguish primary from secondary sources, format references correctly, and assemble the final bibliography.',
                    ],
                    'agenda' => [
                        ['time' => '15:00', 'title' => 'Introduction: sources in academic work', 'note' => 'Workshop curator'],
                        ['time' => '15:30', 'title' => 'Working with subscribed databases', 'note' => 'Collaborative practicum in Room 1/202'],
                        ['time' => '16:30', 'title' => 'Preparing the bibliography', 'note' => 'Review of common errors and individual questions'],
                    ],
                    'speaker' => [
                        'name' => 'Reference and Instruction Librarian',
                        'role' => 'College Fund · Kazakh University of Technology and Business named after K. Kulazhanov',
                        'bio' => 'Responsible for information literacy and support for students preparing graduating projects.',
                    ],
                    'materials' => [
                        ['title' => 'Template: bibliography structure', 'meta' => 'PDF · 540 KB'],
                        ['title' => 'Citation and reference formatting guide', 'meta' => 'PDF · 720 KB'],
                    ],
                ],
            ],
        ],
        'doctoral-writing-clinic-2026' => [
            'secondary_category_slug' => 'writing-support',
            'date_time_range' => '11:00 – 13:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Консультационный формат для докторантов, готовящих статьи, обзоры литературы и ответы рецензентам.',
                    'secondary_category' => 'Поддержка академического письма',
                    'date_time_range' => '11:00 – 13:00 (Астана)',
                    'capacity_label' => '24 места',
                    'capacity_note' => 'Работа в малых группах и индивидуальные слоты',
                    'about' => [
                        'Клиника академического письма предназначена для докторантов, которые завершают рукописи статей и готовят материалы для подачи в рецензируемые журналы.',
                        'Участники получат рекомендации по структуре текста, связности аргументации, корректному использованию источников и стратегии ответа на замечания рецензентов.',
                    ],
                    'agenda' => [
                        ['time' => '11:00', 'title' => 'Диагностика рукописи', 'note' => 'Короткий аудит структуры и аргументации'],
                        ['time' => '11:40', 'title' => 'Сессия по стилю и цитированию', 'note' => 'Работа с примерами участников'],
                        ['time' => '12:20', 'title' => 'Ответы рецензентам', 'note' => 'Шаблоны и типовые сценарии'],
                    ],
                    'speaker' => [
                        'name' => 'Координатор центра академического письма',
                        'role' => 'Казахский университет технологии и бизнеса имени К. Кулажанова · Исследовательская поддержка',
                        'bio' => 'Консультирует авторов по подготовке рукописей, академическому стилю и публикационной стратегии.',
                    ],
                    'materials' => [
                        ['title' => 'Шаблон ответа рецензенту', 'meta' => 'DOCX · 190 КБ'],
                        ['title' => 'Памятка по структуре исследовательской статьи', 'meta' => 'PDF · 510 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Мақалалар, әдебиет шолулары және рецензенттерге жауаптар дайындайтын докторанттарға арналған кеңес беру форматы.',
                    'secondary_category' => 'Академиялық жазуды қолдау',
                    'date_time_range' => '11:00 – 13:00 (Астана)',
                    'capacity_label' => '24 орын',
                    'capacity_note' => 'Шағын топтар және жеке слоттар',
                    'about' => [
                        'Академиялық жазу клиникасы рецензияланатын журналдарға ұсынуға арналған мақала қолжазбаларын аяқтап жатқан докторанттарға арналады.',
                        'Қатысушылар мәтін құрылымы, аргументация байланыстылығы, дереккөздерді дұрыс пайдалану және рецензент ескертулеріне жауап беру стратегиясы бойынша ұсыныстар алады.',
                    ],
                    'agenda' => [
                        ['time' => '11:00', 'title' => 'Қолжазбаны диагностикалау', 'note' => 'Құрылым мен аргументацияға қысқа аудит'],
                        ['time' => '11:40', 'title' => 'Стиль және сілтеме сессиясы', 'note' => 'Қатысушылар мысалдарымен жұмыс'],
                        ['time' => '12:20', 'title' => 'Рецензенттерге жауаптар', 'note' => 'Үлгілер және типтік сценарийлер'],
                    ],
                    'speaker' => [
                        'name' => 'Академиялық жазу орталығының үйлестірушісі',
                        'role' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті · Зерттеу қолдауы',
                        'bio' => 'Авторларға қолжазба дайындау, академиялық стиль және жариялау стратегиясы бойынша кеңес береді.',
                    ],
                    'materials' => [
                        ['title' => 'Рецензентке жауап үлгісі', 'meta' => 'DOCX · 190 КБ'],
                        ['title' => 'Зерттеу мақаласының құрылымы бойынша жадынама', 'meta' => 'PDF · 510 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A consultation format for doctoral candidates preparing journal articles, literature reviews, and reviewer responses.',
                    'secondary_category' => 'Academic Writing Support',
                    'date_time_range' => '11:00 – 13:00 (Astana)',
                    'capacity_label' => '24 seats',
                    'capacity_note' => 'Small-group work and individual slots',
                    'about' => [
                        'The writing clinic is designed for doctoral candidates finalising article manuscripts and submission packages for peer-reviewed journals.',
                        'Participants receive guidance on text structure, argumentative coherence, correct source use, and strategies for answering reviewer comments.',
                    ],
                    'agenda' => [
                        ['time' => '11:00', 'title' => 'Manuscript diagnostic', 'note' => 'A short audit of structure and argument'],
                        ['time' => '11:40', 'title' => 'Style and citation session', 'note' => 'Working with participant examples'],
                        ['time' => '12:20', 'title' => 'Responding to reviewers', 'note' => 'Templates and common scenarios'],
                    ],
                    'speaker' => [
                        'name' => 'Coordinator, Academic Writing Centre',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Research Support',
                        'bio' => 'Advises authors on manuscript preparation, academic style, and publication strategy.',
                    ],
                    'materials' => [
                        ['title' => 'Reviewer response template', 'meta' => 'DOCX · 190 KB'],
                        ['title' => 'Research article structure memo', 'meta' => 'PDF · 510 KB'],
                    ],
                ],
            ],
        ],
        'data-literacy-bootcamp-2026' => [
            'secondary_category_slug' => 'data-skills',
            'date_time_range' => '09:30 – 16:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Однодневный интенсив по поиску, оценке и подготовке исследовательских данных для учебных и прикладных проектов.',
                    'secondary_category' => 'Дата-навыки',
                    'date_time_range' => '09:30 – 16:30 (Астана)',
                    'capacity_label' => '36 мест',
                    'capacity_note' => 'Открыто для студентов и преподавателей прикладных программ',
                    'about' => [
                        'Интенсив знакомит участников с основами дата-грамотности в академической среде: от поиска надежных наборов данных до их базовой очистки и описания.',
                        'Программа построена вокруг практических упражнений и демонстрирует, как библиотечные сервисы поддерживают учебные проекты, дипломные работы и прикладные исследования.',
                    ],
                    'agenda' => [
                        ['time' => '09:30', 'title' => 'Навигация по открытым и подписным данным', 'note' => 'Каталоги, репозитории, документация'],
                        ['time' => '11:30', 'title' => 'Оценка качества источника', 'note' => 'Происхождение, лицензии, методология'],
                        ['time' => '14:00', 'title' => 'Мини-практикум по подготовке данных', 'note' => 'Форматы, таблицы, описание переменных'],
                    ],
                    'speaker' => [
                        'name' => 'Специалист по цифровым исследовательским сервисам',
                        'role' => 'Цифровая лаборатория · Казахский университет технологии и бизнеса имени К. Кулажанова',
                        'bio' => 'Сопровождает учебные проекты по работе с данными и исследовательскими цифровыми инструментами.',
                    ],
                    'materials' => [
                        ['title' => 'Навигатор по открытым наборам данных', 'meta' => 'PDF · 1.1 МБ'],
                        ['title' => 'Чек-лист оценки качества данных', 'meta' => 'PDF · 430 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Оқу және қолданбалы жобалар үшін зерттеу деректерін іздеу, бағалау және дайындау бойынша бір күндік интенсив.',
                    'secondary_category' => 'Дата-дағдылар',
                    'date_time_range' => '09:30 – 16:30 (Астана)',
                    'capacity_label' => '36 орын',
                    'capacity_note' => 'Қолданбалы бағдарламалар студенттері мен оқытушыларына ашық',
                    'about' => [
                        'Интенсив қатысушыларды академиялық ортадағы дата-сауаттылық негіздерімен таныстырады: сенімді деректер жиынтықтарын іздеуден бастап оларды бастапқы тазалау мен сипаттауға дейін.',
                        'Бағдарлама практикалық жаттығуларға құрылған және кітапхана сервистерінің оқу жобаларын, диплом жұмыстарын және қолданбалы зерттеулерді қалай қолдайтынын көрсетеді.',
                    ],
                    'agenda' => [
                        ['time' => '09:30', 'title' => 'Ашық және жазылымдық деректер бойынша навигация', 'note' => 'Каталогтар, репозиторийлер, құжаттама'],
                        ['time' => '11:30', 'title' => 'Дереккөз сапасын бағалау', 'note' => 'Шығу тегі, лицензиялар, әдіснама'],
                        ['time' => '14:00', 'title' => 'Деректерді дайындау бойынша мини-практикум', 'note' => 'Форматтар, кестелер, айнымалылар сипаттамасы'],
                    ],
                    'speaker' => [
                        'name' => 'Цифрлық зерттеу сервистері бойынша маман',
                        'role' => 'Цифрлық зертхана · Қ. Құлажанов атындағы Қазақ технология және бизнес университеті',
                        'bio' => 'Деректермен және зерттеу цифрлық құралдарымен жұмыс істеуге арналған оқу жобаларын сүйемелдейді.',
                    ],
                    'materials' => [
                        ['title' => 'Ашық деректер жиынтықтары бойынша навигатор', 'meta' => 'PDF · 1.1 МБ'],
                        ['title' => 'Деректер сапасын бағалау чек-парағы', 'meta' => 'PDF · 430 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A one-day intensive on locating, evaluating, and preparing research data for coursework and applied projects.',
                    'secondary_category' => 'Data Skills',
                    'date_time_range' => '09:30 – 16:30 (Astana)',
                    'capacity_label' => '36 seats',
                    'capacity_note' => 'Open to students and faculty in applied programmes',
                    'about' => [
                        'This bootcamp introduces participants to the fundamentals of data literacy in the academic setting, from locating trustworthy datasets to basic cleaning and description.',
                        'The programme is built around practical exercises and shows how library services support coursework, final projects, and applied research.',
                    ],
                    'agenda' => [
                        ['time' => '09:30', 'title' => 'Navigating open and subscribed data', 'note' => 'Catalogs, repositories, and documentation'],
                        ['time' => '11:30', 'title' => 'Evaluating source quality', 'note' => 'Provenance, licensing, and methodology'],
                        ['time' => '14:00', 'title' => 'Mini practicum on data preparation', 'note' => 'Formats, tables, and variable description'],
                    ],
                    'speaker' => [
                        'name' => 'Digital Research Services Specialist',
                        'role' => 'Digital Lab · Kazakh University of Technology and Business named after K. Kulazhanov',
                        'bio' => 'Supports coursework that relies on data handling and research-oriented digital tools.',
                    ],
                    'materials' => [
                        ['title' => 'Open dataset navigator', 'meta' => 'PDF · 1.1 MB'],
                        ['title' => 'Data quality checklist', 'meta' => 'PDF · 430 KB'],
                    ],
                ],
            ],
        ],
        'heritage-metadata-roundtable-2026' => [
            'secondary_category_slug' => 'metadata-governance',
            'date_time_range' => '16:00 – 18:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Обсуждение описательных стандартов и устойчивых идентификаторов для редких коллекций и архивов.',
                    'secondary_category' => 'Управление метаданными',
                    'date_time_range' => '16:00 – 18:00 (Астана)',
                    'capacity_label' => '50 мест',
                    'capacity_note' => 'Для библиотекарей, архивистов и преподавателей профильных кафедр',
                    'about' => [
                        'Круглый стол объединяет специалистов по библиотечному описанию, цифровым коллекциям и архивному управлению вокруг общих требований к метаданным культурного наследия.',
                        'Фокус — на совместимости стандартов, межинституциональном обмене записями и роли устойчивых идентификаторов при интеграции коллекций в открытые исследовательские среды.',
                    ],
                    'agenda' => [
                        ['time' => '16:00', 'title' => 'Постановка задачи', 'note' => 'Метаданные для редких и гибридных коллекций'],
                        ['time' => '16:40', 'title' => 'Институциональные кейсы', 'note' => 'Практики обмена и нормализации записей'],
                        ['time' => '17:20', 'title' => 'Открытая дискуссия', 'note' => 'Приоритеты для совместной работы на 2026–2027 годы'],
                    ],
                    'speaker' => [
                        'name' => 'Руководитель направления цифровых коллекций',
                        'role' => 'Казахский университет технологии и бизнеса имени К. Кулажанова · Метаданные и интеграция',
                        'bio' => 'Координирует описание редких коллекций и интеграцию библиотечных записей в цифровые сервисы университета.',
                    ],
                    'materials' => [
                        ['title' => 'Краткий обзор метаданных культурного наследия', 'meta' => 'PDF · 860 КБ'],
                        ['title' => 'Матрица сопоставления идентификаторов', 'meta' => 'XLSX · 280 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Сирек қорлар мен мұрағаттарға арналған сипаттау стандарттары мен тұрақты идентификаторларды талқылау.',
                    'secondary_category' => 'Метадеректерді басқару',
                    'date_time_range' => '16:00 – 18:00 (Астана)',
                    'capacity_label' => '50 орын',
                    'capacity_note' => 'Кітапханашыларға, архивистерге және профильдік кафедра оқытушыларына арналған',
                    'about' => [
                        'Дөңгелек үстел кітапханалық сипаттау, цифрлық жинақтар және архивтік басқару мамандарын мәдени мұра метадеректеріне қойылатын ортақ талаптар төңірегінде біріктіреді.',
                        'Негізгі назар стандарттардың үйлесімділігіне, институтаралық жазба алмасуға және жинақтарды ашық зерттеу орталарына біріктіру кезіндегі тұрақты идентификаторлардың рөліне аударылады.',
                    ],
                    'agenda' => [
                        ['time' => '16:00', 'title' => 'Мәселені қою', 'note' => 'Сирек және гибридті жинақтарға арналған метадеректер'],
                        ['time' => '16:40', 'title' => 'Институционалдық кейстер', 'note' => 'Жазбаларды алмасу және нормализациялау практикасы'],
                        ['time' => '17:20', 'title' => 'Ашық талқылау', 'note' => '2026–2027 жылдарға бірлескен жұмыс басымдықтары'],
                    ],
                    'speaker' => [
                        'name' => 'Цифрлық жинақтар бағытының жетекшісі',
                        'role' => 'Қ. Құлажанов атындағы Қазақ технология және бизнес университеті · Метадеректер және интеграция',
                        'bio' => 'Сирек жинақтарды сипаттауды және кітапхана жазбаларын университеттің цифрлық сервистеріне біріктіруді үйлестіреді.',
                    ],
                    'materials' => [
                        ['title' => 'Мәдени мұра метадеректеріне қысқаша шолу', 'meta' => 'PDF · 860 КБ'],
                        ['title' => 'Идентификаторларды салыстыру матрицасы', 'meta' => 'XLSX · 280 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A discussion on descriptive standards and persistent identifiers for rare collections and archives.',
                    'secondary_category' => 'Metadata Governance',
                    'date_time_range' => '16:00 – 18:00 (Astana)',
                    'capacity_label' => '50 seats',
                    'capacity_note' => 'For librarians, archivists, and faculty in relevant departments',
                    'about' => [
                        'This roundtable brings together specialists in cataloguing, digital collections, and archival management around shared requirements for heritage metadata.',
                        'The focus is on standards interoperability, inter-institutional record exchange, and the role of persistent identifiers when integrating collections into open research environments.',
                    ],
                    'agenda' => [
                        ['time' => '16:00', 'title' => 'Framing the issue', 'note' => 'Metadata for rare and hybrid collections'],
                        ['time' => '16:40', 'title' => 'Institutional cases', 'note' => 'Practices for exchange and record normalisation'],
                        ['time' => '17:20', 'title' => 'Open discussion', 'note' => 'Priorities for collaboration in 2026–2027'],
                    ],
                    'speaker' => [
                        'name' => 'Head of Digital Collections',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Metadata & Integration',
                        'bio' => 'Coordinates rare-collection description and the integration of library records into the university’s digital services.',
                    ],
                    'materials' => [
                        ['title' => 'Briefing on heritage metadata', 'meta' => 'PDF · 860 KB'],
                        ['title' => 'Identifier crosswalk matrix', 'meta' => 'XLSX · 280 KB'],
                    ],
                ],
            ],
        ],
        'freshers-library-orientation-2026' => [
            'secondary_category_slug' => 'student-onboarding',
            'date_time_range' => '13:00 – 14:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Открытая вводная встреча по цифровым сервисам, правилам и академическим маршрутам библиотеки для новых студентов.',
                    'secondary_category' => 'Адаптация студентов',
                    'date_time_range' => '13:00 – 14:30 (Астана)',
                    'capacity_label' => '180 мест',
                    'capacity_note' => 'Открыто для первокурсников и кураторов академических групп',
                    'about' => [
                        'Ориентационная сессия знакомит новых студентов с читательским кабинетом, цифровой библиотекой, правилами пользования фондом и маршрутами получения академической поддержки.',
                        'Сессия также показывает, где искать учебную литературу, как пользоваться тематическими фондами и к кому обращаться по техническим или исследовательским вопросам.',
                    ],
                    'agenda' => [
                        ['time' => '13:00', 'title' => 'Быстрый обзор сервисов', 'note' => 'Кабинет читателя, каталог, цифровые ресурсы'],
                        ['time' => '13:30', 'title' => 'Навигация по фондам и залам', 'note' => '1/200, 1/202, 1/203 и режимы доступа'],
                        ['time' => '14:00', 'title' => 'Q&A и регистрация читателей', 'note' => 'Ответы на вопросы и помощь на месте'],
                    ],
                    'speaker' => [
                        'name' => 'Координатор читательских сервисов',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Public Services',
                        'bio' => 'Отвечает за публичные сервисы библиотеки, маршруты адаптации студентов и поддержку первого обращения.',
                    ],
                    'materials' => [
                        ['title' => 'Памятка первокурсника: библиотечные сервисы', 'meta' => 'PDF · 350 КБ'],
                        ['title' => 'Карта залов и фондов', 'meta' => 'PDF · 540 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Жаңа студенттерге арналған кітапхананың цифрлық сервистері, ережелері және академиялық маршруттары бойынша ашық кіріспе кездесу.',
                    'secondary_category' => 'Студенттерді бейімдеу',
                    'date_time_range' => '13:00 – 14:30 (Астана)',
                    'capacity_label' => '180 орын',
                    'capacity_note' => 'Бірінші курс студенттері мен академиялық топ кураторларына ашық',
                    'about' => [
                        'Бейімдеу сессиясы жаңа студенттерді оқырман кабинетімен, цифрлық кітапханамен, қорды пайдалану ережелерімен және академиялық қолдау маршруттарымен таныстырады.',
                        'Сессия оқу әдебиетін қайдан іздеу, тақырыптық қорларды қалай пайдалану және техникалық не зерттеу сұрақтары бойынша кімге жүгіну керегін көрсетеді.',
                    ],
                    'agenda' => [
                        ['time' => '13:00', 'title' => 'Сервистерге жылдам шолу', 'note' => 'Оқырман кабинеті, каталог, цифрлық ресурстар'],
                        ['time' => '13:30', 'title' => 'Қорлар мен залдар бойынша навигация', 'note' => '1/200, 1/202, 1/203 және қолжетімділік режимдері'],
                        ['time' => '14:00', 'title' => 'Q&A және оқырманды тіркеу', 'note' => 'Сұрақтарға жауап және орнында көмек'],
                    ],
                    'speaker' => [
                        'name' => 'Оқырман сервистерінің үйлестірушісі',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Public Services',
                        'bio' => 'Кітапхананың жария сервистеріне, студенттерді бейімдеу маршруттарына және алғашқы сұрауларды қолдауға жауап береді.',
                    ],
                    'materials' => [
                        ['title' => 'Бірінші курс студентіне арналған жадынама: кітапхана сервистері', 'meta' => 'PDF · 350 КБ'],
                        ['title' => 'Залдар мен қорлар картасы', 'meta' => 'PDF · 540 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'An open introduction to library digital services, rules, and academic support routes for incoming students.',
                    'secondary_category' => 'Student Onboarding',
                    'date_time_range' => '13:00 – 14:30 (Astana)',
                    'capacity_label' => '180 seats',
                    'capacity_note' => 'Open to first-year students and academic group advisers',
                    'about' => [
                        'This orientation introduces incoming students to the reader dashboard, the digital library, collection rules, and routes for academic support.',
                        'It also shows where to find course literature, how to use the themed funds, and whom to contact for technical or research-related questions.',
                    ],
                    'agenda' => [
                        ['time' => '13:00', 'title' => 'Quick service overview', 'note' => 'Reader dashboard, catalog, and digital resources'],
                        ['time' => '13:30', 'title' => 'Navigating funds and rooms', 'note' => '1/200, 1/202, 1/203 and access modes'],
                        ['time' => '14:00', 'title' => 'Q&A and reader registration', 'note' => 'On-site support and questions'],
                    ],
                    'speaker' => [
                        'name' => 'Reader Services Coordinator',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Public Services',
                        'bio' => 'Leads public-facing library services, student onboarding routes, and first-contact support.',
                    ],
                    'materials' => [
                        ['title' => 'Freshers guide to library services', 'meta' => 'PDF · 350 KB'],
                        ['title' => 'Map of rooms and funds', 'meta' => 'PDF · 540 KB'],
                    ],
                ],
            ],
        ],
        'research-data-workshop-2026' => [
            'secondary_category_slug' => 'data-skills',
            'date_time_range' => '10:30 – 12:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Практический воркшоп по сбору, очистке и описанию исследовательских данных для учебных и магистерских проектов.',
                    'secondary_category' => 'Data Skills',
                    'date_time_range' => '10:30 – 12:00 (Астана)',
                    'capacity_label' => '40 мест',
                    'capacity_note' => 'Для студентов и исследовательских групп с собственными наборами данных',
                    'about' => [
                        'Участники разберут простой рабочий поток: как структурировать таблицу, выбрать поля описания и подготовить данные к повторному использованию.',
                        'Отдельный блок будет посвящён тому, как связывать данные с источниками в каталоге библиотеки и научном репозитории.',
                    ],
                    'agenda' => [
                        ['time' => '10:30', 'title' => 'Подготовка данных', 'note' => 'Структура таблиц и проверка качества'],
                        ['time' => '11:00', 'title' => 'Описательные поля', 'note' => 'Минимальный набор метаданных для набора данных'],
                        ['time' => '11:30', 'title' => 'Практика и вопросы', 'note' => 'Разбор реального учебного кейса'],
                    ],
                    'speaker' => [
                        'name' => 'Специалист по цифровым данным',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Research Support',
                        'bio' => 'Помогает исследовательским группам упорядочивать данные, описывать их и готовить к публикации.',
                    ],
                    'materials' => [
                        ['title' => 'Шаблон описания набора данных', 'meta' => 'DOCX · 120 КБ'],
                        ['title' => 'Пример структуры исследовательской таблицы', 'meta' => 'XLSX · 96 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Оқу және магистрлік жобаларға арналған зерттеу деректерін жинау, тазалау және сипаттау бойынша практикалық воркшоп.',
                    'secondary_category' => 'Data Skills',
                    'date_time_range' => '10:30 – 12:00 (Астана)',
                    'capacity_label' => '40 орын',
                    'capacity_note' => 'Өз деректер жиынтығы бар студенттер мен зерттеу топтарына арналған',
                    'about' => [
                        'Қатысушылар қарапайым жұмыс ағынын талдайды: кестені қалай құрылымдау керек, сипаттама өрістерін қалай таңдау керек және деректерді қайта пайдалануға қалай дайындау керек.',
                        'Қосымша блок деректерді кітапхана каталогы мен ғылыми репозиториядағы дереккөздермен қалай байланыстыру керегіне арналған.',
                    ],
                    'agenda' => [
                        ['time' => '10:30', 'title' => 'Деректерді дайындау', 'note' => 'Кесте құрылымы және сапаны тексеру'],
                        ['time' => '11:00', 'title' => 'Сипаттамалық өрістер', 'note' => 'Деректер жиыны үшін минималды метадеректер'],
                        ['time' => '11:30', 'title' => 'Тәжірибе және сұрақтар', 'note' => 'Нақты оқу кейсін талдау'],
                    ],
                    'speaker' => [
                        'name' => 'Цифрлық деректер бойынша маман',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Research Support',
                        'bio' => 'Зерттеу топтарына деректерді реттеуге, сипаттауға және жариялауға дайындауға көмектеседі.',
                    ],
                    'materials' => [
                        ['title' => 'Деректер жиынын сипаттау үлгісі', 'meta' => 'DOCX · 120 КБ'],
                        ['title' => 'Зерттеу кестесінің құрылым үлгісі', 'meta' => 'XLSX · 96 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A practical workshop on collecting, cleaning, and describing research data for coursework and master’s projects.',
                    'secondary_category' => 'Data Skills',
                    'date_time_range' => '10:30 – 12:00 (Astana)',
                    'capacity_label' => '40 seats',
                    'capacity_note' => 'For students and research groups bringing their own datasets',
                    'about' => [
                        'Participants will walk through a simple workflow: how to structure a table, choose descriptive fields, and prepare data for reuse.',
                        'A separate block will show how to connect datasets with sources in the library catalog and scholarly repository.',
                    ],
                    'agenda' => [
                        ['time' => '10:30', 'title' => 'Preparing the data', 'note' => 'Table structure and quality checks'],
                        ['time' => '11:00', 'title' => 'Descriptive fields', 'note' => 'Minimum metadata for a dataset'],
                        ['time' => '11:30', 'title' => 'Practice and questions', 'note' => 'Walking through a real course case'],
                    ],
                    'speaker' => [
                        'name' => 'Digital Data Specialist',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Research Support',
                        'bio' => 'Helps research groups organise, describe, and prepare data for publication.',
                    ],
                    'materials' => [
                        ['title' => 'Dataset description template', 'meta' => 'DOCX · 120 KB'],
                        ['title' => 'Research table structure sample', 'meta' => 'XLSX · 96 KB'],
                    ],
                ],
            ],
        ],
        'scholarly-communication-seminar-2026' => [
            'secondary_category_slug' => 'publishing',
            'date_time_range' => '14:00 – 15:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Семинар о том, как упаковать научную работу, выбрать журнал и подготовить аннотацию без лишней бюрократии.',
                    'secondary_category' => 'Academic Publishing',
                    'date_time_range' => '14:00 – 15:30 (Астана)',
                    'capacity_label' => '60 мест',
                    'capacity_note' => 'Для магистрантов, докторантов и преподавателей',
                    'about' => [
                        'Семинар помогает авторам выстроить понятный путь от черновика статьи до отправки в журнал и ответа редакции.',
                        'Участники увидят, какие элементы публикационной подготовки особенно важны для университетских и международных изданий.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Выбор журнала', 'note' => 'Фокус, индексирование и требования'],
                        ['time' => '14:30', 'title' => 'Аннотация и структура', 'note' => 'Как сделать материал читаемым'],
                        ['time' => '15:00', 'title' => 'Работа с редакцией', 'note' => 'Ответы на замечания и корректуры'],
                    ],
                    'speaker' => [
                        'name' => 'Консультант по научным публикациям',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Publishing Support',
                        'bio' => 'Сопровождает авторов в вопросах публикаций, журналов и требований к оформлению статей.',
                    ],
                    'materials' => [
                        ['title' => 'Чеклист подготовки статьи', 'meta' => 'PDF · 210 КБ'],
                        ['title' => 'Шаблон аннотации и ключевых слов', 'meta' => 'DOCX · 84 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Ғылыми жұмысты қалай дайындау, журнал таңдау және артық бюрократиясыз аннотация әзірлеу туралы семинар.',
                    'secondary_category' => 'Academic Publishing',
                    'date_time_range' => '14:00 – 15:30 (Астана)',
                    'capacity_label' => '60 орын',
                    'capacity_note' => 'Магистранттар, докторанттар және оқытушылар үшін',
                    'about' => [
                        'Семинар авторларға мақаланың қара нұсқасынан журналға жіберуге дейінгі айқын жол құруға көмектеседі.',
                        'Қатысушылар университеттік және халықаралық басылымдар үшін жарияланымды дайындаудың қай элементтері әсіресе маңызды екенін көреді.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Журнал таңдау', 'note' => 'Фокус, индекстелу және талаптар'],
                        ['time' => '14:30', 'title' => 'Аннотация және құрылым', 'note' => 'Материалды оқылымды ету жолы'],
                        ['time' => '15:00', 'title' => 'Редакциямен жұмыс', 'note' => 'Түзетулер мен ескертпелерге жауап беру'],
                    ],
                    'speaker' => [
                        'name' => 'Ғылыми жарияланымдар жөніндегі кеңесші',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Publishing Support',
                        'bio' => 'Авторларды жарияланым, журнал таңдау және мақала рәсімдеу талаптары бойынша сүйемелдейді.',
                    ],
                    'materials' => [
                        ['title' => 'Мақаланы дайындау чек-парағы', 'meta' => 'PDF · 210 КБ'],
                        ['title' => 'Аннотация мен кілт сөздер үлгісі', 'meta' => 'DOCX · 84 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A seminar on shaping a paper, choosing a journal, and preparing an abstract without unnecessary bureaucracy.',
                    'secondary_category' => 'Academic Publishing',
                    'date_time_range' => '14:00 – 15:30 (Astana)',
                    'capacity_label' => '60 seats',
                    'capacity_note' => 'For master’s candidates, doctoral candidates, and faculty',
                    'about' => [
                        'The seminar helps authors build a clear path from a manuscript draft to journal submission and editorial response.',
                        'Participants will see which parts of publication preparation matter most for university and international journals.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Choosing a journal', 'note' => 'Scope, indexing, and requirements'],
                        ['time' => '14:30', 'title' => 'Abstract and structure', 'note' => 'Making the paper readable'],
                        ['time' => '15:00', 'title' => 'Working with editors', 'note' => 'Responding to comments and revisions'],
                    ],
                    'speaker' => [
                        'name' => 'Scholarly Publishing Consultant',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Publishing Support',
                        'bio' => 'Supports authors on publishing routes, journals, and article formatting requirements.',
                    ],
                    'materials' => [
                        ['title' => 'Paper preparation checklist', 'meta' => 'PDF · 210 KB'],
                        ['title' => 'Abstract and keywords template', 'meta' => 'DOCX · 84 KB'],
                    ],
                ],
            ],
        ],
        'new-semester-library-orientation-2026' => [
            'secondary_category_slug' => 'student-onboarding',
            'date_time_range' => '13:30 – 15:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Повторная вводная встреча для студентов о сервисах, поиске книг и быстрых действиях с подборками в каталоге.',
                    'secondary_category' => 'Student Onboarding',
                    'date_time_range' => '13:30 – 15:00 (Астана)',
                    'capacity_label' => '150 мест',
                    'capacity_note' => 'Для всех студентов, кто хочет быстро освежить работу с библиотекой',
                    'about' => [
                        'Команда покажет, как искать книги, сохранять их в подборку и переходить к связанным материалам без лишних шагов.',
                        'Встреча также поможет тем, кто впервые подключается к цифровым сервисам библиотеки в новом семестре.',
                    ],
                    'agenda' => [
                        ['time' => '13:30', 'title' => 'Навигация по сервисам', 'note' => 'Каталог, репозиторий и электронные ресурсы'],
                        ['time' => '14:00', 'title' => 'Подборки и быстрые действия', 'note' => 'Сохранение, избранное и обновления'],
                        ['time' => '14:30', 'title' => 'Вопросы и помощь', 'note' => 'Куда обратиться за поддержкой'],
                    ],
                    'speaker' => [
                        'name' => 'Координатор цифровых сервисов',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Public Services',
                        'bio' => 'Показывает пользователям быстрые сценарии работы с каталогом, избранным и читательским кабинетом.',
                    ],
                    'materials' => [
                        ['title' => 'Быстрый старт: каталог и подборки', 'meta' => 'PDF · 310 КБ'],
                        ['title' => 'Памятка по цифровым сервисам', 'meta' => 'PDF · 260 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Студенттерге арналған сервистер, кітап іздеу және каталогтағы подборкалармен жылдам жұмыс туралы қайталама кіріспе кездесу.',
                    'secondary_category' => 'Student Onboarding',
                    'date_time_range' => '13:30 – 15:00 (Астана)',
                    'capacity_label' => '150 орын',
                    'capacity_note' => 'Кітапханамен жұмысын жылдам қайталағысы келетін барлық студенттер үшін',
                    'about' => [
                        'Команда кітаптарды қалай іздеу, оларды подборкаға қалай сақтау және байланысты материалдарға артық қадамсыз өту керегін көрсетеді.',
                        'Кездесу жаңа семестрде кітапхананың цифрлық сервистеріне алғаш қосылатындарға да көмектеседі.',
                    ],
                    'agenda' => [
                        ['time' => '13:30', 'title' => 'Сервистер бойынша навигация', 'note' => 'Каталог, репозиторий және электрондық ресурстар'],
                        ['time' => '14:00', 'title' => 'Подборкалар және жылдам әрекеттер', 'note' => 'Сақтау, избранное және жаңартулар'],
                        ['time' => '14:30', 'title' => 'Сұрақтар мен көмек', 'note' => 'Қайда жүгіну керек'],
                    ],
                    'speaker' => [
                        'name' => 'Цифрлық сервистер үйлестірушісі',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Public Services',
                        'bio' => 'Пайдаланушыларға каталог, избранное және оқырман кабинетімен жұмыс істеудің жылдам сценарийлерін көрсетеді.',
                    ],
                    'materials' => [
                        ['title' => 'Жылдам бастау: каталог және подборкалар', 'meta' => 'PDF · 310 КБ'],
                        ['title' => 'Цифрлық сервистерге арналған жадынама', 'meta' => 'PDF · 260 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A refreshed student introduction to services, book search, and quick shortlist actions in the catalog.',
                    'secondary_category' => 'Student Onboarding',
                    'date_time_range' => '13:30 – 15:00 (Astana)',
                    'capacity_label' => '150 seats',
                    'capacity_note' => 'For any student who wants a fast refresher on using the library',
                    'about' => [
                        'The team will show how to find books, save them to a shortlist, and move to related materials with fewer steps.',
                        'This session also helps those connecting to the library’s digital services for the first time in the new semester.',
                    ],
                    'agenda' => [
                        ['time' => '13:30', 'title' => 'Service navigation', 'note' => 'Catalog, repository, and digital resources'],
                        ['time' => '14:00', 'title' => 'Shortlists and quick actions', 'note' => 'Saving, favorites, and updates'],
                        ['time' => '14:30', 'title' => 'Questions and support', 'note' => 'Where to go for help'],
                    ],
                    'speaker' => [
                        'name' => 'Digital Services Coordinator',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Public Services',
                        'bio' => 'Shows readers quick workflows for the catalog, favorites, and the reader dashboard.',
                    ],
                    'materials' => [
                        ['title' => 'Quick start: catalog and shortlists', 'meta' => 'PDF · 310 KB'],
                        ['title' => 'Digital services handout', 'meta' => 'PDF · 260 KB'],
                    ],
                ],
            ],
        ],
        'library-search-basics-2026' => [
            'secondary_category_slug' => 'research-skills',
            'date_time_range' => '10:00 – 11:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Семинар по быстрому поиску книг и статей, уточнению запросов и переходу к сохранённым подборкам.',
                    'secondary_category' => 'Research Skills',
                    'date_time_range' => '10:00 – 11:30 (Астана)',
                    'capacity_label' => '70 мест',
                    'capacity_note' => 'Для студентов всех уровней',
                    'about' => [
                        'Участники разберут быстрый поиск, логические операторы, фильтры и переход к нужной книге за минимальное число действий.',
                        'Отдельно покажем, как shortlist помогает собирать рабочие подборки для семинара или курсовой.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Поисковая стратегия', 'note' => 'Ключевые слова и уточнение запроса'],
                        ['time' => '10:30', 'title' => 'Фильтры и выдача', 'note' => 'Как быстро сузить результаты'],
                        ['time' => '11:00', 'title' => 'Shortlist на практике', 'note' => 'Сохранение и повторное использование'],
                    ],
                    'speaker' => [
                        'name' => 'Специалист по поисковым сервисам',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Discovery',
                        'bio' => 'Помогает читателям строить короткие и точные пути от поиска к книге.',
                    ],
                    'materials' => [
                        ['title' => 'Памятка по поиску', 'meta' => 'PDF · 180 КБ'],
                        ['title' => 'Шпаргалка по фильтрам', 'meta' => 'PDF · 90 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Кітаптар мен мақалаларды жылдам іздеу, сұрауды нақтылау және сақталған подборкаларға өту семинары.',
                    'secondary_category' => 'Research Skills',
                    'date_time_range' => '10:00 – 11:30 (Астана)',
                    'capacity_label' => '70 орын',
                    'capacity_note' => 'Барлық деңгейдегі студенттерге арналған',
                    'about' => [
                        'Қатысушылар жылдам іздеу, логикалық операторлар, сүзгілер және керекті кітапқа ең аз қадаммен жету жолын талдайды.',
                        'Shortlist семинар немесе курстық жұмыс үшін жұмыс жинақтарын қалай құруға көмектесетінін бөлек көрсетеміз.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Іздеу стратегиясы', 'note' => 'Кілт сөздер және сұрауды нақтылау'],
                        ['time' => '10:30', 'title' => 'Сүзгілер және нәтиже', 'note' => 'Нәтижені қалай жылдам тарылту керек'],
                        ['time' => '11:00', 'title' => 'Shortlist тәжірибеде', 'note' => 'Сақтау және қайта пайдалану'],
                    ],
                    'speaker' => [
                        'name' => 'Іздеу сервистері бойынша маман',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Discovery',
                        'bio' => 'Оқырмандарға іздеуден кітапқа дейін қысқа әрі нақты жол құруға көмектеседі.',
                    ],
                    'materials' => [
                        ['title' => 'Іздеу жөніндегі жадынама', 'meta' => 'PDF · 180 КБ'],
                        ['title' => 'Сүзгілерге арналған шпаргалка', 'meta' => 'PDF · 90 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A seminar on fast book/article search, query refinement, and moving to saved shortlists.',
                    'secondary_category' => 'Research Skills',
                    'date_time_range' => '10:00 – 11:30 (Astana)',
                    'capacity_label' => '70 seats',
                    'capacity_note' => 'Open to students at every level',
                    'about' => [
                        'Participants will walk through fast search, boolean operators, filters, and reaching the right book in fewer steps.',
                        'We will also show how shortlist helps build working bundles for a seminar or course project.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Search strategy', 'note' => 'Keywords and query refinement'],
                        ['time' => '10:30', 'title' => 'Filters and results', 'note' => 'How to narrow results quickly'],
                        ['time' => '11:00', 'title' => 'Shortlist in practice', 'note' => 'Saving and reuse'],
                    ],
                    'speaker' => [
                        'name' => 'Discovery Services Specialist',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Discovery',
                        'bio' => 'Helps readers build shorter, sharper paths from search to book.',
                    ],
                    'materials' => [
                        ['title' => 'Search handout', 'meta' => 'PDF · 180 KB'],
                        ['title' => 'Filter cheat sheet', 'meta' => 'PDF · 90 KB'],
                    ],
                ],
            ],
        ],
        'citation-tools-clinic-2026' => [
            'secondary_category_slug' => 'writing-support',
            'date_time_range' => '11:00 – 12:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Практика оформления ссылок, библиографий и citation tools для учебных и научных работ.',
                    'secondary_category' => 'Writing Support',
                    'date_time_range' => '11:00 – 12:30 (Астана)',
                    'capacity_label' => '45 мест',
                    'capacity_note' => 'Для студентов и магистрантов',
                    'about' => [
                        'Сессия посвящена стилям цитирования, сохранению источников и базовой автоматизации библиографий.',
                        'Покажем, как быстрее собирать список литературы и не терять уже найденные материалы.',
                    ],
                    'agenda' => [
                        ['time' => '11:00', 'title' => 'Цитирование без ошибок', 'note' => 'Стиль, формат и единообразие'],
                        ['time' => '11:30', 'title' => 'Reference manager', 'note' => 'Как экономить время на библиографии'],
                        ['time' => '12:00', 'title' => 'Разбор примеров', 'note' => 'Практика на реальных ссылках'],
                    ],
                    'speaker' => [
                        'name' => 'Библиографический консультант',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Writing Support',
                        'bio' => 'Помогает с цитированием, оформлением списков литературы и ссылочными менеджерами.',
                    ],
                    'materials' => [
                        ['title' => 'Стили цитирования: кратко', 'meta' => 'PDF · 150 КБ'],
                        ['title' => 'Шаблон списка литературы', 'meta' => 'DOCX · 64 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Оқу және ғылыми жұмыстар үшін сілтемелерді, библиографияны және citation tools қолдануды тәжірибеде көрсету.',
                    'secondary_category' => 'Writing Support',
                    'date_time_range' => '11:00 – 12:30 (Астана)',
                    'capacity_label' => '45 орын',
                    'capacity_note' => 'Студенттер мен магистранттарға арналған',
                    'about' => [
                        'Сессия сілтеме стильдеріне, дереккөздерді сақтауға және библиографияны бастапқы автоматтандыруға арналған.',
                        'Әдебиеттер тізімін қалай тез жинау және табылған материалдарды жоғалтпау керегін көрсетеміз.',
                    ],
                    'agenda' => [
                        ['time' => '11:00', 'title' => 'Қателіксіз дәйексөз келтіру', 'note' => 'Стиль, формат және бірізділік'],
                        ['time' => '11:30', 'title' => 'Reference manager', 'note' => 'Библиографияға уақыт үнемдеу'],
                        ['time' => '12:00', 'title' => 'Мысалдарды талдау', 'note' => 'Нақты сілтемелермен жұмыс'],
                    ],
                    'speaker' => [
                        'name' => 'Библиографиялық кеңесші',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Writing Support',
                        'bio' => 'Дәйексөз келтіру, әдебиеттер тізімі және reference manager құралдары бойынша көмектеседі.',
                    ],
                    'materials' => [
                        ['title' => 'Дәйексөз стильдері: қысқаша', 'meta' => 'PDF · 150 КБ'],
                        ['title' => 'Әдебиеттер тізімі үлгісі', 'meta' => 'DOCX · 64 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'Practical work on citations, bibliographies, and citation tools for academic assignments.',
                    'secondary_category' => 'Writing Support',
                    'date_time_range' => '11:00 – 12:30 (Astana)',
                    'capacity_label' => '45 seats',
                    'capacity_note' => 'For students and master’s candidates',
                    'about' => [
                        'The session focuses on citation styles, keeping sources, and basic bibliography automation.',
                        'We will show how to assemble a reference list faster and avoid losing already found materials.',
                    ],
                    'agenda' => [
                        ['time' => '11:00', 'title' => 'Citing without errors', 'note' => 'Style, format, and consistency'],
                        ['time' => '11:30', 'title' => 'Reference manager', 'note' => 'Saving time on bibliographies'],
                        ['time' => '12:00', 'title' => 'Example walk-throughs', 'note' => 'Practice on live references'],
                    ],
                    'speaker' => [
                        'name' => 'Bibliographic Consultant',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Writing Support',
                        'bio' => 'Helps with citations, reference lists, and reference manager tools.',
                    ],
                    'materials' => [
                        ['title' => 'Citation styles: quick guide', 'meta' => 'PDF · 150 KB'],
                        ['title' => 'Reference list template', 'meta' => 'DOCX · 64 KB'],
                    ],
                ],
            ],
        ],
        'repository-introduction-2026' => [
            'secondary_category_slug' => 'metadata-governance',
            'date_time_range' => '14:30 – 16:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Пошаговый мастер-класс по загрузке материалов в научный репозиторий и проверке метаданных.',
                    'secondary_category' => 'Metadata Governance',
                    'date_time_range' => '14:30 – 16:00 (Астана)',
                    'capacity_label' => '55 мест',
                    'capacity_note' => 'Для авторов и кураторов подразделений',
                    'about' => [
                        'Покажем, как подготовить файл, заполнить поля описания и передать материал на модерацию.',
                        'Участники увидят, какие метаданные влияют на видимость и корректный поиск в репозитории.',
                    ],
                    'agenda' => [
                        ['time' => '14:30', 'title' => 'Подготовка файла', 'note' => 'Формат, версия, вложения'],
                        ['time' => '15:00', 'title' => 'Метаданные записи', 'note' => 'Название, авторы, ключевые поля'],
                        ['time' => '15:30', 'title' => 'Модерация и публикация', 'note' => 'Что происходит после отправки'],
                    ],
                    'speaker' => [
                        'name' => 'Координатор репозитория',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Repository Services',
                        'bio' => 'Сопровождает публикацию материалов в научном репозитории и следит за качеством записей.',
                    ],
                    'materials' => [
                        ['title' => 'Чеклист подачи в репозиторий', 'meta' => 'PDF · 200 КБ'],
                        ['title' => 'Полевая схема метаданных', 'meta' => 'PDF · 110 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Ғылыми репозиторийге материал жүктеу және метадеректерді тексеру бойынша қадамдық мастер-класс.',
                    'secondary_category' => 'Metadata Governance',
                    'date_time_range' => '14:30 – 16:00 (Астана)',
                    'capacity_label' => '55 орын',
                    'capacity_note' => 'Авторлар мен бөлімше кураторларына арналған',
                    'about' => [
                        'Файлды қалай дайындау, сипаттама өрістерін толтыру және материалды модерацияға жіберу керегін көрсетеміз.',
                        'Қатысушылар репозиторийдегі іздеу мен көрінуге әсер ететін метадеректерді көреді.',
                    ],
                    'agenda' => [
                        ['time' => '14:30', 'title' => 'Файлды дайындау', 'note' => 'Формат, нұсқа және қосымшалар'],
                        ['time' => '15:00', 'title' => 'Жазба метадеректері', 'note' => 'Атауы, авторлары, негізгі өрістер'],
                        ['time' => '15:30', 'title' => 'Модерация және жариялау', 'note' => 'Жібергеннен кейін не болады'],
                    ],
                    'speaker' => [
                        'name' => 'Репозиторий үйлестірушісі',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Repository Services',
                        'bio' => 'Ғылыми репозиторийге материал жариялауды сүйемелдейді және жазбалардың сапасын бақылайды.',
                    ],
                    'materials' => [
                        ['title' => 'Репозиторийге беру чек-парағы', 'meta' => 'PDF · 200 КБ'],
                        ['title' => 'Метадеректер өріс картасы', 'meta' => 'PDF · 110 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A step-by-step workshop on uploading materials to the scholarly repository and checking metadata.',
                    'secondary_category' => 'Metadata Governance',
                    'date_time_range' => '14:30 – 16:00 (Astana)',
                    'capacity_label' => '55 seats',
                    'capacity_note' => 'For authors and department coordinators',
                    'about' => [
                        'We will show how to prepare the file, fill in description fields, and send the material for moderation.',
                        'Participants will see which metadata fields affect visibility and accurate discovery in the repository.',
                    ],
                    'agenda' => [
                        ['time' => '14:30', 'title' => 'Preparing the file', 'note' => 'Format, version, attachments'],
                        ['time' => '15:00', 'title' => 'Record metadata', 'note' => 'Title, authors, key fields'],
                        ['time' => '15:30', 'title' => 'Moderation and publication', 'note' => 'What happens after submission'],
                    ],
                    'speaker' => [
                        'name' => 'Repository Coordinator',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Repository Services',
                        'bio' => 'Supports publishing materials in the scholarly repository and keeps records in shape.',
                    ],
                    'materials' => [
                        ['title' => 'Repository submission checklist', 'meta' => 'PDF · 200 KB'],
                        ['title' => 'Metadata field map', 'meta' => 'PDF · 110 KB'],
                    ],
                ],
            ],
        ],
        'reading-club-launch-2026' => [
            'secondary_category_slug' => 'book-exhibits',
            'date_time_range' => '10:00 – 17:00',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Запуск читательского клуба с новой тематической витриной и быстрым сохранением книг в shortlist.',
                    'secondary_category' => 'Community Reading',
                    'date_time_range' => '10:00 – 17:00 (Астана)',
                    'capacity_label' => 'Свободный вход',
                    'capacity_note' => 'Для всех читателей и гостей',
                    'about' => [
                        'Мы открываем площадку для обсуждения новых книг, которые можно сразу добавить в подборку и обсудить с кураторами.',
                        'Витрина помогает быстро перейти от печатной книги к связанным электронным материалам.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Открытие витрины', 'note' => 'Знакомство с новой подборкой'],
                        ['time' => '12:00', 'title' => 'Выбор книги', 'note' => 'Совместный просмотр и shortlist'],
                        ['time' => '15:00', 'title' => 'Свободное обсуждение', 'note' => 'Обмен рекомендациями'],
                    ],
                    'speaker' => [
                        'name' => 'Куратор читательских программ',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Community',
                        'bio' => 'Организует тематические чтения, подборки и открытые библиотечные встречи.',
                    ],
                    'materials' => [
                        ['title' => 'Подборка клуба чтения', 'meta' => 'PDF · 240 КБ'],
                        ['title' => 'Список рекомендуемых книг', 'meta' => 'PDF · 180 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Жаңа тақырыптық витринасы бар оқырман клубын іске қосу және кітаптарды shortlist-ке тез сақтау.',
                    'secondary_category' => 'Community Reading',
                    'date_time_range' => '10:00 – 17:00 (Астана)',
                    'capacity_label' => 'Еркін кіру',
                    'capacity_note' => 'Барлық оқырмандар мен қонақтарға',
                    'about' => [
                        'Жаңа кітаптарды талқылауға арналған алаң ашамыз, оларды бірден подборкаға қосуға және кураторлармен талқылауға болады.',
                        'Витрина баспа кітабынан оған қатысты электрондық материалдарға тез өтуді жеңілдетеді.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Витринаны ашу', 'note' => 'Жаңа подборкамен танысу'],
                        ['time' => '12:00', 'title' => 'Кітап таңдау', 'note' => 'Бірлескен қарау және shortlist'],
                        ['time' => '15:00', 'title' => 'Еркін талқылау', 'note' => 'Ұсынымдармен алмасу'],
                    ],
                    'speaker' => [
                        'name' => 'Оқырман бағдарламаларының кураторы',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Community',
                        'bio' => 'Тақырыптық оқуларды, подборкаларды және ашық кітапхана кездесулерін ұйымдастырады.',
                    ],
                    'materials' => [
                        ['title' => 'Оқырман клубының подборкасы', 'meta' => 'PDF · 240 КБ'],
                        ['title' => 'Ұсынылатын кітаптар тізімі', 'meta' => 'PDF · 180 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'Launch of a reading club with a themed showcase and quick book saving to shortlist.',
                    'secondary_category' => 'Community Reading',
                    'date_time_range' => '10:00 – 17:00 (Astana)',
                    'capacity_label' => 'Open entry',
                    'capacity_note' => 'For all readers and guests',
                    'about' => [
                        'We are opening a space for discussing new books that can be added to shortlist immediately and discussed with the curators.',
                        'The showcase helps readers move quickly from a print book to related digital materials.',
                    ],
                    'agenda' => [
                        ['time' => '10:00', 'title' => 'Showcase opening', 'note' => 'Meet the new bundle'],
                        ['time' => '12:00', 'title' => 'Book selection', 'note' => 'Shared viewing and shortlist'],
                        ['time' => '15:00', 'title' => 'Open discussion', 'note' => 'Exchange recommendations'],
                    ],
                    'speaker' => [
                        'name' => 'Reader Program Curator',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Community',
                        'bio' => 'Runs themed reading sessions, bundles, and open library meetups.',
                    ],
                    'materials' => [
                        ['title' => 'Reading club bundle', 'meta' => 'PDF · 240 KB'],
                        ['title' => 'Recommended books list', 'meta' => 'PDF · 180 KB'],
                    ],
                ],
            ],
        ],
        'digital-exhibitions-tour-2026' => [
            'secondary_category_slug' => 'heritage',
            'date_time_range' => '15:00 – 16:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Обзор цифровых выставок, архивных карточек и связанных тематических подборок библиотеки.',
                    'secondary_category' => 'Digital Heritage',
                    'date_time_range' => '15:00 – 16:30 (Астана)',
                    'capacity_label' => '50 мест',
                    'capacity_note' => 'Для студентов, преподавателей и гостей',
                    'about' => [
                        'Участники увидят, как строится цифровая выставка от описания объекта до размещения на публичной странице.',
                        'Покажем, как связанные карточки помогают переходить между экспонатом, каталогом и новостями.',
                    ],
                    'agenda' => [
                        ['time' => '15:00', 'title' => 'Структура выставки', 'note' => 'Карточки, витрины, связи'],
                        ['time' => '15:30', 'title' => 'Архивный маршрут', 'note' => 'От объекта к коллекции'],
                        ['time' => '16:00', 'title' => 'Вопросы и примеры', 'note' => 'Живой обзор решений'],
                    ],
                    'speaker' => [
                        'name' => 'Куратор цифровых выставок',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Exhibitions',
                        'bio' => 'Собирает цифровые витрины и связывает их с каталогом и архивом.',
                    ],
                    'materials' => [
                        ['title' => 'Путеводитель по витрине', 'meta' => 'PDF · 170 КБ'],
                        ['title' => 'Шаблон карточки экспоната', 'meta' => 'DOCX · 68 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Цифрлық көрмелер, архивтік карточкалар және байланысты тақырыптық подборкаларға шолу.',
                    'secondary_category' => 'Digital Heritage',
                    'date_time_range' => '15:00 – 16:30 (Астана)',
                    'capacity_label' => '50 орын',
                    'capacity_note' => 'Студенттер, оқытушылар және қонақтарға',
                    'about' => [
                        'Қатысушылар цифрлық көрменің зат сипаттамасынан бастап жария бетке дейін қалай құрылатынын көреді.',
                        'Байланысты карточкалар экспонат, каталог және жаңалықтар арасында қалай өтуге көмектесетінін көрсетеміз.',
                    ],
                    'agenda' => [
                        ['time' => '15:00', 'title' => 'Көрме құрылымы', 'note' => 'Карточкалар, витриналар, байланыстар'],
                        ['time' => '15:30', 'title' => 'Архивтік маршрут', 'note' => 'Нысаннан коллекцияға дейін'],
                        ['time' => '16:00', 'title' => 'Сұрақтар мен мысалдар', 'note' => 'Шешімдерді тікелей қарау'],
                    ],
                    'speaker' => [
                        'name' => 'Цифрлық көрмелер кураторы',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Exhibitions',
                        'bio' => 'Цифрлық витриналарды жинайды және оларды каталогпен және архивпен байланыстырады.',
                    ],
                    'materials' => [
                        ['title' => 'Витринаға жолсілтеме', 'meta' => 'PDF · 170 КБ'],
                        ['title' => 'Экспонат карточкасы үлгісі', 'meta' => 'DOCX · 68 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A tour of digital exhibitions, archive cards, and the related themed bundles in the library.',
                    'secondary_category' => 'Digital Heritage',
                    'date_time_range' => '15:00 – 16:30 (Astana)',
                    'capacity_label' => '50 seats',
                    'capacity_note' => 'For students, faculty, and guests',
                    'about' => [
                        'Participants will see how a digital exhibition is built from an object description to the public-facing page.',
                        'We will show how related cards help users move between the exhibit, the catalog, and the news archive.',
                    ],
                    'agenda' => [
                        ['time' => '15:00', 'title' => 'Exhibition structure', 'note' => 'Cards, showcases, and links'],
                        ['time' => '15:30', 'title' => 'Archive route', 'note' => 'From object to collection'],
                        ['time' => '16:00', 'title' => 'Questions and examples', 'note' => 'Live review of solutions'],
                    ],
                    'speaker' => [
                        'name' => 'Digital Exhibitions Curator',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Exhibitions',
                        'bio' => 'Builds digital showcases and connects them to the catalog and archive.',
                    ],
                    'materials' => [
                        ['title' => 'Showcase guide', 'meta' => 'PDF · 170 KB'],
                        ['title' => 'Exhibit card template', 'meta' => 'DOCX · 68 KB'],
                    ],
                ],
            ],
        ],
        'exam-support-clinic-2026' => [
            'secondary_category_slug' => 'student-onboarding',
            'date_time_range' => '12:00 – 13:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Короткие консультации по поиску материалов, управлению временем и подбору источников перед экзаменами.',
                    'secondary_category' => 'Student Onboarding',
                    'date_time_range' => '12:00 – 13:30 (Астана)',
                    'capacity_label' => '60 мест',
                    'capacity_note' => 'Для студентов и кураторов',
                    'about' => [
                        'Покажем, как быстро находить материалы для финальных работ и собирать рабочие подборки без хаоса.',
                        'Отдельный блок поможет распланировать последние недели семестра и не потерять полезные источники.',
                    ],
                    'agenda' => [
                        ['time' => '12:00', 'title' => 'Фокус на задаче', 'note' => 'Что искать в первую очередь'],
                        ['time' => '12:30', 'title' => 'План на дедлайн', 'note' => 'Расстановка приоритетов'],
                        ['time' => '13:00', 'title' => 'Индивидуальные вопросы', 'note' => 'Быстрая консультация'],
                    ],
                    'speaker' => [
                        'name' => 'Координатор студенческой поддержки',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Student Success',
                        'bio' => 'Помогает студентам ориентироваться в библиотечных сервисах перед сессией.',
                    ],
                    'materials' => [
                        ['title' => 'План подготовки к сессии', 'meta' => 'PDF · 130 КБ'],
                        ['title' => 'Подборка полезных ресурсов', 'meta' => 'PDF · 160 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Емтихан алдында материал іздеу, уақытты басқару және дереккөз таңдау бойынша қысқа консультациялар.',
                    'secondary_category' => 'Student Onboarding',
                    'date_time_range' => '12:00 – 13:30 (Астана)',
                    'capacity_label' => '60 орын',
                    'capacity_note' => 'Студенттер мен кураторларға арналған',
                    'about' => [
                        'Қорытынды жұмыстар үшін материалдарды қалай тез табуға болатынын және жұмыс подборкаларын тәртіппен қалай жинауға болатынын көрсетеміз.',
                        'Қосымша блок семестрдің соңғы апталарын жоспарлауға және пайдалы дереккөздерді жоғалтпауға көмектеседі.',
                    ],
                    'agenda' => [
                        ['time' => '12:00', 'title' => 'Мәселеге фокус', 'note' => 'Алдымен нені іздеу керек'],
                        ['time' => '12:30', 'title' => 'Дедлайн жоспары', 'note' => 'Басымдықтарды қою'],
                        ['time' => '13:00', 'title' => 'Жеке сұрақтар', 'note' => 'Жылдам кеңес'],
                    ],
                    'speaker' => [
                        'name' => 'Студенттік қолдау үйлестірушісі',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Student Success',
                        'bio' => 'Студенттерге сессия алдында кітапхана сервистерін түсінуге көмектеседі.',
                    ],
                    'materials' => [
                        ['title' => 'Сессияға дайындық жоспары', 'meta' => 'PDF · 130 КБ'],
                        ['title' => 'Пайдалы ресурстар жинағы', 'meta' => 'PDF · 160 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'Short consultations on finding materials, managing time, and choosing sources before exams.',
                    'secondary_category' => 'Student Onboarding',
                    'date_time_range' => '12:00 – 13:30 (Astana)',
                    'capacity_label' => '60 seats',
                    'capacity_note' => 'For students and advisers',
                    'about' => [
                        'We will show how to quickly find materials for final work and keep working bundles organised.',
                        'A separate block helps plan the last weeks of the semester and avoid losing useful sources.',
                    ],
                    'agenda' => [
                        ['time' => '12:00', 'title' => 'Task focus', 'note' => 'What to look for first'],
                        ['time' => '12:30', 'title' => 'Deadline plan', 'note' => 'Setting priorities'],
                        ['time' => '13:00', 'title' => 'Individual questions', 'note' => 'Quick consultation'],
                    ],
                    'speaker' => [
                        'name' => 'Student Support Coordinator',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Student Success',
                        'bio' => 'Helps students navigate library services before the exam period.',
                    ],
                    'materials' => [
                        ['title' => 'Exam preparation plan', 'meta' => 'PDF · 130 KB'],
                        ['title' => 'Useful resources bundle', 'meta' => 'PDF · 160 KB'],
                    ],
                ],
            ],
        ],
        'library-innovation-lab-2026' => [
            'secondary_category_slug' => 'metadata-governance',
            'date_time_range' => '14:00 – 15:30',
            'i18n' => [
                'ru' => [
                    'subtitle' => 'Круглый стол о новых сценариях каталога, shortlist и цифровых витрин для следующего цикла улучшений.',
                    'secondary_category' => 'Metadata Governance',
                    'date_time_range' => '14:00 – 15:30 (Астана)',
                    'capacity_label' => '40 мест',
                    'capacity_note' => 'Для специалистов и кураторов сервисов',
                    'about' => [
                        'Участники обсудят, какие новые сценарии каталога стоит сделать заметнее и где shortlist помогает быстрее вернуться к материалам.',
                        'Отдельно посмотрим на роль цифровых витрин как точки входа в библиотечные коллекции.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Новые сценарии', 'note' => 'Каталог, shortlist и витрины'],
                        ['time' => '14:30', 'title' => 'Приоритеты улучшений', 'note' => 'Что делаем в следующем цикле'],
                        ['time' => '15:00', 'title' => 'Обратная связь', 'note' => 'Идеи от команды и читателей'],
                    ],
                    'speaker' => [
                        'name' => 'Руководитель сервисных улучшений',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Product & UX',
                        'bio' => 'Координирует развитие каталога, витрин и быстрых библиотечных сценариев.',
                    ],
                    'materials' => [
                        ['title' => 'Список идей для улучшений', 'meta' => 'PDF · 95 КБ'],
                        ['title' => 'Карта сценариев каталога', 'meta' => 'PDF · 140 КБ'],
                    ],
                ],
                'kk' => [
                    'subtitle' => 'Келесі жетілдіру циклі үшін каталог, shortlist және цифрлық витриналар туралы дөңгелек үстел.',
                    'secondary_category' => 'Metadata Governance',
                    'date_time_range' => '14:00 – 15:30 (Астана)',
                    'capacity_label' => '40 орын',
                    'capacity_note' => 'Сервис мамандары мен кураторларға арналған',
                    'about' => [
                        'Қатысушылар қай каталог сценарийлерін айқын ету керек екенін және shortlist материалдарға тез қайтуға қалай көмектесетінін талқылайды.',
                        'Цифрлық витриналардың кітапхана қорларына кіреберіс ретіндегі рөліне де назар аударамыз.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'Жаңа сценарийлер', 'note' => 'Каталог, shortlist және витриналар'],
                        ['time' => '14:30', 'title' => 'Жетілдіру басымдықтары', 'note' => 'Келесі циклде не істейміз'],
                        ['time' => '15:00', 'title' => 'Кері байланыс', 'note' => 'Команда мен оқырмандар идеялары'],
                    ],
                    'speaker' => [
                        'name' => 'Сервистік жетілдіру жетекшісі',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Product & UX',
                        'bio' => 'Каталог, витрина және жылдам кітапхана сценарийлерін дамытуды үйлестіреді.',
                    ],
                    'materials' => [
                        ['title' => 'Жетілдіру идеялары тізімі', 'meta' => 'PDF · 95 КБ'],
                        ['title' => 'Каталог сценарийлерінің картасы', 'meta' => 'PDF · 140 КБ'],
                    ],
                ],
                'en' => [
                    'subtitle' => 'A roundtable on new catalog flows, shortlist, and digital showcases for the next improvement cycle.',
                    'secondary_category' => 'Metadata Governance',
                    'date_time_range' => '14:00 – 15:30 (Astana)',
                    'capacity_label' => '40 seats',
                    'capacity_note' => 'For service specialists and curators',
                    'about' => [
                        'Participants will discuss which catalog journeys should be more visible and where shortlist helps readers return to materials faster.',
                        'We will also look at digital showcases as an entry point into the library collection.',
                    ],
                    'agenda' => [
                        ['time' => '14:00', 'title' => 'New journeys', 'note' => 'Catalog, shortlist, and showcases'],
                        ['time' => '14:30', 'title' => 'Improvement priorities', 'note' => 'What we tackle next'],
                        ['time' => '15:00', 'title' => 'Feedback', 'note' => 'Ideas from the team and readers'],
                    ],
                    'speaker' => [
                        'name' => 'Service Improvement Lead',
                        'role' => 'Kazakh University of Technology and Business named after K. Kulazhanov · Product & UX',
                        'bio' => 'Coordinates the evolution of catalog, showcase, and fast library workflows.',
                    ],
                    'materials' => [
                        ['title' => 'Improvement ideas list', 'meta' => 'PDF · 95 KB'],
                        ['title' => 'Catalog journey map', 'meta' => 'PDF · 140 KB'],
                    ],
                ],
            ],
        ],
    ];

    return [
        'chrome' => $chrome,
        'details' => $details,
    ];
};

// Phase 3 Cluster C.2 — standalone public event detail surface. Only a real,
// published managed record may resolve; fixture slugs and unknown slugs are 404.
Route::get('/events/{slug}', function (Request $request, string $slug) use ($newsModelToPublicArticle) {
    try {
        if (! Schema::hasTable('news') || ! Schema::hasColumn('news', 'type')) {
            abort(404);
        }

        $visibilities = ['public'];
        if ($request->user()) {
            $visibilities[] = 'members';
        }
        if ($request->user()?->can('news.view_internal')) {
            $visibilities[] = 'staff';
        }
        $record = News::query()->published()->whereIn('visibility', $visibilities)->whereIn('type', ['event', 'schedule'])->where(function ($query) use ($slug): void {
            $query->where('slug', $slug)->orWhere('slug_kk', $slug)->orWhere('slug_ru', $slug)->orWhere('slug_en', $slug);
        })->first();
        if ($record) {
            app(NewsAnalyticsService::class)->recordView($record, $request);

            $now = now('UTC');
            $related = News::query()
                ->published()
                ->when(Schema::hasColumn('news', 'visibility'), fn ($builder) => $builder->whereIn('visibility', $visibilities))
                ->whereIn('type', ['event', 'schedule'])
                ->whereKeyNot($record->getKey())
                ->where(function ($builder) use ($now): void {
                    $builder
                        ->where('ends_at', '>=', $now)
                        ->orWhere(function ($withoutEnd) use ($now): void {
                            $withoutEnd->whereNull('ends_at')->where('starts_at', '>=', $now);
                        });
                })
                ->orderBy('starts_at')
                ->limit(3)
                ->get()
                ->map($newsModelToPublicArticle)
                ->all();

            return view('news.show', [
                'activePage' => 'events',
                'article' => $newsModelToPublicArticle($record),
                'relatedArticles' => $related,
            ]);
        }

        abort(404);
    } catch (HttpException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        report($exception);
        abort(503, 'Events are temporarily unavailable.');
    }
})->where('slug', '[a-z0-9-]+');

// Phase 3 Cluster B.1 — standalone public leadership surface.
// Content is driven by $leadershipSeedProvider (trilingual, role-first).
// Per Cluster B Content Contract 8 this route is NOT added to the primary
// navbar; global access is via the footer and (later) the Institutional
// Directory block on /about.
Route::get('/leadership', function () use ($leadershipPublicProvider) {
    return view('leadership', [
        'activePage' => 'leadership',
        'leadership' => $leadershipPublicProvider(),
    ]);
});

// Phase 3 Cluster B.2 — standalone public library-rules surface.
// Content is driven by $rulesPublicProvider (trilingual; stable section
// order + stable anchor IDs per Cluster B Content Contract 2). Per
// contract 8 this route is NOT added to the primary navbar; global
// access is via the footer.
Route::get('/rules', function () use ($rulesPublicProvider) {
    return view('rules', [
        'activePage' => 'rules',
        'rules' => $rulesPublicProvider(),
    ]);
});

Route::get('/resources', function () {
    $externalResourceService = app(ExternalResourceService::class);
    $resources = $externalResourceService->directory();
    $categories = $externalResourceService->categories();

    return view('resources', [
        'activePage' => 'resources',
        'resources' => $resources,
        'categories' => $categories,
    ]);
});
Route::get('/resources/{slug}', PublicExternalResourceController::class)
    ->where('slug', '[a-z0-9][a-z0-9_-]*')
    ->middleware('throttle:external-resources')
    ->name('resources.show');
Route::get('/resources/{externalResource}/open', ExternalResourceRedirectController::class)
    ->whereNumber('externalResource')
    ->middleware('throttle:external-resources')
    ->name('external-resources.open');

Route::get('/for-teachers', fn () => redirect('/resources', 301));

// Scholarly repository — canonical public surface (Master.md 20.3).
// Guests browse director-approved metadata. Only policy-authorised PDFs are
// streamed; drafts stay hidden, embargo/restriction closes the file, and a
// withdrawal remains as a metadata tombstone. Canonical detail key is numeric.
Route::get('/repository', [PublicRepositoryController::class, 'index'])->name('repository.index');
Route::get('/repository/{item}', [PublicRepositoryController::class, 'show'])
    ->whereNumber('item')
    ->middleware('throttle:repository-read')
    ->name('repository.show');
Route::get('/repository/{item}/download', [PublicRepositoryController::class, 'download'])
    ->whereNumber('item')
    ->middleware('throttle:repository-read')
    ->name('repository.download');
Route::get('/repository/{item}/view', [PublicRepositoryController::class, 'view'])
    ->whereNumber('item')
    ->middleware('throttle:repository-read')
    ->name('repository.view');

// Compatibility branch for the retired config-backed slugs (config/repository_works.php).
// Those works have no repository_items row, so the only honest destination is the
// index; slugs that were never published under the old surface still 404 naturally
// because no route matches them.
Route::get('/repository/{slug}', function (string $slug) {
    $legacySlugs = collect(config('repository_works.works', []))->pluck('slug')->filter()->all();

    abort_unless(in_array($slug, $legacySlugs, true), 404);

    return redirect('/repository', 301);
})->where('slug', '[a-z][a-z0-9-]*');

Route::get('/discover', DiscoverController::class);

Route::get('/shortlist', function () {
    return view('shortlist', ['activePage' => 'shortlist']);
});

// WS1 convergence freeze:
// Transitional reader route retained for controlled migration only.
// Do not add new callers; canonical public detail path is /book/{isbn}.
Route::get('/book/{isbn}/read', function ($isbn) {
    return view('reader', ['isbn' => $isbn]);
})->name('reader.transitional');

// Phase 1.4 — transitional compatibility layer.
// Canonical destinations live under /librarian/*; these paths 301-redirect so that
// deep-links and bookmarks keep working while operational traffic migrates. The
// canonical /librarian/* routes enforce their own auth + role gating, so the
// redirects themselves are intentionally public.
Route::permanentRedirect('/internal/dashboard', '/librarian');
Route::permanentRedirect('/internal/circulation', '/librarian/circulation');
Route::permanentRedirect('/internal/stewardship', '/librarian/data-cleanup');
Route::permanentRedirect('/internal/review', '/librarian/data-cleanup');

Route::prefix('internal')->middleware(['library.auth'])->group(function () use ($internalStaffView) {
    // Remaining transitional surface — experimental staff AI assistant;
    // no canonical /librarian destination has been approved yet.
    Route::get('/ai-chat', function (Request $request) use ($internalStaffView) {
        return $internalStaffView($request, 'internal-ai-chat');
    })->middleware('permission:data_cleanup.access');
});

// Authorization is enforced twice on purpose: `library.auth`/`librarian.staff`
// read the legacy session array, while `operational.staff` checks the Eloquent
// user's Spatie roles and permissions. The permission boundary also admits
// future staff roles such as cataloguer without granting access to members.
Route::prefix('librarian')->middleware(['library.auth', 'librarian.staff', 'operational.staff'])->name('librarian.')->group(function (): void {
    Route::get('/profile', [LibrarianProfileController::class, 'show'])
        ->middleware('private.response')->name('profile.show');
    Route::patch('/profile/preferences', [LibrarianProfileController::class, 'updatePreferences'])
        ->middleware(['private.response', 'throttle:10,1'])->name('profile.preferences');
    Route::prefix('settings')->name('settings.')->middleware(['permission:library.settings.manage', 'private.response'])->group(function (): void {
        Route::get('/library-operations', [LibrarianLibraryOperationSettingController::class, 'index'])
            ->name('library-operations.index');
        Route::patch('/library-operations', [LibrarianLibraryOperationSettingController::class, 'update'])
            ->name('library-operations.update');
    });
    Route::middleware(['permission:circulation.issue|circulation.return', 'private.response'])->group(function (): void {
        Route::get('/readers', [LibrarianDirectoryReaderController::class, 'index'])->name('readers.index');
        Route::post('/readers/active-directory', [LibrarianDirectoryReaderController::class, 'provision'])
            ->middleware('throttle:10,1')->name('readers.active-directory.provision');
    });
    Route::prefix('workspace')->name('workspace.')->middleware('private.response')->group(function (): void {
        Route::get('/search', [LibrarianWorkspaceController::class, 'search'])->middleware('permission:catalog.search')->name('search');
        Route::get('/tasks', [LibrarianWorkspaceController::class, 'tasks'])->middleware('permission:tasks.view')->name('tasks');
        Route::post('/tasks', [LibrarianWorkspaceController::class, 'storeTask'])->middleware('permission:tasks.manage_own|tasks.assign')->name('tasks.store');
        Route::patch('/tasks/{task}', [LibrarianWorkspaceController::class, 'updateTask'])->middleware('permission:tasks.manage_own|tasks.assign')->name('tasks.update');
        Route::get('/calendar', [LibrarianWorkspaceController::class, 'calendar'])->middleware('permission:calendar.view')->name('calendar');
        Route::get('/fund-movements', [LibrarianWorkspaceController::class, 'movements'])->middleware('permission:copies.movements.view')->name('movements');
        Route::post('/fund-movements', [LibrarianWorkspaceController::class, 'storeMovement'])->middleware('permission:copies.movements.create')->name('movements.store');
        Route::get('/orders', [LibrarianWorkspaceController::class, 'orders'])->middleware('permission:acquisitions.view')->name('orders');
        Route::post('/orders', [LibrarianWorkspaceController::class, 'storeOrder'])->middleware('permission:acquisitions.create_order|acquisitions.manage')->name('orders.store');
        Route::patch('/orders/{order}/items/{item}/receive', [LibrarianWorkspaceController::class, 'receiveOrderItem'])->middleware('permission:acquisitions.receive|acquisitions.manage')->name('orders.items.receive');
        Route::get('/edd', [LibrarianWorkspaceController::class, 'deliveries'])->middleware('permission:edd.view')->name('edd');
        Route::post('/edd', [LibrarianWorkspaceController::class, 'storeDelivery'])->middleware('permission:edd.manage')->name('edd.store');
        Route::get('/periodicals', [LibrarianWorkspaceController::class, 'periodicals'])->middleware('permission:periodicals.view')->name('periodicals');
        Route::post('/periodicals', [LibrarianWorkspaceController::class, 'storePeriodical'])->middleware('permission:periodicals.manage')->name('periodicals.store');
        Route::post('/periodicals/{subscription}/issues', [LibrarianWorkspaceController::class, 'receiveIssue'])->middleware('permission:periodicals.manage')->name('periodicals.issues.store');
    });
    Route::get('/', LibrarianDashboardController::class)->name('overview');

    Route::prefix('acquisitions')->name('acquisitions.')->middleware('permission:acquisitions.view|acquisitions.manage')->group(function (): void {
        Route::get('/', [LibrarianAcquisitionBatchController::class, 'index'])->name('index');
        Route::post('/', [LibrarianAcquisitionBatchController::class, 'store'])->middleware('permission:acquisitions.intake|acquisitions.manage')->name('store');
        Route::get('/{batch}', [LibrarianAcquisitionBatchController::class, 'show'])->name('show');
        Route::match(['PUT', 'PATCH'], '/{batch}', [LibrarianAcquisitionBatchController::class, 'update'])->middleware('permission:acquisitions.intake|acquisitions.manage')->name('update');
        Route::post('/{batch}/confirm', [LibrarianAcquisitionBatchController::class, 'confirm'])->middleware('permission:acquisitions.confirm|acquisitions.manage')->name('confirm');
        Route::post('/{batch}/cancel', [LibrarianAcquisitionBatchController::class, 'cancel'])->middleware('permission:acquisitions.intake|acquisitions.manage')->name('cancel');
    });

    Route::prefix('ksu')->name('ksu.')->middleware('permission:ksu.view')->group(function (): void {
        Route::get('/', [LibrarianKsuRegisterController::class, 'index'])->name('index');
        Route::get('/conflicts', [LibrarianKsuRegisterController::class, 'conflicts'])->middleware('permission:ksu.manage')->name('conflicts');
        Route::post('/conflicts/resolve-group', [LibrarianKsuRegisterController::class, 'resolveGroup'])->middleware('permission:ksu.resolve')->name('conflicts.resolve-group');
        Route::post('/conflicts/{conflict}/resolve', [LibrarianKsuRegisterController::class, 'resolve'])->middleware('permission:ksu.resolve')->name('conflicts.resolve');
        Route::get('/{entry}', [LibrarianKsuRegisterController::class, 'show'])->name('show');
    });
    Route::post('/executive/alerts/acknowledge', [LibrarianExecutiveDashboardController::class, 'acknowledge'])
        ->middleware(['permission:reports.view_full', 'private.response'])->name('executive.alerts.acknowledge');
    Route::post('/executive/alerts/assign', [LibrarianExecutiveDashboardController::class, 'assign'])
        ->middleware(['permission:tasks.assign', 'private.response'])->name('executive.alerts.assign');
    Route::get('/executive/export/{format}', [LibrarianExecutiveDashboardController::class, 'export'])
        ->whereIn('format', ['csv', 'pdf', 'xlsx', 'docx'])
        ->middleware(['permission:reports.view_full', 'permission:reports.export', 'private.response'])->name('executive.export');

    // Cataloguing (Master.md 6-11).
    Route::get('/catalog', [LibrarianCatalogController::class, 'index'])
        ->middleware('permission:catalog.search|catalog.view_full_metadata|catalog.create_record|catalog.edit_record')
        ->name('catalog.index');
    Route::get('/catalog/udc-search', [LibrarianCatalogController::class, 'udcSearch'])
        ->middleware('permission:catalog.view_udc')
        ->name('catalog.udc-search');
    Route::get('/catalog/duplicate-check', [LibrarianCatalogController::class, 'duplicateCheck'])
        ->middleware('permission:catalog.create_record|catalog.edit_record')
        ->name('catalog.duplicate-check');
    Route::get('/udc-reference', [LibrarianUdcReferenceController::class, 'index'])
        ->middleware('permission:catalog.view_udc')
        ->name('udc-reference.index');
    Route::patch('/udc-reference/{udcCode}', [LibrarianUdcReferenceController::class, 'update'])
        ->middleware('permission:catalog.edit_record')
        ->name('udc-reference.update');
    Route::middleware('permission:catalog.create_record')->group(function (): void {
        Route::get('/catalog/create', [LibrarianCatalogController::class, 'create'])->name('catalog.create');
        Route::post('/catalog', [LibrarianCatalogController::class, 'store'])->name('catalog.store');
    });
    Route::middleware('permission:catalog.edit_record')->group(function (): void {
        Route::post('/catalog/bulk', [LibrarianCatalogController::class, 'bulkUpdate'])->name('catalog.bulk');
        Route::post('/catalog/{record}/revert/{log}', [LibrarianCatalogController::class, 'revert'])->name('catalog.revert');
        Route::get('/catalog/{record}/edit', [LibrarianCatalogController::class, 'edit'])->name('catalog.edit');
        Route::match(['PUT', 'PATCH'], '/catalog/{record}', [LibrarianCatalogController::class, 'update'])->name('catalog.update');

        // Attachments edited from the same form as the metadata (10.4, 18).
        Route::get('/catalog/record-search', [LibrarianCatalogAttachmentController::class, 'recordSearch'])
            ->name('catalog.record-search');
        Route::post('/catalog/{record}/materials', [LibrarianCatalogAttachmentController::class, 'storeMaterial'])
            ->name('catalog.materials.store');
        Route::match(['PUT', 'PATCH'], '/catalog/{record}/materials/{material}', [LibrarianCatalogAttachmentController::class, 'updateMaterial'])
            ->name('catalog.materials.update');
        Route::delete('/catalog/{record}/materials/{material}', [LibrarianCatalogAttachmentController::class, 'destroyMaterial'])
            ->name('catalog.materials.destroy');
        Route::post('/catalog/{record}/relations', [LibrarianCatalogAttachmentController::class, 'storeRelation'])
            ->name('catalog.relations.store');
        Route::delete('/catalog/{record}/relations/{related}', [LibrarianCatalogAttachmentController::class, 'destroyRelation'])
            ->name('catalog.relations.destroy');
    });
    Route::delete('/catalog/{record}', [LibrarianCatalogController::class, 'destroy'])
        ->middleware('permission:catalog.delete_record')
        ->name('catalog.destroy');

    // Copies / inventory (Master.md 12).
    Route::get('/copies/write-off', [LibrarianCopyController::class, 'writeOffForm'])
        ->middleware('permission:copies.write_off')->name('copies.write-off');
    Route::post('/copies/write-off', [LibrarianCopyController::class, 'batchWriteOff'])
        ->middleware('permission:copies.write_off')->name('copies.write-off.store');
    Route::middleware('permission:copies.create|copies.edit')->group(function (): void {
        Route::get('/copies', [LibrarianCopyController::class, 'index'])->name('copies.index');
        Route::get('/copies/create', [LibrarianCopyController::class, 'create'])->name('copies.create');
        Route::post('/copies', [LibrarianCopyController::class, 'store'])->middleware('permission:copies.create')->name('copies.store');
        Route::post('/copies/barcode-batches/preview', [LibrarianCopyController::class, 'batchPreview'])->middleware('permission:barcodes.print_batch')->name('copies.barcode-batches.preview');
        Route::post('/copies/barcode-batches/prepare', [LibrarianCopyController::class, 'batchPrepare'])->middleware('permission:barcodes.print_batch')->name('copies.barcode-batches.prepare');
        Route::post('/copies/barcode-batches/printed', [LibrarianCopyController::class, 'batchMarkPrinted'])->middleware('permission:barcodes.print_batch')->name('copies.barcode-batches.printed');
        Route::get('/copies/{copy}', [LibrarianCopyController::class, 'show'])->name('copies.show');
        Route::get('/copies/{copy}/label', [LibrarianCopyController::class, 'label'])->middleware('permission:barcodes.print')->name('copies.label');
        Route::get('/copy-labels', [LibrarianCopyController::class, 'labels'])->middleware('permission:barcodes.print_batch')->name('copies.labels');
        Route::post('/copies/{copy}/barcode', [LibrarianCopyController::class, 'assignBarcode'])->middleware('permission:copies.edit')->name('copies.barcode.assign');
        Route::post('/copies/{copy}/barcode/confirm', [LibrarianCopyController::class, 'confirmBarcode'])->middleware('permission:copies.edit')->name('copies.barcode.confirm');
        Route::post('/copies/{copy}/barcode/printed', [LibrarianCopyController::class, 'markLabelPrinted'])->middleware('permission:barcodes.print')->name('copies.barcode.printed');
        Route::get('/copies/{copy}/edit', [LibrarianCopyController::class, 'edit'])->middleware('permission:copies.edit')->name('copies.edit');
        Route::match(['PUT', 'PATCH'], '/copies/{copy}', [LibrarianCopyController::class, 'update'])->middleware('permission:copies.edit')->name('copies.update');
        Route::post('/copies/{copy}/status', [LibrarianCopyController::class, 'changeStatus'])->middleware('permission:copies.edit')->name('copies.status');
    });

    // Circulation desk (Master.md 14).
    Route::middleware('permission:circulation.issue|circulation.return')->group(function (): void {
        Route::get('/circulation', [LibrarianCirculationController::class, 'dashboard'])->name('circulation');
        Route::get('/circulation/issue', [LibrarianCirculationController::class, 'issueForm'])->name('circulation.issue');
        Route::post('/circulation/issue', [LibrarianCirculationController::class, 'issue'])->middleware('permission:circulation.issue')->name('circulation.issue.store');
        Route::get('/circulation/return', [LibrarianCirculationController::class, 'returnForm'])->name('circulation.return');
        Route::post('/circulation/return', [LibrarianCirculationController::class, 'returnCopy'])->middleware('permission:circulation.return')->name('circulation.return.store');
        Route::post('/circulation/loans/{loan}/renew', [LibrarianCirculationController::class, 'renew'])->middleware('permission:circulation.renew')->name('circulation.renew');
        Route::get('/circulation/reader-lookup', [LibrarianCirculationController::class, 'readerLookup'])->name('circulation.reader-lookup');
        Route::get('/circulation/copy-lookup', [LibrarianCirculationController::class, 'copyLookup'])->name('circulation.copy-lookup');
        Route::patch('/circulation/readers/{reader}', [LibrarianCirculationController::class, 'updateReader'])->name('circulation.reader.update');
        // 9.4 — printable reader card with the scannable barcode.
        Route::get('/readers/{reader}/card', [LibrarianCirculationController::class, 'readerCard'])->name('readers.card');
    });
    Route::get('/circulation/history', [LibrarianCirculationController::class, 'history'])
        ->middleware('permission:circulation.view_any_history')->name('circulation.history');

    Route::middleware('permission:incidents.view')->group(function (): void {
        Route::get('/incidents', [LibrarianIncidentController::class, 'index'])->name('incidents.index');
        Route::get('/incidents/catalog-search', [LibrarianIncidentController::class, 'catalogSearch'])->name('incidents.catalog-search');
        Route::get('/incidents/{incident}', [LibrarianIncidentController::class, 'show'])->name('incidents.show');
        Route::post('/incidents/{incident}/candidates', [LibrarianIncidentController::class, 'propose'])->middleware('permission:incidents.create')->name('incidents.candidates.store');
        Route::post('/incident-candidates/{candidate}/review', [LibrarianIncidentController::class, 'review'])->middleware('permission:incidents.review')->name('incidents.candidates.review');
        Route::post('/incident-candidates/{candidate}/decision', [LibrarianIncidentController::class, 'decide'])->middleware('permission:incidents.approve')->name('incidents.candidates.decide');
        Route::post('/incident-candidates/{candidate}/draft-record', [LibrarianIncidentController::class, 'createDraft'])->middleware('permission:catalog.create_record')->name('incidents.candidates.draft');
        Route::post('/incidents/{incident}/register', [LibrarianIncidentController::class, 'register'])->middleware('permission:incidents.register_replacement')->name('incidents.register');
        Route::post('/incidents/{incident}/reopen', [LibrarianIncidentController::class, 'reopen'])->middleware('permission:incidents.resolve')->name('incidents.reopen');
        Route::post('/incidents/{incident}/resolve', [LibrarianIncidentController::class, 'resolve'])->middleware('permission:incidents.resolve')->name('incidents.resolve');
        Route::post('/incidents/{incident}/cancel', [LibrarianIncidentController::class, 'cancel'])->middleware('permission:incidents.resolve')->name('incidents.cancel');
        Route::post('/incidents/{incident}/attachments', [LibrarianIncidentController::class, 'uploadAttachment'])->middleware('permission:incidents.create')->name('incidents.attachments.store');
        Route::post('/incidents/{incident}/assign', [LibrarianIncidentController::class, 'assign'])->middleware('permission:incidents.review')->name('incidents.assign');
    });

    // Attendance (9.4) — card scan at the entrance, independent of loans.
    Route::middleware('permission:visits.record')->group(function (): void {
        Route::get('/visits', [LibrarianVisitController::class, 'index'])->name('visits.index');
        Route::get('/visits/lookup', [LibrarianVisitController::class, 'lookup'])->name('visits.lookup');
        Route::post('/visits', [LibrarianVisitController::class, 'store'])->name('visits.store');
    });

    Route::middleware('permission:inventory.view')->group(function (): void {
        Route::get('/inventory', [LibrarianInventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory', [LibrarianInventoryController::class, 'store'])->middleware('permission:inventory.create')->name('inventory.store');
        Route::get('/inventory/{inventory}', [LibrarianInventoryController::class, 'show'])->name('inventory.show');
        Route::post('/inventory/{inventory}/start', [LibrarianInventoryController::class, 'start'])->middleware('permission:inventory.create')->name('inventory.start');
        Route::post('/inventory/{inventory}/scan', [LibrarianInventoryController::class, 'scan'])->middleware('permission:inventory.scan')->name('inventory.scan');
        Route::post('/inventory/{inventory}/verify', [LibrarianInventoryController::class, 'verify'])->middleware('permission:inventory.scan')->name('inventory.verify');
        Route::post('/inventory/{inventory}/copies/{copy}/location', [LibrarianInventoryController::class, 'confirmLocation'])->middleware('permission:inventory.scan')->name('inventory.location.confirm');
        Route::post('/inventory/{inventory}/complete', [LibrarianInventoryController::class, 'complete'])->middleware('permission:inventory.review')->name('inventory.complete');
        Route::post('/inventory/{inventory}/approve', [LibrarianInventoryController::class, 'approve'])->middleware('permission:inventory.approve')->name('inventory.approve');
        Route::get('/inventory/{inventory}/export', [LibrarianInventoryController::class, 'export'])->name('inventory.export');
    });

    // Reservation queue (Master.md 13).
    Route::middleware('permission:reservation.confirm')->group(function (): void {
        Route::get('/reservations', [LibrarianReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations/{reservation}/confirm', [LibrarianReservationController::class, 'confirm'])->name('reservations.confirm');
        Route::post('/reservations/{reservation}/ready', [LibrarianReservationController::class, 'markReady'])
            ->middleware('permission:reservation.fulfill')
            ->name('reservations.ready');
        // 8.3 — extend the pickup hold; the service refuses when anyone is queued.
        Route::post('/reservations/{reservation}/extend', [LibrarianReservationController::class, 'extend'])
            ->middleware('permission:reservation.extend')
            ->name('reservations.extend');
        Route::post('/reservations/{reservation}/transfer', [LibrarianReservationController::class, 'requestTransfer'])->middleware('permission:reservation.manage_transfer')->name('reservations.transfer.request');
        Route::post('/transfers/{transfer}/approve', [LibrarianReservationController::class, 'approveTransfer'])->middleware('permission:reservation.manage_transfer')->name('transfers.approve');
        Route::post('/transfers/{transfer}/send', [LibrarianReservationController::class, 'sendTransfer'])->middleware('permission:reservation.manage_transfer')->name('transfers.send');
        Route::post('/transfers/{transfer}/receive', [LibrarianReservationController::class, 'receiveTransfer'])->middleware('permission:reservation.manage_transfer')->name('transfers.receive');
        Route::post('/transfers/{transfer}/cancel', [LibrarianReservationController::class, 'cancelTransfer'])->middleware('permission:reservation.manage_transfer')->name('transfers.cancel');
        Route::post('/reservations/{reservation}/cancel', [LibrarianReservationController::class, 'cancel'])->middleware('permission:reservation.cancel_any')->name('reservations.cancel');
        // 8 — releasing someone else's hold early is a cancellation in effect.
        Route::post('/reservations/{reservation}/pass-to-next', [LibrarianReservationController::class, 'passToNext'])->middleware('permission:reservation.cancel_any')->name('reservations.pass-to-next');
    });

    // Fines and debts (Master.md 14.4-14.5).
    Route::middleware('permission:fines.view')->group(function (): void {
        Route::get('/fines', [LibrarianFineController::class, 'index'])->name('fines.index');
        Route::post('/fines/{fine}/resolve', [LibrarianFineController::class, 'resolve'])->middleware('permission:fines.manage')->name('fines.resolve');
    });

    // Data quality workbench (Master.md 11).
    // The cataloguer is the primary user of this workbench (ДИР 6) but must
    // not reach the transitional /internal/* tools that `data_cleanup.access`
    // also gates, so cataloguing rights admit them here directly.
    Route::middleware('permission:data_cleanup.access|catalog.edit_record')->group(function (): void {
        Route::get('/data-cleanup', [LibrarianDataCleanupController::class, 'index'])->name('data-cleanup');
        // One-at-a-time retyping console for cp1251 glyph damage (ДИР 6).
        Route::get('/data-cleanup/retype', [LibrarianDataCleanupController::class, 'retype'])
            ->name('data-cleanup.retype');
        Route::post('/data-cleanup/retype/{record}', [LibrarianDataCleanupController::class, 'storeRetype'])
            ->middleware('permission:catalog.edit_record')
            ->name('data-cleanup.retype.store');
        Route::post('/data-cleanup/parallel/{record}', [LibrarianDataCleanupController::class, 'resolveParallel'])
            ->middleware('permission:catalog.edit_record')
            ->name('data-cleanup.parallel');
    });

    Route::middleware('permission:data_quality.view')->prefix('data-quality')->name('data-quality.')->group(function (): void {
        Route::get('/', [LibrarianDataQualityController::class, 'index'])->name('index');
        Route::get('/recovery', [LibrarianRecoveryQualityController::class, 'index'])
            ->middleware('permission:legacy_recovery.review|legacy_recovery.view')->name('recovery');
        Route::post('/recovery/fund-raw/{copy}', [LibrarianRecoveryQualityController::class, 'resolveFundRaw'])
            ->middleware('permission:legacy_recovery.resolve')->name('recovery.fund.resolve');
        Route::post('/recovery/conflicts/{conflict}', [LibrarianRecoveryQualityController::class, 'resolveConflict'])
            ->middleware('permission:legacy_recovery.resolve')->name('recovery.conflicts.resolve');
        Route::post('/recovery/quarantine/{quarantine}/link', [LibrarianRecoveryQualityController::class, 'linkOrphan'])
            ->middleware('permission:legacy_recovery.resolve')->name('recovery.quarantine.link');
        Route::get('/export.csv', [LibrarianDataQualityController::class, 'export'])->middleware('permission:data_quality.view_reports')->name('export');
        Route::get('/exports/{type}.csv', [LibrarianDataQualityController::class, 'export'])->middleware('permission:data_quality.view_reports')->name('exports');
        Route::post('/scans', [LibrarianDataQualityController::class, 'queueScan'])->middleware('permission:data_quality.scan')->name('scans.store');
        Route::get('/issues/{issue}', [LibrarianDataQualityController::class, 'show'])->name('issues.show');
        Route::post('/issues/{issue}/assign', [LibrarianDataQualityController::class, 'assign'])->middleware('permission:data_quality.assign')->name('issues.assign');
        Route::post('/issues/{issue}/correct', [LibrarianDataQualityController::class, 'correct'])->middleware('permission:data_quality.correct')->name('issues.correct');
        Route::post('/issues/{issue}/false-positive', [LibrarianDataQualityController::class, 'falsePositive'])->middleware('permission:data_quality.triage')->name('issues.false-positive');
        Route::post('/issues/{issue}/ignore', [LibrarianDataQualityController::class, 'ignore'])->middleware('permission:data_quality.triage')->name('issues.ignore');
        Route::post('/issues/{issue}/reopen', [LibrarianDataQualityController::class, 'reopen'])->middleware('permission:data_quality.triage')->name('issues.reopen');
        Route::post('/issues/{issue}/comments', [LibrarianDataQualityController::class, 'comment'])->middleware('permission:data_quality.triage')->name('issues.comments');

        Route::get('/duplicates/groups', [LibrarianDataQualityController::class, 'duplicates'])->middleware('permission:data_quality.review_duplicates')->name('duplicates');
        Route::post('/duplicates/{group}/merge', [LibrarianDataQualityController::class, 'proposeMerge'])->middleware('permission:data_quality.merge')->name('merges.propose');
        Route::post('/merges/{operation}/approve', [LibrarianDataQualityController::class, 'approveMerge'])->middleware('permission:data_quality.approve_merge')->name('merges.approve');
        Route::post('/merges/{operation}/execute', [LibrarianDataQualityController::class, 'executeMerge'])->middleware('permission:data_quality.execute_merge')->name('merges.execute');

        Route::post('/batches/preview', [LibrarianDataQualityController::class, 'bulkPreview'])->middleware('permission:data_quality.bulk_edit')->name('batches.preview');
        Route::get('/batches/{batch}', [LibrarianDataQualityController::class, 'batch'])->middleware('permission:data_quality.bulk_edit')->name('batch');
        Route::post('/batches/{batch}/approve', [LibrarianDataQualityController::class, 'bulkApprove'])->middleware('permission:data_quality.approve_bulk')->name('batches.approve');
        Route::post('/batches/{batch}/execute', [LibrarianDataQualityController::class, 'bulkExecute'])->middleware('permission:data_quality.bulk_edit')->name('batches.execute');
        Route::post('/batches/{batch}/rollback', [LibrarianDataQualityController::class, 'bulkRollback'])->middleware('permission:data_quality.bulk_edit')->name('batches.rollback');

        Route::get('/imports/batches', [LibrarianDataQualityController::class, 'imports'])->middleware('permission:data_quality.import')->name('imports');
        Route::post('/imports/batches', [LibrarianDataQualityController::class, 'uploadImport'])->middleware('permission:data_quality.import')->name('imports.upload');
        Route::get('/imports/batches/{batch}', [LibrarianDataQualityController::class, 'showImport'])->middleware('permission:data_quality.import')->name('imports.show');
        Route::post('/imports/rows/{row}/decision', [LibrarianDataQualityController::class, 'decideImportRow'])->middleware('permission:data_quality.import')->name('imports.rows.decision');
        Route::post('/imports/batches/{batch}/approve', [LibrarianDataQualityController::class, 'approveImport'])->middleware('permission:data_quality.approve_import')->name('imports.approve');
        Route::post('/imports/batches/{batch}/execute', [LibrarianDataQualityController::class, 'executeImport'])->middleware('permission:data_quality.import')->name('imports.execute');
    });

    Route::middleware('permission:digital.upload|digital.review_metadata|digital.review_rights|digital.approve|digital.publish')->group(function (): void {
        Route::get('/digital-materials', [LibrarianDigitalMaterialController::class, 'index'])->name('digital-materials.index');
        Route::get('/digital-materials/{material}/edit', [LibrarianDigitalMaterialController::class, 'edit'])->name('digital-materials.edit');
        Route::patch('/digital-materials/{material}', [LibrarianDigitalMaterialController::class, 'update'])->name('digital-materials.update');
        Route::post('/digital-materials/{material}/transition', [LibrarianDigitalMaterialController::class, 'transition'])->name('digital-materials.transition');
    });

    // Scientific repository moderation (Master.md 20).
    Route::middleware('permission:repository.upload|repository.edit|repository.review_metadata|repository.review_rights|repository.request_changes|repository.approve|repository.publish|repository.withdraw')->group(function (): void {
        Route::get('/repository', [LibrarianRepositoryController::class, 'index'])->name('repository');
        Route::get('/repository/create', [LibrarianRepositoryController::class, 'create'])->middleware('permission:repository.upload')->name('repository.create');
        Route::post('/repository', [LibrarianRepositoryController::class, 'store'])->middleware('permission:repository.upload')->name('repository.store');
        Route::get('/repository/{item}/edit', [LibrarianRepositoryController::class, 'edit'])->name('repository.edit');
        Route::get('/repository/{item}/file', [LibrarianRepositoryController::class, 'file'])->name('repository.file');
        Route::match(['PUT', 'PATCH'], '/repository/{item}', [LibrarianRepositoryController::class, 'update'])
            ->middleware('permission:repository.upload|repository.edit')
            ->name('repository.update');
        Route::post('/repository/{item}/revisions', [LibrarianRepositoryController::class, 'revise'])
            ->middleware('permission:repository.manage_versions')
            ->name('repository.revisions.store');
        Route::post('/repository/{item}/transition', [LibrarianRepositoryController::class, 'transition'])->name('repository.transition');
    });

    // News desk — edit_own scope (Master.md 16, 22).
    Route::middleware('permission:news.create|news.edit_own')->group(function (): void {
        Route::get('/news', [LibrarianNewsController::class, 'index'])->name('news.index');
        Route::get('/news/create', [LibrarianNewsController::class, 'create'])->middleware('permission:news.create')->name('news.create');
        Route::post('/news', [LibrarianNewsController::class, 'store'])->middleware('permission:news.create')->name('news.store');
        Route::get('/news/{news}/edit', [LibrarianNewsController::class, 'edit'])->name('news.edit');
        Route::get('/news/{news}/preview', [LibrarianNewsController::class, 'preview'])->middleware(['signed', 'private.response'])->name('news.preview');
        Route::match(['PUT', 'PATCH'], '/news/{news}', [LibrarianNewsController::class, 'update'])->name('news.update');
        Route::post('/news/{news}/autosave', [LibrarianNewsController::class, 'autosave'])->middleware('throttle:12,1')->name('news.autosave');
        Route::post('/news/{news}/transition', [LibrarianNewsController::class, 'transition'])->middleware('permission:news.submit_for_review|news.review|news.request_changes|news.approve|news.schedule|news.publish|news.archive|news.cancel')->name('news.transition');
        Route::post('/news/{news}/emergency-publish', [LibrarianNewsController::class, 'emergencyPublish'])->middleware('permission:news.publish_emergency')->name('news.emergency-publish');
        Route::get('/news-calendar', [LibrarianNewsController::class, 'calendar'])->middleware('permission:news.view_internal')->name('news.calendar');
    });
    Route::prefix('annual-content-plans')->name('news.plans.')->middleware('permission:news.manage_annual_plan')->group(function (): void {
        Route::get('/', [LibrarianAnnualContentPlanController::class, 'index'])->name('index');
        Route::post('/', [LibrarianAnnualContentPlanController::class, 'store'])->name('store');
        Route::get('/{plan}', [LibrarianAnnualContentPlanController::class, 'show'])->name('show');
        Route::post('/{plan}/transition', [LibrarianAnnualContentPlanController::class, 'transition'])->name('transition');
        Route::post('/{plan}/items', [LibrarianAnnualContentPlanController::class, 'storeItem'])->name('items.store');
        Route::post('/items/{item}/complete', [LibrarianAnnualContentPlanController::class, 'completeItem'])->name('items.complete');
    });

    // Unified reports. Definition-level authorization in the controllers is
    // intentional: acquisitions, finance, quality and analytics specialists
    // receive only their own aggregate dataset, never the blanket ops scope.
    Route::middleware('private.response')->group(function (): void {
        Route::prefix('/reports/official')->name('reports.official.')->group(function (): void {
            Route::get('/', [LibrarianOfficialReportController::class, 'index'])->name('index');
            Route::post('/', [LibrarianOfficialReportController::class, 'store'])->name('store');
            Route::get('/exports/{export}', [LibrarianOfficialReportController::class, 'exportStatus'])->middleware('throttle:120,1')->name('exports.status');
            Route::post('/exports/{export}/retry', [LibrarianOfficialReportController::class, 'retryExport'])->middleware('throttle:10,1')->name('exports.retry');
            Route::get('/exports/{export}/download', [LibrarianOfficialReportController::class, 'downloadExport'])->middleware('private.response')->name('exports.download');
            Route::get('/{snapshot}', [LibrarianOfficialReportController::class, 'show'])->name('show');
            Route::post('/{snapshot}/submit', [LibrarianOfficialReportController::class, 'submit'])->name('submit');
            Route::post('/{snapshot}/approve', [LibrarianOfficialReportController::class, 'approve'])->name('approve');
            Route::post('/{snapshot}/reject', [LibrarianOfficialReportController::class, 'reject'])->name('reject');
            Route::post('/{snapshot}/archive', [LibrarianOfficialReportController::class, 'archive'])->name('archive');
            Route::post('/{snapshot}/revisions', [LibrarianOfficialReportController::class, 'revise'])->name('revisions.store');
            Route::delete('/{snapshot}', [LibrarianOfficialReportController::class, 'destroy'])->name('destroy');
            Route::get('/{snapshot}/source', [LibrarianOfficialReportController::class, 'source'])->middleware('private.response')->name('source');
            Route::post('/{snapshot}/exports', [LibrarianOfficialReportController::class, 'export'])->middleware('throttle:10,1')->name('exports.store');
        });
        Route::get('/reports', [LibrarianReportController::class, 'index'])->middleware('throttle:30,1')->name('reports.index');
        Route::get('/reports/{type}/export/{format?}', [LibrarianReportController::class, 'export'])->middleware(['permission:reports.export', 'throttle:10,1'])->name('reports.export');
        Route::get('/reports/{type}/print', [LibrarianReportController::class, 'print'])->middleware(['permission:reports.export', 'throttle:10,1'])->name('reports.print');
    });

    // External-resource approval desk. Unlike /admin, this route is reachable
    // by the library director and exposes no contract files or internal notes.
    Route::get('/external-resources/review', [AdminExternalResourceController::class, 'reviewQueue'])
        ->middleware('permission:external_resources.review|external_resources.publish')
        ->name('external-resources.review');
    Route::post('/external-resources/{externalResource}/workflow', [AdminExternalResourceController::class, 'workflow'])
        ->middleware('permission:external_resources.review|external_resources.publish')
        ->name('external-resources.workflow');

    // Reader inquiries (Historical 5.11: view + resolve, no delete).
    Route::middleware('permission:messages.view_all|messages.view_assigned')->group(function (): void {
        Route::get('/messages', [LibrarianMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{message}', [LibrarianMessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{message}/take', [LibrarianMessageController::class, 'take'])->name('messages.take');
        Route::patch('/messages/{message}/assignment', [LibrarianMessageController::class, 'assign'])->name('messages.assign');
        Route::patch('/messages/{message}/priority', [LibrarianMessageController::class, 'priority'])->name('messages.priority');
        Route::post('/messages/{message}/reply', [LibrarianMessageController::class, 'reply'])->name('messages.reply');
        Route::post('/messages/{message}/clarification', [LibrarianMessageController::class, 'clarification'])->name('messages.clarification');
        Route::post('/messages/{message}/notes', [LibrarianMessageController::class, 'note'])->name('messages.notes');
        Route::post('/messages/{message}/prepare-response', [LibrarianMessageController::class, 'prepare'])->name('messages.prepare');
        Route::post('/messages/{message}/approve-response', [LibrarianMessageController::class, 'approve'])->name('messages.approve');
        Route::post('/messages/{message}/return-response', [LibrarianMessageController::class, 'returnResponse'])->name('messages.return-response');
        Route::post('/messages/{message}/reject', [LibrarianMessageController::class, 'reject'])->name('messages.reject');
        Route::post('/messages/{message}/close', [LibrarianMessageController::class, 'close'])->name('messages.close');
        Route::post('/messages/{message}/reopen', [LibrarianMessageController::class, 'reopen'])->name('messages.reopen');
        Route::get('/messages/{message}/attachments/{attachment}', [LibrarianMessageController::class, 'attachment'])->name('messages.attachments.show');
    });
});

// Phase 2a — canonical member-facing shell for ordinary users (role='reader').
// Librarians and admins are rejected by the `member.reader` middleware and
// redirected to their own operational shells via the standard 403 flow.
// The transitional /account route is retained and unchanged for now.
Route::prefix('dashboard')->middleware(['auth', 'library.auth', 'member.reader', 'private.response'])->name('member.')->group(function (): void {
    // Personal cabinet (Master.md 15) — every screen is backed by the
    // canonical circulation schema and scoped to the signed-in reader inside
    // CabinetController, never merely by what the view chooses to render.
    Route::get('/', [MemberCabinetController::class, 'dashboard'])
        ->middleware('permission:member.dashboard.view')
        ->name('dashboard');

    // 15.2 / 5.3 — materials on hand and reader-initiated renewal.
    Route::get('/loans', [MemberCabinetController::class, 'loans'])
        ->middleware('permission:loans.view_own')
        ->name('loans');
    Route::get('/card', [MemberCabinetController::class, 'ticket'])
        ->middleware('permission:reader_card.view_own')
        ->name('card');
    Route::post('/card/printed', [MemberCabinetController::class, 'cardPrinted'])
        ->middleware(['permission:reader_card.print_own', 'throttle:20,1'])
        ->name('card.printed');
    Route::redirect('/ticket', '/dashboard/card')->name('ticket.legacy');
    Route::post('/loans/{loan}/renew', [MemberCabinetController::class, 'renewLoan'])
        ->middleware(['permission:loans.renew_own', 'throttle:10,1'])
        ->name('loans.renew');

    // 13.1, 15.3 — the reader's own reservation queue.
    Route::get('/reservations', [MemberCabinetController::class, 'reservations'])
        ->middleware('permission:reservation.view_own')
        ->name('reservations');
    Route::post('/reservations', [MemberCabinetController::class, 'storeReservation'])
        ->middleware('permission:reservation.create')
        ->name('reservations.store');
    Route::post('/reservations/{reservation}/cancel', [MemberCabinetController::class, 'cancelReservation'])
        ->middleware('permission:reservation.cancel_own')
        ->name('reservations.cancel');

    Route::redirect('/list', '/dashboard/collections')->name('list.legacy');
    Route::get('/collections', [MemberCollectionController::class, 'index'])
        ->middleware('permission:collections.manage_own|collections.view_public')
        ->name('collections.index');
    Route::post('/collections', [MemberCollectionController::class, 'store'])
        ->middleware(['permission:collections.manage_own', 'throttle:20,1'])
        ->name('collections.store');
    Route::get('/collections/{collection}', [MemberCollectionController::class, 'show'])
        ->middleware('permission:collections.manage_own|collections.view_public')
        ->name('collections.show');
    Route::patch('/collections/{collection}', [MemberCollectionController::class, 'update'])
        ->middleware('permission:collections.manage_own')
        ->name('collections.update');
    Route::delete('/collections/{collection}', [MemberCollectionController::class, 'destroy'])
        ->middleware('permission:collections.manage_own')
        ->name('collections.destroy');
    Route::post('/collections/{collection}/items', [MemberCollectionController::class, 'add'])
        ->middleware('permission:collections.manage_own')
        ->name('collections.items.add');
    Route::delete('/collections/{collection}/items/{item}', [MemberCollectionController::class, 'remove'])
        ->middleware('permission:collections.manage_own')
        ->name('collections.items.remove');
    Route::patch('/collections/{collection}/order', [MemberCollectionController::class, 'reorder'])
        ->middleware('permission:collections.manage_own')
        ->name('collections.reorder');
    Route::post('/collections/{collection}/follow', [MemberCollectionController::class, 'follow'])
        ->middleware('permission:collections.view_public')
        ->name('collections.follow');
    Route::post('/collections/{collection}/copy', [MemberCollectionController::class, 'copy'])
        ->middleware(['permission:collections.view_public', 'permission:collections.manage_own'])
        ->name('collections.copy');

    // 15.4 — closed loans; 15.5 — the reader's own debts (read-only).
    Route::get('/history', [MemberCabinetController::class, 'history'])
        ->middleware('permission:circulation.view_own_history')
        ->name('history');
    Route::get('/fines', [MemberCabinetController::class, 'fines'])
        ->middleware('permission:fines.view_own')
        ->name('fines');
    Route::get('/incidents', [MemberIncidentController::class, 'index'])
        ->middleware('permission:incidents.view_own')
        ->name('incidents.index');
    Route::get('/incidents/{incident}', [MemberIncidentController::class, 'show'])
        ->middleware('permission:incidents.view_own')
        ->name('incidents.show');

    // In-app reader notifications (Master.md 15.6) — real ReaderNotification feed.
    Route::get('/notifications', [MemberNotificationController::class, 'index'])
        ->middleware('permission:notifications.view_own')
        ->name('notifications');
    Route::post('/notifications/read-all', [MemberNotificationController::class, 'markAllRead'])
        ->middleware('permission:notifications.manage_own')
        ->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [MemberNotificationController::class, 'markRead'])
        ->middleware('permission:notifications.manage_own')
        ->name('notifications.read');

    Route::get('/digital-materials', [MemberPortalController::class, 'digitalMaterials'])
        ->middleware('permission:digital.view_metadata')
        ->name('digital-materials');
    Route::get('/search', [MemberPortalController::class, 'search'])
        ->middleware('permission:catalog.search')
        ->name('search');
    Route::get('/profile', [MemberPortalController::class, 'profile'])->name('profile');
    Route::patch('/profile', [MemberPortalController::class, 'updateProfile'])
        ->middleware(['permission:profile.update_own', 'throttle:10,1'])
        ->name('profile.update');
    Route::get('/messages', [MemberPortalController::class, 'messages'])
        ->middleware('permission:messages.view_own')
        ->name('messages');
    Route::get('/messages/{message}', [MemberPortalController::class, 'message'])
        ->middleware('permission:messages.view_own')
        ->name('messages.show');

    Route::post('/messages', [ContactMessageSubmissionController::class, 'store'])
        ->middleware(['permission:messages.submit', 'throttle:5,10'])
        ->name('messages.store');
    Route::post('/messages/{message}/reply', [ContactMessageSubmissionController::class, 'reply'])->middleware(['permission:messages.reply_own', 'throttle:10,10'])->name('messages.reply');
    Route::post('/messages/{message}/reopen', [ContactMessageSubmissionController::class, 'reopen'])->middleware(['permission:messages.reply_own', 'throttle:3,60'])->name('messages.reopen');
    Route::post('/messages/{message}/feedback', [ContactMessageSubmissionController::class, 'feedback'])->middleware('permission:messages.reply_own')->name('messages.feedback');
    Route::get('/messages/{message}/attachments/{attachment}', [ContactMessageSubmissionController::class, 'attachment'])->middleware('permission:messages.view_own')->name('messages.attachments.show');
});

// Delegated administrative tools. These URLs retain their historical
// `/admin` names, but access is determined by the exact capability instead of
// the broad control-plane role. A librarian responsible for electronic
// resources must not hit a dead 403 link, while every unrelated admin screen
// remains behind `control.plane` below.
Route::prefix('admin')->middleware(['auth', 'library.auth', 'operational.staff'])->name('admin.')->group(function (): void {
    Route::middleware('permission:integrations.view')->group(function (): void {
        Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
        Route::get('/integrations/{integration}', [IntegrationController::class, 'show'])->name('integrations.show');
    });
    Route::post('/integrations/check', [IntegrationController::class, 'check'])
        ->middleware(['permission:integrations.health', 'throttle:10,1'])
        ->name('integrations.check');
    Route::post('/integrations/{integration}/health', [IntegrationController::class, 'health'])
        ->middleware(['permission:integrations.health', 'throttle:10,1'])
        ->name('integrations.health');

    Route::middleware('permission:users.manage|external_resources.manage')->group(function (): void {
        Route::get('/import/{type}', [ImportController::class, 'form'])
            ->whereIn('type', ['users', 'external-resources'])
            ->name('imports.form');
        Route::post('/import/{type}/preview', [ImportController::class, 'preview'])
            ->whereIn('type', ['users', 'external-resources'])
            ->name('imports.preview');
        Route::post('/import/{type}', [ImportController::class, 'commit'])
            ->whereIn('type', ['users', 'external-resources'])
            ->name('imports.commit');
    });

    Route::post('/external-resources/{externalResource}/workflow', [AdminExternalResourceController::class, 'workflow'])
        ->middleware('permission:external_resources.manage|external_resources.publish')
        ->name('external-resources.workflow');
    Route::resource('external-resources', AdminExternalResourceController::class)
        ->except('show')
        ->middleware('permission:external_resources.manage');
});

Route::prefix('admin')->middleware(['auth', 'library.auth', 'control.plane'])->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('overview');

    // Global search — no extra permission gate here: every result group is
    // filtered inside the controller by the permission guarding its section.
    Route::get('/search', GlobalSearchController::class)->name('search');

    Route::prefix('library-recovery')->name('library-recovery.')
        ->middleware('permission:legacy_recovery.view')->group(function (): void {
            Route::get('/', [LibraryDataRecoveryController::class, 'index'])->name('index');
            Route::get('/batches/{batchId}', [LibraryDataRecoveryController::class, 'batch'])->name('batches.show');
            Route::get('/raw', [LibraryDataRecoveryController::class, 'rawRecords'])->name('raw.index');
            Route::get('/raw/{recordId}', [LibraryDataRecoveryController::class, 'rawRecord'])->name('raw.show');
            Route::get('/quarantine', [LibraryDataRecoveryController::class, 'quarantine'])->name('quarantine.index');
            Route::get('/quarantine/{quarantineId}', [LibraryDataRecoveryController::class, 'quarantineItem'])->name('quarantine.show');
            Route::get('/conflicts', [LibraryDataRecoveryController::class, 'conflicts'])->name('conflicts.index');
            Route::get('/conflicts/{conflictId}', [LibraryDataRecoveryController::class, 'conflict'])->name('conflicts.show');
            Route::get('/review', [LibraryDataRecoveryController::class, 'librarianReview'])
                ->middleware('permission:legacy_recovery.manage')->name('review');
        });

    Route::middleware('permission:users.manage')->group(function (): void {
        Route::get('/users/export', [UserController::class, 'export'])->name('users.export');
        Route::patch('/users/{user}/active', [UserController::class, 'toggleActive'])->name('users.active');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('/users/{user}/revoke-sessions', [UserController::class, 'revokeSessions'])->name('users.revoke-sessions');
        Route::resource('users', UserController::class)->except('destroy');
    });

    // Self-service profile — any signed-in admin-panel user manages their own
    // name, email, locale, and password; no extra permission required.
    Route::get('/profile', [AdminProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [AdminProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/password', [AdminProfileController::class, 'updatePassword'])->name('profile.password');

    Route::middleware('permission:roles.manage')->group(function (): void {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    Route::middleware('permission:system.logs')->group(function (): void {
        Route::get('/error-log', [ErrorLogController::class, 'index'])->name('error-log.index');
        Route::get('/logs/export', [AuditLogController::class, 'export'])->name('logs.export');
        Route::get('/logs/{activityLog}', [AuditLogController::class, 'show'])->name('logs.show');
        Route::get('/logs', [AuditLogController::class, 'index'])->name('logs.index');
    });

    Route::get('/news/export', [AdminNewsController::class, 'export'])
        ->middleware(['permission:reports.export', 'permission:news.edit_any'])
        ->name('news.export');
    Route::get('/news-categories', [AdminNewsController::class, 'categories'])->middleware('permission:news.manage_categories')->name('news.categories');
    Route::post('/news-categories', [AdminNewsController::class, 'storeCategory'])->middleware('permission:news.manage_categories')->name('news.categories.store');
    Route::patch('/news-categories/{category}', [AdminNewsController::class, 'updateCategory'])->middleware('permission:news.manage_categories')->name('news.categories.update');
    Route::get('/news-analytics', [AdminNewsController::class, 'analytics'])->middleware('permission:news.view_analytics')->name('news.analytics');
    Route::get('/news', [AdminNewsController::class, 'index'])->middleware('permission:news.edit_any|news.edit_own')->name('news.index');
    Route::get('/news/create', [AdminNewsController::class, 'create'])->middleware('permission:news.create')->name('news.create');
    Route::post('/news', [AdminNewsController::class, 'store'])->middleware('permission:news.create')->name('news.store');
    Route::get('/news/{news}/edit', [AdminNewsController::class, 'edit'])->middleware('permission:news.edit_any|news.edit_own')->name('news.edit');
    Route::match(['PUT', 'PATCH'], '/news/{news}', [AdminNewsController::class, 'update'])->middleware('permission:news.edit_any|news.edit_own')->name('news.update');
    Route::post('/news/{news}/autosave', [AdminNewsController::class, 'autosave'])->middleware(['permission:news.edit_any|news.edit_own', 'throttle:12,1'])->name('news.autosave');
    Route::post('/news/{news}/transition', [AdminNewsController::class, 'transition'])->middleware('permission:news.submit_for_review|news.review|news.request_changes|news.approve|news.schedule|news.publish|news.archive|news.cancel')->name('news.transition');
    Route::post('/news/{news}/emergency-publish', [AdminNewsController::class, 'emergencyPublish'])->middleware('permission:news.publish_emergency')->name('news.emergency-publish');
    Route::delete('/news/{news}', [AdminNewsController::class, 'destroy'])->middleware('permission:news.delete_draft')->name('news.destroy');

    Route::get('/messages/export', [ContactMessageController::class, 'export'])
        ->middleware(['permission:reports.export', 'permission:messages.view_analytics'])
        ->name('messages.export');
    Route::middleware('permission:messages.view_assigned')->group(function (): void {
        Route::get('/messages', [ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{message}', [ContactMessageController::class, 'show'])->name('messages.show');
        Route::get('/messages/{message}/attachments/{attachment}', [ContactMessageController::class, 'attachment'])->name('messages.attachments.show');
        Route::get('/feedback', [ContactMessageController::class, 'index'])->name('feedback');
    });
    Route::patch('/messages/{message}', [ContactMessageController::class, 'update'])->middleware('permission:messages.assign|messages.reassign|messages.change_priority')->name('messages.update');
    Route::get('/message-categories', [ContactMessageController::class, 'categories'])->middleware('permission:messages.manage_categories')->name('messages.categories');
    Route::post('/message-categories', [ContactMessageController::class, 'storeCategory'])->middleware('permission:messages.manage_categories')->name('messages.categories.store');
    Route::patch('/message-categories/{category}', [ContactMessageController::class, 'updateCategory'])->middleware('permission:messages.manage_categories')->name('messages.categories.update');
    Route::delete('/message-categories/{category}', [ContactMessageController::class, 'destroyCategory'])->middleware('permission:messages.manage_categories')->name('messages.categories.destroy');
    Route::get('/message-routing', [ContactMessageController::class, 'routing'])->middleware('permission:messages.manage_routing')->name('messages.routing');
    Route::post('/message-routing', [ContactMessageController::class, 'storeRouting'])->middleware('permission:messages.manage_routing')->name('messages.routing.store');
    Route::patch('/message-routing/{rule}', [ContactMessageController::class, 'updateRouting'])->middleware('permission:messages.manage_routing')->name('messages.routing.update');
    Route::get('/message-analytics', [ContactMessageController::class, 'analytics'])->middleware('permission:messages.view_analytics')->name('messages.analytics');

    Route::middleware('permission:reports.view_full')->group(function (): void {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/{type}', [ReportController::class, 'show'])->name('reports.show');
    });
    Route::get('/reports/{type}/export/{format}', [ReportController::class, 'export'])
        ->middleware(['permission:reports.export', 'permission:reports.view_full'])
        ->name('reports.export');

    Route::middleware('permission:system.settings')->group(function (): void {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::patch('/settings/notifications', [SettingController::class, 'updateNotifications'])->name('settings.notifications');
        Route::get('/system', [SystemController::class, 'index'])->name('system.index');
        Route::post('/system/backups', [SystemController::class, 'createBackup'])->middleware('throttle:2,10')->name('system.backups.create');
        Route::post('/system/backups/{backup}/restore-test', [SystemController::class, 'restoreTest'])->middleware('throttle:1,10')->name('system.backups.restore-test');
    });

    Route::post('/integrations/{integration}/toggle', [IntegrationController::class, 'toggle'])->middleware(['permission:integrations.manage', 'throttle:10,1'])->name('integrations.toggle');
    Route::post('/integrations/{integration}/dry-run', [IntegrationController::class, 'dryRun'])->middleware(['permission:integrations.sync', 'throttle:5,1'])->name('integrations.dry-run');
    Route::post('/integrations/{integration}/sync', [IntegrationController::class, 'startSync'])->middleware(['permission:integrations.sync', 'throttle:5,1'])->name('integrations.sync');
    Route::post('/integrations/{integration}/reconcile', [IntegrationController::class, 'reconcile'])->middleware(['permission:integrations.reconcile', 'throttle:5,1'])->name('integrations.reconcile');
    Route::post('/integrations/{integration}/mappings', [IntegrationController::class, 'storeMapping'])->middleware('permission:integrations.manage_mapping')->name('integrations.mappings.store');
    Route::post('/integrations/{integration}/outbox/{message}/retry', [IntegrationController::class, 'retry'])->middleware(['permission:integrations.retry', 'throttle:10,1'])->name('integrations.outbox.retry');
    Route::post('/integrations/{integration}/conflicts/{conflict}/resolve', [IntegrationController::class, 'resolveConflict'])->middleware('permission:integrations.resolve_conflicts')->name('integrations.conflicts.resolve');

    Route::middleware('permission:branches.manage')->group(function (): void {
        Route::get('/branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('/branches', [BranchController::class, 'store'])->name('branches.store');
        Route::patch('/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
        Route::post('/funds', [FundController::class, 'store'])->name('funds.store');
        Route::patch('/funds/{fund}', [FundController::class, 'update'])->name('funds.update');
        Route::delete('/funds/{fund}', [FundController::class, 'destroy'])->name('funds.destroy');
    });

    Route::redirect('/repository', '/librarian/repository')
        ->middleware('permission:repository.upload|repository.approve|repository.publish|repository.remove')
        ->name('repository');
    Route::redirect('/data-cleanup', '/librarian/data-cleanup')
        ->middleware('permission:data_cleanup.access')
        ->name('data-cleanup');
});

// Retired prototype React catalog shell. Keep a permanent redirect for old
// bookmarks while the only canonical public catalog remains /catalog.
Route::get('/app/{any?}', fn () => redirect('/catalog', 301))->where('any', '.*');
