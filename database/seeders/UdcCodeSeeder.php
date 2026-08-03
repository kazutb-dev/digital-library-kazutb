<?php

namespace Database\Seeders;

use App\Models\Catalog\UdcCode;
use Illuminate\Database\Seeder;

/**
 * Reference UDC classifier — the ten main classes plus the subdivisions the
 * university actually shelves by. The structure supports later import of the
 * full classifier (126k codes); firstOrCreate keeps re-runs idempotent.
 */
class UdcCodeSeeder extends Seeder
{
    private const CODES = [
        ['0', 'Общий отдел. Наука и знание. Информация', 'Жалпы бөлім. Ғылым және білім. Ақпарат', 'Generalities. Science and knowledge. Information'],
        ['004', 'Информационные технологии. Вычислительная техника', 'Ақпараттық технологиялар. Есептеу техникасы', 'Computer science and technology', '0'],
        ['004.8', 'Искусственный интеллект', 'Жасанды интеллект', 'Artificial intelligence', '004'],
        ['005', 'Управление. Менеджмент', 'Басқару. Менеджмент', 'Management', '0'],
        ['02', 'Библиотечное дело. Библиотековедение', 'Кітапхана ісі. Кітапханатану', 'Librarianship', '0'],
        ['1', 'Философия. Психология', 'Философия. Психология', 'Philosophy. Psychology'],
        ['159.9', 'Психология', 'Психология', 'Psychology', '1'],
        ['2', 'Религия. Теология', 'Дін. Теология', 'Religion. Theology'],
        ['3', 'Общественные науки', 'Қоғамдық ғылымдар', 'Social sciences'],
        ['33', 'Экономика. Экономические науки', 'Экономика. Экономикалық ғылымдар', 'Economics', '3'],
        ['330', 'Экономическая теория', 'Экономикалық теория', 'Economic theory', '33'],
        ['336', 'Финансы. Банковское дело', 'Қаржы. Банк ісі', 'Finance. Banking', '33'],
        ['338', 'Экономическая политика. Отраслевая экономика', 'Экономикалық саясат. Салалық экономика', 'Economic policy', '33'],
        ['34', 'Право. Юридические науки', 'Құқық. Заң ғылымдары', 'Law', '3'],
        ['37', 'Образование. Воспитание. Обучение', 'Білім беру. Тәрбие. Оқыту', 'Education', '3'],
        ['4', 'Резерв (не используется)', 'Резерв (қолданылмайды)', 'Vacant'],
        ['5', 'Математика. Естественные науки', 'Математика. Жаратылыстану ғылымдары', 'Mathematics. Natural sciences'],
        ['51', 'Математика', 'Математика', 'Mathematics', '5'],
        ['53', 'Физика', 'Физика', 'Physics', '5'],
        ['54', 'Химия. Кристаллография. Минералогия', 'Химия. Кристаллография. Минералогия', 'Chemistry', '5'],
        ['57', 'Биологические науки', 'Биология ғылымдары', 'Biological sciences', '5'],
        ['6', 'Прикладные науки. Медицина. Техника', 'Қолданбалы ғылымдар. Медицина. Техника', 'Applied sciences. Medicine. Technology'],
        ['62', 'Инженерное дело. Техника в целом', 'Инженерия. Жалпы техника', 'Engineering', '6'],
        ['620.9', 'Энергетика', 'Энергетика', 'Energy engineering', '62'],
        ['63', 'Сельское хозяйство. Пищевые производства', 'Ауыл шаруашылығы. Тамақ өндірісі', 'Agriculture. Food production', '6'],
        ['664', 'Пищевая промышленность и технологии', 'Тамақ өнеркәсібі және технологиялары', 'Food technology', '6'],
        ['66', 'Химическая технология', 'Химиялық технология', 'Chemical technology', '6'],
        ['7', 'Искусство. Развлечения. Спорт', 'Өнер. Ойын-сауық. Спорт', 'The arts. Recreation. Sport'],
        ['8', 'Язык. Языкознание. Литература', 'Тіл. Тіл білімі. Әдебиет', 'Language. Linguistics. Literature'],
        ['811', 'Языки', 'Тілдер', 'Languages', '8'],
        ['82', 'Литература. Литературоведение', 'Әдебиет. Әдебиеттану', 'Literature', '8'],
        ['9', 'География. Биографии. История', 'География. Өмірбаян. Тарих', 'Geography. Biography. History'],
        ['93/94', 'История', 'Тарих', 'History', '9'],
    ];

    public function run(): void
    {
        $ids = [];

        foreach (self::CODES as $entry) {
            [$code, $ru, $kk, $en] = $entry;
            $parentCode = $entry[4] ?? null;

            $model = UdcCode::query()->firstOrCreate(
                ['code' => $code],
                [
                    'description' => $ru,
                    'description_kk' => $kk,
                    'description_en' => $en,
                    'parent_id' => $parentCode !== null ? ($ids[$parentCode] ?? null) : null,
                ],
            );

            $ids[$code] = $model->getKey();
        }
    }
}
