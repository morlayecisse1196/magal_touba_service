<?php
// app/Http/Controllers/NotificationController.php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Models\Evenement;
use App\Models\NotificationUser;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    /**
     * Constructeur : Gestion des permissions
     */
    public function __construct()
    {
        // Authentification obligatoire
        $this->middleware('auth:api');
        
        // Seuls les admins peuvent créer/modifier/supprimer des notifications
        $this->middleware(function ($request, $next) {
            if (in_array($request->route()->getActionMethod(), ['store', 'update', 'destroy', 'envoyerATous', 'envoyerAuxInscrits'])) {
                if (!Auth::user()->isAdmin()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Accès refusé. Seuls les administrateurs peuvent gérer les notifications.'
                    ], 403);
                }
            }
            return $next($request);
        });
    }

    /**
     * LISTER toutes les notifications (GET /api/notifications)
     * 
     * Pour les admins : toutes les notifications
     * Pour les users : seulement leurs notifications reçues
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            if ($user->isAdmin()) {
                // Admin : voir toutes les notifications créées
                $query = Notification::with('evenement:id,titre')
                                    ->withCount(['notificationUsers as total_destinataires'])
                                    ->withCount(['notificationUsers as total_lues' => function ($q) {
                                        $q->where('est_lu', true);
                                    }]);

                // Filtre par événement si spécifié
                if ($request->has('evenement_id')) {
                    $query->where('evenement_id', $request->evenement_id);
                }

                // Tri par date d'envoi (plus récent en premier)
                $query->orderBy('date_envoi', 'desc');
                
                $notifications = $query->paginate($request->get('per_page', 10));

                // Enrichir les données
                $notifications->getCollection()->transform(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'evenement' => $notification->evenement ? [
                            'id' => $notification->evenement->id,
                            'titre' => $notification->evenement->titre
                        ] : null,
                        'date_envoi' => $notification->date_envoi->format('d/m/Y H:i'),
                        'total_destinataires' => $notification->total_destinataires,
                        'total_lues' => $notification->total_lues,
                        'taux_lecture' => $notification->total_destinataires > 0 
                            ? round(($notification->total_lues / $notification->total_destinataires) * 100, 1)
                            : 0
                    ];
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'Notifications administrateur récupérées.',
                    'data' => $notifications->items(),
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'last_page' => $notifications->lastPage(),
                        'total' => $notifications->total()
                    ]
                ], 200);

            } else {
                // Utilisateur normal : voir ses notifications reçues
                $query = $user->notifications()
                             ->withPivot(['est_lu', 'date_lu'])
                             ->with('evenement:id,titre');

                // Filtre par statut de lecture
                if ($request->has('statut')) {
                    if ($request->statut === 'non_lues') {
                        $query->wherePivot('est_lu', false);
                    } elseif ($request->statut === 'lues') {
                        $query->wherePivot('est_lu', true);
                    }
                }

                // Tri par date d'envoi (plus récent en premier)
                $query->orderBy('date_envoi', 'desc');
                
                $notifications = $query->paginate($request->get('per_page', 15));

                // Enrichir les données
                $notifications->getCollection()->transform(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'evenement' => $notification->evenement ? [
                            'id' => $notification->evenement->id,
                            'titre' => $notification->evenement->titre
                        ] : null,
                        'date_envoi' => $notification->date_envoi->format('d/m/Y H:i'),
                        'est_lu' => $notification->pivot->est_lu,
                        'date_lu' => $notification->pivot->date_lu ? 
                            $notification->pivot->date_lu->format('d/m/Y H:i') : null
                    ];
                });

                return response()->json([
                    'status' => 'success',
                    'message' => 'Vos notifications récupérées.',
                    'data' => $notifications->items(),
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'last_page' => $notifications->lastPage(),
                        'total' => $notifications->total()
                    ],
                    'stats' => [
                        'total' => $user->notifications()->count(),
                        'non_lues' => $user->notifications()->wherePivot('est_lu', false)->count()
                    ]
                ], 200);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la récupération des notifications.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CRÉER et ENVOYER une notification à TOUS les utilisateurs
     * (POST /api/notifications/envoyer-a-tous)
     * 
     * Réservé aux administrateurs
     */
    public function envoyerATous(Request $request): JsonResponse
    {
        try {
            // Validation des données
            $request->validate([
                'titre' => 'required|string|min:5|max:200',
                'message' => 'required|string|min:10|max:1000'
            ], [
                'titre.required' => 'Le titre est obligatoire.',
                'titre.min' => 'Le titre doit contenir au moins 5 caractères.',
                'message.required' => 'Le message est obligatoire.',
                'message.min' => 'Le message doit contenir au moins 10 caractères.'
            ]);

            // Créer la notification
            $notification = Notification::create([
                'titre' => $request->titre,
                'message' => $request->message,
                'evenement_id' => null, // Notification générale
                'date_envoi' => now()
            ]);

            // Récupérer tous les utilisateurs (sauf les admins si voulu)
            $utilisateurs = User::where('role', 'user')->pluck('id');

            // Créer les liaisons notification-utilisateur
            $donneesLiaison = $utilisateurs->map(function ($userId) use ($notification) {
                return [
                    'notification_id' => $notification->id,
                    'user_id' => $userId,
                    'est_lu' => false,
                    'date_lu' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            });

            // Insérer en lot pour optimiser les performances
            NotificationUser::insert($donneesLiaison->toArray());

            return response()->json([
                'status' => 'success',
                'message' => 'Notification envoyée avec succès à tous les utilisateurs.',
                'data' => [
                    'notification' => [
                        'id' => $notification->id,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'date_envoi' => $notification->date_envoi->format('d/m/Y H:i')
                    ],
                    'nombre_destinataires' => $utilisateurs->count()
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreurs de validation.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'envoi de la notification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CRÉER et ENVOYER une notification aux INSCRITS d'un événement
     * (POST /api/notifications/envoyer-aux-inscrits)
     * 
     * Réservé aux administrateurs
     */
    public function envoyerAuxInscrits(Request $request): JsonResponse
    {
        try {
            // Validation des données
            $request->validate([
                'titre' => 'required|string|min:5|max:200',
                'message' => 'required|string|min:10|max:1000',
                'evenement_id' => 'required|exists:evenements,id'
            ], [
                'titre.required' => 'Le titre est obligatoire.',
                'message.required' => 'Le message est obligatoire.',
                'evenement_id.required' => 'L\'événement est obligatoire.',
                'evenement_id.exists' => 'L\'événement sélectionné n\'existe pas.'
            ]);

            // Vérifier que l'événement existe et récupérer ses inscrits
            $evenement = Evenement::findOrFail($request->evenement_id);
            $inscritsIds = $evenement->utilisateurs()->pluck('users.id');

            if ($inscritsIds->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Aucun utilisateur n\'est inscrit à cet événement.'
                ], 400);
            }

            // Créer la notification
            $notification = Notification::create([
                'titre' => $request->titre,
                'message' => $request->message,
                'evenement_id' => $evenement->id,
                'date_envoi' => now()
            ]);

            // Créer les liaisons notification-utilisateur pour les inscrits
            $donneesLiaison = $inscritsIds->map(function ($userId) use ($notification) {
                return [
                    'notification_id' => $notification->id,
                    'user_id' => $userId,
                    'est_lu' => false,
                    'date_lu' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            });

            // Insérer en lot
            NotificationUser::insert($donneesLiaison->toArray());

            return response()->json([
                'status' => 'success',
                'message' => 'Notification envoyée avec succès aux inscrits de l\'événement.',
                'data' => [
                    'notification' => [
                        'id' => $notification->id,
                        'titre' => $notification->titre,
                        'message' => $notification->message,
                        'date_envoi' => $notification->date_envoi->format('d/m/Y H:i')
                    ],
                    'evenement' => [
                        'id' => $evenement->id,
                        'titre' => $evenement->titre
                    ],
                    'nombre_destinataires' => $inscritsIds->count()
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreurs de validation.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'envoi de la notification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * MARQUER une notification comme LUE
     * (POST /api/notifications/{id}/marquer-comme-lue)
     */
    public function marquerCommeLue(string $notificationId): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Vérifier que l'utilisateur a bien reçu cette notification
            $notificationUser = NotificationUser::where('notification_id', $notificationId)
                                                ->where('user_id', $user->id)
                                                ->first();

            if (!$notificationUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification non trouvée ou vous n\'êtes pas destinataire.'
                ], 404);
            }

            // Si déjà lue, ne rien faire
            if ($notificationUser->est_lu) {
                return response()->json([
                    'status' => 'info',
                    'message' => 'Cette notification est déjà marquée comme lue.'
                ], 200);
            }

            // Marquer comme lue
            $notificationUser->update([
                'est_lu' => true,
                'date_lu' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marquée comme lue.',
                'data' => [
                    'notification_id' => $notificationId,
                    'date_lu' => now()->format('d/m/Y H:i')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du marquage de la notification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * MARQUER TOUTES les notifications comme LUES
     * (POST /api/notifications/marquer-toutes-comme-lues)
     */
    public function marquerToutesCommeLues(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Mettre à jour toutes les notifications non lues de l'utilisateur
            $nombreMisesAJour = NotificationUser::where('user_id', $user->id)
                                                ->where('est_lu', false)
                                                ->update([
                                                    'est_lu' => true,
                                                    'date_lu' => now()
                                                ]);

            return response()->json([
                'status' => 'success',
                'message' => "Toutes vos notifications ont été marquées comme lues.",
                'data' => [
                    'nombre_notifications_marquees' => $nombreMisesAJour,
                    'date_marquage' => now()->format('d/m/Y H:i')
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du marquage des notifications.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * OBTENIR le nombre de notifications non lues
     * (GET /api/notifications/non-lues/count)
     */
    public function compterNonLues(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $nombreNonLues = $user->notifications()
                                 ->wherePivot('est_lu', false)
                                 ->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'nombre_non_lues' => $nombreNonLues,
                    'a_nouvelles_notifications' => $nombreNonLues > 0
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors du comptage des notifications.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * SUPPRIMER une notification (pour les admins)
     * (DELETE /api/notifications/{id})
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $notification = Notification::findOrFail($id);
            
            // Compter les destinataires avant suppression
            $nombreDestinataires = $notification->notificationUsers()->count();
            
            // La suppression cascade supprimera automatiquement les notification_user
            $notification->delete();

            return response()->json([
                'status' => 'success',
                'message' => "Notification supprimée avec succès. {$nombreDestinataires} liaisons utilisateur ont également été supprimées."
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Notification non trouvée.'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de la notification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}