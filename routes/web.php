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

Route::get('/pwa', function () {
    return redirect('/', 301);
})->name('pwa');
Route::get('/pwa/', function () {
    return redirect('/', 301);
});

Route::get('/pwa/manifest.json', function () {
    return response()->file(public_path('manifest.json'), [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=0, must-revalidate',
    ]);
});
Route::get('/pwa/sw.js', function () {
    return response()->file(public_path('sw.js'), [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});
Route::get('/sw.js', function () {
    return response()->file(public_path('sw.js'), [
        'Content-Type' => 'application/javascript',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
    ]);
});
Route::get('/manifest.json', function () {
    return response()->file(public_path('manifest.json'), [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=0, must-revalidate',
    ]);
});
Route::get('/offline.html', function () {
    return response()->file(public_path('offline.html'), [
        'Content-Type' => 'text/html',
        'Cache-Control' => 'public, max-age=0, must-revalidate',
    ]);
});
Route::get('/health', function () {
    return response('OK '.now()->toIso8601String().' views:'.count(glob(storage_path('framework/views/*.php'))), 200)->header('Content-Type','text/plain');
});
Route::get('/health/nojs', function () {
    return response('<html><body><h1>OK '.now()->toIso8601String().'</h1><p>no JS test - if you see this, server is fast</p><a href="/login">go login</a></body></html>',200)->header('Content-Type','text/html');
});

// Shared attachment viewer (WhatsApp share): login required.
// Writer → view + download · Monitor → view only · Guest → login first.
Route::get('/s/attachments/{attachment}', [NoteController::class, 'sharedViewAttachment'])
    ->name('shared.attachments.view')
    ->middleware('auth');
Route::get('/s/attachments/{attachment}/file', [NoteController::class, 'sharedFileAttachment'])
    ->name('shared.attachments.file')
    ->middleware('auth');
// Shared submission-attachment viewer (WhatsApp share): login required (same rules).
Route::get('/s/submission-attachments/{attachment}', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'sharedViewAttachment'])
    ->name('shared.submission-attachments.view')
    ->middleware('auth');
Route::get('/s/submission-attachments/{attachment}/file', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'sharedFileAttachment'])
    ->name('shared.submission-attachments.file')
    ->middleware('auth');
Route::get('/notes-minimal', function () {
    if (!auth()->check()) return redirect()->route('login');
    $user = auth()->user();
    $notes = \App\Models\Note::with(['owner'])->latest()->limit(15)->get();
    $html = '<html dir="rtl" lang="ar"><head><meta charset="utf-8"><title>minimal</title><style>body{font-family:sans-serif;padding:20px}table{width:100%;border-collapse:collapse}td,th{border:1px solid #ccc;padding:8px}</style></head><body><h1>اختبار سرعة بدون JS/CSS</h1><p>وقت: '.now()->toIso8601String().' | مستخدم: '.e($user->name).' | عدد: '.$notes->count().'</p><table><tr><th>#</th><th>كاميرا</th><th>طابق</th><th>وصف</th></tr>';
    foreach($notes as $n) $html .= '<tr><td>'.$n->id.'</td><td>'.$n->camera_number.'</td><td>'.$n->floor_number.'</td><td>'.e(\Illuminate\Support\Str::limit($n->description,80)).'</td></tr>';
    $html .= '</table><p><a href="/notes?nosse=1">اختبار بدون SSE</a> | <a href="/login">login</a></p></body></html>';
    return response($html);
})->middleware('auth');

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
    Route::get('/notifications/stream', [\App\Http\Controllers\Web\NotificationStreamController::class, 'stream'])->name('notifications.stream');
    Route::get('/notifications/feed', [\App\Http\Controllers\Web\NotificationStreamController::class, 'feed'])->name('notifications.feed');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.markRead');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markOneAsRead'])->name('notifications.markOneRead');
    Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
    Route::get('/push/vapid-public-key', function(){ return response()->json(['key'=> config('app.vapid_public_key') ?? env('VAPID_PUBLIC_KEY')]); });

    Route::get('/print-test', function(){ return view('print-test'); })->name('print.test');

    Route::get('/general-submissions', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'index'])->name('general-submissions.index');
    Route::get('/general-submissions/create', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'create'])->name('general-submissions.create');
    Route::post('/general-submissions', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'store'])->name('general-submissions.store');
    Route::get('/general-submissions/{generalSubmission}', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'show'])->name('general-submissions.show');
    Route::post('/general-submissions/{generalSubmission}/accept', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'accept'])->name('general-submissions.accept');
    Route::post('/general-submissions/{generalSubmission}/reject', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'reject'])->name('general-submissions.reject');

    Route::get('/submission-attachments/{attachment}/view', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'viewAttachment'])->name('submission-attachments.view');
    Route::get('/submission-attachments/{attachment}/download', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'downloadAttachment'])->name('submission-attachments.download');

    
    Route::get('/_diag/runtime', function () {
        $user = auth()->user();
        return response()->json([
            'APP_ENV' => config('app.env'),
            'APP_URL' => config('app.url'),
            'DB_CONNECTION' => config('database.default'),
            'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
            'DB_HOST' => config('database.connections.'.config('database.default').'.host') ?? 'sqlite',
            'filesystem_default' => config('filesystems.default'),
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
