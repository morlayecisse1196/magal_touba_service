<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * Les attributs qui peuvent être assignés en masse
     * (Mass Assignment) - Sécurité contre les attaques
     */
    protected $fillable = [
        'nom',
        'prenom', 
        'email',
        'password',
        'telephone',
        'role',
    ];

    /**
     * Les attributs cachés lors de la sérialisation JSON
     * Important pour la sécurité (ne pas exposer le mot de passe)
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversion automatique de types (Casting)
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // Auto-hashage du mot de passe
    ];

    // =====================================
    // MÉTHODES JWT OBLIGATOIRES
    // =====================================

    /**
     * Identifiant unique pour JWT (généralement l'ID)
     */
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Retourne l'ID de l'utilisateur
    }

    /**
     * Données personnalisées à inclure dans le token JWT
     * Utile pour éviter des requêtes supplémentaires
     */
    public function getJWTCustomClaims()
    {
        return [
            'role' => $this->role,
            'nom' => $this->nom,
            'prenom' => $this->prenom,
        ];
    }

    // =====================================
    // RELATIONS ELOQUENT
    // =====================================

    /**
     * RELATION : Un utilisateur peut s'inscrire à plusieurs événements
     * Relation Many-to-Many via la table pivot 'inscriptions'
     */
    public function evenements()
    {
        return $this->belongsToMany(Evenement::class, 'inscriptions', 'user_id', 'evenement_id')
                    ->withPivot('date_inscription') // Inclure les colonnes de la table pivot
                    ->withTimestamps(); // Inclure created_at/updated_at de la pivot
    }

    /**
     * RELATION : Un utilisateur peut avoir plusieurs points d'intérêt favoris
     * Relation Many-to-Many via la table pivot 'favoris'
     */
    public function pointsInteretFavoris()
    {
        return $this->belongsToMany(PointInteret::class, 'favoris', 'user_id', 'point_interet_id')
                    ->withPivot('date_ajout')
                    ->withTimestamps();
    }

    // =====================================
    // MÉTHODES UTILITAIRES
    // =====================================

    /**
     * Vérifier si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Vérifier si l'utilisateur est inscrit à un événement
     */
    public function estInscritA($evenementId): bool
    {
        return $this->evenements()->where('evenement_id', $evenementId)->exists();
    }

    /**
     * Vérifier si un point d'intérêt est dans les favoris
     */
    public function aEnFavori($pointInteretId): bool
    {
        return $this->pointsInteretFavoris()->where('point_interet_id', $pointInteretId)->exists();
    }
    /**
     * RELATION : Notifications reçues par l'utilisateur
     */
    public function notifications()
    {
        return $this->belongsToMany(Notification::class, 'notification_user')
                    ->withPivot(['est_lu', 'date_lu'])
                    ->withTimestamps();
    }

    /**
     * RELATION : Table pivot notification_user pour cet utilisateur
     */
    public function notificationUsers()
    {
        return $this->hasMany(NotificationUser::class, 'user_id');
    }

    /**
     * Obtenir le nombre de notifications non lues
     */
    public function getNombreNotificationsNonLues()
    {
        return $this->notifications()->wherePivot('est_lu', false)->count();
    }
}