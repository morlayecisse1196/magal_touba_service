<?php
// app/Http/Controllers/FavoriController.php

namespace App\Http\Controllers;

use App\Models\PointInteret;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class FavoriController extends Controller
{
    /**
     * Constructeur : Authentification obligatoire
     */
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * AJOUTER un point aux favoris
     * (POST /api/points-interet/{id}/favori)
     */
    public function ajouter(string $pointInteretId): JsonResponse
    {
        try {
            $user = Auth::user();
            $pointInteret = PointInteret::findOrFail($pointInteretId);

            // Vérifier si déjà en favoris
            if ($pointInteret->estDansLesFavoris($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce point d\'intérêt est déjà dans vos favoris.'
                ], 409);
            }

            // Ajouter aux favoris
            $user->pointsInteretFavoris()->attach($pointInteret->id, [
                'date_ajout' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Point d\'intérêt ajouté à vos favoris.',
                'data' => [
                    'point_interet' => [
                        'id' => $pointInteret->id,
                        'nom' => $pointInteret->nom,
                        'type' => $pointInteret->type,
                        'type_libelle' => $pointInteret->type_libelle
                    ],
                    'date_ajout' => now()->format('d/m/Y H:i')
                ]
            ], 201);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Point d\'intérêt non trouvé.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'ajout aux favoris.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * RETIRER un point des favoris
     * (DELETE /api/points-interet/{id}/favori)
     */
    public function retirer(string $pointInteretId): JsonResponse
    {
        try {
            $user = Auth::user();
            $pointInteret = PointInteret::findOrFail($pointInteretId);

            // Vérifier si dans les favoris
            if (!$pointInteret->estDansLesFavoris($user->id)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ce point d\'intérêt n\'est pas dans vos favoris.'
                ], 400);
            }

            // Retirer des favoris
            $user->pointsInteretFavoris()->detach($pointInteret->id);

            return response()->json([
                'status' => 'success',
                'message' => 'Point d\'intérêt retiré de vos favoris.',
                'data' => [
                    'point_interet' => [
                        'id' => $pointInteret->id,
                        'nom' => $pointInteret->nom,
                        'type' => $pointInteret->type
                    ]
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Point d\'intérêt non trouvé.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du retrait des favoris.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * MES FAVORIS - Liste de mes points d'intérêt favoris
     * (GET /api/mes-favoris)
     */
    public function mesFavoris(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = $user->pointsInteretFavoris()
                          ->withPivot('date_ajout');

            // Filtre par type si spécifié
            if ($request->has('type') && $request->type !== 'tous') {
                $query->where('type', $request->type);
            }

            // Tri par date d'ajout (plus récent en premier par défaut)
            $sortOrder = $request->get('sort', 'desc');
            $query->orderBy('favoris.date_ajout', $sortOrder);

            $favoris = $query->get();

            // Formatter les résultats
            $results = $favoris->map(function ($point) {
                return [
                    'id' => $point->id,
                    'nom' => $point->nom,
                    'type' => $point->type,
                    'type_libelle' => $point->type_libelle,
                    'adresse' => $point->adresse,
                    'description' => $point->description,
                    'numero_urgence' => $point->numero_urgence,
                    'image_url' => $point->image_url,
                    'date_ajout_favoris' => $point->pivot->date_ajout,
                    'nombre_favoris_total' => $point->getNombreFavoris()
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Liste de vos favoris récupérée avec succès.',
                'data' => $results,
                'total' => $results->count(),
                'filters_applied' => $request->only(['type', 'sort']),
                'types_disponibles' => PointInteret::TYPES
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de vos favoris.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}