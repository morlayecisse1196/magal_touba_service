<?php
// app/Http/Requests/Evenement/UpdateEvenementRequest.php

namespace App\Http\Requests\Evenement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateEvenementRequest extends FormRequest
{
    /**
     * Seuls les administrateurs peuvent modifier des événements
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }
}