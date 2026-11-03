<?php

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Services\SupervisorInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->to(asset('landing/index.html')))->name('home');
Route::get('/login', fn () => Auth::check() ? to_route('dashboard') : view('login'))->name('login');
Route::post('/login', function (Request $request) {
    $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
    if (! Auth::attempt($credentials + ['is_active' => true])) {
        return back()->withErrors(['email' => 'Invalid email or password. / E-mel atau kata laluan tidak tepat.'])->onlyInput('email');
    }$request->session()->regenerate();

    return to_route('dashboard');
})->middleware('throttle:6,1');
Route::middleware(['auth', EnsureActiveAccount::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/images/{kind}', [ProfileController::class, 'image'])->name('profile.image');
    Route::get('/dashboard', function () {
        abort_unless(in_array(Auth::user()->role, ['superadmin', 'supervisor', 'intern']), 403);

        return Auth::user()->role === 'superadmin' ? to_route('management.dashboard') : to_route('workspace.overview');
    })->name('dashboard');
    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    })->name('logout');
    Route::prefix('management')->name('management.')->middleware('can:manage-internpulse')->group(function () {
        Route::get('/', [ManagementController::class, 'index'])->name('dashboard');
        Route::post('/supervisors', [ManagementController::class, 'store'])->middleware('throttle:20,1,management-invite:')->name('supervisors.store');
        Route::post('/invitations/{invitation}/resend', [ManagementController::class, 'resend'])->middleware('throttle:10,1,management-edit:')->name('invitations.resend');
        Route::post('/invitations/{invitation}/revoke', [ManagementController::class, 'revoke'])->name('invitations.revoke');
        Route::get('/import/template', [ManagementController::class, 'template'])->name('import.template');
        Route::post('/import/preview', [ManagementController::class, 'preview'])->middleware('throttle:10,1,management-edit:')->name('import.preview');
        Route::post('/import/send', [ManagementController::class, 'import'])->middleware('throttle:2,1,management-bulk:')->name('import.send');
    });
});
Route::middleware(['guest', 'throttle:20,1,management-invite:'])->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->name('invitation.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->where('token', '[A-Za-z0-9]{64}')->name('invitation.accept');
});

Route::middleware(['auth', EnsureActiveAccount::class])->prefix('workspace')->name('workspace.')->group(function () {
    Route::get('/overview', [WorkspaceController::class, 'overview'])->name('overview');
    Route::get('/team', [WorkspaceController::class, 'team'])->name('team');
    Route::post('/team/invite', function (Request $request, SupervisorInvitations $service) {
        abort_unless($request->user()->role === 'supervisor', 403);
        $request->merge(['supervisor_id' => $request->user()->id]);

        return app(WorkspaceController::class)->inviteIntern($request, $service);
    })->middleware('throttle:20,1')->name('team.invite');
    Route::get('/tasks', [WorkspaceController::class, 'tasks'])->name('tasks');
    Route::post('/tasks', [WorkspaceController::class, 'saveTask'])->name('tasks.store');
    Route::post('/tasks/{id}/edit', [WorkspaceController::class, 'saveTask'])->name('tasks.update');
    Route::post('/tasks/{id}/archive', [WorkspaceController::class, 'archiveTask'])->name('tasks.archive');
    Route::post('/tasks/{id}/progress', [WorkspaceController::class, 'progress'])->name('tasks.progress');
    Route::post('/tasks/{id}/due', [WorkspaceController::class, 'requestDue'])->name('tasks.due');
    Route::get('/reviews', [WorkspaceController::class, 'reviews'])->name('reviews');
    Route::post('/reviews/{type}/{id}', [WorkspaceController::class, 'review'])->name('review');
    Route::get('/records', [WorkspaceController::class, 'records'])->name('records');
    Route::post('/logbook', [WorkspaceController::class, 'saveLog'])->name('logbook.store');
    Route::post('/attendance', [WorkspaceController::class, 'checkIn'])->middleware('throttle:6,1,attendance:')->name('attendance.store');
    Route::get('/guides', [WorkspaceController::class, 'guides'])->name('guides');
    Route::get('/files/{type}/{id}', [WorkspaceController::class, 'download'])->name('files');
});
Route::middleware(['auth', EnsureActiveAccount::class, 'can:manage-internpulse'])->prefix('management')->name('management.')->group(function () {
    Route::get('/people', [WorkspaceController::class, 'people'])->name('people');
    Route::post('/people/{id}', [WorkspaceController::class, 'updatePerson'])->name('people.update');
    Route::post('/interns/invite', [WorkspaceController::class, 'inviteIntern'])->middleware('throttle:20,1,intern-invite:')->name('interns.invite');
    Route::get('/departments', [WorkspaceController::class, 'departments'])->name('departments');
    Route::post('/departments', [WorkspaceController::class, 'saveDepartment'])->name('departments.store');
    Route::post('/departments/{id}/edit', [WorkspaceController::class, 'saveDepartment'])->name('departments.update');
    Route::post('/departments/{id}/delete', [WorkspaceController::class, 'deleteDepartment'])->name('departments.delete');
    Route::post('/guides', [WorkspaceController::class, 'saveGuide'])->name('guides.store');
    Route::post('/guides/{id}/edit', [WorkspaceController::class, 'saveGuide'])->name('guides.update');
    Route::post('/guides/{id}/delete', [WorkspaceController::class, 'deleteGuide'])->name('guides.delete');
    Route::get('/reports', [WorkspaceController::class, 'reports'])->name('reports');
    Route::get('/reports/export', [WorkspaceController::class, 'export'])->name('reports.export');
    Route::get('/activity', [WorkspaceController::class, 'activity'])->name('activity');
});
