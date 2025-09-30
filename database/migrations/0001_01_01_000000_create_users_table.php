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
        Schema::create('users', function (Blueprint $table) {
            // Clé primaire auto-incrémentée
            $table->id();
            
            // Informations personnelles obligatoires
            $table->string('nom'); // Nom de famille
            $table->string('prenom'); // Prénom
            $table->string('email')->unique(); // Email unique pour connexion
            
            // Authentification
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password'); // Mot de passe hashé
            
            // Informations complémentaires
            $table->string('telephone')->nullable(); // Numéro optionnel
            $table->enum('role', ['user', 'admin'])->default('user'); // Rôle utilisateur
            
            // Tokens et timestamps
            $table->rememberToken();
            $table->timestamps(); // created_at et updated_at automatiques
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
