<?php
// app/Http/Requests/Evenement/StoreEvenementRequest.php

namespace App\Http\Requests\Evenement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreEvenementRequest extends FormRequest
{
    /**
     * Seuls les administrateurs peuvent créer des événements
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }
    /**
     * Préparer les données avant validation
     */
    protected function prepareForValidation(): void
    {
        // Si est_actif n'est pas fourni, le mettre à true par défaut
        if (!$this->has('est_actif')) {
            $this->merge(['est_actif' => true]);
        }
    }
}