<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('rozetka_chat_mappings', function (Blueprint $table) {
            // Додаємо ID користувача Розетки (receiver_id)
            // Використовуємо unsignedBigInteger, бо ID в Розетці великі
            $table->unsignedBigInteger('rozetka_user_id')->nullable()->after('rozetka_chat_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rozetka_chat_mappings', function (Blueprint $table) {
            //
        });
    }
};
