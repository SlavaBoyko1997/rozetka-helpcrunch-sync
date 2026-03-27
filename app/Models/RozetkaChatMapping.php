<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RozetkaChatMapping extends Model
{
    protected $fillable = [
        'rozetka_chat_id',
        'last_processed_message_id',
        'helpcrunch_customer_id', // якщо ви плануєте його записувати
        'helpcrunch_chat_id', // ПЕРЕВІРТЕ ЦЕЙ РЯДОК
        'rozetka_user_id', // ПЕРЕВІРТЕ ЦЕЙ РЯДОК
    ];
}
