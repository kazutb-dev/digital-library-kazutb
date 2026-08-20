<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\ReaderProfile;
use App\Models\Fund;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Starter fond: real bibliographic records with physical copies spread across
 * the seeded branches and funds, so circulation, reservations, and the public
 * catalog operate on genuine rows from day one. Idempotent via ISBN/title.
 */
class LibraryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()->pluck('id', 'code');
        $funds = Fund::query()->pluck('id', 'code');
        $librarian = User::query()->where('email', 'demo-librarian@kazutb.local')->first();

        foreach ($this->records() as $definition) {
            $copies = $definition['copies'];
            unset($definition['copies']);

            $record = BibliographicRecord::query()->firstOrCreate(
                ['title' => $definition['title'], 'publication_year' => $definition['publication_year']],
                [...$definition, 'responsible_librarian_id' => $librarian?->getKey()],
            );

            if ($record->copies()->exists()) {
                continue;
            }

            foreach ($copies as $index => $copy) {
                $sequence = $record->getKey() * 100 + $index + 1;
                $record->copies()->create([
                    'inventory_number' => sprintf('INV-%06d', $sequence),
                    'barcode' => sprintf('KUTB%08d', $sequence),
                    'accounting_type' => 'individual',
                    'storage_sigla' => 'НБ',
                    'branch_id' => $branches[$copy['branch'] ?? 'SCIENTIFIC-LIBRARY'] ?? null,
                    'fund_id' => $funds[$copy['fund'] ?? 'MAIN'] ?? null,
                    'shelf_location' => $copy['shelf'] ?? null,
                    'price' => $copy['price'] ?? 4500,
                    'acquisition_source' => 'purchase',
                    'acquisition_date' => now()->subMonths(6)->toDateString(),
                    'registration_date' => now()->subMonths(6)->toDateString(),
                    'condition' => $copy['condition'] ?? 'good',
                    'status' => $copy['status'] ?? 'available',
                    'access_restriction' => $copy['access'] ?? 'free',
                ]);
            }
        }

        // Reader tickets for the seeded demo members (3 регистрация читателей).
        foreach (['demo-student@kazutb.local' => 'student', 'demo-teacher@kazutb.local' => 'teacher'] as $email => $category) {
            $user = User::query()->where('email', $email)->first();
            if ($user !== null) {
                ReaderProfile::query()->firstOrCreate(
                    ['user_id' => $user->getKey()],
                    ['ticket_number' => ReaderProfile::nextTicketNumber(), 'category' => $category],
                );
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(): array
    {
        return [
            [
                'title' => 'Современное академическое письмо',
                'primary_author' => 'Айгерим Сагындыкова',
                'publisher' => 'Издательство КазУТБ',
                'publication_year' => 2024,
                'language' => 'ru',
                'udc_code' => '37',
                'author_mark' => 'С13',
                'category' => 'education',
                'annotation' => 'Практическое руководство по академическому письму: структура научного текста, цитирование, работа с источниками и стилистика.',
                'keywords' => ['академическое письмо', 'научный текст', 'цитирование'],
                'isbn' => '9786012345671',
                'resource_type' => 'textbook',
                'copies' => [
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'EDUCATIONAL', 'shelf' => '3-2'],
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'EDUCATIONAL', 'shelf' => '3-2'],
                    ['branch' => 'READING-ROOM', 'fund' => 'MAIN', 'shelf' => 'RR-1', 'access' => 'reading_room'],
                ],
            ],
            [
                'title' => 'Artificial Intelligence in Higher Education',
                'primary_author' => 'Marcus Lee',
                'additional_authors' => ['Sara Connor'],
                'publisher' => 'Springer',
                'publication_year' => 2023,
                'language' => 'en',
                'udc_code' => '004.8',
                'author_mark' => 'L41',
                'category' => 'technology',
                'annotation' => 'Applications of artificial intelligence in university teaching, assessment, and administration, with case studies from Central Asia.',
                'keywords' => ['artificial intelligence', 'higher education', 'edtech'],
                'isbn' => '9783031234561',
                'resource_type' => 'book',
                'copies' => [
                    ['branch' => 'ENGINEERING-IT-DESK', 'fund' => 'UNIVERSITY-TECHNOLOGY', 'shelf' => '7-4'],
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'RESEARCH', 'shelf' => '12-1'],
                ],
            ],
            [
                'title' => 'Тұрақты технологиялар және энергия',
                'primary_author' => 'Бақытжан Оспанов',
                'publisher' => 'Фолиант',
                'publication_year' => 2022,
                'language' => 'kk',
                'udc_code' => '620.9',
                'author_mark' => 'О-75',
                'category' => 'technology',
                'annotation' => 'Жаңартылатын энергия көздері, энергия тиімділігі және тұрақты даму технологиялары бойынша оқу құралы.',
                'keywords' => ['энергетика', 'тұрақты даму', 'жасыл технологиялар'],
                'isbn' => '9786013021453',
                'resource_type' => 'study_guide',
                'copies' => [
                    ['branch' => 'TECHNOLOGY-DESK', 'fund' => 'UNIVERSITY-TECHNOLOGY', 'shelf' => '5-3'],
                    ['branch' => 'TECHNOLOGY-DESK', 'fund' => 'UNIVERSITY-TECHNOLOGY', 'shelf' => '5-3'],
                ],
            ],
            [
                'title' => 'Data Science Methods for Research',
                'primary_author' => 'Elena Petrova',
                'publisher' => 'O\'Reilly Media',
                'publication_year' => 2023,
                'language' => 'en',
                'udc_code' => '004',
                'author_mark' => 'P49',
                'category' => 'technology',
                'annotation' => 'Statistical and machine-learning methods for academic research: experiment design, reproducibility, and data pipelines.',
                'keywords' => ['data science', 'statistics', 'research methods'],
                'isbn' => '9781492057123',
                'resource_type' => 'book',
                'copies' => [
                    ['branch' => 'ENGINEERING-IT-DESK', 'fund' => 'UNIVERSITY-TECHNOLOGY', 'shelf' => '7-2'],
                ],
            ],
            [
                'title' => 'Экономические трансформации Казахстана',
                'primary_author' => 'Динара Абылкасымова',
                'publisher' => 'Экономика',
                'publication_year' => 2024,
                'language' => 'ru',
                'udc_code' => '338',
                'author_mark' => 'А17',
                'category' => 'economics',
                'annotation' => 'Анализ структурных реформ экономики Казахстана: индустриализация, цифровизация, региональное развитие и внешняя торговля.',
                'keywords' => ['экономика Казахстана', 'реформы', 'индустриализация'],
                'isbn' => '9786012987651',
                'resource_type' => 'book',
                'copies' => [
                    ['branch' => 'ECONOMICS-DESK', 'fund' => 'UNIVERSITY-ECONOMIC', 'shelf' => '2-1'],
                    ['branch' => 'ECONOMICS-DESK', 'fund' => 'UNIVERSITY-ECONOMIC', 'shelf' => '2-1'],
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'MAIN', 'shelf' => '9-5'],
                ],
            ],
            [
                'title' => 'История Центральной Азии: архивы и карты',
                'primary_author' => 'Кайрат Нурланов',
                'publisher' => 'Атамұра',
                'publication_year' => 2021,
                'language' => 'ru',
                'udc_code' => '93/94',
                'author_mark' => 'Н90',
                'category' => 'history',
                'annotation' => 'Историческая география Центральной Азии по архивным материалам и картографическим источникам XVIII-XX веков.',
                'keywords' => ['история', 'Центральная Азия', 'архивы', 'картография'],
                'isbn' => '9786013331245',
                'resource_type' => 'book',
                'copies' => [
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'MAIN', 'shelf' => '15-2'],
                    ['branch' => 'READING-ROOM', 'fund' => 'MAIN', 'shelf' => 'RR-3', 'access' => 'reading_room', 'condition' => 'worn'],
                ],
            ],
            [
                'title' => 'Қаржылық менеджмент негіздері',
                'primary_author' => 'Гүлнар Байжанова',
                'publisher' => 'Экономика баспасы',
                'publication_year' => 2023,
                'language' => 'kk',
                'udc_code' => '336',
                'author_mark' => 'Б18',
                'category' => 'economics',
                'annotation' => 'Кәсіпорын қаржысын басқару: қаржылық талдау, инвестициялық шешімдер және тәуекелдерді бағалау.',
                'keywords' => ['қаржы', 'менеджмент', 'инвестициялар'],
                'isbn' => '9786012456782',
                'resource_type' => 'textbook',
                'copies' => [
                    ['branch' => 'ECONOMICS-DESK', 'fund' => 'UNIVERSITY-ECONOMIC', 'shelf' => '2-4'],
                    ['branch' => 'ECONOMICS-DESK', 'fund' => 'UNIVERSITY-ECONOMIC', 'shelf' => '2-4'],
                ],
            ],
            [
                'title' => 'Химия пищевых производств',
                'primary_author' => 'Владимир Ким',
                'additional_authors' => ['Асель Токтарова'],
                'publisher' => 'Лань',
                'publication_year' => 2022,
                'language' => 'ru',
                'udc_code' => '664',
                'author_mark' => 'К40',
                'category' => 'technology',
                'annotation' => 'Химические основы пищевых технологий: состав сырья, процессы переработки, контроль качества и безопасность продуктов.',
                'keywords' => ['пищевая химия', 'технология', 'контроль качества'],
                'isbn' => '9785811412342',
                'resource_type' => 'textbook',
                'copies' => [
                    ['branch' => 'TECHNOLOGY-DESK', 'fund' => 'UNIVERSITY-TECHNOLOGY', 'shelf' => '4-1'],
                    ['branch' => 'TECHNOLOGY-DESK', 'fund' => 'UNIVERSITY-TECHNOLOGY', 'shelf' => '4-1'],
                    ['branch' => 'TECHNOLOGY-DESK', 'fund' => 'UNIVERSITY-TECHNOLOGY', 'shelf' => '4-1'],
                ],
            ],
            [
                'title' => 'Правовое регулирование предпринимательской деятельности в РК',
                'primary_author' => 'Мадина Есимова',
                'publisher' => 'Жеті жарғы',
                'publication_year' => 2023,
                'language' => 'ru',
                'udc_code' => '34',
                'author_mark' => 'Е83',
                'category' => 'law',
                'annotation' => 'Правовые основы предпринимательства в Республике Казахстан: регистрация, налогообложение, договорные отношения, защита прав.',
                'keywords' => ['право', 'предпринимательство', 'Казахстан'],
                'isbn' => '9786010412356',
                'resource_type' => 'book',
                'copies' => [
                    ['branch' => 'ECONOMICS-DESK', 'fund' => 'UNIVERSITY-ECONOMIC', 'shelf' => '1-3'],
                ],
            ],
            [
                'title' => 'Вестник КазУТБ. Серия экономическая',
                'primary_author' => null,
                'publisher' => 'КазУТБ',
                'publication_year' => 2026,
                'language' => 'ru',
                'udc_code' => '33',
                'author_mark' => 'В38',
                'category' => 'periodicals',
                'annotation' => 'Научный журнал университета: статьи по экономике, финансам и менеджменту. Выходит ежеквартально.',
                'keywords' => ['вестник', 'экономика', 'научный журнал'],
                'isbn' => null,
                'resource_type' => 'periodical',
                'copies' => [
                    ['branch' => 'READING-ROOM', 'fund' => 'PERIODICALS', 'shelf' => 'P-2', 'access' => 'reading_room'],
                ],
            ],
            [
                'title' => 'Психология делового общения',
                'primary_author' => 'Наталья Ершова',
                'publisher' => 'Юрайт',
                'publication_year' => 2021,
                'language' => 'ru',
                'udc_code' => '159.9',
                'author_mark' => 'Е80',
                'category' => 'psychology',
                'annotation' => 'Коммуникации в профессиональной среде: переговоры, публичные выступления, управление конфликтами.',
                'keywords' => ['психология', 'коммуникации', 'переговоры'],
                'isbn' => '9785534123457',
                'resource_type' => 'study_guide',
                'copies' => [
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'EDUCATIONAL', 'shelf' => '6-2'],
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'EDUCATIONAL', 'shelf' => '6-2'],
                ],
            ],
            [
                'title' => 'Цифровая трансформация библиотек',
                'primary_author' => 'Сауле Джаксыбекова',
                'publisher' => 'Издательство КазУТБ',
                'publication_year' => 2025,
                'language' => 'ru',
                'udc_code' => '02',
                'author_mark' => 'Д40',
                'category' => 'library_science',
                'annotation' => 'Электронные каталоги, оцифровка фондов, открытые репозитории и цифровые сервисы современной академической библиотеки.',
                'keywords' => ['библиотековедение', 'цифровизация', 'электронный каталог'],
                'isbn' => '9786012349876',
                'resource_type' => 'book',
                'copies' => [
                    ['branch' => 'SCIENTIFIC-LIBRARY', 'fund' => 'MAIN', 'shelf' => '1-1'],
                ],
            ],
            [
                'title' => 'Математикалық талдау. 1-бөлім',
                'primary_author' => 'Ерлан Досжанов',
                'publisher' => 'Мектеп',
                'publication_year' => 2020,
                'language' => 'kk',
                'udc_code' => '51',
                'author_mark' => 'Д64',
                'category' => 'mathematics',
                'annotation' => 'Шектер теориясы, дифференциалдық және интегралдық есептеу негіздері. Техникалық мамандықтар студенттеріне арналған.',
                'keywords' => ['математика', 'математикалық талдау', 'оқулық'],
                'isbn' => '9786010203458',
                'resource_type' => 'textbook',
                'copies' => [
                    ['branch' => 'ENGINEERING-IT-DESK', 'fund' => 'EDUCATIONAL', 'shelf' => '8-1'],
                    ['branch' => 'ENGINEERING-IT-DESK', 'fund' => 'EDUCATIONAL', 'shelf' => '8-1'],
                    ['branch' => 'ENGINEERING-IT-DESK', 'fund' => 'EDUCATIONAL', 'shelf' => '8-1'],
                    ['branch' => 'ENGINEERING-IT-DESK', 'fund' => 'EDUCATIONAL', 'shelf' => '8-1'],
                ],
            ],
        ];
    }
}
