<?php
// app/Models/Notification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    /**
     * Les attributs assignables en masse
     */
    protected $fillable = [
        'titre',
        'message',
        'evenement_id',
        'date_envoi',
    ];

    /**
     * Les attributs qui doivent être castés
     */
    protected $casts = [
        'date_envoi' => 'datetime',
    ];

    // =====================================
    // RELATIONS ELOQUENT
    // =====================================

    /**
     * RELATION : Une notification peut être liée à un événement
     */
    public function evenement()
    {
        return $this->belongsTo(Evenement::class, 'evenement_id');
    }

    /**
     * RELATION : Une notification peut être envoyée à plusieurs utilisateurs
     */
    public function utilisateurs()
    {
        return $this->belongsToMany(User::class, 'notification_user')
                    ->withPivot(['est_lu', 'date_lu'])
                    ->withTimestamps();
    }

    /**
     * RELATION : Table pivot notification_user
     */
    public function notificationUsers()
    {
        return $this->hasMany(NotificationUser::class, 'notification_id');
    }

    // =====================================
    // SCOPES
    // =====================================

    /**
     * SCOPE : Notifications récentes (7 derniers jours par défaut)
     */
    public function scopeRecentes($query, $jours = 7)
    {
        return $query->where('date_envoi', '>=', now()->subDays($jours));
    }

    /**
     * SCOPE : Notifications liées à un événement
     */
    public function scopePourEvenement($query, $evenementId)
    {
        return $query->where('evenement_id', $evenementId);
    }

    /**
     * SCOPE : Notifications générales (non liées à un événement)
     */
    public function scopeGenerales($query)
    {
        return $query->whereNull('evenement_id');
    }
}