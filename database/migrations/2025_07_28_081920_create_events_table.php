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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_id')->constrained()->onDelete('cascade');
            $table->enum('event_type', [
                'message_sent',
                'message_delivered',
                'message_read',
                'typing_start',
                'typing_end',
                'message_edited'
            ]);
            $table->unsignedBigInteger('sender_id');
            $table->enum('sender_type', ['client', 'operator']);
            $table->json('data')->nullable(); // Для дополнительной информации, например, ID сообщения
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
