<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\InternSupportController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\Management\DepartmentController;
use App\Http\Controllers\Management\GuideController as ManagementGuideController;
use App\Http\Controllers\Management\PeopleController;
use App\Http\Controllers\Management\ReportController;
use App\Http\Controllers\ManagementController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PasswordRecoveryController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfileSecurityController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Services\SupervisorInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->to(asset('landing/index.html')))->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'send'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'form'])->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
});
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1,login:')->name('login.store');
Route::get('/login/two-factor', [TwoFactorChallengeController::class, 'show'])->name('login.two-factor');
Route::post('/login/two-factor', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:6,1,two-factor:')->name('login.two-factor.store');
Route::post('/login/two-factor/cancel', [TwoFactorChallengeController::class, 'destroy'])->name('login.two-factor.cancel');
Route::middleware(['auth', EnsureActiveAccount::class])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/reminders', [ProfileController::class, 'reminders'])->middleware('throttle:10,1')->name('profile.reminders');
    Route::post('/profile/password', [ProfileSecurityController::class, 'password'])->middleware('throttle:10,1')->name('profile.password');
    Route::post('/profile/two-factor', [ProfileSecurityController::class, 'start'])->middleware('throttle:10,1')->name('profile.two-factor.start');
    Route::post('/profile/two-factor/cancel', [ProfileSecurityController::class, 'cancel'])->name('profile.two-factor.cancel');
    Route::post('/profile/two-factor/enable', [ProfileSecurityController::class, 'enable'])->middleware('throttle:10,1')->name('profile.two-factor.enable');
    Route::post('/profile/two-factor/disable', [ProfileSecurityController::class, 'disable'])->middleware('throttle:10,1')->name('profile.two-factor.disable');
    Route::post('/profile/two-factor/recovery-codes', [ProfileSecurityController::class, 'regenerate'])->middleware('throttle:10,1')->name('profile.two-factor.recovery');
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
    Route::get('/notifications', [NotificationController::class, 'notifications'])->name('notifications');
    Route::post('/notifications/read', [NotificationController::class, 'readNotifications'])->name('notifications.read');
    Route::get('/tasks/{id}', [TaskController::class, 'taskDetail'])->whereNumber('id')->name('tasks.show');
    Route::post('/tasks/{id}/comments', [TaskController::class, 'comment'])->middleware('throttle:30,1')->name('tasks.comment');
    Route::post('/tasks/{id}/blockers', [InternSupportController::class, 'createBlocker'])->whereNumber('id')->name('tasks.blockers.store');
    Route::post('/blockers/{id}/resolve', [InternSupportController::class, 'resolveBlocker'])->whereNumber('id')->name('blockers.resolve');
    Route::get('/evaluations/{id}', [InternSupportController::class, 'showEvaluations'])->whereNumber('id')->name('evaluations.show');
    Route::post('/evaluations/{id}', [InternSupportController::class, 'evaluation'])->whereNumber('id')->name('evaluations.save');
    Route::post('/tasks/{id}/decision', [TaskController::class, 'decideTask'])->name('tasks.decision');
    Route::get('/overview', [WorkspaceController::class, 'overview'])->name('overview');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::get('/team', [TeamController::class, 'team'])->name('team');
    Route::post('/team/invite', function (Request $request, SupervisorInvitations $service) {
        Gate::authorize('workspace.team');
        $request->merge(['supervisor_id' => $request->user()->id]);

        return app(TeamController::class)->inviteIntern($request, $service);
    })->middleware('throttle:20,1')->name('team.invite');
    Route::get('/tasks', [TaskController::class, 'tasks'])->name('tasks');
    Route::post('/tasks', [TaskController::class, 'saveTask'])->name('tasks.store');
    Route::post('/tasks/{id}/edit', [TaskController::class, 'saveTask'])->name('tasks.update');
    Route::post('/tasks/{id}/archive', [TaskController::class, 'archiveTask'])->name('tasks.archive');
    Route::post('/tasks/{id}/progress', [TaskController::class, 'progress'])->name('tasks.progress');
    Route::post('/tasks/{id}/due', [TaskController::class, 'requestDue'])->name('tasks.due');
    Route::get('/reviews', [ReviewController::class, 'reviews'])->name('reviews');
    Route::post('/reviews/{type}/{id}', [ReviewController::class, 'review'])->name('review');
    Route::get('/records', [AttendanceController::class, 'records'])->name('records');
    Route::post('/logbook', [LogbookController::class, 'store'])->name('logbook.store');
    Route::get('/logbook', [LogbookController::class, 'index'])->name('logbook');
    Route::get('/logbook/photos/{id}', [LogbookController::class, 'photo'])->name('logbook.photo');
    Route::post('/logbook/documents', [LogbookController::class, 'submit'])->name('logbook.submit');
    Route::post('/logbook/documents/{id}/verify', [LogbookController::class, 'verify'])->name('logbook.verify');
    Route::post('/logbook/documents/{id}/return', [LogbookController::class, 'returnForChanges'])->whereNumber('id')->name('logbook.return');
    Route::post('/logbook/documents/{id}/resubmit', [LogbookController::class, 'resubmit'])->whereNumber('id')->name('logbook.resubmit');
    Route::get('/logbook/documents/{id}/receipt', [LogbookController::class, 'receipt'])->name('logbook.receipt');
    Route::get('/logbook/documents/{id}/{kind}', [LogbookController::class, 'file'])->name('logbook.file');
    Route::post('/attendance', [AttendanceController::class, 'checkIn'])->middleware('throttle:6,1,attendance:')->name('attendance.store');
    Route::get('/guides', [GuideController::class, 'guides'])->name('guides');
    Route::get('/files/{type}/{id}', [FileController::class, 'download'])->name('files');
});
Route::middleware(['auth', EnsureActiveAccount::class, 'can:manage-internpulse'])->prefix('management')->name('management.')->group(function () {
    Route::get('/people', [PeopleController::class, 'people'])->name('people');
    Route::post('/people/{id}', [PeopleController::class, 'updatePerson'])->name('people.update');
    Route::post('/people/{id}/internship', [PeopleController::class, 'updateInternship'])->whereNumber('id')->name('people.internship');
    Route::post('/people/{id}/two-factor', [PeopleController::class, 'resetTwoFactor'])->whereNumber('id')->middleware('throttle:10,1,management-edit:')->name('people.two-factor');
    Route::post('/interns/invite', [TeamController::class, 'inviteIntern'])->middleware('throttle:20,1,intern-invite:')->name('interns.invite');
    Route::get('/departments', [DepartmentController::class, 'departments'])->name('departments');
    Route::post('/departments', [DepartmentController::class, 'saveDepartment'])->name('departments.store');
    Route::post('/departments/{id}/edit', [DepartmentController::class, 'saveDepartment'])->name('departments.update');
    Route::post('/departments/{id}/delete', [DepartmentController::class, 'deleteDepartment'])->name('departments.delete');
    Route::post('/guides', [ManagementGuideController::class, 'saveGuide'])->name('guides.store');
    Route::post('/guides/{id}/edit', [ManagementGuideController::class, 'saveGuide'])->name('guides.update');
    Route::post('/guides/{id}/delete', [ManagementGuideController::class, 'deleteGuide'])->name('guides.delete');
    Route::get('/reports', [ReportController::class, 'reports'])->name('reports');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('/activity', [ManagementController::class, 'activity'])->name('activity');
    Route::get('/health', [SystemHealthController::class, 'index'])->name('health');
});
