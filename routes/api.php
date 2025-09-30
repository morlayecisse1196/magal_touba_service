<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\FavoriController;
use App\Http\Controllers\ImageController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PointInteretController;
use App\Models\Evenement;
use App\Models\PointInteret;
use App\Models\User;


// Route de test temporaire (à supprimer en production)
Route::get('/test', function () {
    
    echo "<h2>🧪 Test des Modèles Eloquent</h2>";
    
    // Test 1 : Créer un utilisateur
    echo "<h3>Test 1 : Création d'un utilisateur</h3>";
    $user = User::create([
        'nom' => 'Diallo',
        'prenom' => 'Amadou',
        'email' => 'amad@test.com',
        'password' => bcrypt('password123'),
        'telephone' => '771234567',
        'role' => 'user'
    ]);
    echo "✅ Utilisateur créé : {$user->prenom} {$user->nom} (ID: {$user->id})<br>";
    
    // Test 2 : Créer un événement
    echo "<h3>Test 2 : Création d'un événement</h3>";
    $evenement = Evenement::create([
        'titre' => 'Grande Conférence sur Serigne Touba',
        'description' => 'Conférence spirituelle exceptionnelle',
        'date_heure' => now()->addDays(7),
        'lieu' => 'Grande Mosquée de Touba',
        'capacite_max' => 500,
        'est_actif' => true
    ]);
    echo "✅ Événement créé : {$evenement->titre} (ID: {$evenement->id})<br>";
    
    // Test 3 : Créer un point d'intérêt
    echo "<h3>Test 3 : Création d'un point d'intérêt</h3>";
    $point = PointInteret::create([
        'nom' => 'Grande Mosquée de Touba',
        'type' => 'mosquee',
        'adresse' => 'Touba, Sénégal',
        'latitude' => 14.8500,
        'longitude' => -15.8833,
        'description' => 'Mosquée principale de Touba'
    ]);
    echo "✅ Point d'intérêt créé : {$point->nom} (ID: {$point->id})<br>";
    
    // Test 4 : Test des relations
    echo "<h3>Test 4 : Relations</h3>";
    
    // Inscrire l'utilisateur à l'événement
    $user->evenements()->attach($evenement->id, [
        'date_inscription' => now()
    ]);
    echo "✅ Utilisateur inscrit à l'événement<br>";
    
    // Ajouter le point aux favoris
    $user->pointsInteretFavoris()->attach($point->id, [
        'date_ajout' => now()
    ]);
    echo "✅ Point d'intérêt ajouté aux favoris<br>";
    
    // Test 5 : Vérification des relations
    echo "<h3>Test 5 : Vérification des relations</h3>";
    
    $userEvents = $user->evenements()->count();
    echo "📊 Nombre d'événements de l'utilisateur : {$userEvents}<br>";
    
    $userFavoris = $user->pointsInteretFavoris()->count();
    echo "📊 Nombre de favoris de l'utilisateur : {$userFavoris}<br>";
    
    $eventUsers = $evenement->utilisateurs()->count();
    echo "📊 Nombre d'inscrits à l'événement : {$eventUsers}<br>";
    
    // Test 6 : Utilisation des accesseurs
    echo "<h3>Test 6 : Accesseurs</h3>";
    echo "📍 Type de point d'intérêt : {$point->type_libelle}<br>";
    echo "📊 Places restantes événement : " . ($evenement->places_restantes ?? 'Illimité') . "<br>";
    echo "❓ Événement complet : " . ($evenement->est_complet ? 'Oui' : 'Non') . "<br>";
    
    echo "<br>✅ <strong>Tous les tests sont passés avec succès !</strong>";
    
    return response('Tests terminés - Vérifiez votre navigateur');
});

/*
|--------------------------------------------------------------------------
| ROUTES D'AUTHENTIFICATION
|--------------------------------------------------------------------------
|
| Ces routes gèrent l'inscription, la connexion et la gestion des tokens JWT
|
*/
// Routes d'authentification (étape précédente)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    Route::middleware('auth:api')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| ROUTES DES ÉVÉNEMENTS
|--------------------------------------------------------------------------
*/

// Routes Resource pour les événements (CRUD complet)
Route::middleware('auth:api')->group(function () {
    
    // CRUD des événements
    Route::apiResource('evenements', EvenementController::class);
    // Génère automatiquement :
    // GET    /api/evenements           → index()    (lister)
    // POST   /api/evenements           → store()    (créer)     [ADMIN SEULEMENT]
    // GET    /api/evenements/{id}      → show()     (détail)
    // PUT    /api/evenements/{id}      → update()   (modifier)  [ADMIN SEULEMENT]  
    // DELETE /api/evenements/{id}      → destroy()  (supprimer) [ADMIN SEULEMENT]


    // Gestion des inscriptions
    Route::post('evenements/{evenement}/inscription', [InscriptionController::class, 'inscrire'])
         ->name('evenements.inscription');
         
         // Liste des inscriptions de l'utilisateur connecté
         Route::get('mes-inscriptions', [InscriptionController::class, 'mesInscriptions'])
         ->name('mes-inscriptions');

         Route::delete('evenements/{evenement}/inscription', [InscriptionController::class, 'desinscrire'])
              ->name('evenements.desinscription');

});

/*
|--------------------------------------------------------------------------
| ROUTES DES POINTS D'INTÉRÊT ET FAVORIS (VERSION SIMPLIFIÉE)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    
    // CRUD des points d'intérêt (admin uniquement pour create/update/delete)
    Route::apiResource('points-interet', PointInteretController::class);
    // GET    /api/points-interet           → index()    (lister - tous)
    // POST   /api/points-interet           → store()    (créer - admin seulement)
    // GET    /api/points-interet/{id}      → show()     (détail - tous)
    // PUT    /api/points-interet/{id}      → update()   (modifier - admin seulement)  
    // DELETE /api/points-interet/{id}      → destroy()  (supprimer - admin seulement)

    // Gestion des favoris (utilisateurs)
    Route::post('points-interet/{pointInteret}/favori', [FavoriController::class, 'ajouter'])
         ->name('favoris.ajouter');
         
    Route::delete('points-interet/{pointInteret}/favori', [FavoriController::class, 'retirer'])
         ->name('favoris.retirer');
         
    Route::get('mes-favoris', [FavoriController::class, 'mesFavoris'])
         ->name('mes.favoris');
});

/*
|--------------------------------------------------------------------------
| ROUTES DES NOTIFICATIONS (VERSION SIMPLIFIÉE)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:api')->group(function () {
    
    // Consulter les notifications (admin = toutes, user = siennes)
    Route::get('notifications', [NotificationController::class, 'index'])
         ->name('notifications.index');

    // Actions pour les utilisateurs normaux
    Route::post('notifications/{notification}/marquer-comme-lue', [NotificationController::class, 'marquerCommeLue'])
         ->name('notifications.marquer-lue');
         
    Route::post('notifications/marquer-toutes-comme-lues', [NotificationController::class, 'marquerToutesCommeLues'])
         ->name('notifications.marquer-toutes-lues');
         
    Route::get('notifications/non-lues/count', [NotificationController::class, 'compterNonLues'])
         ->name('notifications.compter-non-lues');

    // Actions pour les administrateurs uniquement
    Route::post('notifications/envoyer-a-tous', [NotificationController::class, 'envoyerATous'])
         ->name('notifications.envoyer-tous');
         
    Route::post('notifications/envoyer-aux-inscrits', [NotificationController::class, 'envoyerAuxInscrits'])
         ->name('notifications.envoyer-inscrits');
         
    Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])
         ->name('notifications.destroy');

    // routes/api.php - Ajouter ces routes dans le groupe middleware auth:api

    // Upload d'images (Admin seulement)
    Route::post('/images/evenements/upload', [ImageController::class, 'uploadEvenementImage'])
        ->name('images.evenements.upload');
        
    Route::delete('/images/evenements/delete', [ImageController::class, 'deleteEvenementImage'])
        ->name('images.evenements.delete');
});


// Route temporaire pour créer un admin (à supprimer en production)
Route::post('/create-admin', function () {
    $admin = \App\Models\User::create([
        'nom' => 'Admin',
        'prenom' => 'Système',
        'email' => 'admin@magaltouba.com',
        'password' => bcrypt('AdminPassword123!'),
        'role' => 'admin'
    ]);
    
    $token = \Tymon\JWTAuth\Facades\JWTAuth::fromUser($admin);
    
    return response()->json([
        'message' => 'Admin créé avec succès',
        'admin' => $admin,
        'token' => $token
    ]);
});

