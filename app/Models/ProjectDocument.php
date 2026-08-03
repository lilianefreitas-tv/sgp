<?php

namespace App\Models;

use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    protected $fillable = [
        'project_id',
        'document_template_id',
        'generated_by',
        'type',
        'title',
        'version',
        'disk',
        'docx_path',
        'pdf_path',
        'docx_file_name',
        'docx_sha256',
        'pdf_file_name',
        'pdf_sha256',
        'metadata',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'version' => 'integer',
            'metadata' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'document_template_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function versionLabel(): string
    {
        return 'v'.$this->version.'.0';
    }
}
