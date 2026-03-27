<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RozetkaChatMapping;
use App\Services\RozetkaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HelpCrunchWebhookController extends Controller
{
    public function handle(Request $request, RozetkaService $rozetka)
    {
        $eventData = $request->input('eventData');

        // 1. Отримуємо текст повідомлення від оператора HelpCrunch
        if (is_string($eventData)) {
            $eventData = json_decode($eventData, true);
        }
        $messageText = $eventData['message']['text'] ?? null;

        if (!$messageText) {
            Log::warning("HelpCrunch Webhook: Текст не знайдено.", $request->all());
            return response()->json(['status' => 'error', 'message' => 'Empty text'], 400);
        }

        // 2. Отримуємо ID чату HelpCrunch (це наш ключ для пошуку в базі)
        $hcChatId = $eventData['chat_id'] ?? null;

        if (!$hcChatId) {
            return response()->json(['status' => 'error', 'message' => 'Chat ID missing'], 400);
        }

        // 3. Шукаємо мапінг у нашій базі даних
        $mapping = RozetkaChatMapping::where('helpcrunch_chat_id', $hcChatId)->first();

        if ($mapping) {
            // 4. Відправляємо в Rozetka, використовуючи дані з бази

            $result = $rozetka->sendMessageToChat(
                $mapping->rozetka_chat_id,
                $messageText,
                $mapping->rozetka_user_id // Наш новий receiver_id з міграції
            );

            return response()->json([
                'status' => 'success',
                'rozetka_response' => $result
            ]);
        }

        Log::error("Мапінг не знайдено для HelpCrunch Chat ID: {$hcChatId}");
        return response()->json(['status' => 'error', 'message' => 'Mapping not found'], 404);
    }
}
