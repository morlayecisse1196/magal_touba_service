<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_points_interet_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Créer la table des points d'intérêt
     * 
     * Cette table stocke tous les lieux importants pour les pèlerins :
     * mosquées, centres de santé, hébergements, restaurants, etc.
     */
    public function up(): void
    {
        Schema::create('points_interet', function (Blueprint $table) {
            $table->id();
            
            // Informations de base
            $table->string('nom'); // Ex: "Mosquée de Touba", "Hôpital Ahmadou Bamba"
            $table->enum('type', [
                'mosquee',      // Lieux de prière
                'sante',        // Centres médicaux
                'hebergement',  // Hôtels, maisons d'hôtes
                'restauration', // Restaurants, cantines
                'transport',    // Gares, arrêts de bus
                'autre'         // Autres services
            ]);
            
            // Localisation
            $table->string('adresse')->nullable(); // Adresse textuelle
            // $table->decimal('latitude', 10, 8)->nullable();    // Coordonnée GPS (latitude)
            // $table->decimal('longitude', 11, 8)->nullable();   // Coordonnée GPS (longitude)
            
            // Informations complémentaires
            $table->text('description')->nullable(); // Détails sur le lieu
            $table->string('numero_urgence')->nullable(); // Pour centres de santé
             $table->string('image_url')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Supprimer la table points_interet
     */
    public function down(): void
    {
        Schema::dropIfExists('points_interet');
    }
};