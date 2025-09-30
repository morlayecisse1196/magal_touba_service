<?php
// app/Http/Requests/Auth/RegisterRequest.php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé à faire cette requête
     * 
     * Pour l'inscription, tout le monde peut s'inscrire
     */
    public function authorize(): bool
    {
        return true; // Ouvert à tous
    }

    /**
     * Règles de validation pour l'inscription
     * 
     * Ces règles s'exécutent AVANT que les données n'atteignent le controller
     */
    public function rules(): array
    {
        return [
            
            // Email obligatoire, format email, unique en base
            'email' => [
                'required',
                'string',
                'email:rfc,dns', // Validation stricte de l'email
                'max:255',
                'unique:users,email' // Doit être unique dans la table users
            ],
            
            // Mot de passe avec règles de sécurité Laravel
            'password' => [
                'required',
                'string'
            ],
            
            // Confirmation du mot de passe
            'password_confirmation' => [
                'required',
                'string',
                'same:password' // Doit être identique au mot de passe
            ]
        ];
    }

    /**
     * Messages d'erreur personnalisés
     * 
     * Messages en français pour une meilleure UX
     */
    public function messages(): array
    {
        return [
            'email.required' => 'L\'adresse email est obligatoire.',
            'email.email' => 'L\'adresse email doit être valide.',
            'email.unique' => 'Cette adresse email est déjà utilisée.',
            
            'password.required' => 'Le mot de passe est obligatoire.',
            'password_confirmation.same' => 'La confirmation du mot de passe ne correspond pas.'

        ];
    }
}