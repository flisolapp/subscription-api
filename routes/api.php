<?php

use App\Http\Controllers\EditionController;
use App\Http\Controllers\Subscription\SubscriptionCollaboratorController;
use App\Http\Controllers\Subscription\SubscriptionParticipantController;
use App\Http\Controllers\Subscription\SubscriptionSpeakerController;
use App\Http\Controllers\Subscription\SpeakerPhotoUploadController;
use App\Http\Controllers\Subscription\TalkSlideUploadController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Editions ─────────────────────────────────────────────────────────────────

Route::get('editions', [EditionController::class, 'index']);
Route::get('editions/active', [EditionController::class, 'active']);

// ── Subscriptions ─────────────────────────────────────────────────────────────

Route::post('subscriptions/participants',  [SubscriptionParticipantController::class, 'store']);
Route::post('subscriptions/collaborators', [SubscriptionCollaboratorController::class, 'store']);
Route::post('subscriptions/speakers',      [SubscriptionSpeakerController::class,      'store']);

// ── File uploads (step 2 of speaker registration) ─────────────────────────────

Route::post('subscriptions/speakers/{speakerId}/photo', [SpeakerPhotoUploadController::class, 'store']);
Route::post('subscriptions/talks/{talkId}/slide',       [TalkSlideUploadController::class,     'store']);
