<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProjectCodeGenerator
{
    public function next(int $organizationId): string
    {
        $number = DB::transaction(function () use ($organizationId): int {
            $organization = DB::table('organizations')
                ->where('id', $organizationId)
                ->lockForUpdate()
                ->first(['next_project_number']);

            if ($organization === null) {
                throw new RuntimeException('Não foi possível reservar a sequência do projeto.');
            }

            $number = max(1, (int) $organization->next_project_number);

            DB::table('organizations')
                ->where('id', $organizationId)
                ->update([
                    'next_project_number' => $number + 1,
                    'updated_at' => now(),
                ]);

            return $number;
        });

        return 'PRJ-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }
}
