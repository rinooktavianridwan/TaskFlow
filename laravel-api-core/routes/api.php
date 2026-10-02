<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationAcceptanceController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskActivityController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Tahap 1: Pengelolaan utama resource project
    Route::controller(ProjectController::class)->group(function () {
        Route::get('/projects', 'index');
        Route::post('/projects', 'store');
        Route::get('/projects/{project}', 'show')->middleware('can:view,project');
        Route::patch('/projects/{project}', 'update')->middleware('can:update,project');
        Route::delete('/projects/{project}', 'destroy')->middleware('can:delete,project');
    });

    // Tahap 2: Mengurus anggota yang sudah tergabung di dalam project
    Route::controller(MemberController::class)->group(function () {
        Route::get('/projects/{project}/members', 'index')->middleware('can:view,project');
        Route::patch('/projects/{project}/members/{user}', 'update')->middleware('can:update,project');
        Route::delete('/projects/{project}/members/{user}', 'destroy')->middleware('can:removeMember,project,user');
    });

    // Tahap 2: Fitur owner membuat dan mengelola undangan
    Route::controller(InvitationController::class)->group(function () {
        Route::get('/projects/{project}/invitations', 'index')->middleware('can:update,project');
        Route::post('/projects/{project}/invitations', 'store')->middleware('can:update,project');
        Route::delete('/projects/{project}/invitations/{invitation:id}', 'destroy')->middleware('can:update,project');
    });

    // Tahap 2: Validasi dan respon token undangan dari luar project
    Route::controller(InvitationAcceptanceController::class)->group(function () {
        Route::post('/invitations/{invitation}/accept', 'accept')->middleware('can:accept,invitation');
        Route::post('/invitations/{invitation}/decline', 'decline')->middleware('can:decline,invitation');
    });

    // Tahap 3: Pembuatan dan daftar task di dalam konteks project
    Route::controller(ProjectTaskController::class)->group(function () {
        Route::get('/projects/{project}/tasks', 'index')->middleware('can:view,project');
        Route::post('/projects/{project}/tasks', 'store')->middleware('can:createTask,project');
    });

    // Tahap 3: Mengelola detail, update status, dan hapus task
    Route::controller(TaskController::class)->group(function () {
        Route::get('/tasks/{task}', 'show')->middleware('can:view,task');
        Route::patch('/tasks/{task}', 'update')->middleware('can:view,task');
        Route::delete('/tasks/{task}', 'destroy')->middleware('can:delete,task');
    });

    // Tahap 4: Riwayat aktivitas dan perubahan pada sebuah task
    Route::controller(TaskActivityController::class)->group(function () {
        Route::get('/tasks/{task}/activities', 'index')->middleware('can:view,task');
    });
});
