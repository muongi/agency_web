<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceProduct extends Model
{
    /** Product categories as printed on the SUNU Vie visa register. */
    public const CATEGORIES = ['Retraite', 'Projet', 'Education', 'Funérailles', 'Santé', 'Autre'];

    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'quarterly', 'annual'];

    protected $fillable = [
        'business_unit_id',
        'code',
        'display_order',
        'name_fr',
        'name_en',
        'visa_number',
        'category',
        'guarantees_description',
        'family',
        'default_premium_frequency',
        'default_premium_amount',
        'policy_cost_amount',
        'sunulife_product_code',
        'is_active',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'default_premium_amount' => 'decimal:2',
        'policy_cost_amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** Catalogue order used by the back office and the POS dropdown. */
    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order')->orderBy('code');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(SunuPolicy::class, 'insurance_product_id');
    }

    /** Localized product name for the current app locale. */
    public function localizedName(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        if ($locale === 'en' && filled($this->name_en)) {
            return (string) $this->name_en;
        }

        return (string) ($this->name_fr ?: $this->name_en ?: $this->code);
    }

    public static function categoryLabel(?string $category): string
    {
        if (! $category) {
            return '—';
        }
        $key = 'common.sunu_category_'.$category;
        $translated = __($key);

        return $translated === $key ? $category : $translated;
    }

    public static function frequencyLabel(?string $frequency): string
    {
        if (! $frequency) {
            return '—';
        }
        $key = 'common.sunu_frequency_'.$frequency;
        $translated = __($key);

        return $translated === $key ? $frequency : $translated;
    }
}
