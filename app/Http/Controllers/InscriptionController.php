<?php
// app/Http/Controllers/InscriptionController.php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Models\Evenement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class InscriptionController extends Controller
{
    /**
     * Constructeur : Toutes les routes nécessitent une authentification
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * S'INSCRIRE à un événement (POST /api/evenements/{id}/inscription)
     */
    public function inscrire(string $evenementId): JsonResponse
    {
        try {
            $user = Auth::user();
            $evenement = Evenement::findOrFail($evenementId);

            // Vérifications métier
            
            // 1. L'événement doit être actif
            if (!$evenement->est_actif) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cet événement n\'est pas disponible pour les inscriptions.'
                ], 400);
            }

            // 2. L'événement ne doit pas être passé
            if ($evenement->date_heure < now()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Impossible de s\'inscrire à un événement passé.'
                ], 400);
            }

            // 3. Vérifier si déjà inscrit
            if ($evenement->utilisateurEstInscrit($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous êtes déjà inscrit à cet événement.'
                ], 409); // Conflict
            }

            // 4. Vérifier la capacité
            if ($evenement->getEstCompletAttribute()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cet événement est complet. Plus de places disponibles.'
                ], 409);
            }

            // Inscription
            $user->evenements()->attach($evenement->id, [
                'date_inscription' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Inscription réussie ! Vous êtes maintenant inscrit à cet événement.',
                'data' => [
                    'evenement' => [
                        'id' => $evenement->id,
                        'titre' => $evenement->titre,
                        'date_heure' => $evenement->date_heure->format('d/m/Y H:i'),
                        'lieu' => $evenement->lieu
                    ],
                    'date_inscription' => now()->format('d/m/Y H:i'),
                    'places_restantes' => $evenement->fresh()->places_restantes
                ]
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Événement non trouvé.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'inscription.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SE DÉSINSCRIRE d'un événement (DELETE /api/evenements/{id}/inscription)
     */
    public function desinscrire(string $evenementId): JsonResponse
    {
        try {
            $user = auth()->user();
            $evenement = Evenement::findOrFail($evenementId);

            // Vérifier si inscrit
            if (!$evenement->utilisateurEstInscrit($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Vous n\'êtes pas inscrit à cet événement.'
                ], 400);
            }

            // Empêcher la désinscription si l'événement commence dans moins de 24h
            if ($evenement->date_heure <= now()->addDay()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Impossible de se désinscrire d\'un événement qui commence dans moins de 24 heures.'
                ], 400);
            }

            // Désinscription
            $user->evenements()->detach($evenement->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Désinscription réussie.',
                'data' => [
                    'evenement' => [
                        'id' => $evenement->id,
                        'titre' => $evenement->titre
                    ],
                    'places_restantes' => $evenement->fresh()->places_restantes
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Événement non trouvé.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la désinscription.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * MES INSCRIPTIONS - Lister les événements auxquels l'utilisateur est inscrit
     * (GET /api/mes-inscriptions)
     */
    public function mesInscriptions(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $inscriptions = $user->evenements()
                                 ->withPivot('date_inscription')
                                 ->orderBy('date_heure', 'asc')
                                 ->get()
                                 ->map(function ($evenement) {
                                     return [
                                         'id' => $evenement->id,
                                         'titre' => $evenement->titre,
                                         'description' => $evenement->description,
                                         'date_heure' => $evenement->date_heure->format('d/m/Y H:i'),
                                         'lieu' => $evenement->lieu,
                                         'image_url' => $evenement->image_url,
                                         'date_inscription' => $evenement->pivot->date_inscription,
                                         'statut' => $evenement->date_heure < now() ? 'passé' : 'à venir'
                                     ];
                                 });

            return response()->json([
                'status' => 'success',
                'message' => 'Liste de vos inscriptions récupérée avec succès.',
                'data' => $inscriptions,
                'total' => $inscriptions->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de vos inscriptions.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}