<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeneralSubmissionController;
use App\Http\Controllers\Api\NoteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::get('/ping', function () {
    return response()->json(['app' => 'rasd', 'ok' => true, 'time' => now()->toIso8601String()]);
})->middleware('throttle:120,1');

Route::middleware('auth.api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/me/stats', [AuthController::class, 'stats']);
    Route::post('/me', [AuthController::class, 'updateMe']);

    Route::get('/notifications', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
    Route::get('/notifications/feed', [\App\Http\Controllers\Api\NotificationController::class, 'feed']);
    Route::post('/notifications/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/mark-read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markOneAsRead']);

    Route::post('/devices/register', [\App\Http\Controllers\Api\DeviceController::class, 'register']);
    Route::post('/devices/unregister', [\App\Http\Controllers\Api\DeviceController::class, 'unregister']);

    Route::get('/notes/{note}/pdf', [\App\Http\Controllers\Api\PdfDocumentController::class, 'notePdf']);
    Route::get('/notes/{note}/pdf-status', [\App\Http\Controllers\Api\PdfDocumentController::class, 'noteStatus']);
    Route::get('/notes/{note}/attachments.zip', [\App\Http\Controllers\Api\PdfDocumentController::class, 'noteZip']);
    Route::get('/general-submissions/{submission}/pdf', [\App\Http\Controllers\Api\PdfDocumentController::class, 'submissionPdf']);
    Route::get('/general-submissions/{submission}/pdf-status', [\App\Http\Controllers\Api\PdfDocumentController::class, 'submissionStatus']);
    Route::get('/general-submissions/{submission}/attachments.zip', [\App\Http\Controllers\Api\PdfDocumentController::class, 'submissionZip']);
    Route::get('/reports/{report}/pdf', [\App\Http\Controllers\Api\PdfDocumentController::class, 'reportPdf']);
    Route::get('/reports/{report}/pdf-status', [\App\Http\Controllers\Api\PdfDocumentController::class, 'reportStatus']);
    Route::get('/reports/{report}/attachments.zip', [\App\Http\Controllers\Api\PdfDocumentController::class, 'reportZip']);

    Route::get('/managed-users', [\App\Http\Controllers\Api\ManageUsersController::class, 'index']);
    Route::post('/managed-users', [\App\Http\Controllers\Api\ManageUsersController::class, 'store']);
    Route::put('/managed-users/{user}', [\App\Http\Controllers\Api\ManageUsersController::class, 'update']);
    Route::post('/managed-users/{user}/ban', [\App\Http\Controllers\Api\ManageUsersController::class, 'ban']);
    Route::post('/managed-users/{user}/unban', [\App\Http\Controllers\Api\ManageUsersController::class, 'unban']);

    Route::get('/users/writers', [\App\Http\Controllers\Api\RankingController::class, 'writers']);
    Route::get('/users/observers', [\App\Http\Controllers\Api\RankingController::class, 'observers']);
    Route::get('/ranking', [\App\Http\Controllers\Api\RankingController::class, 'index']);

    Route::get('/users/{id}', [\App\Http\Controllers\Api\UserProfileController::class, 'show'])
        ->whereNumber('id');

    Route::get('/gallery', [\App\Http\Controllers\Api\GalleryController::class, 'index']);

    Route::get('/notes', [NoteController::class, 'index']);
    Route::get('/my-notes', [NoteController::class, 'myNotes']);
    Route::post('/notes', [NoteController::class, 'store']);
    Route::get('/notes/{note}', [NoteController::class, 'show']);
    Route::put('/notes/{note}', [NoteController::class, 'update']);
    Route::delete('/notes/{note}', [NoteController::class, 'destroy']);

    Route::post('/notes/{note}/send', [NoteController::class, 'send']);
    Route::post('/notes/{note}/accept', [NoteController::class, 'accept']);
    Route::post('/notes/{note}/reject', [NoteController::class, 'reject']);
    Route::post('/notes/{note}/resend', [NoteController::class, 'resend']);
    Route::get('/notes/{note}/translation-status', [\App\Http\Controllers\Web\NoteTranslationController::class, 'status']);
    Route::post('/notes/{note}/translation-retry', [\App\Http\Controllers\Web\NoteTranslationController::class, 'retry']);

    Route::post('/notes/{note}/attachments', [NoteController::class, 'storeAttachment']);
    Route::delete('/notes/{note}/attachments/{attachment}', [NoteController::class, 'destroyAttachment']);

    Route::get('/general-submissions', [GeneralSubmissionController::class, 'index']);
    Route::post('/general-submissions', [GeneralSubmissionController::class, 'store']);
    Route::get('/general-submissions/{generalSubmission}', [GeneralSubmissionController::class, 'show']);
    Route::post('/general-submissions/{generalSubmission}/submit', [GeneralSubmissionController::class, 'submit']);
    Route::post('/general-submissions/{generalSubmission}/accept', [GeneralSubmissionController::class, 'accept']);
    Route::post('/general-submissions/{generalSubmission}/reject', [GeneralSubmissionController::class, 'reject']);

    Route::get('/submission-attachments/{attachment}/view', [GeneralSubmissionController::class, 'viewAttachment']);
    Route::get('/submission-attachments/{attachment}/download', [GeneralSubmissionController::class, 'downloadAttachment']);

    Route::get('/reports', [\App\Http\Controllers\Api\ReportController::class, 'index']);
    Route::get('/reports/daily-status', [\App\Http\Controllers\Api\ReportController::class, 'dailyStatus']);
    Route::post('/reports', [\App\Http\Controllers\Api\ReportController::class, 'store']);
    Route::post('/reports/ai-preview', \App\Http\Controllers\ReportAiPreviewController::class)->middleware('throttle:3,1');
    Route::get('/reports/insights', [\App\Http\Controllers\Api\ReportController::class, 'insights']);
    Route::get('/reports/{report}', [\App\Http\Controllers\Api\ReportController::class, 'show']);
    Route::get('/reports/{report}/comments', [\App\Http\Controllers\Api\EngagementController::class, 'comments']);
    Route::post('/reports/{report}/comments', [\App\Http\Controllers\Api\EngagementController::class, 'storeComment']);
    Route::put('/reports/{report}/reaction', [\App\Http\Controllers\Api\EngagementController::class, 'reactReport']);
    Route::put('/notes/{note}/reaction', [\App\Http\Controllers\Api\EngagementController::class, 'reactNote']);
    Route::put('/report-comments/{comment}/reaction', [\App\Http\Controllers\Api\EngagementController::class, 'reactComment']);
    Route::put('/reports/{report}', [\App\Http\Controllers\Api\ReportController::class, 'update']);
    Route::delete('/reports/{report}', [\App\Http\Controllers\Api\ReportController::class, 'destroy']);
    Route::post('/reports/{report}/publish', [\App\Http\Controllers\Api\ReportController::class, 'publish']);
    Route::post('/reports/{report}/unpublish', [\App\Http\Controllers\Api\ReportController::class, 'unpublish']);
    Route::post('/reports/{report}/attach', [\App\Http\Controllers\Api\ReportController::class, 'attach']);
    Route::delete('/reports/{report}/notes/{noteId}', [\App\Http\Controllers\Api\ReportController::class, 'detach']);
    Route::post('/reports/{report}/reorder', [\App\Http\Controllers\Api\ReportController::class, 'reorder']);
    Route::post('/reports/{report}/generate', [\App\Http\Controllers\Api\ReportController::class, 'generate'])->middleware('throttle:5,1');
    Route::get('/reports/{report}/document', [\App\Http\Controllers\Api\ReportController::class, 'document']);
    Route::get('/report-sheets/{n}', [\App\Http\Controllers\Api\ReportController::class, 'sheet'])->whereNumber('n');
});

Route::get('/attachments/{attachment}', [NoteController::class, 'downloadAttachment'])
    ->middleware('auth.api');
Route::get('/attachments/{attachment}/view', [NoteController::class, 'viewAttachment'])
    ->middleware('auth.api');

Route::get('/attachments/{attachment}/signed-view', [NoteController::class, 'signedAttachmentView'])
    ->middleware('throttle:120,1');
Route::get('/submission-attachments/{attachment}/signed-view', [GeneralSubmissionController::class, 'signedAttachmentView'])
    ->middleware('throttle:120,1');
