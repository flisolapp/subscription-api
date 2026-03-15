<?php

use App\Http\Controllers\CertificatesDownloadController;
use App\Http\Controllers\CertificatesReleaseController;
use App\Http\Controllers\CertificatesSearchController;
use App\Http\Controllers\EditionController;
use App\Http\Controllers\Subscription\SubscriptionCollaboratorController;
use App\Http\Controllers\Subscription\SubscriptionParticipantController;
use App\Http\Controllers\Subscription\SubscriptionSpeakerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ── Auth (existing) ─────────────────────────────────────────────────────────

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
