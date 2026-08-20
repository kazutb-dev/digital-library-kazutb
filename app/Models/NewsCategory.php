<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsCategory extends Model
{
    protected $fillable = ['slug', 'name_kk', 'name_ru', 'name_en', 'icon', 'color_token', 'allowed_types', 'default_visibility', 'active', 'sort_order'];

    protected function casts(): array
    {
        return ['allowed_types' => 'array', 'active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function publications(): HasMany
    {
        return $this->hasMany(News::class, 'category_id');
    }

    public function name(?string $locale = null): string
    {
        $locale = in_array($locale, News::LANGUAGES, true) ? $locale : app()->getLocale();

        return (string) ($this->{'name_'.$locale} ?: $this->name_kk);
    }
}
