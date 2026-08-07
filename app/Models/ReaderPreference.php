<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReaderPreference extends Model
{
    /**
     * Attributes that may be mass assigned.
     */
    protected $fillable = [
        'user_id',
        'theme',
        'font_size',
        'font_family',
        'line_spacing',
        'reading_mode',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'font_size' => 'integer',
            'line_spacing' => 'float',
        ];
    }

    /**
     * The customer these reader preferences belong to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}