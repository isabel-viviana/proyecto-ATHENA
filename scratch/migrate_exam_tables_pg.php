<?php
require __DIR__ . '/../athena-question-bank/vendor/autoload.php';
$app = require_once __DIR__ . '/../athena-question-bank/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== MIGRACIÓN DE TABLAS DE SIMULACROS EN SUPABASE POSTGRESQL ===\n";

$sql = <<<SQL
-- Tabla de Intentos de Simulacro
CREATE TABLE IF NOT EXISTS exam_attempts (
    id UUID PRIMARY KEY,
    student_id VARCHAR(255) NOT NULL,
    subject_id INTEGER DEFAULT 1 REFERENCES subjects(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    mode VARCHAR(50) NOT NULL DEFAULT 'area',
    questions_count INTEGER NOT NULL,
    duration_minutes INTEGER NOT NULL,
    started_at TIMESTAMPTZ NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    finished_at TIMESTAMPTZ,
    status VARCHAR(50) NOT NULL DEFAULT 'in_progress',
    score INTEGER NOT NULL DEFAULT 0,
    correct_answers INTEGER NOT NULL DEFAULT 0,
    incorrect_answers INTEGER NOT NULL DEFAULT 0,
    omitted_answers INTEGER NOT NULL DEFAULT 0,
    accuracy NUMERIC(5, 2) NOT NULL DEFAULT 0.00,
    total_time_seconds INTEGER NOT NULL DEFAULT 0,
    question_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
    flagged_question_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_exam_attempts_student ON exam_attempts(student_id);
CREATE INDEX IF NOT EXISTS idx_exam_attempts_status ON exam_attempts(status);

-- Tabla de Respuestas por Intento de Simulacro
CREATE TABLE IF NOT EXISTS exam_attempt_answers (
    id UUID PRIMARY KEY,
    attempt_id UUID NOT NULL REFERENCES exam_attempts(id) ON DELETE CASCADE,
    question_id UUID NOT NULL REFERENCES questions(id) ON DELETE CASCADE,
    order_position INTEGER NOT NULL,
    selected_option_id UUID REFERENCES question_options(id) ON DELETE SET NULL,
    selected_option_letter VARCHAR(1),
    is_correct BOOLEAN,
    time_spent_seconds INTEGER NOT NULL DEFAULT 0,
    flagged BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_attempt_question UNIQUE (attempt_id, question_id)
);

CREATE INDEX IF NOT EXISTS idx_attempt_answers_attempt ON exam_attempt_answers(attempt_id);
CREATE INDEX IF NOT EXISTS idx_attempt_answers_question ON exam_attempt_answers(question_id);
SQL;

try {
    DB::connection('question_bank')->unprepared($sql);
    echo "Tablas 'exam_attempts' y 'exam_attempt_answers' creadas exitosamente en PostgreSQL.\n";
} catch (\Exception $e) {
    echo "Error en migración: " . $e->getMessage() . "\n";
    exit(1);
}

