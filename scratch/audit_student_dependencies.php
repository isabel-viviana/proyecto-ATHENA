<?php

require 'athena-question-bank/vendor/autoload.php';
$app = require_once 'athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== AUDITORIA DE DEPENDENCIAS DE ESTUDIANTES Y SIMULACROS ===" . PHP_EOL . PHP_EOL;

// 1. Student answers
echo "1. TABLA student_answers (Respuestas de Práctica):" . PHP_EOL;
$studentAnswers = DB::connection('question_bank')
    ->table('student_answers')
    ->join('questions', 'student_answers.question_id', '=', 'questions.id')
    ->join('source_documents', 'questions.source_document_id', '=', 'source_documents.id')
    ->select(
        'student_answers.id as answer_id',
        'student_answers.student_id',
        'questions.id as question_id',
        'questions.order_in_group',
        'questions.subject_id',
        'source_documents.id as doc_id',
        'source_documents.title as doc_title'
    )
    ->get();

echo "Total respuestas registradas: " . $studentAnswers->count() . PHP_EOL;
$byDoc = [];
foreach ($studentAnswers as $sa) {
    $byDoc[$sa->doc_title] = ($byDoc[$sa->doc_title] ?? 0) + 1;
}
foreach ($byDoc as $docTitle => $cnt) {
    echo "  - Documento '{$docTitle}': {$cnt} respuestas asociadas." . PHP_EOL;
}

// 2. Exam attempts & answers
echo PHP_EOL . "2. TABLA exam_attempt_answers (Respuestas de Simulacro):" . PHP_EOL;
$examAnswers = DB::connection('question_bank')
    ->table('exam_attempt_answers')
    ->join('questions', 'exam_attempt_answers.question_id', '=', 'questions.id')
    ->join('source_documents', 'questions.source_document_id', '=', 'source_documents.id')
    ->select(
        'exam_attempt_answers.id as exam_answer_id',
        'exam_attempt_answers.attempt_id',
        'questions.id as question_id',
        'questions.order_in_group',
        'source_documents.id as doc_id',
        'source_documents.title as doc_title'
    )
    ->get();

echo "Total respuestas de simulacro: " . $examAnswers->count() . PHP_EOL;
$byDocExam = [];
foreach ($examAnswers as $ea) {
    $byDocExam[$ea->doc_title] = ($byDocExam[$ea->doc_title] ?? 0) + 1;
}
foreach ($byDocExam as $docTitle => $cnt) {
    echo "  - Documento '{$docTitle}': {$cnt} respuestas de simulacro asociadas." . PHP_EOL;
}

// 3. Document b352295c (Matemáticas 2026) vs f59ac90b (Lectura Crítica)
echo PHP_EOL . "3. VINCULACION ESPECIFICA POR DOCUMENTO:" . PHP_EOL;
$mathDocId = 'b352295c-6b85-480e-a635-37ed53ca134c';
$mathQuestions = DB::connection('question_bank')->table('questions')->where('source_document_id', $mathDocId)->pluck('id');

$mathPracticeAnswers = DB::connection('question_bank')->table('student_answers')->whereIn('question_id', $mathQuestions)->count();
$mathExamAnswers = DB::connection('question_bank')->table('exam_attempt_answers')->whereIn('question_id', $mathQuestions)->count();

echo "Documento Matemáticas 2026 ({$mathDocId}):" . PHP_EOL;
echo "  - Total preguntas: " . $mathQuestions->count() . " (Todas en estado draft)" . PHP_EOL;
echo "  - Respuestas de práctica asociadas: {$mathPracticeAnswers}" . PHP_EOL;
echo "  - Respuestas de simulacro asociadas: {$mathExamAnswers}" . PHP_EOL;

$lcDocId = 'f59ac90b-509a-493d-a75b-ae85c4c0cadf';
$lcQuestions = DB::connection('question_bank')->table('questions')->where('source_document_id', $lcDocId)->pluck('id');

$lcPracticeAnswers = DB::connection('question_bank')->table('student_answers')->whereIn('question_id', $lcQuestions)->count();
$lcExamAnswers = DB::connection('question_bank')->table('exam_attempt_answers')->whereIn('question_id', $lcQuestions)->count();

echo PHP_EOL . "Documento Lectura Crítica ({$lcDocId}):" . PHP_EOL;
echo "  - Total preguntas: " . $lcQuestions->count() . " (Todas en estado published)" . PHP_EOL;
echo "  - Respuestas de práctica asociadas: {$lcPracticeAnswers}" . PHP_EOL;
echo "  - Respuestas de simulacro asociadas: {$lcExamAnswers}" . PHP_EOL;

