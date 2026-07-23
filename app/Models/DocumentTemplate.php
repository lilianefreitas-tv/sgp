<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'created_by',
        'name',
        'description',
        'type',
        'version',
        'header_text',
        'footer_text',
        'is_active',
    ];

    protected static function booted(): void
    {
        static::creating(function (DocumentTemplate $template): void {
            if (blank($template->code)) {
                $sequence = ((int) self::query()->max('id')) + 1;
                $template->forceFill([
                    'code' => 'MOD-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
                ]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'version' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }
}
