<?php

namespace App\Domains\Patients\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

/**
 * Title and content are translatable: French is required, English and
 * Arabic optional. A patient can only sign in a language whose title
 * AND content are filled (see availableLocales()).
 */
class ConsentTemplate extends Model
{
    use HasFactory, HasTranslations;

    public const LOCALES = ['fr', 'en', 'ar'];

    /** @var array<int, string> */
    public array $translatable = ['title', 'content'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'bool'];

    /** @return array<int, string> */
    public function availableLocales(): array
    {
        return array_values(array_filter(
            self::LOCALES,
            fn (string $locale) => filled($this->getTranslation('title', $locale, false))
                && filled($this->getTranslation('content', $locale, false)),
        ));
    }

    /** @return HasMany<Consent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }
}
