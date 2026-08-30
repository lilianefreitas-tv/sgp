<?php

namespace App\Models;

use App\Enums\TestCaseSeverity;
use App\Enums\TestCaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTestCase extends Model
{
    protected $fillable = [
        'organization_id', 'project_id', 'sequence', 'code', 'title', 'objective',
        'preconditions', 'test_data', 'steps', 'expected_result', 'severity', 'status',
        'assigned_tester_id', 'requirement_id', 'change_request_id', 'baseline_id',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'severity' => TestCaseSeverity::class,
            'status' => TestCaseStatus::class,
        ];
    }

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }
    public function assignedTester(): BelongsTo { return $this->belongsTo(User::class, 'assigned_tester_id'); }
    public function requirement(): BelongsTo { return $this->belongsTo(Requirement::class); }
    public function changeRequest(): BelongsTo { return $this->belongsTo(ChangeRequest::class); }
    public function baseline(): BelongsTo { return $this->belongsTo(ProjectBaseline::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function executions(): HasMany { return $this->hasMany(TestExecution::class, 'test_case_id')->latest('execution_number'); }

    public function latestExecution(): ?TestExecution
    {
        return $this->executions->sortByDesc('execution_number')->first();
    }
}
