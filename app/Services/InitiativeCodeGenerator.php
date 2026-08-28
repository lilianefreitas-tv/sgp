<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitiativeCodeGenerator
{
    public function next(int $organizationId): string
    {
        $number = DB::transaction(function () use ($organizationId): int {
            $organization = DB::table('organizations')->where('id', $organizationId)->lockForUpdate()->first(['next_initiative_number']);
            if ($organization === null) {
                throw new RuntimeException('Não foi possível reservar a sequência da iniciativa.');
            }
            $number = max(1, (int) $organization->next_initiative_number);
            DB::table('organizations')->where('id', $organizationId)->update(['next_initiative_number' => $number + 1, 'updated_at' => now()]);

            return $number;
        });

        return 'INI-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
