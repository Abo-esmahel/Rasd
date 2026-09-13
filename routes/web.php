<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\NoteController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SmartRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/pwa', function () {
    return redirect('/', 301);
})->name('pwa');
Route::get('/pwa/', function () {
    return redirect('/', 301);
});

Route::get('/pwa/manifest.json', function (\Illuminate\Http\Request $request) {
    // Locale-aware manifest (brand strings follow saved locale — no AI, no network).
    $locale = $request->cookie('rasd_locale');
    if (!in_array($locale, ['ar', 'en'], true)) {
        $locale = $request->session()->get('locale');
    }
    if (!in_array($locale, ['ar', 'en'], true)) {
        $locale = \App\Services\Localization\SourceLanguage::normalizeLocale($request->getPreferredLanguage(['ar', 'en']) ?? 'ar');
    }
    $base = json_decode(@file_get_contents(public_path('manifest.json')), true) ?: [];
    if ($locale === 'en') {
        $base['name'] = 'Surveillance Camera Notes';
        $base['short_name'] = 'Notes';
        $base['description'] = 'Camera notes app — Ministry of Information';
        $base['dir'] = 'ltr';
        $base['lang'] = 'en';
        if (isset($base['shortcuts'][0]['name'])) {
            $base['shortcuts'][0]['name'] = 'New note';
        }
        if (isset($base['shortcuts'][1]['name'])) {
            $base['shortcuts'][1]['name'] = 'Notes';
        }
    }

    return response()->json($base, 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=0, must-revalidate',
        'Content-Language' => $locale,
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
    return redirect('/pwa/manifest.json', 301);
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

Route::get('/r', [SmartRedirectController::class, 'show'])
    ->name('smart.redirect')
    ->middleware('throttle:60,1');


Route::get('/s/attachments/{attachment}', [NoteController::class, 'sharedViewAttachment'])
    ->name('shared.attachments.view')
    ->middleware(['auth', 'throttle:60,1']);
Route::get('/s/attachments/{attachment}/file', [NoteController::class, 'sharedFileAttachment'])
    ->name('shared.attachments.file')
    ->middleware(['auth', 'throttle:60,1']);

Route::get('/s/submission-attachments/{attachment}', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'sharedViewAttachment'])
    ->name('shared.submission-attachments.view')
    ->middleware(['auth', 'throttle:60,1']);
Route::get('/s/submission-attachments/{attachment}/file', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'sharedFileAttachment'])
    ->name('shared.submission-attachments.file')
    ->middleware(['auth', 'throttle:60,1']);

Route::post('/locale', [\App\Http\Controllers\Web\LocaleController::class, 'update'])->name('locale.update')->middleware('throttle:30,1');

Route::middleware('auth')->group(function () {
    Route::post('/translations/page', [\App\Http\Controllers\Web\DynamicTranslationController::class, 'translatePage'])->name('translations.page')->middleware('throttle:30,1');

    Route::get('/dashboard', function () {
        return redirect()->route('notes.index');
    })->name('dashboard');

    Route::resource('notes', NoteController::class);
    Route::get('/my-notes', [NoteController::class, 'myNotes'])->name('notes.my');
    Route::get('/gallery', [\App\Http\Controllers\Web\GalleryController::class, 'index'])->name('gallery.index');

    Route::post('/notes/{note}/send', [NoteController::class, 'send'])->name('notes.send');
    Route::post('/notes/{note}/accept', [NoteController::class, 'accept'])->name('notes.accept');
    Route::post('/notes/{note}/reject', [NoteController::class, 'reject'])->name('notes.reject');
    Route::post('/notes/{note}/resend', [NoteController::class, 'resend'])->name('notes.resend');

    Route::get('/notes/{note}/translation-status', [\App\Http\Controllers\Web\NoteTranslationController::class, 'status'])->name('notes.translation.status')->middleware('throttle:60,1');
    Route::post('/notes/{note}/translation-retry', [\App\Http\Controllers\Web\NoteTranslationController::class, 'retry'])->name('notes.translation.retry')->middleware('throttle:10,1');
    Route::post('/translations/retry', [\App\Http\Controllers\Web\DynamicTranslationController::class, 'retry'])->name('translations.retry')->middleware('throttle:10,1');

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
    Route::get('/notifications/stream', [\App\Http\Controllers\Web\NotificationStreamController::class, 'stream'])->name('notifications.stream')->middleware('throttle:30,1');
    Route::get('/notifications/feed', [\App\Http\Controllers\Web\NotificationStreamController::class, 'feed'])->name('notifications.feed')->middleware('throttle:60,1');
    Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
    Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    Route::post('/notifications/mark-read', [NotificationController::class, 'markAsRead'])->name('notifications.markRead');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markOneAsRead'])->name('notifications.markOneRead');
    Route::post('/push/subscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe')->middleware('throttle:30,1');
    Route::delete('/push/unsubscribe', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe')->middleware('throttle:30,1');
    Route::get('/push/vapid-public-key', function(){
        $key = config('app.vapid_public_key') ?? env('VAPID_PUBLIC_KEY');
        if (!$key) {
            return response()->json(['key' => null, 'error' => 'VAPID_NOT_CONFIGURED'], 503);
        }
        return response()->json(['key'=> $key]);
    })->name('push.vapid');

    Route::get('/print-test', function(){ return view('print-test'); })->name('print.test');

    Route::get('/general-submissions', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'index'])->name('general-submissions.index');
    Route::get('/general-submissions/create', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'create'])->name('general-submissions.create');
    Route::post('/general-submissions', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'store'])->name('general-submissions.store');
    Route::get('/general-submissions/{generalSubmission}', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'show'])->name('general-submissions.show');
    Route::post('/general-submissions/{generalSubmission}/accept', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'accept'])->name('general-submissions.accept');
    Route::post('/general-submissions/{generalSubmission}/reject', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'reject'])->name('general-submissions.reject');

    Route::get('/submission-attachments/{attachment}/view', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'viewAttachment'])->name('submission-attachments.view');
    Route::get('/submission-attachments/{attachment}/download', [\App\Http\Controllers\Web\GeneralSubmissionController::class, 'downloadAttachment'])->name('submission-attachments.download');


    Route::get('/reports/insights', [\App\Http\Controllers\Web\ReportController::class, 'insights'])->name('reports.insights');
    Route::get('/reports', [\App\Http\Controllers\Web\ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/create', [\App\Http\Controllers\Web\ReportController::class, 'create'])->name('reports.create');
    Route::post('/reports', [\App\Http\Controllers\Web\ReportController::class, 'store'])->name('reports.store');
    Route::get('/report-sheets/{n}', [\App\Http\Controllers\Web\ReportController::class, 'sheet'])->whereNumber('n')->name('report-sheets.image');
    Route::get('/reports/{report}', [\App\Http\Controllers\Web\ReportController::class, 'show'])->name('reports.show');
    Route::get('/reports/{report}/edit', [\App\Http\Controllers\Web\ReportController::class, 'edit'])->name('reports.edit');
    Route::put('/reports/{report}', [\App\Http\Controllers\Web\ReportController::class, 'update'])->name('reports.update');
    Route::delete('/reports/{report}', [\App\Http\Controllers\Web\ReportController::class, 'destroy'])->name('reports.destroy');
    Route::post('/reports/{report}/publish', [\App\Http\Controllers\Web\ReportController::class, 'publish'])->name('reports.publish');
    Route::post('/reports/{report}/unpublish', [\App\Http\Controllers\Web\ReportController::class, 'unpublish'])->name('reports.unpublish');
    Route::post('/reports/{report}/attach', [\App\Http\Controllers\Web\ReportController::class, 'attach'])->name('reports.attach');
    Route::delete('/reports/{report}/notes/{noteId}', [\App\Http\Controllers\Web\ReportController::class, 'detach'])->name('reports.detach');
    Route::post('/reports/{report}/reorder', [\App\Http\Controllers\Web\ReportController::class, 'reorder'])->name('reports.reorder');
    Route::post('/reports/{report}/generate', [\App\Http\Controllers\Web\ReportController::class, 'generate'])->name('reports.generate')->middleware('throttle:5,1');
    Route::post('/reports/{report}/generate-data', [\App\Http\Controllers\Web\ReportController::class, 'generateData'])->name('reports.generate-data')->middleware('throttle:5,1');
    Route::post('/reports/{report}/render', [\App\Http\Controllers\Web\ReportController::class, 'render'])->name('reports.render')->middleware('throttle:3,10');
    Route::post('/reports/{report}/fill-sheet', [\App\Http\Controllers\Web\ReportController::class, 'fillSheet'])->name('reports.fill-sheet')->middleware('throttle:3,10');
    Route::delete('/reports/{report}/fill-sheet', [\App\Http\Controllers\Web\ReportController::class, 'destroyFilledSheet'])->name('reports.fill-sheet.destroy');
    Route::get('/reports/{report}/filled-sheet', [\App\Http\Controllers\Web\ReportController::class, 'filledSheetImage'])->name('reports.filled-sheet.image');
    Route::get('/reports/{report}/sheet-html', [\App\Http\Controllers\Web\ReportController::class, 'sheetHtml'])->name('reports.sheet-html');
    Route::get('/reports/{report}/print', [\App\Http\Controllers\Web\ReportController::class, 'print'])->name('reports.print');
    Route::get('/reports/{report}/preview', [\App\Http\Controllers\Web\ReportController::class, 'preview'])->name('reports.preview');
    Route::get('/reports/{report}/localized', [\App\Http\Controllers\Web\ReportController::class, 'localized'])->name('reports.localized')->middleware('throttle:30,1');
    Route::get('/reports/{report}/export-pdf', [\App\Http\Controllers\Web\ReportController::class, 'exportPdf'])->name('reports.export.pdf');
    Route::get('/reports/{report}/export-image', [\App\Http\Controllers\Web\ReportController::class, 'exportImage'])->name('reports.export.image');
});

Route::fallback([SmartRedirectController::class, 'missing'])->name('smart.missing');
