<?php
// database/migrations/xxxx_xx_xx_xxxxxx_create_evenements_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Créer la table des événements du Magal
     * 
     * Cette table stocke tous les événements du programme :
     * conférences, prières, cérémonies, etc.
     */
    public function up(): void
    {
        Schema::create('evenements', function (Blueprint $table) {
            $table->id();
            
            // Informations de l'événement
            $table->string('titre'); // Ex: "Grande Conférence sur Serigne Touba"
            $table->text('description'); // Description détaillée
            $table->dateTime('date_heure'); // Quand a lieu l'événement
            $table->string('lieu'); // Où se déroule l'événement
            
            // Gestion de la capacité
            $table->integer('capacite_max')->nullable(); // NULL = illimité
            
            // Média et statut
            $table->string('image_url')->nullable(); // Photo de l'événement
            $table->boolean('est_actif')->default(true); // Visible ou masqué
            
            $table->timestamps();
        });
    }

    /**
     * Supprimer la table evenements
     */
    public function down(): void
    {
        Schema::dropIfExists('evenements');
    }
};