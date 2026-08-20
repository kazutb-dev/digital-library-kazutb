<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageCategory extends Model
{
    protected $fillable = ['slug', 'message_type', 'name_kk', 'name_ru', 'name_en', 'active', 'sort_order', 'default_priority', 'default_assignee_role', 'requires_director_review', 'sla_hours', 'allowed_attachment_types'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'requires_director_review' => 'boolean', 'sla_hours' => 'integer', 'sort_order' => 'integer', 'allowed_attachment_types' => 'array'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ContactMessage::class, 'category_id');
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(MessageRoutingRule::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function localizedName(?string $locale = null): string
    {
        $locale = in_array($locale, ['kk', 'ru', 'en'], true) ? $locale : app()->getLocale();

        return (string) ($this->getAttribute('name_'.$locale) ?: $this->name_kk);
    }
}
