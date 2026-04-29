<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\InternshipController;
use App\Http\Controllers\Api\V1\SkillController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', function () {
        return response()->json([
            'message' => 'API is online',
        ]);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function() {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/skills', [SkillController::class, 'index']);

    // Use middleware to protect routes that require authentication
    Route::middleware('auth:sanctum')->group(function() {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('internships', [InternshipController::class, 'index']);
        Route::get('internships/{internship}', [InternshipController::class, 'show']);
    });

    Route::middleware(['auth:sanctum', 'role:company'])->group(function() {
        Route::get('/company/internships', [InternshipController::class, 'companyIndex']);
        Route::get('/company/internships/archived', [InternshipController::class, 'archived']);
        Route::post('/company/internships', [InternshipController::class, 'store']);
        Route::put('/internships/{internship}', [InternshipController::class, 'update']);
        Route::patch('/internships/{internship}/archive', [InternshipController::class, 'archive']);
        Route::delete('/internships/{internship}', [InternshipController::class, 'destroy']);
    });
});
