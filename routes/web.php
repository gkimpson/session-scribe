<?php

use App\Http\Controllers\RecorderController;
use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RecordingUploadController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\TranscriptSummaryController;
use Illuminate\Support\Facades\Route;

Route::post('transcripts/{transcript}/summaries', [TranscriptSummaryController::class, 'store'])->name('transcripts.summaries.store');
Route::get('summaries/{summary}', [SummaryController::class, 'show'])->name('summaries.show');

Route::get('/{transcriptId?}', RecorderController::class)
    ->whereNumber('transcriptId')
    ->name('home');

Route::post('recordings', [RecordingController::class, 'store'])->name('recordings.store');
Route::get('recordings/{recording}', [RecordingController::class, 'show'])->name('recordings.show');
Route::post('recordings/{recording}/upload', RecordingUploadController::class)->name('recordings.upload');
