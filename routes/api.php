<?php

use App\Http\Controllers\Api\AuthController;
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
});

Route::get('/attachments/{attachment}', [NoteController::class, 'downloadAttachment'])
    ->middleware('auth.api');
