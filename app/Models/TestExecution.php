<?php

namespace App\Models;

use App\Enums\TestExecutionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class TestExecution extends Model
{
    protected $fillable = [
        'organization_id', 'test_case_id', 'execution_number', 'result', 'case_snapshot', 'environment',
        'observed_result', 'notes', 'defect_reference', 'executed_by', 'executed_at',
    ];

    protected function casts(): array
    {
        return ['result' => TestExecutionResult::class, 'case_snapshot' => 'array', 'executed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Execuções de teste são imutáveis. Registre uma nova execução.'));
        static::deleting(fn () => throw new LogicException('Execuções de teste não podem ser removidas.'));
    }

    public function testCase(): BelongsTo { return $this->belongsTo(ProjectTestCase::class, 'test_case_id'); }
    public function executor(): BelongsTo { return $this->belongsTo(User::class, 'executed_by'); }
    public function evidences(): HasMany { return $this->hasMany(TestEvidence::class, 'test_execution_id')->oldest(); }
}
