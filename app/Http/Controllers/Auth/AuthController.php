<?php
// app/Http/Controllers/Auth/AuthController.php

namespace App\Http\Controllers\Auth;

use Illuminate\Routing\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Constructeur : définir les middlewares
     * 
     * Le middleware 'auth:api' protège certaines routes
     * Seules register et login sont ouvertes à tous
     */
    public function __construct()
    {
        // Toutes les méthodes nécessitent une authentification SAUF :
        $this->middleware('auth:api', ['except' => ['register', 'login']]);
    }

    /**
     * INSCRIPTION - Créer un nouveau compte utilisateur
     * 
     * @param RegisterRequest $request - Données validées automatiquement
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            // Les données sont déjà validées par RegisterRequest
            // Créer l'utilisateur en base
            $user = User::create([
                'nom' => $request->nom,
                'prenom' => $request->prenom,
                'email' => $request->email,
                'password' => Hash::make($request->password), // Hasher le mot de passe
                'telephone' => $request->telephone,
                'role' => 'user' // Par défaut, nouveau utilisateur = pèlerin
            ]);

            // Générer un token JWT pour l'utilisateur
            $token = JWTAuth::fromUser($user);

            // Retourner la réponse de succès avec le token
            return response()->json([
                'status' => 'success',
                'message' => 'Inscription réussie ! Bienvenue dans l\'application Magal Touba.',
                'user' => [
                    'id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                    'role' => $user->role
                ],
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60 // Durée en secondes
            ], 201); // Status 201 = Created

        } catch (\Exception $e) {
            // En cas d'erreur lors de l'inscription
            return response()->json([
                'status' => 'error',
                'message' => 'Une erreur est survenue lors de l\'inscription.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CONNEXION - Authentifier un utilisateur existant
     * 
     * @param LoginRequest $request - Email et mot de passe validés
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Récupérer les identifiants
        $credentials = $request->only('email', 'password');

        try {
            // Tenter de générer un token avec ces identifiants
            if (!$token = JWTAuth::attempt($credentials)) {
                // Identifiants incorrects
                return response()->json([
                    'status' => 'error',
                    'message' => 'Identifiants incorrects. Vérifiez votre email et mot de passe.'
                ], 401); // Status 401 = Unauthorized
            }

            // Récupérer l'utilisateur connecté
            $user = Auth::user();

            // Connexion réussie
            return response()->json([
                'status' => 'success',
                'message' => 'Connexion réussie ! Bienvenue ' . $user->prenom . '.',
                'user' => [
                    'id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                    'role' => $user->role
                ],
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 200);

        } catch (JWTException $e) {
            // Erreur lors de la génération du token
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de créer le token d\'authentification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PROFIL - Obtenir les informations de l'utilisateur connecté
     * 
     * Route protégée : nécessite un token valide
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            // L'utilisateur est automatiquement disponible grâce au middleware auth:api
            $user = Auth::user();

            return response()->json([
                'status' => 'success',
                'message' => 'Profil utilisateur récupéré avec succès.',
                'user' => [
                    'id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                    'telephone' => $user->telephone,
                    'role' => $user->role,
                    'created_at' => $user->created_at->format('d/m/Y H:i'),
                    'est_admin' => $user->isAdmin()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération du profil.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * RAFRAÎCHIR - Générer un nouveau token
     * 
     * Permet de prolonger la session sans se reconnecter
     * 
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        try {
            // Générer un nouveau token et invalider l'ancien
            $newToken = JWTAuth::refresh();

            return response()->json([
                'status' => 'success',
                'message' => 'Token rafraîchi avec succès.',
                'access_token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de rafraîchir le token.',
                'error' => $e->getMessage()
            ], 401);
        }
    }

    /**
     * DÉCONNEXION - Invalider le token actuel
     * 
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            // Invalider le token actuel
            JWTAuth::invalidate();

            return response()->json([
                'status' => 'success',
                'message' => 'Déconnexion réussie. À bientôt sur Magal Touba !'
            ], 200);

        } catch (JWTException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la déconnexion.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}