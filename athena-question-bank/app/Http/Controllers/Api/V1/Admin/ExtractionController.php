<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\SourceDocument;
use App\Services\GeminiQuestionExtractionService;
use Illuminate\Http\JsonResponse;
use Throwable;

class ExtractionController extends Controller
{
    public function __construct(
        protected GeminiQuestionExtractionService $extractionService
    ) {}

    /**
     * Inicia o ejecuta la extracción de preguntas de un documento PDF mediante Gemini.
     *
     * POST /api/v1/admin/documents/{id}/extract
     */
    public function extract(string $id): JsonResponse
    {
        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        try {
            $summary = $this->extractionService->extractQuestionsFromDocument($document);

            return response()->json([
                'success' => true,
                'message' => 'Extracción con Gemini completada exitosamente. Preguntas guardadas como borradores.',
                'data'    => [
                    'document_id' => $document->id,
                    'title'       => $document->title,
                    'status'      => $document->status,
                    'summary'     => $summary,
                ],
            ]);

        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Fallo en la extracción con Gemini: ' . $e->getMessage(),
                'status'  => $document->fresh()->status,
            ], 500);
        }
    }

    /**
     * Consulta el estado de procesamiento y metadatos de extracción del documento.
     *
     * GET /api/v1/admin/documents/{id}/extraction-status
     */
    public function status(string $id): JsonResponse
    {
        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $questionsCount = Question::where('source_document_id', $id)->count();

        return response()->json([
            'success' => true,
            'data'    => [
                'document_id'         => $document->id,
                'title'               => $document->title,
                'status'              => $document->status,
                'questions_count'     => $questionsCount,
                'extraction_metadata' => $document->extraction_metadata,
            ],
        ]);
    }

    /**
     * Consulta las preguntas y opciones extraídas vinculadas al documento.
     *
     * GET /api/v1/admin/documents/{id}/extracted-questions
     */
    public function questions(string $id): JsonResponse
    {
        $document = SourceDocument::find($id);

        if (! $document) {
            return response()->json([
                'success' => false,
                'message' => 'Documento no encontrado.',
            ], 404);
        }

        $questions = Question::with(['options', 'stimulus.confirmedMediaAssets', 'confirmedMediaAssets'])
            ->where('source_document_id', $id)
            ->orderBy('order_in_group', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'document_id'     => $document->id,
                'title'           => $document->title,
                'total_questions' => $questions->count(),
                'questions'       => $questions,
            ],
        ]);
    }
}

