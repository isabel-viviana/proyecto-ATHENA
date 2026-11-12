<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Collection;

class IqpeDuplicateDetectionService
{
    /**
     * Palabras funcionales frecuentes en español que no aportan valor semántico distintivo.
     */
    private const STOP_WORDS = [
        'a', 'al', 'ante', 'bajo', 'con', 'contra', 'de', 'del', 'desde', 'durante',
        'el', 'ella', 'ellas', 'ellos', 'en', 'entre', 'era', 'es', 'esa', 'esas',
        'ese', 'eso', 'esos', 'esta', 'estas', 'este', 'esto', 'estos', 'fue', 'ha',
        'han', 'hasta', 'la', 'las', 'le', 'les', 'lo', 'los', 'mas', 'me', 'mi',
        'mientras', 'muy', 'no', 'nos', 'o', 'para', 'pero', 'por', 'porque', 'que',
        'quien', 'se', 'segun', 'ser', 'si', 'sin', 'sobre', 'son', 'su', 'sus',
        'tambien', 'te', 'tiene', 'tienen', 'un', 'una', 'unas', 'uno', 'unos', 'y',
        'ya', 'cual', 'cuales', 'cuanto', 'cuanta', 'cuantos', 'cuantas', 'como', 'donde',
    ];

    /**
     * Analiza un lote de preguntas contra todo el banco activo (published, review, draft),
     * comparando enunciados normalizados, opciones, estímulos, contexto y recursos visuales.
     *
     * @param Collection<int, Question> $targetQuestions
     * @param Collection<int, Question>|null $candidatePool Pool externo opcional (para tests o inyección controlada)
     * @return array<string, array<string, mixed>> Mapa question_id => resultado de duplicados
     */
    public function analyzeBatch(Collection $targetQuestions, ?Collection $candidatePool = null): array
    {
        if ($targetQuestions->isEmpty()) {
            return [];
        }

        if ($candidatePool === null) {
            $candidatePool = Question::with([
                'options' => fn ($q) => $q->select(['id', 'question_id', 'option_letter', 'option_text', 'option_image_path'])->orderBy('option_letter', 'asc'),
                'stimulus.confirmedMediaAssets',
                'stimulus.mediaAssets',
                'confirmedMediaAssets',
                'mediaAssets',
            ])
                ->where('is_current_version', true)
                ->whereIn('status', ['published', 'review', 'draft'])
                ->get();
        } else {
            $candidatePool->each(function (Question $q) {
                $q->loadMissing([
                    'options',
                    'stimulus.confirmedMediaAssets',
                    'stimulus.mediaAssets',
                    'confirmedMediaAssets',
                    'mediaAssets',
                ]);
            });
        }

        // Asegurar que las preguntas objetivo también formen parte del índice precalculado
        $combinedById = [];
        foreach ($candidatePool as $cand) {
            $combinedById[$cand->id] = $cand;
        }
        foreach ($targetQuestions as $tq) {
            $tq->loadMissing([
                'options',
                'stimulus.confirmedMediaAssets',
                'stimulus.mediaAssets',
                'confirmedMediaAssets',
                'mediaAssets',
            ]);
            $combinedById[$tq->id] = $tq;
        }

        $signatures = [];
        foreach ($combinedById as $id => $q) {
            $signatures[$id] = $this->buildQuestionSignature($q);
        }

        $results = [];
        foreach ($targetQuestions as $tq) {
            $results[$tq->id] = $this->compareSignatureAgainstPool(
                $signatures[$tq->id],
                $signatures
            );
        }

        return $results;
    }

    /**
     * Construye la huella normalizada multimodal y textual de una pregunta.
     *
     * @return array<string, mixed>
     */
    public function buildQuestionSignature(Question $question): array
    {
        $normStatement = $this->normalizeText((string) $question->statement);
        $stmtTokens = $this->tokenizeMeaningful($normStatement);

        $optionsData = [];
        foreach ($question->options->sortBy('option_letter') as $opt) {
            $normOpt = $this->normalizeText((string) $opt->option_text);
            $optionsData[] = [
                'letter'     => strtoupper(trim((string) $opt->option_letter)),
                'norm_text'  => $normOpt,
                'tokens'     => $this->tokenizeMeaningful($normOpt),
                'image_path' => trim((string) ($opt->option_image_path ?? '')),
            ];
        }

        $stimulusText = '';
        if ($question->stimulus) {
            $stimulusText = trim(implode(' ', array_filter([
                $question->stimulus->title,
                $question->stimulus->content_text,
                $question->stimulus->visual_transcription,
            ])));
        }
        $normStimulus = $this->normalizeText($stimulusText);

        $contextText = trim(implode(' ', array_filter([
            $question->visual_transcription,
            $question->visual_description,
            $question->stimulus?->visual_description,
        ])));
        $normContext = $this->normalizeText($contextText);

        // Recolectar hashes/rutas visuales propios de la pregunta y sus opciones (independientes del estímulo compartido)
        $questionVisualFingerprints = [];
        $qAssets = $question->confirmedMediaAssets->isNotEmpty()
            ? $question->confirmedMediaAssets
            : $question->mediaAssets;
        foreach ($qAssets as $asset) {
            if (! empty($asset->sha256)) {
                $questionVisualFingerprints[] = 'sha256:' . strtolower((string) $asset->sha256);
            } elseif (! empty($asset->storage_path)) {
                $questionVisualFingerprints[] = 'path:' . trim((string) $asset->storage_path);
            }
        }
        if (! empty($question->question_image_path)) {
            $questionVisualFingerprints[] = 'path:' . trim((string) $question->question_image_path);
        }
        foreach ($optionsData as $od) {
            if ($od['image_path'] !== '') {
                $questionVisualFingerprints[] = 'opt_path:' . $od['image_path'];
            }
        }
        $questionVisualFingerprints = array_values(array_unique($questionVisualFingerprints));

        return [
            'id'                   => $question->id,
            'source_document_id'   => $question->source_document_id,
            'subject_id'           => $question->subject_id ? (int) $question->subject_id : null,
            'stimulus_id'          => $question->stimulus_id,
            'status'               => (string) $question->status,
            'order_in_group'       => (int) $question->order_in_group,
            'norm_statement'       => $normStatement,
            'statement_tokens'     => $stmtTokens,
            'options'              => $optionsData,
            'norm_stimulus'        => $normStimulus,
            'stimulus_tokens'      => $this->tokenizeMeaningful($normStimulus),
            'norm_context'         => $normContext,
            'visual_fingerprints'  => $questionVisualFingerprints,
        ];
    }

    /**
     * Compara una pregunta contra el resto del pool y clasifica hallazgos por severidad y similitud.
     *
     * @param array<string, mixed> $target
     * @param array<string, array<string, mixed>> $pool
     * @return array<string, mixed>
     */
    protected function compareSignatureAgainstPool(array $target, array $pool): array
    {
        $matches = [];

        foreach ($pool as $candId => $candidate) {
            if ($candId === $target['id']) {
                continue;
            }

            $comparison = $this->evaluatePair($target, $candidate);
            if ($comparison['classification'] !== 'independent') {
                $matches[] = $comparison;
            }
        }

        // Ordenar: primero exact_duplicate, luego probable_duplicate, luego related_question; priorizando published y mayor score
        $rank = [
            'exact_duplicate'    => 3,
            'probable_duplicate' => 2,
            'related_question'   => 1,
            'independent'        => 0,
        ];
        $statusRank = ['published' => 3, 'review' => 2, 'draft' => 1];

        usort($matches, function (array $a, array $b) use ($rank, $statusRank) {
            $rDiff = ($rank[$b['classification']] ?? 0) <=> ($rank[$a['classification']] ?? 0);
            if ($rDiff !== 0) {
                return $rDiff;
            }
            $sDiff = ($statusRank[$b['candidate_status']] ?? 0) <=> ($statusRank[$a['candidate_status']] ?? 0);
            if ($sDiff !== 0) {
                return $sDiff;
            }
            return $b['similarity_score'] <=> $a['similarity_score'];
        });

        // Limitar top 5 coincidencias más relevantes para mantener conciso el JSON
        $topMatches = array_slice($matches, 0, 5);
        $top = $topMatches[0] ?? null;

        $overallClassification = $top['classification'] ?? 'independent';
        $isDuplicateRisk = in_array($overallClassification, ['exact_duplicate', 'probable_duplicate'], true);
        $involvesPublished = false;
        foreach ($topMatches as $m) {
            if ($m['involves_published'] && in_array($m['classification'], ['exact_duplicate', 'probable_duplicate'], true)) {
                $involvesPublished = true;
                break;
            }
        }

        return [
            'classification'              => $overallClassification,
            'is_duplicate_risk'           => $isDuplicateRisk,
            'involves_published_question' => $involvesPublished,
            'primary_candidate_id'        => $isDuplicateRisk ? ($top['candidate_question_id'] ?? null) : null,
            'matches'                     => $topMatches,
        ];
    }

    /**
     * Evalúa el par de preguntas (A, B) en 5 dimensiones sin confundir estímulos o temas compartidos con duplicados.
     *
     * @return array<string, mixed>
     */
    protected function evaluatePair(array $a, array $b): array
    {
        $stmtSim = $this->computeTextSimilarity(
            $a['norm_statement'],
            $b['norm_statement'],
            $a['statement_tokens'],
            $b['statement_tokens']
        );

        // Prefiltro rápido: si el enunciado tiene similitud muy baja y no comparten estímulo, son independientes
        $sharedStimulus = ! empty($a['stimulus_id']) && $a['stimulus_id'] === $b['stimulus_id'];
        if ($stmtSim < 0.28 && ! $sharedStimulus) {
            return ['classification' => 'independent'];
        }

        $optSim = $this->computeOptionsSimilarity($a['options'], $b['options']);

        $bothHaveStimulus = $a['norm_stimulus'] !== '' && $b['norm_stimulus'] !== '';
        $neitherHasStimulus = $a['norm_stimulus'] === '' && $b['norm_stimulus'] === '';
        $stimSim = 0.0;
        if ($sharedStimulus) {
            $stimSim = 1.0;
        } elseif ($bothHaveStimulus) {
            $stimSim = $this->computeTextSimilarity(
                $a['norm_stimulus'],
                $b['norm_stimulus'],
                $a['stimulus_tokens'],
                $b['stimulus_tokens']
            );
        }

        // Similitud visual propia de la pregunta/opciones
        $visualMatch = false;
        if (! empty($a['visual_fingerprints']) && ! empty($b['visual_fingerprints'])) {
            $visualMatch = ! empty(array_intersect($a['visual_fingerprints'], $b['visual_fingerprints']));
        }

        $sameSubject = $a['subject_id'] !== null && $a['subject_id'] === $b['subject_id'];
        $visualDescSim = ($a['norm_context'] !== '' && $b['norm_context'] !== '')
            ? $this->computeTextSimilarity($a['norm_context'], $b['norm_context'])
            : ($sameSubject ? 0.5 : 0.0);

        $contextSim = round(
            ($sameSubject ? 0.40 : 0.0) + (0.60 * ($visualMatch ? 1.0 : $visualDescSim)),
            3
        );

        // Reglas estrictas de clasificación en 4 niveles
        $stimulusCompatible = $sharedStimulus || $neitherHasStimulus || ($bothHaveStimulus && $stimSim >= 0.70);

        $classification = 'independent';
        if ($stmtSim >= 0.98 && $optSim >= 0.90 && $stimulusCompatible) {
            $classification = 'exact_duplicate';
        } elseif (
            ($stmtSim >= 0.78 && $optSim >= 0.65)
            || ($stmtSim >= 0.65 && $optSim >= 0.85 && ($stimulusCompatible || $visualMatch))
            || ($stmtSim >= 0.88 && $optSim >= 0.50 && $stimulusCompatible)
        ) {
            $classification = 'probable_duplicate';
        } elseif (
            $sharedStimulus
            || ($bothHaveStimulus && $stimSim >= 0.75)
            || ($sameSubject && $stmtSim >= 0.38 && ($optSim >= 0.30 || $contextSim >= 0.45))
        ) {
            $classification = 'related_question';
        }

        // Puntaje compuesto: si comparten estímulo pero enunciado/opciones difieren, el estímulo no infla el score
        if ($bothHaveStimulus || $sharedStimulus) {
            $effectiveStim = ($sharedStimulus && $stmtSim < 0.60) ? 0.25 : $stimSim;
            $score = (0.45 * $stmtSim) + (0.30 * $optSim) + (0.15 * $effectiveStim) + (0.10 * $contextSim);
        } else {
            $score = (0.55 * $stmtSim) + (0.35 * $optSim) + (0.10 * $contextSim);
        }

        if ($visualMatch && $stmtSim >= 0.50) {
            $score = min(1.0, $score + 0.08);
        }

        $score = round(max(0.0, min(1.0, $score)), 3);

        if ($classification === 'independent') {
            return ['classification' => 'independent'];
        }

        $evidenceParts = [
            'Enunciado=' . round($stmtSim * 100, 1) . '%',
            'Opciones=' . round($optSim * 100, 1) . '%',
        ];
        if ($sharedStimulus) {
            $evidenceParts[] = 'Estímulo compartido idéntico';
        } elseif ($bothHaveStimulus) {
            $evidenceParts[] = 'Estímulo=' . round($stimSim * 100, 1) . '%';
        }
        if ($visualMatch) {
            $evidenceParts[] = 'Recurso visual coincidente';
        }

        return [
            'candidate_question_id'        => $b['id'],
            'candidate_status'             => $b['status'],
            'candidate_source_document_id' => $b['source_document_id'],
            'candidate_order_in_group'     => $b['order_in_group'],
            'classification'               => $classification,
            'involves_published'           => $b['status'] === 'published',
            'similarity_score'             => $score,
            'statement_similarity'         => round($stmtSim, 3),
            'options_similarity'           => round($optSim, 3),
            'stimulus_similarity'          => round($stimSim, 3),
            'context_similarity'           => $contextSim,
            'visual_match'                 => $visualMatch,
            'shared_stimulus'              => $sharedStimulus,
            'evidence'                     => implode(' | ', $evidenceParts),
        ];
    }

    /**
     * Normaliza un texto removiendo prefijos numerados, tildes, puntuación y espacios extra.
     */
    public function normalizeText(string $text): string
    {
        $clean = mb_strtolower(trim($text), 'UTF-8');
        if ($clean === '') {
            return '';
        }

        $clean = strtr($clean, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n',
        ]);

        // Remover prefijos comunes de numeración ("pregunta 12:", "12.", "12)")
        $clean = preg_replace('/^(?:pregunta\s*)?\d{1,3}[\)\.\:\-]\s+/u', '', $clean);
        // Reemplazar puntuación/símbolos por espacio manteniendo letras y números
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $clean);
        $clean = preg_replace('/\s+/u', ' ', trim((string) $clean));

        return (string) $clean;
    }

    /**
     * Tokeniza eliminando palabras vacías de baja relevancia semántica.
     *
     * @return array<int, string>
     */
    protected function tokenizeMeaningful(string $normalizedText): array
    {
        if ($normalizedText === '') {
            return [];
        }

        $parts = explode(' ', $normalizedText);
        $tokens = [];
        foreach ($parts as $p) {
            if ($p === '' || in_array($p, self::STOP_WORDS, true)) {
                continue;
            }
            $tokens[] = $p;
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Calcula similitud combinando coincidencia léxica difusa (tolerando flexiones/sinónimos cortos)
     * y similitud de caracteres.
     *
     * @param array<int, string>|null $tokensA
     * @param array<int, string>|null $tokensB
     */
    protected function computeTextSimilarity(
        string $normA,
        string $normB,
        ?array $tokensA = null,
        ?array $tokensB = null
    ): float {
        if ($normA === '' || $normB === '') {
            return 0.0;
        }
        if ($normA === $normB) {
            return 1.0;
        }

        $tokensA = $tokensA ?? $this->tokenizeMeaningful($normA);
        $tokensB = $tokensB ?? $this->tokenizeMeaningful($normB);

        $tokenSim = 0.0;
        if (! empty($tokensA) && ! empty($tokensB)) {
            $matchedScore = 0.0;
            $usedB = [];
            foreach ($tokensA as $ta) {
                $best = 0.0;
                $bestIdx = null;
                foreach ($tokensB as $idx => $tb) {
                    if (isset($usedB[$idx])) {
                        continue;
                    }
                    if ($ta === $tb) {
                        $best = 1.0;
                        $bestIdx = $idx;
                        break;
                    }
                    if (mb_strlen($ta) >= 4 && mb_strlen($tb) >= 4) {
                        $pct = 0.0;
                        similar_text($ta, $tb, $pct);
                        if ($pct >= 72.0 && ($pct / 100.0) > $best) {
                            $best = 0.80;
                            $bestIdx = $idx;
                        }
                    }
                }
                if ($bestIdx !== null) {
                    $usedB[$bestIdx] = true;
                    $matchedScore += $best;
                }
            }
            $tokenSim = (2.0 * $matchedScore) / (count($tokensA) + count($tokensB));
        }

        $charPct = 0.0;
        similar_text(substr($normA, 0, 500), substr($normB, 0, 500), $charPct);
        $charSim = $charPct / 100.0;

        return round(min(1.0, max($tokenSim, (0.60 * $tokenSim) + (0.40 * $charSim))), 4);
    }

    /**
     * Calcula similitud independiente del orden entre las opciones de dos preguntas.
     *
     * @param array<int, array<string, mixed>> $optsA
     * @param array<int, array<string, mixed>> $optsB
     */
    protected function computeOptionsSimilarity(array $optsA, array $optsB): float
    {
        if (empty($optsA) || empty($optsB)) {
            return 0.0;
        }

        $usedB = [];
        $totalSim = 0.0;
        $count = max(count($optsA), count($optsB));

        foreach ($optsA as $oa) {
            $bestSim = 0.0;
            $bestIdx = null;

            foreach ($optsB as $idx => $ob) {
                if (isset($usedB[$idx])) {
                    continue;
                }

                $sim = 0.0;
                if ($oa['image_path'] !== '' && $oa['image_path'] === $ob['image_path']) {
                    $sim = 1.0;
                } elseif ($oa['norm_text'] !== '' && $ob['norm_text'] !== '') {
                    $sim = $this->computeTextSimilarity(
                        $oa['norm_text'],
                        $ob['norm_text'],
                        $oa['tokens'],
                        $ob['tokens']
                    );
                }

                if ($sim > $bestSim) {
                    $bestSim = $sim;
                    $bestIdx = $idx;
                }
            }

            if ($bestIdx !== null) {
                $usedB[$bestIdx] = true;
                $totalSim += $bestSim;
            }
        }

        return round($totalSim / max(1, $count), 4);
    }
}
