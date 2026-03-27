<?php

use App\Http\Controllers\Api\HelpCrunchWebhookController;
use App\Http\Controllers\WebhookController;
use App\Services\HelpCrunchService;
use App\Services\RozetkaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/rozetka-me', function (App\Services\RozetkaService $rozetka) {

    $response = $rozetka->get('messages/counts');

    return $response->json();
});

// Ендпоінт для Rozetka
Route::post('/rozetka/webhook', [WebhookController::class, 'handleRozetka']);

// Ендпоінт для HelpCrunch
Route::post('/helpcrunch/webhook', [WebhookController::class, 'handleHelpCrunch']);

Route::get('/test-rozetka-counts', function (RozetkaService $rozetka) {
    return $rozetka->getMessagesCounts();
});

Route::get('/test-messages-list', function (RozetkaService $rozetka) {
    return $rozetka->syncNewMessages();
});

Route::get('/rozetka/sync', function (RozetkaService $rozetka, HelpCrunchService $helpcrunch) {
    $rozetka->syncNewMessages($helpcrunch);
    return response()->json(['status' => 'Sync triggered']);
});

Route::post('/webhooks/helpcrunch', [HelpCrunchWebhookController::class, 'handle']);

