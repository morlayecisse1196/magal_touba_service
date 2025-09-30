<?php
// database/migrations/xxxx_xx_xx_create_notification_user_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->onDelete('cascade'); // Référence notification
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Référence utilisateur
            $table->boolean('est_lu')->default(false); // Statut de lecture
            $table->timestamp('date_lu')->nullable(); // Date de lecture
            $table->timestamps();
            
            // Contrainte d'unicité
            $table->unique(['notification_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_user');
    }
};