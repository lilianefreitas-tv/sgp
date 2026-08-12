<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ArtifactCodeGenerator
{
    public function next(int $organizationId): string
    {
        $number = DB::transaction(function () use ($organizationId): int {
            $organization = DB::table('organizations')->where('id', $organizationId)->lockForUpdate()->first(['next_artifact_number']);

            if ($organization === null) {
                throw new RuntimeException('Não foi possível reservar a sequência do artefato.');
            }

            $number = max(1, (int) $organization->next_artifact_number);
            DB::table('organizations')->where('id', $organizationId)->update(['next_artifact_number' => $number + 1, 'updated_at' => now()]);

            return $number;
        });

        return 'ART-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
    }
}
