<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifact_publications', function (Blueprint $table): void {
            $table->dropUnique('artifact_publications_revision_round_unique');
            $table->string('mode', 30)->default('individual')->after('sequence');
            $table->string('audience', 30)->default('internal')->after('mode');
            $table->string('purpose', 255)->nullable()->after('audience');
            $table->unsignedBigInteger('reference_revision_id')->nullable()->after('purpose');
            $table->json('selection')->nullable()->after('reference_revision_id');
            $table->string('publication_fingerprint', 64)->default('legacy')->after('selection');

            $table->foreign(['reference_revision_id', 'organization_id'], 'artifact_publications_reference_org_fk')
                ->references(['id', 'organization_id'])->on('artifact_revisions')->restrictOnDelete();
            $table->index(['reference_revision_id', 'organization_id'], 'artifact_publications_reference_org_idx');
            $table->unique(['artifact_revision_id', 'artifact_workflow_round_id', 'publication_fingerprint'], 'artifact_publications_revision_round_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('artifact_publications', function (Blueprint $table): void {
            $table->dropUnique('artifact_publications_revision_round_fingerprint_unique');
            $table->dropForeign('artifact_publications_reference_org_fk');
            $table->dropIndex('artifact_publications_reference_org_idx');
            $table->dropColumn(['mode', 'audience', 'purpose', 'reference_revision_id', 'selection', 'publication_fingerprint']);
            $table->unique(['artifact_revision_id', 'artifact_workflow_round_id'], 'artifact_publications_revision_round_unique');
        });
    }
};
