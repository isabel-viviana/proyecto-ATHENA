<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Models\Stimulus;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Gestiona y entrega recursos visuales almacenados en el bucket privado icfes-media.
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

    /**
     * Transmite un archivo de imagen desde el bucket privado icfes-media.
     *
     * GET /api/v1/admin/media?path=...
     */
    public function show(Request $request): Response|JsonResponse
    {
        $path = (string) $request->query('path', '');

        // Solo rutas relativas simples dentro del bucket de medios (bloquea path traversal y otros formatos)
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')
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
                'png'  => 'image/png',
                'webp' => 'image/webp',
                'gif'  => 'image/gif',
                default => 'image/jpeg',
            };

            return response($bytes, 200, [
                'Content-Type'                => $mime,
                'Cache-Control'               => 'private, max-age=3600',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => 'No se pudo recuperar el recurso visual.'], 500);
        }
    }

    /**
     * Lista recursos visuales registrados en media_assets con filtros opcionales.
     *
     * GET /api/v1/admin/media-assets
     */
    public function index(Request $request): JsonResponse
    {
        $query = MediaAsset::with([
            'stimulus:id,subject_id,source_document_id,title,media_type,source_page,instructions,visual_transcription,visual_description',
            'question:id,subject_id,stimulus_id,source_document_id,order_in_group,statement,source_page,status,visual_transcription,visual_description,extraction_warnings',
        ]);

        if ($request->filled('source_document_id')) {
            $docId = (string) $request->query('source_document_id');
            if (! Str::isUuid($docId)) {
                return response()->json(['success' => false, 'message' => 'UUID de documento no válido.'], 422);
            }
            $query->where('source_document_id', $docId);
        }

        if ($request->filled('association_status')) {
            $status = (string) $request->query('association_status');
            if (! in_array($status, ['pending', 'confirmed'], true)) {
                return response()->json(['success' => false, 'message' => 'Estado de asociación no válido.'], 422);
            }
            $query->where('association_status', $status);
        }

        $assets = $query
            ->orderBy('page_number', 'asc')
            ->orderBy('storage_path', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $assets,
            'counts'  => [
                'total'     => $assets->count(),
                'pending'   => $assets->where('association_status', 'pending')->count(),
                'confirmed' => $assets->where('association_status', 'confirmed')->count(),
            ],
        ]);
    }

    /**
     * Consulta los recursos visuales de un documento en media_assets junto con los
     * estímulos y preguntas del mismo documento disponibles para asociación.
     *
     * GET /api/v1/admin/documents/{id}/media
     */
    public function documentMedia(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json(['success' => false, 'message' => 'UUID de documento no válido.'], 422);
        }

        $doc = SourceDocument::find($id);
        if (! $doc) {
            return response()->json(['success' => false, 'message' => 'Documento no encontrado.'], 404);
        }

        try {
            $assets = MediaAsset::with([
                'stimulus:id,subject_id,source_document_id,title,media_type,source_page,instructions',
                'question:id,subject_id,stimulus_id,source_document_id,order_in_group,statement,source_page,status',
            ])
                ->where('source_document_id', $doc->id)
                ->orderBy('page_number', 'asc')
                ->orderBy('storage_path', 'asc')
                ->get();

            $stimuli = Stimulus::with(['questions:id,stimulus_id,order_in_group'])
                ->where('source_document_id', $doc->id)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'subject_id', 'source_document_id', 'title', 'media_type', 'source_page', 'instructions']);

            $questions = Question::where('source_document_id', $doc->id)
                ->orderBy('order_in_group', 'asc')
                ->get(['id', 'subject_id', 'stimulus_id', 'source_document_id', 'order_in_group', 'statement', 'source_page', 'status']);

            $images = $assets->map(function (MediaAsset $asset) {
                return [
                    'id'                 => $asset->id,
                    'source_document_id' => $asset->source_document_id,
                    'storage_path'       => $asset->storage_path,
                    'page'               => $asset->page_number,
                    'page_number'        => $asset->page_number,
                    'width'              => $asset->width,
                    'height'             => $asset->height,
                    'mime_type'          => $asset->mime_type,
                    'sha256'             => $asset->sha256,
                    'status'             => 'ok',
                    'association_status' => $asset->association_status,
                    'associated_at'      => $asset->associated_at?->toIso8601String(),
                    'stimulus_id'        => $asset->stimulus_id,
                    'question_id'        => $asset->question_id,
                    'stimulus'           => $asset->stimulus,
                    'question'           => $asset->question,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data'    => [
                    'extracted'   => $assets->isNotEmpty(),
                    'total_pages' => $doc->total_pages,
                    'counts'      => [
                        'total'     => $assets->count(),
                        'pending'   => $assets->where('association_status', 'pending')->count(),
                        'confirmed' => $assets->where('association_status', 'confirmed')->count(),
                    ],
                    'assets'      => $assets,
                    'images'      => $images,
                    'targets'     => [
                        'stimuli'   => $stimuli,
                        'questions' => $questions,
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron consultar los recursos visuales del documento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirma la asociación de un recurso visual a un estímulo O a una pregunta
     * del mismo documento de origen. No altera las filas de stimuli ni questions.
     *
     * POST /api/v1/admin/media-assets/{id}/associate
     */
    public function associate(Request $request, string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json(['success' => false, 'message' => 'UUID de recurso visual no válido.'], 422);
        }

        $asset = MediaAsset::find($id);
        if (! $asset) {
            return response()->json(['success' => false, 'message' => 'Recurso visual no encontrado.'], 404);
        }

        $targetType = $request->input('target_type');
        $targetId = $request->input('target_id');
        $stimulusIdInput = $request->input('stimulus_id');
        $questionIdInput = $request->input('question_id');

        // Normalizar entrada si se enviaron stimulus_id o question_id directamente
        if (! empty($stimulusIdInput) && ! empty($questionIdInput)) {
            return response()->json([
                'success' => false,
                'message' => 'Un recurso visual solo puede asociarse a un estímulo o a una pregunta, nunca a ambos simultáneamente.',
            ], 422);
        }

        if (empty($targetType)) {
            if (! empty($stimulusIdInput)) {
                $targetType = 'stimulus';
                $targetId = $stimulusIdInput;
            } elseif (! empty($questionIdInput)) {
                $targetType = 'question';
                $targetId = $questionIdInput;
            }
        }

        if (! in_array($targetType, ['stimulus', 'question'], true) || empty($targetId) || ! Str::isUuid((string) $targetId)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe especificar un destino válido (target_type: stimulus|question y un UUID válido).',
            ], 422);
        }

        $stimulusId = null;
        $questionId = null;

        if ($targetType === 'stimulus') {
            $stimulus = Stimulus::find($targetId);
            if (! $stimulus) {
                return response()->json(['success' => false, 'message' => 'El estímulo seleccionado no existe.'], 404);
            }
            if ($stimulus->source_document_id !== $asset->source_document_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'El estímulo seleccionado no pertenece al mismo documento de origen del recurso visual.',
                ], 422);
            }
            $stimulusId = $stimulus->id;
        } else {
            $question = Question::find($targetId);
            if (! $question) {
                return response()->json(['success' => false, 'message' => 'La pregunta seleccionada no existe.'], 404);
            }
            if ($question->source_document_id !== $asset->source_document_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'La pregunta seleccionada no pertenece al mismo documento de origen del recurso visual.',
                ], 422);
            }
            $questionId = $question->id;
        }

        try {
            $asset->update([
                'stimulus_id'        => $stimulusId,
                'question_id'        => $questionId,
                'association_status' => 'confirmed',
                'associated_at'      => now(),
            ]);

            $fresh = MediaAsset::with([
                'stimulus:id,subject_id,source_document_id,title,media_type,source_page,instructions',
                'question:id,subject_id,stimulus_id,source_document_id,order_in_group,statement,source_page,status',
            ])->find($asset->id);

            return response()->json([
                'success' => true,
                'message' => 'Asociación de recurso visual confirmada exitosamente.',
                'data'    => $fresh,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No fue posible asociar el recurso visual: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Desasocia de forma controlada un recurso visual, devolviéndolo a estado 'pending'.
     * Conserva el registro en media_assets y el archivo original en Supabase Storage.
     *
     * POST /api/v1/admin/media-assets/{id}/disassociate
     */
    public function disassociate(string $id): JsonResponse
    {
        if (! Str::isUuid($id)) {
            return response()->json(['success' => false, 'message' => 'UUID de recurso visual no válido.'], 422);
        }

        $asset = MediaAsset::find($id);
        if (! $asset) {
            return response()->json(['success' => false, 'message' => 'Recurso visual no encontrado.'], 404);
        }

        try {
            $asset->update([
                'stimulus_id'        => null,
                'question_id'        => null,
                'association_status' => 'pending',
                'associated_at'      => null,
            ]);

            $fresh = MediaAsset::with([
                'stimulus:id,subject_id,source_document_id,title,media_type,source_page,instructions',
                'question:id,subject_id,stimulus_id,source_document_id,order_in_group,statement,source_page,status',
            ])->find($asset->id);

            return response()->json([
                'success' => true,
                'message' => 'El recurso visual volvió al estado pendiente de asociación. El archivo original permanece intacto en Storage.',
                'data'    => $fresh,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'No fue posible desasociar el recurso visual: ' . $e->getMessage(),
            ], 500);
        }
    }
}
