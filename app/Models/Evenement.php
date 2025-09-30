<?php
// app/Models/Evenement.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Evenement extends Model
{
    use HasFactory;

    /**
     * Nom de la table (optionnel si respecte la convention)
     * Laravel devine automatiquement le nom de la table
     */
    protected $table = 'evenements';

    /**
     * Les attributs assignables en masse
     */
    protected $fillable = [
        'titre',
        'description',
        'date_heure',
        'lieu',
        'capacite_max',
        'image_url',
        'est_actif',
    ];

    /**
     * Casting automatique des types
     * Laravel convertit automatiquement les types
     */
    protected $casts = [
        'date_heure' => 'datetime',    // String ↔ Carbon/DateTime
        'est_actif' => 'boolean',      // 1/0 ↔ true/false
        'capacite_max' => 'integer',   // String ↔ Integer
    ];

    // =====================================
    // RELATIONS ELOQUENT
    // =====================================

    /**
     * RELATION : Un événement peut avoir plusieurs utilisateurs inscrits
     * Relation Many-to-Many inverse de User::evenements()
     */
    public function utilisateurs()
    {
        return $this->belongsToMany(User::class, 'inscriptions', 'evenement_id', 'user_id')
                    ->withPivot('date_inscription')
                    ->withTimestamps();
    }

    // =====================================
    // SCOPES (Requêtes réutilisables)
    // =====================================

    /**
     * SCOPE : Filtrer les événements actifs
     * Usage : Evenement::actifs()->get()
     */
    public function scopeActifs($query)
    {
        return $query->where('est_actif', true);
    }

    /**
     * SCOPE : Filtrer les événements à venir
     * Usage : Evenement::aVenir()->get()
     */
    public function scopeAVenir($query)
    {
        return $query->where('date_heure', '>', Carbon::now());
    }

    /**
     * SCOPE : Filtrer les événements passés
     */
    public function scopePasses($query)
    {
        return $query->where('date_heure', '<', Carbon::now());
    }

    // =====================================
    // ACCESSEURS (Attributs calculés)
    // =====================================

    /**
     * ACCESSEUR : Calculer le nombre de places restantes
     * Usage : $evenement->places_restantes
     */
    public function getPlacesRestantesAttribute()
    {
        // Si capacité illimitée
        if ($this->capacite_max === null) {
            return null;
        }
        
        $inscrits = $this->utilisateurs()->count();
        return max(0, $this->capacite_max - $inscrits);
    }

    /**
     * ACCESSEUR : Vérifier si l'événement est complet
     * Usage : $evenement->est_complet
     */
    public function getEstCompletAttribute()
    {
        if ($this->capacite_max === null) {
            return false; // Capacité illimitée = jamais complet
        }
        
        return $this->utilisateurs()->count() >= $this->capacite_max;
    }

    // =====================================
    // MÉTHODES UTILITAIRES
    // =====================================

    /**
     * Vérifier si un utilisateur est inscrit à cet événement
     */
    public function utilisateurEstInscrit($userId): bool
    {
        return $this->utilisateurs()->where('user_id', $userId)->exists();
    }

    /**
     * Obtenir le nombre d'inscrits
     */
    public function getNombreInscrits(): int
    {
        return $this->utilisateurs()->count();
    }
}