<?php
// app/Http/Controllers/PointInteretController.php

namespace App\Http\Controllers;

use App\Models\PointInteret;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Routing\Controller;

class PointInteretController extends Controller
{
    /**
     * Constructeur : Gestion des permissions
     */
    public function __construct()
    {
        // Authentification obligatoire
        $this->middleware('auth:api');
        
        // Seuls les admins pour créer/modifier/supprimer
        $this->middleware(function ($request, $next) {
            if (in_array($request->route()->getActionMethod(), ['store', 'update', 'destroy'])) {
                if (!Auth::user()->isAdmin()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Seuls les administrateurs peuvent gérer les points d\'intérêt.'
                    ], 403);
                }
            }
            return $next($request);
        });
    }

    /**
     * LISTER tous les points d'intérêt (GET /api/points-interet)
     * 
     * Avec filtres par type et recherche simple
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PointInteret::query();

            // Filtre par type
            if ($request->has('type') && $request->type !== 'tous') {
                $query->where('type', $request->type);
            }

            // Recherche simple dans nom et adresse
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nom', 'LIKE', "%{$search}%")
                      ->orWhere('adresse', 'LIKE', "%{$search}%");
                });
            }

            // Tri par nom par défaut
            $query->orderBy('nom');

            // Pagination
            $perPage = $request->get('per_page', 10);
            $pointsInteret = $query->paginate($perPage);

            // Enrichir chaque point avec info sur les favoris
            $pointsInteret->getCollection()->transform(function ($point) {
                return [
                    'id' => $point->id,
                    'nom' => $point->nom,
                    'type' => $point->type,
                    'type_libelle' => $point->type_libelle, // Utilise l'accesseur du modèle
                    'adresse' => $point->adresse,
                    'description' => $point->description,
                    'numero_urgence' => $point->numero_urgence,
                    'image_url' => $point->image_url,
                    'nombre_favoris' => $point->getNombreFavoris(),
                    'est_dans_mes_favoris' => $point->estDansLesFavoris(Auth::id()),
                    'created_at' => $point->created_at->format('d/m/Y H:i')
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Points d\'intérêt récupérés avec succès.',
                'data' => $pointsInteret->items(),
                'pagination' => [
                    'current_page' => $pointsInteret->currentPage(),
                    'last_page' => $pointsInteret->lastPage(),
                    'per_page' => $pointsInteret->perPage(),
                    'total' => $pointsInteret->total()
                ],
                'filters_applied' => $request->only(['type', 'search']),
                'types_disponibles' => PointInteret::TYPES
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des points d\'intérêt.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CRÉER un nouveau point d'intérêt (POST /api/points-interet)
     * 
     * Réservé aux administrateurs
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validation simple directement dans le controller
            $validatedData = $request->validate([
                'nom' => 'required|string|min:3|max:200',
                'type' => ['required', 'string', Rule::in(array_keys(PointInteret::TYPES))],
                'adresse' => 'nullable|string|max:300',
                'description' => 'nullable|string|max:1000',
                'numero_urgence' => 'nullable|string',
                'image_url' => 'nullable|url|max:500'
            ]);

            // Créer le point d'intérêt
            $pointInteret = PointInteret::create($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Point d\'intérêt créé avec succès.',
                'data' => [
                    'id' => $pointInteret->id,
                    'nom' => $pointInteret->nom,
                    'type' => $pointInteret->type,
                    'type_libelle' => $pointInteret->type_libelle,
                    'adresse' => $pointInteret->adresse,
                    'description' => $pointInteret->description,
                    'numero_urgence' => $pointInteret->numero_urgence,
                    'image_url' => $pointInteret->image_url,
                    'created_at' => $pointInteret->created_at->format('d/m/Y H:i')
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Données invalides.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * AFFICHER un point d'intérêt (GET /api/points-interet/{id})
     * 
     * Avec détails complets
     */
    public function show(string $id): JsonResponse
    {
        try {
            $pointInteret = PointInteret::findOrFail($id);
            
            $data = [
                'id' => $pointInteret->id,
                'nom' => $pointInteret->nom,
                'type' => $pointInteret->type,
                'type_libelle' => $pointInteret->type_libelle,
                'adresse' => $pointInteret->adresse,
                'description' => $pointInteret->description,
                'numero_urgence' => $pointInteret->numero_urgence,
                'image_url' => $pointInteret->image_url,
                'nombre_favoris' => $pointInteret->getNombreFavoris(),
                'est_dans_mes_favoris' => $pointInteret->estDansLesFavoris(Auth::id()),
                'created_at' => $pointInteret->created_at->format('d/m/Y H:i'),
                'updated_at' => $pointInteret->updated_at->format('d/m/Y H:i')
            ];

            // Si admin, ajouter la liste des utilisateurs qui ont ce point en favori
            if (Auth::user()->isAdmin()) {
                $utilisateursFavoris = $pointInteret->utilisateursFavoris()
                                                   ->select('users.id', 'users.nom', 'users.prenom', 'favoris.date_ajout')
                                                   ->get()
                                                   ->map(function ($user) {
                                                       return [
                                                           'id' => $user->id,
                                                           'nom' => $user->nom,
                                                           'prenom' => $user->prenom,
                                                           'date_ajout_favoris' => $user->pivot->date_ajout
                                                       ];
                                                   });

                $data['utilisateurs_favoris'] = $utilisateursFavoris;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Détails récupérés avec succès.',
                'data' => $data
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Point d\'intérêt non trouvé.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * MODIFIER un point d'intérêt (PUT /api/points-interet/{id})
     * 
     * Réservé aux administrateurs
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $pointInteret = PointInteret::findOrFail($id);

            // Validation avec règles optionnelles pour la mise à jour
            $validatedData = $request->validate([
                'nom' => 'sometimes|string|min:3|max:200',
                'type' => ['sometimes', 'string', Rule::in(array_keys(PointInteret::TYPES))],
                'adresse' => 'sometimes|nullable|string|max:300',
                'description' => 'sometimes|nullable|string|max:1000',
                'numero_urgence' => 'sometimes|nullable|string|regex:/^(\+221|00221|221)?[7][0-8][0-9]{7}$/',
                'image_url' => 'sometimes|nullable|url|max:500'
            ], [
                'nom.min' => 'Le nom doit contenir au moins 3 caractères.',
                'type.in' => 'Le type sélectionné n\'est pas valide.',
                'numero_urgence.regex' => 'Le numéro doit être au format sénégalais.',
                'image_url.url' => 'L\'URL de l\'image n\'est pas valide.'
            ]);

            // Mettre à jour
            $pointInteret->update($validatedData);

            return response()->json([
                'status' => 'success',
                'message' => 'Point d\'intérêt modifié avec succès.',
                'data' => [
                    'id' => $pointInteret->id,
                    'nom' => $pointInteret->nom,
                    'type' => $pointInteret->type,
                    'type_libelle' => $pointInteret->type_libelle,
                    'adresse' => $pointInteret->adresse,
                    'description' => $pointInteret->description,
                    'numero_urgence' => $pointInteret->numero_urgence,
                    'image_url' => $pointInteret->image_url,
                    'updated_at' => $pointInteret->updated_at->format('d/m/Y H:i')
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Point d\'intérêt non trouvé.'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Données invalides.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la modification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SUPPRIMER un point d'intérêt (DELETE /api/points-interet/{id})
     * 
     * Réservé aux administrateurs
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $pointInteret = PointInteret::findOrFail($id);
            
            // Compter les favoris avant suppression
            $nombreFavoris = $pointInteret->getNombreFavoris();
            
            // Supprimer (les favoris seront supprimés automatiquement avec cascade)
            $pointInteret->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Point d\'intérêt supprimé avec succès. {$nombreFavoris} favoris supprimés également."
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Point d\'intérêt non trouvé.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}