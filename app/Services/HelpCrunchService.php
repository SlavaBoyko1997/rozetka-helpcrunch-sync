<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HelpCrunchService
{
    protected string $baseUrl = 'https://api.helpcrunch.com/v1';
    protected string $apiKey;
    protected int $applicationId = 2; // Переконайтеся, що це вірний ID

    public function __construct()
    {
        $this->apiKey = config('services.helpcrunch.api_key');
    }

    public function sendFromRozetka(array $customerData, string $text, int $rozetkaChatId, ?int $existingHcChatId = null)
    {
        $customer = $this->getOrCreateCustomer($customerData);
        $customerId = $customer['id'];

        $chatId = $existingHcChatId;

        // ПЕРЕВІРКА: Якщо ID є в базі, перевіримо, чи він не закритий
        if ($chatId && $this->isChatClosed($chatId)) {
            Log::info("HelpCrunch: Чат #{$chatId} закритий. Будемо шукати активний або створювати новий.");
            $chatId = null; // Скидаємо, щоб спрацювала логіка пошуку/створення
        }

        // Якщо в базі не було або той був закритий — шукаємо будь-який інший активний через API
        if (!$chatId) {
            $chatId = $this->getLastActiveChatId($customerId);
        }

        // Якщо активних взагалі немає — створюємо новий
        if (!$chatId) {
            $newChat = $this->createChat($customerId);
            $chatId = $newChat['id'] ?? null;
            Log::info("HelpCrunch: Створено новий чат #{$chatId} для клієнта {$customerId}");
        }

        if (!$chatId) {
            throw new \Exception("HelpCrunch: Не вдалося отримати ID чату.");
        }

        $this->addMessageToChat($chatId, $text);

        return $chatId;
    }

    protected function addMessageToChat(int $chatId, string $text)
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/messages", [
                'chat' => $chatId,
                'text' => $text,
                'type' => 'message'
            ]);

        if ($response->failed()) {
            Log::error("HelpCrunch Message Error: Не вдалося відправити повідомлення в чат #{$chatId}", [
                'status' => $response->status(),
                'error'  => $response->json()
            ]);
        }

        return $response->json();
    }

    protected function getOrCreateCustomer(array $data)
    {
        $safeUserId = (string) $data['id'];

        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/customers", [
                'name'   => $data['name'],
                'email'  => ($data['email'] !== 'true' && !empty($data['email'])) ? $data['email'] : null,
                'userId' => $safeUserId,
            ]);

        // Якщо клієнт уже існує — шукаємо його
        if ($response->status() === 400 && $response->json('errors.0.code') === 'invalid_userId_value') {
            $searchResponse = Http::withToken($this->apiKey)
                ->post("{$this->baseUrl}/customers/search", [
                    'filter' => [
                        [
                            'field'    => 'customers.userId',
                            'operator' => '=',
                            'value'    => $safeUserId
                        ]
                    ],
                    'limit' => 1
                ]);

            return $searchResponse->json('data.0');
        }

        // Якщо клієнт щойно створений, HelpCrunch повертає об'єкт клієнта прямо в корені відповіді
        return $response->json();
    }

    protected function getLastActiveChatId(int $customerId)
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/chats", [
                'filter' => [
                    [
                        'field' => 'customer', // або 'customers.id' залежно від версії, спробуйте спочатку так
                        'operator' => '=',
                        'value' => $customerId
                    ],
                    [
                        'field' => 'status',
                        'operator' => '!=',
                        'value' => 'closed'
                    ]
                ],
                // Сортування за ID (найновіший зверху) надійніше за дату повідомлення на старті
                'sort' => '-id',
                'limit' => 1
            ]);

        $data = $response->json('data');

        // Якщо масив порожній, значить активних чатів немає
        if (empty($data)) {
            Log::info("HelpCrunch: Активних чатів для клієнта {$customerId} не знайдено.");
            return null;
        }

        $chatId = $data[0]['id'];
        Log::info("HelpCrunch: Знайдено існуючий чат #{$chatId}");

        return $chatId;
    }

    protected function createChat(int $customerId)
    {
        $response = Http::withToken($this->apiKey)
            ->post("{$this->baseUrl}/chats", [
                'customer'    => $customerId,
                'application' => $this->applicationId,
            ]);

        return $response->json();
    }
    protected function isChatClosed(int $chatId): bool
    {
        $response = Http::withToken($this->apiKey)
            ->get("{$this->baseUrl}/chats/{$chatId}");

        if ($response->failed()) {
            return true; // Якщо чат не знайдено, вважаємо його недійсним
        }

        $status = $response->json('status');

        // Якщо статус 'closed', повертаємо true
        return $status === 'closed';
    }
}
