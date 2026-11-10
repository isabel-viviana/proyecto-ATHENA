<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Entrega recursos visuales almacenados en el bucket privado icfes-media.
 * El frontend nunca recibe credenciales: el backend lee el archivo y lo transmite.
 */
class MediaController extends Controller
{
    public function __construct(protected SupabaseStorageService $storage)
    {
    }

    protected function mediaBucket(): string
    {
        return config('filesystems.disks.question_bank_media.bucket', 'icfes-media');
    }

    /** GET /api/v1/admin/media?path=... */
    public function show(Request $request): Response|JsonResponse
    {
        $path = (string) $request->query('path', '');

        // Solo rutas relativas simples dentro del bucket de medios.
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/')
            || ! preg_match('#^[A-Za-z0-9_\-./]+\.(jpg|jpeg|png|webp|gif)$#i', $path)) {
            return response()->json(['success' => false, 'message' => 'Ruta de recurso no válida.'], 422);
        }

        try {
            $bucket = $this->mediaBucket();
            if (! $this->storage->exists($bucket, $path)) {
                return response()->json(['success' => false, 'message' => 'El recurso visual no existe.'], 404);
            }

            $bytes = $this->storage->get($bucket, $path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                default => 'image/jpeg',
            };

            return response($bytes, 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'private, max-age=3600',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'No se pudo recuperar el recurso visual.'], 500);
        }
    }

    /** GET /api/v1/admin/documents/{id}/media */
    public function documentMedia(string $id): JsonResponse
    {
        $doc = SourceDocument::find($id);
        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado.'], 404);
        }

        try {
            $path = "extracted/{$doc->id}/manifest.json";
            $bucket = $this->mediaBucket();
            if (! $this->storage->exists($bucket, $path)) {
                return response()->json(['success' => true, 'data' => ['images' => [], 'extracted' => false]]);
            }
            $manifest = json_decode((string) $this->storage->get($bucket, $path), true) ?: [];

            return response()->json(['success' => true, 'data' => [
                'extracted'    => true,
                'generated_at' => $manifest['generated_at'] ?? null,
                'total_pages'  => $manifest['total_pages'] ?? null,
                'images'       => $manifest['images'] ?? [],
            ]]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'No se pudo leer el manifiesto de recursos.'], 500);
        }
    }
}

