<?php
// app/Models/NotificationUser.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationUser extends Model
{
    use HasFactory;

    /**
     * Nom de la table
     */
    protected $table = 'notification_user';

    /**
     * Les attributs assignables en masse
     */
    protected $fillable = [
        'notification_id',
        'user_id',
        'est_lu',
        'date_lu',
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [
        'est_lu' => 'boolean',
        'date_lu' => 'datetime',
    ];

    // =====================================
    // RELATIONS ELOQUENT
    // =====================================

    /**
     * RELATION : Appartient à une notification
     */
    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id');
    }

    /**
     * RELATION : Appartient à un utilisateur
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // =====================================
    // SCOPES
    // =====================================

    /**
     * SCOPE : Notifications non lues
     */
    public function scopeNonLues($query)
    {
        return $query->where('est_lu', false);
    }

    /**
     * SCOPE : Notifications lues
     */
    public function scopeLues($query)
    {
        return $query->where('est_lu', true);
    }

    /**
     * SCOPE : Pour un utilisateur spécifique
     */
    public function scopePourUtilisateur($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}