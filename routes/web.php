<?php

use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\ArtifactPublicationController;
use App\Http\Controllers\ArtifactWorkflowController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CommercialJourneyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\InitiativeConversionController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\OrganizationAuditController;
use App\Http\Controllers\OrganizationContextController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\PlatformOrganizationController;
use App\Http\Controllers\PlatformUserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectAttachmentController;
use App\Http\Controllers\ProjectCommentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectContractController;
use App\Http\Controllers\ProjectBaselineController;
use App\Http\Controllers\ProjectOriginDocumentController;
use App\Http\Controllers\ProjectHistoryController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectScheduleController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'active', 'verified', 'organization'])
    ->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'active', 'organization'])->group(function () {
    Route::put('/organization-context', [OrganizationContextController::class, 'update'])
        ->name('organization-context.update');
    Route::resource('clients', ClientController::class)->except(['show', 'destroy']);
    Route::get('initiatives', [InitiativeConversionController::class, 'index'])->name('initiatives.index');
    Route::get('initiatives/create', [InitiativeConversionController::class, 'create'])->name('initiatives.create');
    Route::post('initiatives', [InitiativeConversionController::class, 'store'])->name('initiatives.store');
    Route::get('initiatives/{initiative}/conversion', [InitiativeConversionController::class, 'show'])->name('initiatives.conversion.show');
    Route::post('initiatives/{initiative}/conversion', [InitiativeConversionController::class, 'convert'])->name('initiatives.conversion.convert');
    Route::get('initiatives/{initiative}/artifacts', [ArtifactController::class, 'initiativeIndex'])->name('initiatives.artifacts.index');
    Route::post('initiatives/{initiative}/artifacts', [ArtifactController::class, 'storeForInitiative'])->name('initiatives.artifacts.store');
    Route::post('initiatives/{initiative}/documents/dossier', [ArtifactController::class, 'synchronizeInitiativeDossier'])->name('initiatives.documents.dossier');
    Route::get('commercial', [CommercialJourneyController::class, 'index'])->name('commercial.index');
    Route::get('initiatives/{initiative}/commercial', [CommercialJourneyController::class, 'show'])->name('commercial.show');
    Route::post('initiatives/{initiative}/commercial/opportunities', [CommercialJourneyController::class, 'storeOpportunity'])->name('commercial.opportunities.store');
    Route::post('opportunities/{opportunity}/assessments', [CommercialJourneyController::class, 'assessment'])->name('commercial.assessments.store');
    Route::post('opportunities/{opportunity}/proposals', [CommercialJourneyController::class, 'proposal'])->name('commercial.proposals.store');
    Route::post('opportunities/{opportunity}/negotiations', [CommercialJourneyController::class, 'negotiation'])->name('commercial.negotiations.store');
    Route::patch('opportunities/{opportunity}/state', [CommercialJourneyController::class, 'transition'])->name('commercial.opportunities.transition');
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::resource('contracts', ProjectContractController::class)->except('destroy');
    Route::get('contracts/{contract}/attachments/{attachment}', [ProjectContractController::class, 'download'])->name('contracts.attachments.download');
    Route::get('projects/{project}/artifacts', [ArtifactController::class, 'projectIndex'])->name('projects.artifacts.index');
    Route::post('projects/{project}/artifacts', [ArtifactController::class, 'storeForProject'])->name('projects.artifacts.store');
    Route::get('artifact-pendencies', [ArtifactController::class, 'pending'])->name('artifacts.pending');
    Route::get('artifacts/{artifact}', [ArtifactController::class, 'show'])->name('artifacts.show');
    Route::post('artifacts/{artifact}/revisions', [ArtifactController::class, 'revise'])->name('artifacts.revisions.store');
    Route::patch('artifacts/{artifact}/archive', [ArtifactController::class, 'archive'])->name('artifacts.archive');
    Route::post('artifacts/{artifact}/workflow/assignments', [ArtifactWorkflowController::class, 'assign'])->name('artifacts.workflow.assignments.store');
    Route::post('artifacts/{artifact}/workflow/submit', [ArtifactWorkflowController::class, 'submit'])->name('artifacts.workflow.submit');
    Route::post('artifact-workflow-rounds/{round}/decision', [ArtifactWorkflowController::class, 'decide'])->name('artifacts.workflow.decide');
    Route::post('artifacts/{artifact}/publications', [ArtifactPublicationController::class, 'store'])->name('artifacts.publications.store');
    Route::get('artifact-publications/{publication}/download', [ArtifactPublicationController::class, 'download'])->middleware('audit.file-boundary')->name('artifact-publications.download');
    Route::patch('artifact-publications/{publication}/revoke', [ArtifactPublicationController::class, 'revoke'])->name('artifact-publications.revoke');
    Route::get('/organization-members', [OrganizationMemberController::class, 'index'])->name('organization-members.index');
    Route::post('/organization-members', [OrganizationMemberController::class, 'store'])->name('organization-members.store');
    Route::patch('/organization-members/{membership}', [OrganizationMemberController::class, 'update'])->name('organization-members.update');
    Route::delete('/organization-members/{membership}', [OrganizationMemberController::class, 'destroy'])->name('organization-members.destroy');
    Route::get('/requirements', [RequirementController::class, 'overview'])->name('requirements.index');
    Route::get('/tasks', [TaskController::class, 'overview'])->name('tasks.index');
    Route::get('/kanban', [KanbanController::class, 'overview'])->name('kanban.index');
    Route::get('/documents', [DocumentController::class, 'overview'])->name('documents.index');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::patch('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::patch('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
    Route::post('/projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::delete('/projects/{project}/members/{user}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
    Route::resource('projects.requirements', RequirementController::class)->except('destroy');
    Route::patch('/projects/{project}/requirements/{requirement}/deactivate', [RequirementController::class, 'deactivate'])->name('projects.requirements.deactivate');
    Route::patch('/projects/{project}/requirements/{requirement}/reactivate', [RequirementController::class, 'reactivate'])->name('projects.requirements.reactivate');
    Route::resource('projects.tasks', TaskController::class)->except('destroy');
    Route::patch('/projects/{project}/tasks/{task}/deactivate', [TaskController::class, 'deactivate'])->name('projects.tasks.deactivate');
    Route::patch('/projects/{project}/tasks/{task}/reactivate', [TaskController::class, 'reactivate'])->name('projects.tasks.reactivate');
    Route::get('/projects/{project}/kanban', [KanbanController::class, 'show'])->name('projects.kanban.show');
    Route::patch('/projects/{project}/kanban/tasks/{task}/move', [KanbanController::class, 'move'])->name('projects.kanban.tasks.move');
    Route::patch('/projects/{project}/kanban/columns', [KanbanController::class, 'updateColumns'])->name('projects.kanban.columns.update');
    Route::get('/projects/{project}/documents', [DocumentController::class, 'index'])->name('projects.documents.index');
    Route::get('/projects/{project}/documents/information', [DocumentController::class, 'editSetup'])->name('projects.documents.setup.edit');
    Route::put('/projects/{project}/documents/information', [DocumentController::class, 'updateSetup'])->name('projects.documents.setup.update');
    Route::post('/projects/{project}/documents/generate', [DocumentController::class, 'generate'])->name('projects.documents.generate');
    Route::get('/projects/{project}/documents/{document}/download/{format}', [DocumentController::class, 'download'])
        ->middleware('audit.file-boundary')
        ->name('projects.documents.download');
    Route::get('/projects/{project}/comments', [ProjectCommentController::class, 'index'])->name('projects.comments.index');
    Route::post('/projects/{project}/comments', [ProjectCommentController::class, 'store'])->name('projects.comments.store');
    Route::get('/projects/{project}/attachments', [ProjectAttachmentController::class, 'index'])->name('projects.attachments.index');
    Route::get('/projects/{project}/origin-documents', [ProjectOriginDocumentController::class, 'index'])->name('projects.origin-documents.index');
    Route::get('/projects/{project}/baselines', [ProjectBaselineController::class, 'index'])->name('projects.baselines.index');
    Route::post('/projects/{project}/baselines', [ProjectBaselineController::class, 'store'])->name('projects.baselines.store');
    Route::get('/projects/{project}/baselines/{baseline}', [ProjectBaselineController::class, 'show'])->name('projects.baselines.show');
    Route::post('/projects/{project}/origin-documents', [ProjectOriginDocumentController::class, 'store'])->name('projects.origin-documents.store');
    Route::post('/projects/{project}/origin-baseline', [ProjectOriginDocumentController::class, 'establishBaseline'])->name('projects.origin-baseline.store');
    Route::post('/projects/{project}/attachments', [ProjectAttachmentController::class, 'store'])->name('projects.attachments.store');
    Route::get('/projects/{project}/attachments/{attachment}/download', [ProjectAttachmentController::class, 'download'])
        ->middleware('audit.file-boundary')
        ->name('projects.attachments.download');
    Route::delete('/projects/{project}/attachments/{attachment}', [ProjectAttachmentController::class, 'destroy'])->name('projects.attachments.destroy');
    Route::get('/projects/{project}/history', [ProjectHistoryController::class, 'index'])->name('projects.history.index');
    Route::get('/projects/{project}/calendar', [CalendarController::class, 'project'])->name('projects.calendar.index');
    Route::get('/projects/{project}/schedule', ProjectScheduleController::class)->name('projects.schedule.show');
});

Route::middleware(['auth', 'active', 'administrator'])->prefix('platform')->name('platform.')->group(function () {
    Route::resource('organizations', PlatformOrganizationController::class)->except(['show', 'destroy']);
    Route::resource('users', PlatformUserController::class)->except(['show', 'destroy']);
    Route::post('/users/{user}/password-reset-link', [PlatformUserController::class, 'sendPasswordResetLink'])
        ->middleware('throttle:6,1')
        ->name('users.password-reset-link');
    Route::post('/organizations/{organization}/access', [PlatformOrganizationController::class, 'access'])
        ->name('organizations.access');
    Route::delete('/organization-access', [PlatformOrganizationController::class, 'leave'])
        ->name('organization-access.leave');
});

Route::post('/organization-members/{membership}/password-reset-link', [OrganizationMemberController::class, 'sendPasswordResetLink'])
    ->middleware(['auth', 'active', 'organization', 'throttle:6,1'])
    ->name('organization-members.password-reset-link');

Route::middleware(['auth', 'active', 'organization'])->group(function () {
    Route::resource('document-templates', DocumentTemplateController::class)->except(['show', 'destroy']);
    Route::patch('/document-templates/{documentTemplate}/deactivate', [DocumentTemplateController::class, 'deactivate'])->name('document-templates.deactivate');
    Route::patch('/document-templates/{documentTemplate}/reactivate', [DocumentTemplateController::class, 'reactivate'])->name('document-templates.reactivate');
});

Route::get('/audit', OrganizationAuditController::class)
    ->middleware(['auth', 'active', 'organization'])
    ->name('audit.index');

require __DIR__.'/auth.php';
