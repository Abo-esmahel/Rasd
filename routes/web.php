<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\NoteController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// PWA — نسخة الجوال القابلة للتثبيت (عامة، بدون تسجيل دخول)
Route::get('/pwa', function () {
    return response()->file(public_path('pwa/index.html'));
})->name('pwa');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('notes.index');
    })->name('dashboard');

    Route::resource('notes', NoteController::class);
    Route::get('/my-notes', [NoteController::class, 'myNotes'])->name('notes.my');

    Route::post('/notes/{note}/send', [NoteController::class, 'send'])->name('notes.send');
    Route::post('/notes/{note}/accept', [NoteController::class, 'accept'])->name('notes.accept');
    Route::post('/notes/{note}/reject', [NoteController::class, 'reject'])->name('notes.reject');
    Route::post('/notes/{note}/resend', [NoteController::class, 'resend'])->name('notes.resend');

    Route::post('/notes/{note}/attachments', [NoteController::class, 'storeAttachment'])->name('notes.attachments.store');
    Route::delete('/notes/{note}/attachments/{attachment}', [NoteController::class, 'destroyAttachment'])->name('notes.attachments.destroy');
    Route::get('/attachments/{attachment}/view', [NoteController::class, 'viewAttachment'])->name('notes.attachments.view');
    Route::get('/attachments/{attachment}/download', [NoteController::class, 'downloadAttachment'])->name('notes.attachments.download');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/{id}', [ProfileController::class, 'show'])->whereNumber('id')->name('profile.showUser');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/ranking', [ProfileController::class, 'ranking'])->name('ranking');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.markRead');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markOneAsRead'])->name('notifications.markOneRead');

    Route::get('/print-test', function(){ return view('print-test'); })->name('print.test');

    Route::get('/general-submissions', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'index'])->name('general-submissions.index');
    Route::get('/general-submissions/create', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'create'])->name('general-submissions.create');
    Route::post('/general-submissions', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'store'])->name('general-submissions.store');
    Route::get('/general-submissions/{generalSubmission}', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'show'])->name('general-submissions.show');
    Route::post('/general-submissions/{generalSubmission}/accept', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'accept'])->name('general-submissions.accept');
    Route::post('/general-submissions/{generalSubmission}/reject', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'reject'])->name('general-submissions.reject');

    Route::get('/submission-attachments/{attachment}/view', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'viewAttachment'])->name('submission-attachments.view');
    Route::get('/submission-attachments/{attachment}/download', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'downloadAttachment'])->name('submission-attachments.download');

    // TEMP DIAGNOSTIC - Web Runtime (remove after verification)
    Route::get('/_diag/runtime', function () {
        $user = auth()->user();
        return response()->json([
            'APP_ENV' => config('app.env'),
            'APP_URL' => config('app.url'),
            'DB_CONNECTION' => config('database.default'),
            'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
            'DB_HOST' => config('database.connections.'.config('database.default').'.host') ?? 'sqlite',
            'Cloudinary_cloud_name' => config('filesystems.disks.cloudinary.cloud_name'),
            'Cloudinary_folder' => config('filesystems.disks.cloudinary.folder'),
            'Cloudinary_SDK_version' => \Cloudinary\Cloudinary::VERSION ?? 'unknown',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'max_input_time' => ini_get('max_input_time'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
            'config_cache' => file_exists(base_path('bootstrap/cache/config.php')) ? 'cached' : 'not cached',
            'auth_user_id' => $user?->id,
            'auth_user_role' => $user?->role,
            'code_version' => trim(@exec('git rev-parse --short HEAD 2>&1') ?: 'unknown'),
            'time' => now()->toIso8601String(),
        ]);
    })->name('diag.runtime');
});
