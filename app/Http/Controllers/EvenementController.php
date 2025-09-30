<?php
// app/Http/Controllers/EvenementController.php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use App\Http\Requests\Evenement\StoreEvenementRequest;
use App\Http\Requests\Evenement\UpdateEvenementRequest;
use App\Models\Evenement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class EvenementController extends Controller
{
    /**
     * Constructeur : Définir les permissions
     * 
     * - index, show : Tout le monde peut voir les événements
     * - store, update, destroy : Seuls les admins
     */
    public function __construct()
    {
        // Toutes les routes nécessitent une authentification
        $this->middleware('auth:api');
        
        // Seuls les admins peuvent créer/modifier/supprimer
        $this->middleware(function ($request, $next) {
            if (in_array($request->route()->getActionMethod(), ['store', 'update', 'destroy'])) {
                if (!Auth::user()->isAdmin()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Accès refusé. Seuls les administrateurs peuvent effectuer cette action.'
                    ], 403);
                }
            }
            return $next($request);
        });
    }

    /**
     * LISTER tous les événements (GET /api/evenements)
     * 
     * Avec filtres optionnels : statut, date, recherche
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Commencer la requête de base
            $query = Evenement::query();

            // Filtre par statut (actif/inactif)
            if ($request->has('statut')) {
                if ($request->statut === 'actif') {
                    $query->actifs();
                } elseif ($request->statut === 'inactif') {
                    $query->where('est_actif', false);
                }
            }

            // Filtre par période (à venir/passés)
            if ($request->has('periode')) {
                if ($request->periode === 'avenir') {
                    $query->aVenir();
                } elseif ($request->periode === 'passes') {
                    $query->passes();
                }
            }

            // Recherche textuelle dans titre, description et lieu
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('titre', 'LIKE', "%{$search}%")
                      ->orWhere('description', 'LIKE', "%{$search}%")
                      ->orWhere('lieu', 'LIKE', "%{$search}%");
                });
            }

            // Trier par date (les plus récents en premier par défaut)
            $sortOrder = $request->get('sort', 'asc'); // asc ou desc
            $query->orderBy('date_heure', $sortOrder);

            // Pagination (10 par page par défaut)
            $perPage = $request->get('per_page', 10);
            $evenements = $query->paginate($perPage);

            // Enrichir chaque événement avec des données calculées
            $evenements->getCollection()->transform(function ($evenement) {
                return [
                    'id' => $evenement->id,
                    'titre' => $evenement->titre,
                    'description' => $evenement->description,
                    'date_heure' => $evenement->date_heure->format('d/m/Y H:i'),
                    'date_heure_iso' => $evenement->date_heure->toISOString(),
                    'lieu' => $evenement->lieu,
                    'capacite_max' => $evenement->capacite_max,
                    'places_restantes' => $evenement->places_restantes,
                    'nombre_inscrits' => $evenement->getNombreInscrits(),
                    'est_complet' => $evenement->est_complet,
                    'est_actif' => $evenement->est_actif,
                    'image_url' => $evenement->image_url,
                    'created_at' => $evenement->created_at->format('d/m/Y H:i'),
                    
                    // Info pour l'utilisateur connecté
                    'utilisateur_inscrit' => $evenement->utilisateurEstInscrit(Auth::id())
                ];
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Liste des événements récupérée avec succès.',
                'data' => $evenements->items(),
                'pagination' => [
                    'current_page' => $evenements->currentPage(),
                    'last_page' => $evenements->lastPage(),
                    'per_page' => $evenements->perPage(),
                    'total' => $evenements->total()
                ],
                'filters_applied' => $request->only(['statut', 'periode', 'search', 'sort'])
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des événements.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * CRÉER un nouvel événement (POST /api/evenements)
     * 
     * Réservé aux administrateurs
     */
    public function store(StoreEvenementRequest $request): JsonResponse
    {
        try {
            // Les données sont déjà validées par StoreEvenementRequest
            $evenement = Evenement::create([
                'titre' => $request->titre,
                'description' => $request->description,
                'date_heure' => $request->date_heure,
                'lieu' => $request->lieu,
                'capacite_max' => $request->capacite_max,
                'image_url' => $request->image_url,
                'est_actif' => $request->est_actif ?? true
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Événement créé avec succès.',
                'data' => [
                    'id' => $evenement->id,
                    'titre' => $evenement->titre,
                    'description' => $evenement->description,
                    'date_heure' => $evenement->date_heure->format('d/m/Y H:i'),
                    'lieu' => $evenement->lieu,
                    'capacite_max' => $evenement->capacite_max,
                    'est_actif' => $evenement->est_actif,
                    'image_url' => $evenement->image_url,
                    'created_at' => $evenement->created_at->format('d/m/Y H:i')
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la création de l\'événement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * AFFICHER un événement spécifique (GET /api/evenements/{id})
     * 
     * Avec détails complets et liste des inscrits (pour les admins)
     */
    public function show(string $id): JsonResponse
    {
        try {
            $evenement = Evenement::findOrFail($id);
            
            $data = [
                'id' => $evenement->id,
                'titre' => $evenement->titre,
                'description' => $evenement->description,
                'date_heure' => $evenement->date_heure->format('d/m/Y H:i'),
                'date_heure_iso' => $evenement->date_heure->toISOString(),
                'lieu' => $evenement->lieu,
                'capacite_max' => $evenement->capacite_max,
                'places_restantes' => $evenement->places_restantes,
                'nombre_inscrits' => $evenement->getNombreInscrits(),
                'est_complet' => $evenement->est_complet,
                'est_actif' => $evenement->est_actif,
                'image_url' => $evenement->image_url,
                'created_at' => $evenement->created_at->format('d/m/Y H:i'),
                'updated_at' => $evenement->updated_at->format('d/m/Y H:i'),
                
                // Info pour l'utilisateur connecté
                'utilisateur_inscrit' => $evenement->utilisateurEstInscrit(Auth::id())
            ];

            // Si l'utilisateur est admin, ajouter la liste des inscrits
            if (Auth::user()->isAdmin()) {
                $inscrits = $evenement->utilisateurs()
                                     ->select('users.id', 'users.nom', 'users.prenom', 'users.email', 'inscriptions.date_inscription')
                                     ->get()
                                     ->map(function ($user) {
                                         return [
                                             'id' => $user->id,
                                             'nom' => $user->nom,
                                             'prenom' => $user->prenom,
                                             'email' => $user->email,
                                             'date_inscription' => $user->pivot->date_inscription
                                         ];
                                     });

                $data['inscrits'] = $inscrits;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Détails de l\'événement récupérés avec succès.',
                'data' => $data
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Événement non trouvé.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération de l\'événement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

     /**
     * MODIFIER un événement (PUT/PATCH /api/evenements/{id})
     * 
     * Réservé aux administrateurs
     */
    public function update(UpdateEvenementRequest $request, string $id): JsonResponse
    {
        try {
            $evenement = Evenement::findOrFail($id);

            // Mettre à jour uniquement les champs fournis
            $evenement->update($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Événement modifié avec succès.',
                'data' => [
                    'id' => $evenement->id,
                    'titre' => $evenement->titre,
                    'description' => $evenement->description,
                    'date_heure' => $evenement->date_heure->format('d/m/Y H:i'),
                    'lieu' => $evenement->lieu,
                    'capacite_max' => $evenement->capacite_max,
                    'est_actif' => $evenement->est_actif,
                    'image_url' => $evenement->image_url,
                    'updated_at' => $evenement->updated_at->format('d/m/Y H:i')
                ]
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Événement non trouvé.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la modification de l\'événement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SUPPRIMER un événement (DELETE /api/evenements/{id})
     * 
     * Réservé aux administrateurs
     * ATTENTION : Supprime aussi toutes les inscriptions liées
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $evenement = Evenement::findOrFail($id);
            
            // Compter les inscriptions avant suppression
            $nombreInscrits = $evenement->getNombreInscrits();
            
            // La suppression cascade supprimera automatiquement les inscriptions
            $evenement->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Événement supprimé avec succès. {$nombreInscrits} inscriptions ont également été supprimées.",
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Événement non trouvé.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de l\'événement.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}