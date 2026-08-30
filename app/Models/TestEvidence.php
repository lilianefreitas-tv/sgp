<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestEvidence extends Model
{
    protected $table = 'test_evidences';

    protected $fillable = [
        'organization_id', 'test_execution_id', 'uploaded_by', 'disk', 'path',
        'original_name', 'mime_type', 'size_bytes', 'sha256', 'description',
    ];

    protected function casts(): array { return ['size_bytes' => 'integer']; }

    public function execution(): BelongsTo { return $this->belongsTo(TestExecution::class, 'test_execution_id'); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }

    public function formattedSize(): string
    {
        if ($this->size_bytes < 1024) return $this->size_bytes.' B';
        if ($this->size_bytes < 1048576) return number_format($this->size_bytes / 1024, 1, ',', '.').' KB';
        return number_format($this->size_bytes / 1048576, 1, ',', '.').' MB';
    }
}
