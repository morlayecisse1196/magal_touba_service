<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_favoris_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Créer la table des favoris (RELATION MANY-TO-MANY)
     * Table pivot qui lie les utilisateurs aux points d'intérêt.
     * Permet aux pèlerins de sauvegarder leurs lieux préférés.
     */
    public function up(): void
    {
        Schema::create('favoris', function (Blueprint $table) {
            $table->id();
            
            // Clés étrangères
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
                  
            $table->foreignId('point_interet_id')
                  ->constrained('points_interet')
                  ->onDelete('cascade');
            
            // Métadonnée
            $table->timestamp('date_ajout')->useCurrent(); // Quand ajouté aux favoris
            
            $table->timestamps();
            
            // Éviter les doublons : un user ne peut pas ajouter 2 fois le même favori
            $table->unique(['user_id', 'point_interet_id']);
        });
    }

    /**
     * Supprimer la table favoris
     */
    public function down(): void
    {
        Schema::dropIfExists('favoris');
    }
};