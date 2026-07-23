<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\KanbanController;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\Task;
use App\Enums\TaskStatus;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    $query = Project::query()->visibleTo(request()->user())->whereNull('archived_at');

    return view('dashboard', [
        'activeProjectsCount' => (clone $query)->where('is_active', true)->count(),
        'requirementsCount' => Requirement::query()
            ->where('is_active', true)
            ->whereIn('project_id', (clone $query)->select('id'))
            ->count(),
        'pendingTasksCount' => Task::query()
            ->where('is_active', true)
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereIn('project_id', (clone $query)->select('id'))
            ->count(),
        'completedTasksCount' => Task::query()
            ->where('is_active', true)
            ->where('status', TaskStatus::Completed->value)
            ->whereIn('project_id', (clone $query)->select('id'))
            ->count(),
        'recentProjects' => (clone $query)->with(['client', 'manager'])->latest('updated_at')->limit(5)->get(),
    ]);
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('clients', ClientController::class)->except(['show', 'destroy']);
    Route::resource('projects', ProjectController::class)->except('destroy');
    Route::get('/requirements', [RequirementController::class, 'overview'])->name('requirements.index');
    Route::get('/tasks', [TaskController::class, 'overview'])->name('tasks.index');
    Route::get('/kanban', [KanbanController::class, 'overview'])->name('kanban.index');
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
});

Route::middleware(['auth', 'administrator'])->group(function () {
    Route::resource('users', UserController::class)->except(['show', 'destroy']);
});

require __DIR__.'/auth.php';
