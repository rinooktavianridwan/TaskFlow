<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProjectController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show'])->middleware('can:view,project');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->middleware('can:update,project');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('can:delete,project');
});
