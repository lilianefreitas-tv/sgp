<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const CODES = [
        'TMP-SGP-MOD-001' => 'MOD-001',
        'TMP-SGP-MOD-002' => 'MOD-002',
        'TMP-SGP-MOD-003' => 'MOD-003',
        'TMP-SGP-MOD-004' => 'MOD-004',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $sgpId = DB::table('organizations')->where('slug', 'sgp')->value('id');

            if ($sgpId === null) {
                return;
            }

            $codes = DB::table('document_templates')
                ->where('organization_id', $sgpId)
                ->orderBy('code')
                ->pluck('code')
                ->all();
            $temporary = array_keys(self::CODES);
            $final = array_values(self::CODES);
            sort($temporary);
            sort($final);

            if ($codes === $final) {
                return;
            }

            if ($codes !== $temporary) {
                throw new \LogicException(
                    'Transição BL-SGP-002 interrompida: os códigos temporários dos modelos do SGP estão incompletos.'
                );
            }

            foreach (self::CODES as $temporaryCode => $finalCode) {
                $updated = DB::table('document_templates')
                    ->where('organization_id', $sgpId)
                    ->where('code', $temporaryCode)
                    ->update(['code' => $finalCode]);

                if ($updated !== 1) {
                    throw new \LogicException(
                        "Transição BL-SGP-002 interrompida: o modelo {$temporaryCode} não pôde ser finalizado."
                    );
                }
            }
        }, 3);
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $sgpId = DB::table('organizations')->where('slug', 'sgp')->value('id');

            if ($sgpId === null) {
                return;
            }

            foreach (array_reverse(self::CODES, true) as $temporaryCode => $finalCode) {
                DB::table('document_templates')
                    ->where('organization_id', $sgpId)
                    ->where('code', $finalCode)
                    ->update(['code' => $temporaryCode]);
            }
        });
    }
};
