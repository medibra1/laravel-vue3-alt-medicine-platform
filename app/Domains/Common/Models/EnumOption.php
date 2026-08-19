<?php

namespace App\Domains\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class EnumOption extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'bool',
        'order' => 'int',
        'properties' => 'array',
        'label' => 'array', // spatie/laravel-translatable can take over later for finer fallback handling
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function modelOptions()
    {
        return $this->hasMany(ModelOption::class, 'option_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Active options for a given type, cached (automatically invalidated
     * on save/delete — see booted()).
     */
    public static function cachedByType(string $enumType, ?string $domain = null)
    {
        $cacheKey = "enum_options:{$enumType}".($domain ? ":{$domain}" : '');

        return Cache::rememberForever($cacheKey, function () use ($enumType, $domain) {
            return static::query()
                ->where('enum_type', $enumType)
                ->when($domain, fn ($q) => $q->where('domain', $domain))
                ->active()
                ->orderBy('order')
                ->get();
        });
    }

    public static function flushCache(string $enumType, ?string $domain = null): void
    {
        Cache::forget("enum_options:{$enumType}".($domain ? ":{$domain}" : ''));
    }

    protected static function booted(): void
    {
        static::saved(fn (self $option) => static::flushCache($option->enum_type, $option->domain));
        static::deleted(fn (self $option) => static::flushCache($option->enum_type, $option->domain));
    }
}
