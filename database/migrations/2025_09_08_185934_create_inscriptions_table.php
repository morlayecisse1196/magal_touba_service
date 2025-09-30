<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_inscriptions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Créer la table des inscriptions (RELATION MANY-TO-MANY)
     * 
     * Table pivot qui lie les utilisateurs aux événements.
     * Un utilisateur peut s'inscrire à plusieurs événements,
     * Un événement peut avoir plusieurs utilisateurs inscrits.
     */
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            
            // Clés étrangères vers les tables liées
            $table->foreignId('user_id')
                  ->constrained('users')           // Référence vers table users
                  ->onDelete('cascade');           // Si user supprimé → supprimer inscriptions
                  
            $table->foreignId('evenement_id')
                  ->constrained('evenements')      // Référence vers table evenements
                  ->onDelete('cascade');           // Si événement supprimé → supprimer inscriptions
            
            // Métadonnées de l'inscription
            $table->timestamp('date_inscription')->useCurrent(); // Quand l'inscription a eu lieu
            
            $table->timestamps();
            
            // CONTRAINTE IMPORTANTE : Éviter les inscriptions en double
            // Un utilisateur ne peut s'inscrire qu'une seule fois au même événement
            $table->unique(['user_id', 'evenement_id']);
        });
    }

    /**
     * Supprimer la table inscriptions
     */
    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};