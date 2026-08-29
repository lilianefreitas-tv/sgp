<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTestEvidenceRequest;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectTestCase;
use App\Models\TestEvidence;
use App\Models\TestExecution;
use App\Services\OrganizationAuditService;
use App\Services\OrganizationStoragePath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TestEvidenceController extends Controller
{
    public function store(StoreTestEvidenceRequest $request, Project $project, ProjectTestCase $testCase, TestExecution $execution, OrganizationStoragePath $paths, OrganizationAuditService $audit): RedirectResponse
    {
        $this->nested($project, $testCase, $execution);
        $file = $request->file('file');
        $temporaryPath = $file->getRealPath();
        abort_if($temporaryPath === false, 500, 'Não foi possível identificar o arquivo recebido.');
        $sha256 = hash_file('sha256', $temporaryPath);
        abort_if($sha256 === false, 500, 'Não foi possível calcular a integridade da evidência.');
        $disk = (string) config('sgp.storage.private_disk', 'local');
        $path = $file->store($paths->testEvidence($project, $testCase, $execution), $disk);
        abort_if($path === false, 500, 'Não foi possível armazenar a evidência.');

        try {
            $evidence = DB::transaction(fn (): TestEvidence => $execution->evidences()->create([
                'organization_id' => $project->organization_id, 'uploaded_by' => $request->user()->id,
                'disk' => $disk, 'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
                'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(), 'sha256' => $sha256,
                'description' => $request->string('description')->trim()->toString() ?: null,
            ]));
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);
            throw $exception;
        }
        ProjectActivity::record($project, $request->user(), 'test_evidence_uploaded', 'Evidência de teste vinculada', 'test_case', $testCase->id, [
            'details' => $testCase->code.' · execução '.$execution->execution_number.' · '.$evidence->original_name,
        ]);
        $audit->record('test.evidence.upload', 'success', 'test_evidence', $evidence->id, ['test_case_id' => $testCase->id, 'sha256' => $sha256]);
        return back()->with('success', 'Evidência vinculada à execução.');
    }

    public function download(Request $request, Project $project, ProjectTestCase $testCase, TestExecution $execution, TestEvidence $evidence, OrganizationAuditService $audit): StreamedResponse
    {
        $this->nested($project, $testCase, $execution);
        abort_unless($evidence->test_execution_id === $execution->id && $request->user()->canAccessProject($project), 404);
        if (! Storage::disk($evidence->disk)->exists($evidence->path)) {
            $audit->record('test.evidence.download', 'failed', 'test_evidence', $evidence->id, ['reason' => 'file_missing']);
            abort(404, 'A evidência não está disponível no armazenamento privado.');
        }
        $audit->record('test.evidence.download', 'success', 'test_evidence', $evidence->id, ['sha256' => $evidence->sha256]);
        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }

    private function nested(Project $project, ProjectTestCase $case, TestExecution $execution): void
    {
        abort_unless($case->project_id === $project->id && $execution->test_case_id === $case->id, 404);
    }
}
