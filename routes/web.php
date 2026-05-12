<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [App\Http\Controllers\Auth\RegisterController::class, 'showRegistrationForm'])
        ->name('register');
    Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register']);
    Route::get('/login', [App\Http\Controllers\Auth\LoginController::class, 'showLoginForm'])
        ->name('login');
    Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
});

Route::post('/logout', [App\Http\Controllers\Auth\LogoutController::class, 'store'])
    ->name('logout');

Route::get('/sections-by-office/{office}', function (App\Models\Office $office) {
    return $office->sections()->orderBy('name')->pluck('name', 'id');
})->name('sections-by-office');

Route::middleware('auth')->group(function () {
    Route::get('/dtr', [App\Http\Controllers\DtrController::class, 'index'])
        ->name('dtr.index');
    Route::get('/dtr/show', [App\Http\Controllers\DtrController::class, 'show'])
        ->name('dtr.show');

    Route::get('/dtr/edit-request', function () {
        return redirect()->route('dtr.index');
    });
    Route::post('/dtr/edit-request', [App\Http\Controllers\DtrEditRequestController::class, 'store'])
        ->name('dtr.edit-request.store');
    Route::post('/dtr/edit-request/{edit_request}/approve', [App\Http\Controllers\DtrEditRequestController::class, 'approve'])
        ->name('dtr.edit-request.approve');
    Route::post('/dtr/edit-request/{edit_request}/reject', [App\Http\Controllers\DtrEditRequestController::class, 'reject'])
        ->name('dtr.edit-request.reject');

    Route::post('/notifications/mark-read', [App\Http\Controllers\DtrEditRequestController::class, 'markAsRead'])
        ->name('notifications.mark-read');

    Route::get('/supervisor/pending', [App\Http\Controllers\SupervisorController::class, 'pending'])
        ->name('supervisor.pending');
});

Route::middleware(['auth', 'super'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [App\Http\Controllers\AdminController::class, 'dashboard'])
        ->name('dashboard');
    Route::get('/offices', [App\Http\Controllers\AdminController::class, 'offices'])
        ->name('offices');
    Route::post('/offices', [App\Http\Controllers\AdminController::class, 'storeOffice'])
        ->name('offices.store');
    Route::delete('/offices/{office}', [App\Http\Controllers\AdminController::class, 'deleteOffice'])
        ->name('offices.delete');
    Route::post('/offices/{office}/assign-supervisor', [App\Http\Controllers\AdminController::class, 'assignOfficeSupervisor'])
        ->name('offices.assign-supervisor');
    Route::post('/offices/{office}/assign-senior-manager', [App\Http\Controllers\AdminController::class, 'assignSeniorManager'])
        ->name('offices.assign-senior-manager');
    Route::get('/sections', [App\Http\Controllers\AdminController::class, 'sections'])
        ->name('sections');
    Route::post('/sections', [App\Http\Controllers\AdminController::class, 'storeSection'])
        ->name('sections.store');
    Route::delete('/sections/{section}', [App\Http\Controllers\AdminController::class, 'deleteSection'])
        ->name('sections.delete');
    Route::post('/sections/{section}/assign-supervisor', [App\Http\Controllers\AdminController::class, 'assignSectionSupervisor'])
        ->name('sections.assign-supervisor');
    Route::get('/employees', [App\Http\Controllers\AdminController::class, 'employees'])
        ->name('employees');
    Route::post('/employees/{employee}/assign', [App\Http\Controllers\AdminController::class, 'assignEmployee'])
        ->name('employees.assign');
    Route::post('/employees/{employee}/reset-password', [App\Http\Controllers\AdminController::class, 'resetPassword'])
        ->name('employees.reset-password');
    Route::get('/settings', [App\Http\Controllers\AdminController::class, 'settings'])
        ->name('settings');
    Route::post('/settings', [App\Http\Controllers\AdminController::class, 'updateSettings'])
        ->name('settings.update');
});
