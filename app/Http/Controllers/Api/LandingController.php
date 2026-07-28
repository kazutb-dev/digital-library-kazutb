<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\CatalogReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

class LandingController extends Controller
{
    public function index(CatalogReadService $catalogReadService): JsonResponse
    {
        $stats = Config::get('library.stats');

        $catalogPayload = $catalogReadService->search(limit: 6, sort: 'popular', includeTotal: false, includeLocations: false);
        $books = is_array($catalogPayload['data'] ?? null) ? $catalogPayload['data'] : [];
        $totalBooks = (int) ($catalogPayload['meta']['total'] ?? 0);

        // Keep configured fallback when DB-backed catalog returns no count.
        if ($totalBooks <= 0) {
            $totalBooks = 8930;
        }

        if (!empty($stats) && is_array($stats)) {
            $stats[0]['value'] = $totalBooks . '+';
        }

        $showcase = [
            ['label' => 'AI University', 'name' => 'Цифровой Разум', 'title' => 'Искусственный интеллект', 'meta' => 'Учебник · Электронная версия доступна', 'isbn' => '9780134610993'],
            ['label' => 'Data Lab', 'name' => 'Сигналы Данных', 'title' => 'Data Science', 'meta' => 'Практикум · В наличии', 'isbn' => '9781449373320'],
            ['label' => 'Cyber Lab', 'name' => 'Код Безопасности', 'title' => 'Академическое письмо', 'meta' => 'Методическое пособие · В наличии', 'isbn' => '9781506386706'],
        ];

        if (count($books) >= 3) {
            $showcase = [];
            $labels = ['AI University', 'Data Lab', 'Cyber Lab'];

            for ($i = 0; $i < 3 && $i < count($books); $i++) {
                $book = $books[$i];
                $titleDisplay = (string) ($book['title']['display'] ?? $book['title']['raw'] ?? 'Книга');
                $showcase[] = [
                    'label' => $labels[$i],
                    'name' => mb_strlen($titleDisplay) > 20 ? mb_substr($titleDisplay, 0, 20) : $titleDisplay,
                    'title' => (string) ($book['title']['display'] ?? $book['title']['raw'] ?? 'Без названия'),
                    'meta' => ((string) ($book['primaryAuthor'] ?? 'Автор не указан')) . ' · ' . ((int) ($book['copies']['available'] ?? 0)) . ' в наличии',
                    'isbn' => (string) ($book['isbn']['raw'] ?? ''),
                ];
            }
        }

        return response()->json([
            'header' => [
                'brand' => 'DIGITAL LIBRARY',
                'subtitle' => 'Современная академическая библиотека',
                'login_label' => 'Войти',
                'catalog_label' => 'Электронный каталог',
            ],
            'hero' => [
                'title' => 'Библиотека нового поколения для студентов, преподавателей и исследователей',
                'description' => 'Пространство, где классический библиотечный фонд объединяется с электронным каталогом, научными базами, онлайн-бронированием и быстрым поиском знаний в одном чистом и ярком интерфейсе.',
                'actions' => [
                    'primary' => 'Найти книгу',
                    'secondary' => 'Посмотреть новинки',
                ],
                'tags' => [
                    'Учебная литература',
                    'Научные публикации',
                    'E-books',
                    'Онлайн-доступ 24/7',
                ],
                'stats' => $stats,
                'showcase_title' => 'Популярные книги',
                'showcase' => $showcase,
                'search_title' => 'Найдите нужную книгу быстро и удобно',
                'search_description' => 'Поиск по названию, автору, дисциплине, ISBN или ключевым словам с удобной фильтрацией.',
                'search_placeholder' => 'Например: программирование, экономика, дизайн',
                'search_button' => 'Поиск',
            ],
            'advantages' => [
                'title' => 'Почему это больше, чем библиотека',
                'description' => 'Это современная образовательная экосистема, где печатные фонды, цифровые ресурсы и сервисы работают как единое пространство знаний.',
                'badges' => ['Clean UI', 'Bright landing', 'Адаптивный дизайн'],
                'items' => [
                    ['icon' => '🚀', 'title' => 'Быстрый доступ', 'description' => 'Студенты и преподаватели быстро находят нужные материалы без сложной навигации и перегруженных страниц.'],
                    ['icon' => '🎨', 'title' => 'Яркая подача', 'description' => 'Сильный первый экран, визуальные карточки, акценты и современный стиль делают сайт живым и привлекательным.'],
                    ['icon' => '🔐', 'title' => 'Личный кабинет', 'description' => 'Бронирование, история выдачи, доступ к электронным ресурсам и персональные рекомендации в одном месте.'],
                    ['icon' => '🌐', 'title' => 'Цифровая библиотека', 'description' => 'Онлайн-каталог, электронные книги, научные базы и удаленный доступ к материалам из любой точки.'],
                ],
            ],
            'catalog' => [
                'title' => 'Каталог знаний',
                'description' => 'Фонд библиотеки разделен на удобные направления, чтобы пользователь сразу понимал, где искать нужные материалы.',
            ],
            'services' => [
                'eyebrow' => 'Сервисы библиотеки',
                'title' => 'Удобный цифровой опыт для каждого пользователя',
                'description' => 'Библиотека становится полноценным онлайн-сервисом: поиск, бронирование, доступ к научным базам, сопровождение исследований и помощь в работе с академическими материалами.',
                'stats' => [
                    ['value' => '5 мин', 'label' => 'в среднем до нахождения нужного материала'],
                    ['value' => '1 клик', 'label' => 'до перехода к электронному ресурсу'],
                    ['value' => '100%', 'label' => 'адаптация под мобильные устройства'],
                    ['value' => '24/7', 'label' => 'доступ к каталогу и цифровым разделам'],
                ],
                'items' => [
                    ['icon' => '🔎', 'title' => 'Умный поиск по фонду', 'description' => 'Поиск по названию, автору, теме, дисциплине и ключевым словам с понятной фильтрацией.'],
                    ['icon' => '📦', 'title' => 'Онлайн-бронирование книг', 'description' => 'Пользователь может заранее забронировать нужную литературу и получить уведомление.'],
                    ['icon' => '🧠', 'title' => 'Поддержка исследований', 'description' => 'Консультации по поиску источников, оформлению списка литературы и научным базам.'],
                    ['icon' => '💻', 'title' => 'Удаленный доступ', 'description' => 'Электронная библиотека и цифровые подписки доступны не только в кампусе, но и онлайн.'],
                ],
            ],
            'events' => [
                'title' => 'События библиотеки',
                'description' => 'Лекции, презентации новых книг, выставки и семинары для развития академической среды.',
                'timeline' => [
                    ['day' => '12', 'month' => 'апр', 'title' => 'Презентация новых поступлений', 'description' => 'Обзор новых учебных и научных изданий по актуальным направлениям подготовки.'],
                    ['day' => '18', 'month' => 'апр', 'title' => 'Тренинг по поиску научных статей', 'description' => 'Практика по работе с международными базами данных и цифровыми платформами.'],
                    ['day' => '24', 'month' => 'апр', 'title' => 'Лекция по цифровой грамотности', 'description' => 'Как быстро находить, хранить и использовать академические источники в учебе и исследованиях.'],
                ],
                'news_eyebrow' => 'Объявления',
                'news_title' => 'Актуальная информация',
                'news_description' => 'Все важные обновления: график работы, новые ресурсы, подписки и изменения в доступе к сервисам.',
                'news' => [
                    ['title' => 'Открыт доступ к новым электронным базам', 'description' => 'Пользователи могут работать с дополнительными цифровыми ресурсами удаленно.'],
                    ['title' => 'Поступили новые книги по IT, бизнесу и дизайну', 'description' => 'Обновлен фонд по востребованным направлениям обучения.'],
                    ['title' => 'Изменен порядок выдачи литературы', 'description' => 'Актуальный график и правила опубликованы в личном кабинете.'],
                ],
                'news_button' => 'Смотреть все объявления',
            ],
            'footer' => [
                'brand_title' => 'Digital Library',
                'brand_description' => 'Цифровая библиотечная платформа для студентов, преподавателей, библиотекарей и аналитиков.',
                'contacts_title' => 'Контакты',
                'contact_items' => [
                    [
                        'label' => 'Приемная',
                        'value' => '+7 (775) 232-22-66',
                        'href' => 'tel:+77752322266',
                    ],
                    [
                        'label' => 'Библиотека',
                        'value' => '+7 (7172) 66-99-99',
                        'href' => 'tel:+77172669999',
                    ],
                    [
                        'label' => 'Email',
                        'value' => 'library@digital-library.demo',
                        'href' => 'mailto:library@digital-library.demo',
                    ],
                ],
                'address_title' => 'Адрес и режим работы',
                'address' => 'г. Астана, ул. К. Мухамеджанова, 37А',
                'hours' => [
                    'Пн-Пт: 09:00-18:00',
                    'Сб: 10:00-14:00, Вс: выходной',
                ],
                'quick_links_title' => 'Быстрые ссылки',
                'quick_links' => [
                    ['label' => 'Обзор', 'href' => '#advantages'],
                    ['label' => 'Каталог', 'href' => '#catalog'],
                    ['label' => 'Поиск по каталогу', 'href' => '#catalog'],
                    ['label' => 'Вход в систему', 'href' => '#contacts'],
                ],
                'bottom_lines' => [
                    '© 2026 Digital Library. Все права защищены.',
                    'Product engineering team · 2026',
                ],
            ],
        ]);
    }
}
