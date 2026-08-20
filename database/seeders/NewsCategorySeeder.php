<?php

namespace Database\Seeders;

use App\Models\NewsCategory;
use Illuminate\Database\Seeder;

class NewsCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['new-arrivals', 'Жаңа түсімдер', 'Новые поступления', 'New arrivals', 'menu_book', ['update']],
            ['opening-hours', 'Жұмыс кестесі', 'График работы', 'Opening hours', 'schedule', ['announcement', 'schedule']],
            ['branch-closure', 'Филиалдың жабылуы', 'Закрытие филиала', 'Branch closure', 'warning', ['announcement', 'schedule']],
            ['library-rules', 'Пайдалану ережелері', 'Правила пользования', 'Library rules', 'policy', ['announcement', 'update']],
            ['electronic-resources', 'Электрондық ресурстар', 'Электронные ресурсы', 'Electronic resources', 'language', ['update']],
            ['events', 'Іс-шаралар', 'Мероприятия', 'Events', 'event', ['event', 'schedule']],
            ['lectures', 'Дәрістер', 'Лекции', 'Lectures', 'school', ['event', 'schedule']],
            ['author-meetings', 'Авторлармен кездесулер', 'Встречи с авторами', 'Author meetings', 'groups', ['event']],
            ['exhibitions', 'Көрмелер', 'Выставки', 'Exhibitions', 'gallery_thumbnail', ['event']],
            ['competitions', 'Байқаулар', 'Конкурсы', 'Competitions', 'emoji_events', ['event', 'announcement']],
            ['thematic-collections', 'Тақырыптық жинақтар', 'Тематические коллекции', 'Thematic collections', 'collections_bookmark', ['update']],
            ['university-news', 'Университет жаңалықтары', 'Новости университета', 'University news', 'account_balance', ['update', 'announcement']],
            ['technical', 'Техникалық хабарландырулар', 'Технические объявления', 'Technical notices', 'build', ['announcement', 'schedule']],
        ];
        foreach ($categories as $order => [$slug,$kk,$ru,$en,$icon,$types]) {
            NewsCategory::query()->updateOrCreate(['slug' => $slug], ['name_kk' => $kk, 'name_ru' => $ru, 'name_en' => $en, 'icon' => $icon, 'allowed_types' => $types, 'sort_order' => $order, 'active' => true]);
        }
    }
}
