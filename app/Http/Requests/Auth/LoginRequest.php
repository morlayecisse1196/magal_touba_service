<?php
// app/Http/Requests/Auth/LoginRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * Tout le monde peut essayer de se connecter
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation pour la connexion
     * 
     * Plus simples que l'inscription
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string', 
                'email'
            ],
            'password' => [
                'required',
                'string',
                'min:1' // Au moins 1 caractère pour la connexion
            ]
        ];
    }

    /**
     * Messages personnalisés
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'password.required' => 'Le mot de passe est obligatoire.'
        ];
    }
}