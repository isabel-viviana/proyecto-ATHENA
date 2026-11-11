<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIqpeDocumentJob;
use App\Models\IqpeDocumentJob;
use App\Models\IqpeExecutionLog;
use App\Models\SourceDocument;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentController extends Controller
{
    public function __construct(
        protected SupabaseStorageService $storageService
    ) {}

    /**
     * Listar documentos con filtros y paginación.
     *
     * GET /api/v1/admin/documents
     * Parámetros opcionales: status, year, exam_type, search, per_page, page.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SourceDocument::query();

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filtro por año
        if ($request->filled('year')) {
            $query->where('year', (int) $request->input('year'));
        }

        // Filtro por tipo de examen (default 'Saber 11')
        if ($request->filled('exam_type')) {
            $query->where('exam_type', $request->input('exam_type'));
        }

        // Búsqueda por título (insensible a mayúsculas en PostgreSQL con ILIKE)
        if ($request->filled('search')) {
            $search = '%' . trim($request->input('search')) . '%';
            $query->where('title', 'ilike', $search);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $documents = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $documents->items(),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page'    => $documents->lastPage(),
                'per_page'     => $documents->perPage(),
                'total'        => $documents->total(),
            ],
        ]);
    }

    /**
     * Registrar y subir un nuevo documento PDF.
     *
     * POST /api/v1/admin/documents
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validación de campos y tamaño
        $maxKilobytes = (int) config('services.question_bank.max_document_size_kb', 51200); // 50MB default

        // Normalizar booleanos en multipart/form-data si vienen como string
        if ($request->has('has_answer_key')) {
            $request->merge([
                'has_answer_key' => $request->boolean('has_answer_key'),
            ]);
        }

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                "max:{$maxKilobytes}",
            ],
            'title' => ['required', 'string', 'max:255'],
            'year'  => ['nullable', 'integer', 'between:1990,2050'],
            'exam_type' => ['nullable', 'string', 'max:100'],
            'total_pages' => ['nullable', 'integer', 'min:1'],
            'has_answer_key' => ['nullable', 'boolean'],
            'subject_id' => ['nullable', 'integer'], // Recibido como metadato informativo si se provee
        ], [
            'file.required' => 'Debe adjuntar un archivo PDF obligatoriamente.',
            'file.mimes'    => 'El archivo debe tener formato PDF.',
            'file.max'      => "El archivo excede el tamaño máximo permitido de {$maxKilobytes} KB.",
            'title.required' => 'El título del documento es obligatorio.',
        ]);

        $file = $request->file('file');

        // 2. Validación de firma binaria real del PDF (Magic Bytes: %PDF-)
        $handle = fopen($file->getRealPath(), 'rb');
        $magicBytes = fread($handle, 4);
        fclose($handle);

        if ($magicBytes !== '%PDF') {
            throw ValidationException::withMessages([
                'file' => ['El archivo cargado no es un documento PDF válido (firma binaria incorrecta).'],
            ]);
        }

        // 3. Preparación de la ruta en Storage privado
        $yearFolder = !empty($validated['year']) ? (string) $validated['year'] : 'general';
        $uuid = (string) Str::uuid();
        $safeBaseName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $storagePath = "documents/{$yearFolder}/{$uuid}_{$safeBaseName}.pdf";
        $bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');

        $uploaded = false;

        try {
            // 4. Subida física al bucket privado icfes-documents
            $uploaded = $this->storageService->upload(
                bucket: $bucket,
                path: $storagePath,
                contents: file_get_contents($file->getRealPath()),
                options: [
                    'ContentType' => 'application/pdf',
                ]
            );

            if (! $uploaded) {
                throw new \RuntimeException('No fue posible completar la subida física del PDF a Supabase Storage.');
            }

            // 5. Inserción en la tabla source_documents de PostgreSQL
            $document = SourceDocument::create([
                'id'             => $uuid,
                'title'          => trim($validated['title']),
                'storage_path'   => $storagePath,
                'year'           => $validated['year'] ?? null,
                'exam_type'      => $validated['exam_type'] ?? 'Saber 11',
                'total_pages'    => $validated['total_pages'] ?? null,
                'has_answer_key' => $request->boolean('has_answer_key', false),
                'status'         => 'uploaded',
            ]);

            // 6. Creación y Despacho Asíncrono de Trabajo IQPE
            $jobUuid = (string) Str::uuid();
            $runNumber = 1;

            $iqpeJob = IqpeDocumentJob::create([
                'id'                 => $jobUuid,
                'source_document_id' => $document->id,
                'run_number'         => $runNumber,
                'status'             => 'queued',
                'config'             => [
                    'auto_dispatched' => true,
                    'original_name'   => $file->getClientOriginalName(),
                    'size_bytes'      => $file->getSize(),
                ],
            ]);

            IqpeExecutionLog::record(
                $jobUuid,
                'pdf_ingestion',
                'info',
                "Documento '{$document->title}' cargado exitosamente. Trabajo IQPE #{$runNumber} encolado para procesamiento asíncrono."
            );

            // Despachar Job a la cola en segundo plano
            ProcessIqpeDocumentJob::dispatch($document->id, $jobUuid);

            return response()->json([
                'success' => true,
                'message' => 'Documento PDF subido exitosamente. Procesamiento IQPE encolado en segundo plano.',
                'data' => [
                    'id'             => $document->id,
                    'document_id'    => $document->id,
                    'job_id'         => $jobUuid,
                    'run_number'     => $runNumber,
                    'title'          => $document->title,
                    'storage_path'   => $document->storage_path,
                    'year'           => $document->year,
                    'exam_type'      => $document->exam_type,
                    'total_pages'    => $document->total_pages,
                    'has_answer_key' => $document->has_answer_key,
                    'status'         => 'queued',
                    'created_at'     => $document->created_at?->toIso8601String(),
                    'file_metadata'  => [
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type'     => 'application/pdf',
                        'size_bytes'    => $file->getSize(),
                    ],
                ],
            ], 201);

        } catch (Throwable $e) {
            // 6. Limpieza preventiva en caso de error en la base de datos
            if ($uploaded) {
                try {
                    $this->storageService->delete($bucket, $storagePath, force: true);
                } catch (Throwable $cleanupError) {
                    // Evitar enmascarar la excepción original
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el documento PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar detalles de un documento.
     *
     * GET /api/v1/admin/documents/{id}
     */
    public function show(string $id): JsonResponse
    {
        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');
        $fileExists = $this->storageService->exists($bucket, $document->storage_path);

        $storageDetails = [
            'bucket'      => $bucket,
            'file_exists' => $fileExists,
            'size_bytes'  => $fileExists ? $this->storageService->size($bucket, $document->storage_path) : null,
            'mime_type'   => $fileExists ? $this->storageService->mimeType($bucket, $document->storage_path) : null,
        ];

        // Se puede solicitar una URL temporal firmada para previsualización administrativa protegida
        $signedUrl = null;
        if (request()->boolean('signed_url') && $fileExists) {
            $signedUrl = $this->storageService->temporaryUrl($bucket, $document->storage_path, 3600);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id'             => $document->id,
                'title'          => $document->title,
                'storage_path'   => $document->storage_path,
                'year'           => $document->year,
                'exam_type'      => $document->exam_type,
                'total_pages'    => $document->total_pages,
                'has_answer_key' => $document->has_answer_key,
                'status'         => $document->status,
                'created_at'     => $document->created_at?->toIso8601String(),
                'storage'        => $storageDetails,
                'temporary_url'  => $signedUrl,
            ],
        ]);
    }

    /**
     * Eliminar un documento con confirmación explícita.
     *
     * DELETE /api/v1/admin/documents/{id}?confirm=true
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        // 1. Requerir confirmación explícita
        $confirmed = $request->boolean('confirm') || $request->query('confirm') === 'true';

        if (! $confirmed) {
            return response()->json([
                'success' => false,
                'message' => 'Eliminación rechazada: se requiere confirmación explícita obligatoria (?confirm=true o body { "confirm": true }).',
            ], 400);
        }

        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        // 2. Comprobar integridad referencial (stimuli y questions)
        $stimuliCount = DB::connection('question_bank')
            ->table('stimuli')
            ->where('source_document_id', $id)
            ->count();

        $questionsCount = DB::connection('question_bank')
            ->table('questions')
            ->where('source_document_id', $id)
            ->count();

        if ($stimuliCount > 0 || $questionsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Operación rechazada: El documento está referenciado por {$stimuliCount} estímulo(s) y {$questionsCount} pregunta(s). Elimine o desvincule esos registros antes de suprimir el documento.",
                'references' => [
                    'stimuli'   => $stimuliCount,
                    'questions' => $questionsCount,
                ],
            ], 409);
        }

        $storagePath = $document->storage_path;
        $bucket = config('filesystems.disks.question_bank_documents.bucket', 'icfes-documents');

        try {
            // 3. Eliminar registro en base de datos
            $document->delete();

            // 4. Eliminar archivo físico en Storage
            if (!empty($storagePath) && $this->storageService->exists($bucket, $storagePath)) {
                $this->storageService->delete($bucket, $storagePath, force: true);
            }

            return response()->json([
                'success' => true,
                'message' => 'Documento y archivo físico en Supabase Storage eliminados exitosamente.',
                'deleted_id' => $id,
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el documento: ' . $e->getMessage(),
            ], 500);
        }
    }
}
