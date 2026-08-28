<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'uploaded_by',
        'deleted_by',
        'context_type',
        'context_id',
        'attachment_kind',
        'is_origin_document',
        'origin_series_uuid',
        'origin_category',
        'origin_title',
        'external_reference',
        'original_document_date',
        'declared_version',
        'origin_version',
        'origin_status',
        'replaces_attachment_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'extension',
        'size_bytes',
        'sha256',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'is_origin_document' => 'boolean',
            'original_document_date' => 'date',
            'origin_version' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function replacedVersion(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_attachment_id');
    }

    public function formattedSize(): string
    {
        if ($this->size_bytes < 1024) {
            return $this->size_bytes.' B';
        }

        if ($this->size_bytes < 1048576) {
            return number_format($this->size_bytes / 1024, 1, ',', '.').' KB';
        }

        return number_format($this->size_bytes / 1048576, 1, ',', '.').' MB';
    }
}
