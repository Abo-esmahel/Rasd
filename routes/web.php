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
});
