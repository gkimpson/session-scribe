<?php

use App\Http\Controllers\RecordingController;
use App\Http\Controllers\RecordingUploadController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Recorder')->name('home');

Route::post('recordings', [RecordingController::class, 'store'])->name('recordings.store');
Route::post('recordings/{recording}/upload', RecordingUploadController::class)->name('recordings.upload');
