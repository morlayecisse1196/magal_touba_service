<?php
// app/Http/Controllers/ImageController.php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware(function ($request, $next) {
            if (!Auth::user()->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Accès refusé. Seuls les administrateurs peuvent uploader des images.'
                ], 403);
            }
            return $next($request);
        });
    }

    /**
     * Upload d'une image d'événement
     */
    public function uploadEvenementImage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,webp', // 5MB max
                'evenement_id' => 'nullable|string'
            ]);

            $image = $request->file('image');
            $evenementId = $request->evenement_id ?? 'new';
            
            // Générer un nom unique
            $extension = $image->getClientOriginalExtension();
            $filename = 'evenement-' . $evenementId . '-' . time() . '.' . $extension;
            
            // Créer le dossier s'il n'existe pas dans public
            $uploadPath = public_path('images/evenements');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            // Déplacer le fichier vers public/images/evenements
            $image->move($uploadPath, $filename);

            // Obtenez la taille après avoir déplacé le fichier
            $filePath = $uploadPath . '/' . $filename;
            $fileSize = filesize($filePath);
            
            // Retourner l'URL relative
            $imageUrl = '/images/evenements/' . $filename;

            return response()->json([
                'status' => 'success',
                'message' => 'Image uploadée avec succès',
                'data' => [
                    'url' => $imageUrl,
                    'filename' => $filename,
                    'size' => $fileSize
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreurs de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de l\'upload de l\'image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer une image d'événement
     */
    public function deleteEvenementImage(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'image_url' => 'required|string'
            ]);

            $imageUrl = $request->image_url;
            
            // Extraire le nom du fichier de l'URL
            $filename = basename($imageUrl);
            $filePath = public_path('images/evenements/' . $filename);
            
            // Supprimer le fichier s'il existe
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Image supprimée avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Erreur lors de la suppression de l\'image',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}