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
        Schema::create('rozetka_chat_mappings', function (Blueprint $table) {
            $table->id();
            $table->integer('rozetka_chat_id')->unique();
            $table->integer('last_processed_message_id'); // ID останнього повідомлення від покупця
            $table->string('helpcrunch_customer_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rozetka_chat_mappings');
    }
};
