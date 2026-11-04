<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class TestQuestionBankConnection extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'question-bank:test-connection';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verifica la conexión independiente con Supabase PostgreSQL y consulta las áreas académicas (public.subjects)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando prueba de conexión independiente [question_bank]...');

        // 1. Verificación de la extensión PHP pdo_pgsql
        if (! extension_loaded('pdo_pgsql')) {
            $this->error('Error de entorno PHP: La extensión "pdo_pgsql" no está habilitada en el intérprete PHP activo.');
            $this->line('Para resolverlo, habilite "extension=pdo_pgsql" en su archivo php.ini.');
            return Command::FAILURE;
        }

        // 2. Verificación de configuración en .env
        $host = config('database.connections.question_bank.host');
        $database = config('database.connections.question_bank.database');
        $username = config('database.connections.question_bank.username');

        if (empty($host) || empty($database) || empty($username)) {
            $this->error('Configuración incompleta: Faltan variables de entorno QB_DB_* en el archivo .env.');
            $this->line('Verifique que QB_DB_HOST, QB_DB_DATABASE y QB_DB_USERNAME estén definidos.');
            return Command::FAILURE;
        }

        // 3. Intento de conexión y consulta de solo lectura
        try {
            $this->comment('Conectando a Supabase PostgreSQL...');

            // Conexión explícita usando la configuración 'question_bank'
            $connection = DB::connection('question_bank');

            // Consulta de solo lectura sobre public.subjects
            $subjects = $connection->select('SELECT id, name, slug, description FROM public.subjects ORDER BY id ASC;');

            $total = count($subjects);

            $this->newLine();
            $this->info("Conexión exitosa a Supabase PostgreSQL.");
            $this->info("Total de áreas (subjects) encontradas: {$total}");
            $this->newLine();

            // 4. Mostrar listado de áreas (ID, Nombre y Slug)
            $tableData = array_map(function ($subject) {
                return [
                    'id'   => $subject->id ?? 'N/A',
                    'name' => $subject->name ?? 'N/A',
                    'slug' => $subject->slug ?? 'N/A',
                ];
            }, $subjects);

            $this->table(['ID', 'Nombre del Área', 'Slug'], $tableData);

            $this->newLine();
            $this->info('Prueba de solo lectura completada satisfactoriamente.');

            return Command::SUCCESS;

        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Fallo en la conexión con Supabase PostgreSQL.');
            
            // Sanitización del mensaje de error para evitar filtrar credenciales en consola
            $password = (string) config('database.connections.question_bank.password');
            $rawMessage = $e->getMessage();
            $sanitizedMessage = !empty($password) 
                ? str_replace($password, '********', $rawMessage) 
                : $rawMessage;

            $this->line('Detalle del error: ' . $sanitizedMessage);
            $this->newLine();

            return Command::FAILURE;
        }
    }
}

