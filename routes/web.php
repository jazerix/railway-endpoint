<?php

use App\Http\Controllers\AnalysisController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('upload', [AnalysisController::class, 'showUpload']);
Route::post('upload', [AnalysisController::class, 'upload'])->name('upload');
Route::get('measurements', [AnalysisController::class, 'measurements'])->name('measurements');
Route::post('measurements/{recordingId}/positions', [AnalysisController::class, 'uploadPositions']);
Route::get('measurements/{recordingId}/positions', [AnalysisController::class, 'positions']);
Route::get('measurements/{recordingId}/closest', [AnalysisController::class, 'closest']);
Route::get('measurements/{recordingId}/data', [AnalysisController::class, 'data']);
