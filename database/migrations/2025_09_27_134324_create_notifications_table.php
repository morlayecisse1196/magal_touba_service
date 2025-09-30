<?php
// database/migrations/xxxx_xx_xx_create_notifications_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('titre'); // Titre de la notification
            $table->text('message'); // Contenu du message
            $table->foreignId('evenement_id')->nullable()->constrained('evenements')->onDelete('set null'); // Événement lié (optionnel)
            $table->timestamp('date_envoi')->useCurrent(); // Date d'envoi
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};