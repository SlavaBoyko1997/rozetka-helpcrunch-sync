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
            $table->unsignedBigInteger('helpcrunch_chat_id')->nullable()->after('rozetka_chat_id');
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
