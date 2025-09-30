<?php
// app/Models/PointInteret.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointInteret extends Model
{
    use HasFactory;

    /**
     * Nom de la table
     */
    protected $table = 'points_interet';

    /**
     * Attributs assignables en masse
     */
   protected $fillable = [
    'nom',
    'type',
    'adresse',
    // 'latitude',     // On garde ces champs même si pas utilisés pour le moment
    // 'longitude',    // Ils sont dans la migration de l'étape 2
    'description',
    'numero_urgence',
    'image_url',    // Nouveau champ ajouté
];
    /**
     * Casting des types
     */
    protected $casts = [
        'latitude' => 'decimal:8',   // 8 chiffres après la virgule
        'longitude' => 'decimal:8',  // Précision GPS
    ];

    /**
     * Constantes pour les types de points d'intérêt
     * Utile pour la validation et l'affichage
     */
    const TYPES = [
        'mosquee' => 'Mosquée',
        'sante' => 'Centre de santé',
        'hebergement' => 'Hébergement',
        'restauration' => 'Restauration',
        'transport' => 'Transport',
        'autre' => 'Autre',
    ];

    // =====================================
    // RELATIONS ELOQUENT
    // =====================================

    /**
     * RELATION : Un point d'intérêt peut être dans les favoris de plusieurs utilisateurs
     */
    public function utilisateursFavoris()
    {
        return $this->belongsToMany(User::class, 'favoris', 'point_interet_id', 'user_id')
                    ->withPivot('date_ajout')
                    ->withTimestamps();
    }

    // =====================================
    // SCOPES
    // =====================================

    /**
     * SCOPE : Filtrer par type
     * Usage : PointInteret::parType('mosquee')->get()
     */
    public function scopeParType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * SCOPE : Obtenir les mosquées uniquement
     */
    public function scopeMosquees($query)
    {
        return $query->where('type', 'mosquee');
    }

    /**
     * SCOPE : Obtenir les centres de santé uniquement
     */
    public function scopeCentresSante($query)
    {
        return $query->where('type', 'sante');
    }

    // =====================================
    // ACCESSEURS
    // =====================================

    /**
     * ACCESSEUR : Obtenir le libellé du type
     * Usage : $point->type_libelle
     */
    public function getTypeLibelleAttribute()
    {
        return self::TYPES[$this->type] ?? 'Type inconnu';
    }

    // =====================================
    // MÉTHODES UTILITAIRES
    // =====================================

    /**
     * Vérifier si ce point est dans les favoris d'un utilisateur
     */
    public function estDansLesFavoris($userId): bool
    {
        return $this->utilisateursFavoris()->where('user_id', $userId)->exists();
    }

    /**
     * Obtenir le nombre d'utilisateurs qui ont ce point en favori
     */
    public function getNombreFavoris(): int
    {
        return $this->utilisateursFavoris()->count();
    }
}