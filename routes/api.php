<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GeneralSubmissionController;
use App\Http\Controllers\Api\NoteController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth.api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

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
    Route::post('/reports', [\App\Http\Controllers\Api\ReportController::class, 'store']);
    Route::get('/reports/{report}', [\App\Http\Controllers\Api\ReportController::class, 'show']);
    Route::put('/reports/{report}', [\App\Http\Controllers\Api\ReportController::class, 'update']);
    Route::delete('/reports/{report}', [\App\Http\Controllers\Api\ReportController::class, 'destroy']);
    Route::post('/reports/{report}/publish', [\App\Http\Controllers\Api\ReportController::class, 'publish']);
    Route::post('/reports/{report}/unpublish', [\App\Http\Controllers\Api\ReportController::class, 'unpublish']);
    Route::post('/reports/{report}/attach', [\App\Http\Controllers\Api\ReportController::class, 'attach']);
    Route::delete('/reports/{report}/notes/{noteId}', [\App\Http\Controllers\Api\ReportController::class, 'detach']);
    Route::post('/reports/{report}/reorder', [\App\Http\Controllers\Api\ReportController::class, 'reorder']);
    Route::post('/reports/{report}/generate', [\App\Http\Controllers\Api\ReportController::class, 'generate'])->middleware('throttle:5,1');
});

Route::get('/attachments/{attachment}', [NoteController::class, 'downloadAttachment'])
    ->middleware('auth.api');
Route::get('/attachments/{attachment}/view', [NoteController::class, 'viewAttachment'])
    ->middleware('auth.api');
