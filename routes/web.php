<?php

use App\Http\Controllers\RecorderController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RecordingUploadController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TranscriptSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('/{transcriptId?}', RecorderController::class)
    ->whereUuid('transcriptId')
    ->name('home');

Route::middleware('throttle:recordings')->group(function () {
    Route::post('recordings', [RecordingController::class, 'store'])->name('recordings.store');
    Route::post('recordings/{recording}/upload', RecordingUploadController::class)->name('recordings.upload');
});

Route::middleware('throttle:summaries')->group(function () {
    Route::post('transcripts/{transcript}/summaries', [TranscriptSummaryController::class, 'store'])->name('transcripts.summaries.store');
});

Route::middleware('throttle:polling')->group(function () {
    Route::get('recordings/{recording}', [RecordingController::class, 'show'])->name('recordings.show');
    Route::get('summaries/{summary}', [SummaryController::class, 'show'])->name('summaries.show');
});
