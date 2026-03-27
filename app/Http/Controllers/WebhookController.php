<?php

namespace App\Http\Controllers;


use App\Models\RozetkaChatMapping;
use App\Services\RozetkaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HelpCrunchWebhookController extends Controller
{
    public function handle(Request $request, RozetkaService $rozetka)
    {
        $event = $request->json('event');
        $eventData = $request->json('eventData');

        // Логуємо вхідний запит для відладки (можна прибрати після тестів)
        Log::info("HelpCrunch Webhook Received: " . $event);

        // Нас цікавлять тільки повідомлення від агентів у чаті
        if ($event === 'message.chat.agent') {

            $chatId = $eventData['chat_id'] ?? null;
            $messageText = $eventData['message']['text'] ?? '';

            if (!$chatId || empty($messageText)) {
                return response()->json(['status' => 'empty_data'], 400);
            }

            // Шукаємо мапінг чату в базі
            $mapping = RozetkaChatMapping::where('helpcrunch_chat_id', $chatId)->first();

            if ($mapping) {
                try {
                    // Відправляємо відповідь покупцю в Rozetka
                    $rozetka->sendMessageToChat($mapping->rozetka_chat_id, $messageText);

                    Log::info("Webhook Success: Agent reply sent to Rozetka Chat #{$mapping->rozetka_chat_id}");
                } catch (\Exception $e) {
                    Log::error("Webhook Rozetka Send Error: " . $e->getMessage());
                    return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
                }
            } else {
                Log::warning("Webhook Warning: No mapping found for HelpCrunch Chat #{$chatId}");
            }
        }

        return response()->json(['status' => 'success']);
    }
}


