<?php
namespace App\Services;

use App\Models\RozetkaChatMapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RozetkaService
{
    protected string $baseUrl = 'https://api-seller.rozetka.com.ua';

    public function getAccessToken(): string
    {
        return Cache::remember('rozetka_access_token', now()->addHours(12), function () {
            // В ідеалі ці дані мають бути в .env через config('services.rozetka...')
            $username = 'Ibis';
            $password = 'R2VhclBvaW50MTM=';

            $response = Http::post("{$this->baseUrl}/sites", [
                'username' => $username,
                'password' => $password, // Тут уже base64, як ми з'ясували раніше
            ]);

            $token = $response->json('content.access_token');

            if (!$token) {
                Log::error('Rozetka Auth Failed', $response->json());
                throw new \Exception("Не вдалося отримати токен.");
            }

            return (string)$token;
        });
    }

    /**
     * Отримання чатів з розгорнутими повідомленнями getChatsWithMessages
     */
    public function getChatsWithMessages(): array
    {
        $types = ['orders', 'items', 'deleted'];
        $allChats = [];
        $todayStart = now()->startOfDay();

        foreach ($types as $type) {
            $response = Http::withToken($this->getAccessToken())
                ->get("{$this->baseUrl}/messages/search", [
                    'expand'  => 'messages,user_fio',
                    'msgType' => $type,
                    'page'    => 1,
                    'sort'    => '-updated',
                ]);

            if ($response->successful()) {
                $chats = $response->json('content.chats') ?? [];
                foreach ($chats as $chat) {
                    $allChats[] = $chat;
                }
            }
        }

        // Використовуємо колекції для безпечної фільтрації
        return collect($allChats)
            // 1. Залишаємо тільки ті чати, що оновилися сьогодні
            ->filter(function ($chat) use ($todayStart) {
                return \Carbon\Carbon::parse($chat['updated'])->greaterThanOrEqualTo($todayStart);
            })
            // 2. Очищаємо повідомлення від адмінів (sender: 2)
            ->map(function ($chat) {
                if (isset($chat['messages'])) {
                    $chat['messages'] = collect($chat['messages'])
                        ->filter(function ($message) {
                            return (int)$message['sender'] === 3; // Тільки клієнт
                        })
                        ->values() // Скидаємо ключі масиву повідомлень
                        ->all();
                }
                return $chat;
            })
            // 3. (Опціонально) Видалити чати, де після фільтрації не залишилося повідомлень
            // ->filter(fn($chat) => !empty($chat['messages']))

            // 4. Сортуємо
            ->sortByDesc('updated')
            ->values() // Скидаємо ключі головного масиву
            ->all();
    }

    /**
     * Основна логіка синхронізації
     */
    // У вашому RozetkaService.php

    /**
     * Основна логіка синхронізації
     */
    public function syncNewMessages(HelpCrunchService $helpCrunch)
    {
        $chats = $this->getChatsWithMessages();

        foreach ($chats as $chat) {
            $chatId = $chat['id'];

            $mapping = RozetkaChatMapping::where('rozetka_chat_id', $chatId)->first();
            $lastSavedId = $mapping ? $mapping->last_processed_message_id : 0;

            // 1. Отримуємо нові повідомлення
            $newMessages = collect($chat['messages'])->filter(function ($msg) use ($lastSavedId) {
                return $msg['id'] > $lastSavedId;
            })->sortBy('id');

            if ($newMessages->isEmpty()) continue;

            // 2. Якщо мапінгу немає (новий чат), модифікуємо ПЕРШЕ повідомлення в списку
            if (!$mapping) {
                $firstMsg = $newMessages->first();
                $subject = $chat['subject'] ?? 'Без теми';

                // Оновлюємо текст першого повідомлення, додаючи тему зверху
                $firstMsg['body'] = "Rozetka 📌 **ТЕМА:** {$subject}\n\n" . $firstMsg['body'];

                // Повертаємо оновлене повідомлення назад у колекцію
                $newMessages->shift(); // видаляємо старе перше
                $newMessages->prepend($firstMsg); // додаємо оновлене першим
            }

            foreach ($newMessages as $message) {
                $messageId = $message['id'];

                $customerData = [
                    'id' => "rozetka_" . $chat['user_id'],
                    'name' => $chat['user_fio'] ?? 'Клієнт Rozetka',
                    'email' => $chat['user']['email'] ?? null,
                ];

                try {
                    $hcChatId = $helpCrunch->sendFromRozetka(
                        $customerData,
                        $message['body'], // Тут вже буде текст з темою, якщо це перший запуск
                        $chatId,
                        $mapping->helpcrunch_chat_id ?? null
                    );

                    $mapping = RozetkaChatMapping::updateOrCreate(
                        ['rozetka_chat_id' => $chatId],
                        [
                            'last_processed_message_id' => $messageId,
                            'helpcrunch_chat_id'        => $hcChatId,
                            'rozetka_user_id'           => $chat['user_id']
                        ]
                    );

                    dump("Повідомлення ID {$messageId} переслано.");

                } catch (\Exception $e) {
                    Log::error("Помилка синхронізації: " . $e->getMessage());
                }
            }
        }
    }
    public function sendMessageToChat($rozetka_chat_id, string $text, $rozetka_user_id)
    {
        $url = "{$this->baseUrl}/messages/create";


        // Змінюємо bodyText на body
        $payload = [
            'chat_id'       => $rozetka_chat_id,
            'body'          => (string) $text, // ТУТ ЗМІНА: body замість bodyText
            'receiver_id'   => (int) $rozetka_user_id ,
            'sendEmailUser' => 0
        ];

        $response = Http::withToken($this->getAccessToken())
            ->post($url, $payload);

        $result = $response->json();

        Log::info("Rozetka Final Attempt Result:", [$result]);

        return $result;
    }
}
