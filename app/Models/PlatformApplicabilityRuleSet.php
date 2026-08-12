<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PlatformApplicabilityRuleSet extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['effective_from' => 'datetime', 'retired_at' => 'datetime', 'activated_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            if ($model->getOriginal('status') === 'active') throw new LogicException('Conjuntos ativos de regras não podem ser editados.');
        });
    }
    public function rules(): HasMany { return $this->hasMany(PlatformApplicabilityRule::class, 'rule_set_id'); }
}
