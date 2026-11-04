# CONTEXTO HISTÓRICO, AUDITORÍA DE RESPALDOS Y PLAN DE MIGRACIÓN A SUPABASE
**Proyecto:** ATHENA — Plataforma Inteligente de Preparación para las Pruebas ICFES Saber 11  
**Rol:** Arquitecto de Software Senior  
**Fecha de Auditoría:** 4 de noviembre de 2026  
**Ubicación del Proyecto:** `C:\laragon\www\proyecto ATHENA`  
**Estado:** Documento de solo lectura. Ninguna operación destructiva, modificación de datos, limpieza o migración fue ejecutada.

---

## 1. Resumen del Trabajo Histórico

El proyecto **ATHENA** fue concebido y desarrollado como una plataforma web cliente-servidor orientada al entrenamiento para las pruebas de Estado ICFES Saber 11 en Colombia.

Durante la fase de desarrollo previa en el equipo anterior:
1. Se construyó el modelo relacional completo en **MySQL 8** administrado mediante migraciones y modelos de **Laravel 12**.
2. Se poblaron las tablas con datos sintéticos mediante factories y seeders (`DatabaseSeeder.php` y posteriormente `MathematicsPracticeQuestionsSeeder.php`) para poner a prueba la lógica de examen, autenticación, gamificación y telemetría de rendimiento académico.
3. Se integró una base de datos NoSQL (**MongoDB Atlas**) para la persistencia de las conversaciones conversacionales y metadatos del tutor inteligente basado en **Google Gemini**.
4. Con el sistema funcional, se inició la preparación para profesionalizar el almacenamiento relacional migrándolo a **Supabase (PostgreSQL)** y poblar la plataforma con el banco real de preguntas ICFES.
5. Antes de dicha migración, se planificó una **limpieza controlada de 1.025 registros de prueba** generados por los seeders en las tablas académicas y de simulacros, manteniendo intactos los usuarios, materias, temas y módulos protegidos.
6. Se estableció como regla inquebrantable de seguridad:
   - Crear un respaldo completo de la base MySQL.
   - Restaurar el respaldo en una base de datos secundaria para comprobar su integridad registro a registro.
   - Suspender cualquier operación de eliminación (`DELETE`, `TRUNCATE`, `DROP`) hasta documentar el contexto y recibir autorización expresa.
7. La tarea de limpieza quedó **pausada** y el proyecto se trasladó al entorno actual para consolidar la arquitectura y la documentación antes de dar cualquier paso destructivo.

---

## 2. Motivo por el que se Creó el Respaldo

El respaldo MySQL fue creado con el objetivo estricto de **mitigación de riesgo y preservación inmutable del estado del sistema** antes de efectuar la limpieza de datos de prueba:
- **Prevención de pérdida accidental:** En el modelo relacional existen dependencias en cascada (`ON DELETE CASCADE` y restricciones foráneas cruzadas entre `exam_attempts`, `attempt_answers`, `questions`, `question_options`, `stimuli` y `user_topic_performance`). Cualquier instrucción SQL masiva errónea podría destruir usuarios, configuración o historial crítico.
- **Punto de restauración comprobable:** Garantizar que si la depuración o el seeder posterior de preguntas fallaba, se pudiera regresar al estado operacional exacto en segundos.
- **Auditoría forense:** Comparar los esquemas y datos antes y después del traspaso de plataforma entre computadores y antes de iniciar el traspaso a Supabase.

---

## 3. Información y Estado Verificable del Respaldo

Se realizó una inspección física y de contenido sobre el archivo de respaldo reportado en el espacio de trabajo:

| Parámetro | Valor Histórico Reportado | Valor Real Verificado en Disco | Estado | Evidencia |
| :--- | :--- | :--- | :--- | :--- |
| **Nombre de archivo** | `backup_athena_prelimpieza.sql` | `backup_athena_prelimpieza.sql` | **VERIFICADO** | `C:\laragon\www\proyecto ATHENA\backup_athena_prelimpieza.sql` |
| **Ruta física** | `C:\laragon\www\proyecto ATHENA\backup_athena_prelimpieza.sql` | `C:\laragon\www\proyecto ATHENA\backup_athena_prelimpieza.sql` | **VERIFICADO** | FileSystem Windows |
| **Fecha de generación** | 28 de octubre de 2026, 11:52:53 | 28/10/2026 11:52:53 a. m. | **VERIFICADO** | Atributo `LastWriteTime` de Windows |
| **Tamaño en bytes** | 463.172 bytes | 463.172 bytes | **VERIFICADO** | Atributo `Length` exacto de Windows |
| **Motor emisor** | MySQL 8.4.3 | MySQL dump 10.13 Distrib 8.4.3 for Win64 | **VERIFICADO** | Línea 1 y 5 del encabezado SQL |
| **Base origen** | `Athena` | `Database: Athena` | **VERIFICADO** | Línea 3 del archivo SQL |
| **Total tablas respaldadas** | 31 tablas | 31 tablas con estructura e inserciones | **VERIFICADO** | Detección de sentencias `DROP/CREATE TABLE` |

El archivo de respaldo **se encuentra intacto, sin alteraciones, presente en la raíz del proyecto y plenamente legible**.

---

## 4. Estado de la Base Original y de la Base de Verificación

Se inspeccionó de forma directa y en **modo solo lectura** el motor de base de datos MySQL local activo en Laragon (`mysql-8.4.3-winx64` en el puerto 3306):

1. **Instancia MySQL:** En ejecución en el sistema (Procesos PID 16216 / 17736 `mysqld.exe`).
2. **Bases de datos detectadas:**
   - `athena`: Base de datos de desarrollo activa.
   - `athena_backup_verify`: Base de datos creada previamente para restaurar y cotejar el respaldo.
   - `information_schema`, `mysql`, `performance_schema`, `sys`, `adminsena`.

### Conteo exhaustivo tabla por tabla (Comparativo de Integridad)

Se ejecutó un conteo exacto mediante consultas `SELECT COUNT(*) FROM <tabla>;` en ambas bases y se comparó con las tuplas del archivo `backup_athena_prelimpieza.sql`:

| # | Tabla | Conteo en `backup_athena_prelimpieza.sql` | Conteo en BD `athena` (MySQL) | Conteo en BD `athena_backup_verify` | Coincidencia Exacta |
| :---: | :--- | :---: | :---: | :---: | :---: |
| 1 | `achievements` | 4 | 4 | 4 | **VERIFICADO** (100% Idéntico) |
| 2 | `ai_chat_messages` | 18 | 18 | 18 | **VERIFICADO** (100% Idéntico) |
| 3 | `ai_chat_sessions` | 9 | 9 | 9 | **VERIFICADO** (100% Idéntico) |
| 4 | **`attempt_answers`** | **340** | **340** | **340** | **VERIFICADO** (100% Idéntico) |
| 5 | `cache` | 0 | 0 | 0 | **VERIFICADO** (100% Idéntico) |
| 6 | `cache_locks` | 0 | 0 | 0 | **VERIFICADO** (100% Idéntico) |
| 7 | **`exam_attempts`** | **43** | **43** | **43** | **VERIFICADO** (100% Idéntico) |
| 8 | **`exam_config_subjects`** | **113** | **113** | **113** | **VERIFICADO** (100% Idéntico) |
| 9 | **`exam_configs`** | **33** | **33** | **33** | **VERIFICADO** (100% Idéntico) |
| 10 | `failed_jobs` | 0 | 0 | 0 | **VERIFICADO** (100% Idéntico) |
| 11 | `inventories` | 4 | 4 | 4 | **VERIFICADO** (100% Idéntico) |
| 12 | `job_batches` | 0 | 0 | 0 | **VERIFICADO** (100% Idéntico) |
| 13 | `jobs` | 0 | 0 | 0 | **VERIFICADO** (100% Idéntico) |
| 14 | `migrations` | 31 | 31 | 31 | **VERIFICADO** (100% Idéntico) |
| 15 | `notifications` | 10 | 10 | 10 | **VERIFICADO** (100% Idéntico) |
| 16 | `password_resets` | 0 | 0 | 0 | **VERIFICADO** (100% Idéntico) |
| 17 | `payment_transactions` | 5 | 5 | 5 | **VERIFICADO** (100% Idéntico) |
| 18 | `personal_access_tokens` | 0 | 0 | 0 | **VERIFICADO** (100% Idéntico) |
| 19 | `purchases` | 4 | 4 | 4 | **VERIFICADO** (100% Idéntico) |
| 20 | **`question_options`** | **340** | **340** | **340** | **VERIFICADO** (100% Idéntico) |
| 21 | **`questions`** | **85** | **85** | **85** | **VERIFICADO** (100% Idéntico) |
| 22 | `sessions` | 4 | 4 | 4 | **VERIFICADO** (100% Idéntico) |
| 23 | **`stimuli`** | **1** | **1** | **1** | **VERIFICADO** (100% Idéntico) |
| 24 | `store_items` | 4 | 4 | 4 | **VERIFICADO** (100% Idéntico) |
| 25 | **`subjects`** | **5** | **5** | **5** | **VERIFICADO** (100% Idéntico) |
| 26 | `subscription_plans` | 3 | 3 | 3 | **VERIFICADO** (100% Idéntico) |
| 27 | `subscriptions` | 8 | 8 | 8 | **VERIFICADO** (100% Idéntico) |
| 28 | **`topics`** | **15** | **15** | **15** | **VERIFICADO** (100% Idéntico) |
| 29 | `user_achievements` | 14 | 14 | 14 | **VERIFICADO** (100% Idéntico) |
| 30 | **`user_topic_performance`** | **70** | **70** | **70** | **VERIFICADO** (100% Idéntico) |
| 31 | **`users`** | **10** | **10** | **10** | **VERIFICADO** (100% Idéntico) |

**Conclusión del cotejo:**
La base de datos original `athena`, la base de verificación `athena_backup_verify` y el archivo `backup_athena_prelimpieza.sql` presentan una **paridad absoluta del 100%** en todas las 31 tablas.

---

## 5. Tablas que se Planeaba Limpiar y Cantidades

El objetivo de la limpieza planificada era purgar los registros de prueba generados por seeders y simulaciones aleatorias, dejando la base lista para la carga del banco oficial de preguntas del ICFES:

| Tabla | Registros Planificados para Limpieza | Registros Actuales en `athena` | Origen de los Datos |
| :--- | :---: | :---: | :--- |
| `attempt_answers` | 340 | 340 | Respuestas aleatorias simuladas en exámenes |
| `question_options` | 340 | 340 | 4 opciones sintéticas (A, B, C, D) por cada pregunta de prueba |
| `questions` | 85 | 85 | 75 generadas por `DatabaseSeeder` + 10 agregadas por `MathematicsPracticeQuestionsSeeder` |
| `stimuli` | 1 | 1 | 1 estímulo sintético de estadística generado en `MathematicsPracticeQuestionsSeeder` |
| `exam_config_subjects` | 113 | 113 | Relaciones N:M de asignación de materias a configuraciones de prueba |
| `exam_attempts` | 43 | 43 | Intentos de prueba ejecutados durante las pruebas |
| `exam_configs` | 33 | 33 | Configuraciones de simulacros creadas por faker |
| `user_topic_performance` | 70 | 70 | Métricas de rendimiento derivadas de los intentos de prueba |
| **TOTAL** | **1.025** | **1.025** | **Registros de prueba identificados** |

---

## 6. Tablas y Datos que Debían Conservarse

De acuerdo con las decisiones arquitectónicas previas, los siguientes elementos **NO deben eliminarse bajo ninguna circunstancia**:

### A. Estructura Académica Base (Inmutable)
1. **`users` (10 registros):**
   - ID 1: `Juan Sebastian Acevedo Medina` (`juan.acevedo@athena.edu.co`, rol `student`, XP 0, Monedas 0).
   - IDs 2 al 10: Usuarios secundarios de prueba con rol `student`.
2. **`subjects` (5 registros):**
   - ID 1: *Matemáticas* (`slug: matematicas`)
   - ID 2: *Ciencias Naturales* (`slug: ciencias-naturales`)
   - ID 3: *Lectura Crítica* (`slug: lectura-critica`)
   - ID 4: *Sociales y Ciudadanas* (`slug: sociales-ciudadanas`)
   - ID 5: *Inglés* (`slug: ingles`)
3. **`topics` (15 registros, IDs 1 al 15, 3 temas oficiales por cada materia):**
   - *Matemáticas (IDs 1-3):* Estadística y Probabilidad, Trigonometría Básica, Ecuaciones Lineales.
   - *Ciencias Naturales (IDs 4-6):* Genética y Célula, Cinemática (Física), Estequiometría (Química).
   - *Lectura Crítica (IDs 7-9):* Identificación de Tesis, Figuras Literarias, Análisis de Infografías.
   - *Sociales y Ciudadanas (IDs 10-12):* Mecanismos de Participación, La Patria Boba, Conflictos Globales (Siglo XX).
   - *Inglés (IDs 13-15):* Tiempos Verbales (Past/Present), Phrasal Verbs, Reading Comprehension Parts 1-7.

### B. Módulos y Tablas Protegidas
- `achievements` (4 registros de logros del sistema: "Primera Sangre", "Racha Imparable", "Einstein Moderno", "Pionero ATHENA").
- `user_achievements` (14 asignaciones).
- `store_items` (4 items de catálogo preparados para versión futura).
- `purchases` (4 compras históricas).
- `inventories` (4 registros).
- `subscription_plans` (3 planes de suscripción).
- `subscriptions` (8 registros).
- `payment_transactions` (5 transacciones).
- `notifications` (10 notificaciones).
- `ai_chat_sessions` (9 sesiones de chat históricas en MySQL).
- `ai_chat_messages` (18 mensajes históricos en MySQL).
- `sessions` (4 sesiones web activas).
- `migrations` (31 migraciones registradas).

---

## 7. Confirmación de si la Limpieza se Ejecutó o Sigue Pendiente

**CONFIRMACIÓN OFICIAL: LA LIMPIEZA SIGUE 100% PENDIENTE. NO SE HA EJECUTADO.**

### Evidencia:
1. En la base de datos MySQL activa `athena`, la tabla `questions` contiene exactamente **85 registros** (ni uno menos).
2. La tabla `question_options` contiene **340 registros**.
3. La tabla `attempt_answers` contiene **340 registros**.
4. La tabla `exam_attempts` contiene **43 registros**.
5. Los 1.025 registros de prueba identificados siguen existiendo tanto en `athena` como en `athena_backup_verify`.
6. En el repositorio no existen scripts SQL destructivos ejecutados (`cleanup.sql` no existe ni fue corrido).
7. Se respetó la directriz de suspender cualquier acción destructiva hasta completar este diagnóstico.

---

## 8. Arquitectura Actual de ATHENA

### 8.1 Stack Tecnológico y Entorno Real Verificado

| Capa / Componente | Elemento Reportado | Valor Real Verificado en Este Computador | Evidencia |
| :--- | :--- | :--- | :--- |
| **Sistema Operativo** | Windows | Windows 10/11 x64 | PowerShell Environment |
| **Servidor Local** | Laragon 2026 | Laragon en `C:\laragon` | Directorios del sistema |
| **PHP Activo (CLI)** | PHP 8.4 objetivo / PHP 8.3 instalado | **PHP 8.5.8 (cli)** en `C:\laragon\bin\php\php-8.5.8-Win32-vs17-x64` *(PHP 8.3.30 disponible en directorio adyacente)* | `php -v` |
| **Composer** | Composer 2.11.1 | **Composer 2.10.2** | `composer -V` |
| **Node.js** | Node.js 26.13.0 | **Node.js v24.18.0** | `node -v` |
| **npm** | npm 11.20.0 | **npm 11.16.0** | `npm -v` |
| **MySQL** | MySQL 8.4.3 | **MySQL 8.4.3 (Win64)** en puerto 3306 | `mysqld.exe` |
| **MongoDB Ext.** | Extensión MongoDB 2.4.1 | **Módulo `mongodb` cargado y activo en PHP 8.5.8** | `php -m` |
| **Backend Framework** | Laravel 12 | **Laravel Framework 12.61.0** | `php artisan --version` |
| **Frontend Framework** | React 19 + Vite 8 | **React 19.2.5, Vite 8.0.10, Axios 1.16.1, Zustand 5.0.15, Tailwind CSS 4.3.3** | `ATHENAreact/package.json` |

### 8.2 Hallazgo Crítico de Dependencias (PHP 8.4+)
- Al inspeccionar `Repository-ATHENA-Laravel/composer.lock` (líneas 9027-9041), se comprueba que el paquete `symfony/yaml` está bloqueado en la versión **`v8.0.12`**, cuya definición de requisitos es taxativa:
  ```json
  "require": {
      "php": ">=8.4",
      "symfony/polyfill-ctype": "^1.8"
  }
  ```
- Por esta razón, ejecutar Composer o Artisan bajo PHP 8.3 genera un fallo de plataforma.
- En el computador actual, **PHP 8.5.8 está activo por defecto**, lo que satisface holgadamente la restricción (`8.5.8 >= 8.4`) sin necesidad de alterar `composer.lock` ni forzar banderas inseguras.

### 8.3 Bases de Datos y División de Responsabilidades
1. **MySQL 8 (Relacional - Candidata a migración a Supabase PostgreSQL):**
   - Administra usuarios (`users`), estructura académica (`subjects`, `topics`), banco evaluativo (`stimuli`, `questions`, `question_options`), motor de simulacros (`exam_configs`, `exam_attempts`, `attempt_answers`), métricas agregadas (`user_topic_performance`), gamificación (`achievements`) y módulos comerciales futuros.
2. **MongoDB Atlas (NoSQL - Mantenimiento permanente):**
   - Configurado en `config/database.php` mediante el driver `mongodb/laravel-mongodb`.
   - Administra exclusivamente los modelos conversacionales del tutor IA ubicados en `app/Models/Mongo/`:
     * `AiConversation.php` (colección `ai_conversations`): Sesiones de conversación con historial dinámico.
     * `AiInteraction.php` (colección `ai_interactions`): Registro de mensajes individuales, latencia, tokens y respuesta.
     * `AiLearningContext.php` (colección `ai_learning_contexts`): Instantáneas del perfil cognitivo del alumno.
3. **Google Gemini Flash API:**
   - Integrado mediante `google-gemini-php/laravel` y orquestado a través de `AiService.php` y `GeminiService.php` para generar respuestas pedagógicas socráticas adaptadas al rendimiento del estudiante.

---

## 9. Estructura de Datos de Preguntas y Simulacros

### 9.1 Estructura del Banco de Preguntas y Opciones
- **`stimuli` (Estímulos / Textos de Lectura Crítica e Infografías):**
  - Creado mediante migración `2026_10_14_000001_create_stimuli_table.php`.
  - Campos: `id`, `subject_id` (FK a `subjects`), `title`, `instructions`, `content_text` (longText), `media_type` (`enum: none, image, chart, table, mixed`), `media_url` (500), `media_caption`, `source_pdf`, `source_page`, `metadata` (JSON), `timestamps`, `softDeletes`.
  - Modelo: `app/Models/Stimulus.php`. Relación `hasMany(Question::class)->orderBy('order_in_group', 'asc')`.
- **`questions` (Banco de Reactivos):**
  - Creado en `2026_05_19_000003_create_questions_table.php` y ampliado en `2026_10_14_000002_add_stimulus_and_metadata_to_questions_table.php`.
  - Campos: `id`, `subject_id` (FK), `topic_id` (FK), `stimulus_id` (FK nullable), `order_in_group` (default 1), `statement` (text), `question_image_url` (500), `difficulty` (`enum: easy, medium, hard`), `explanation` (text), `source`, `source_pdf`, `source_page`, `original_question_number`, `status` (`enum: draft, review, published, archived`), `timestamps`, `softDeletes`.
  - Modelo: `app/Models/Question.php`. Scope `scopePublished()`.
- **`question_options` (Opciones de Respuesta A, B, C, D):**
  - Migración `2026_05_19_000004_create_question_options_table.php` y `2026_10_14_000005_add_image_and_soft_deletes_to_question_options_table.php`.
  - Campos: `id`, `question_id` (FK), `option_letter` (`enum: A, B, C, D`), `option_text` (text), `option_image_url` (500), `is_correct` (boolean), `timestamps`, `softDeletes`. Restricción única: `['question_id', 'option_letter']`.
  - Regla: Cada pregunta dispone exactamente de 4 opciones y solo una tiene `is_correct = true`.

### 9.2 Simulacros, Intentos y Respuestas
- **`exam_configs` (Configuración del Simulacro):**
  - Campos: `id`, `user_id`, `topic_id` (nullable), `exam_type` (`enum: quick, full, practice`), `difficulty` (`enum: easy, medium, hard`), `questions_count` (unsigned smallint, default 25).
  - Relación N:M con materias a través de `exam_config_subjects`.
- **`exam_attempts` (Intento de Presentación):**
  - Campos: `id`, `user_id`, `exam_config_id` (nullable), `mode` (`enum: quick, full, adaptive, practice`), `started_at`, `finished_at`, `duration_seconds`, `score` (0 a 100), `correct_answers`, `incorrect_answers`, `omitted_answers`, `xp_earned`, `coins_earned`, `status` (`enum: in_progress, completed, cancelled`).
- **`attempt_answers` (Trazabilidad y Congelamiento de Respuestas):**
  - Campos: `id`, `exam_attempt_id` (FK), `question_id` (FK), `order_position` (smallint), `selected_option_id` (FK nullable), `snapshot_correct_option_id` (FK nullable), `snapshot_data` (JSON con el enunciado, opciones, estímulo y tema congelados en el momento de la creación), `is_correct` (boolean), `time_spent_seconds`, `explanation_viewed` (boolean).
  - Restricción única: `['exam_attempt_id', 'question_id']`.

### 9.3 Diferenciación Documentada: Prácticas vs. Simulacros Rápidos vs. Simulacros Completos

Se verificó en el código fuente (`ExamService.php`, `mock_quick.jsx`, `practice_config.jsx`, `DiagramaArquitecturaCompleto.md`) la existencia de tres modalidades perfectamente separadas en la lógica de negocio:

1. **Mini Simulacro Rápido Diagnóstico (`mode: quick`):**
   - **Propósito:** Diagnóstico exprés multi-área de entrada.
   - **Regla de negocio:** Evalúa las **5 materias oficiales simultáneamente** (`subject_ids: [1, 2, 3, 4, 5]`).
   - **Cantidad de preguntas:** Exactamente **5 preguntas** (1 por cada área del conocimiento), con un tiempo estimado de 15 minutos (`mock_quick.jsx` línea 84 y `ExamService.php` línea 464).
   - **Persistencia:** No pre-genera snapshots; selecciona reactivos en orden aleatorio al vuelo mediante `inRandomOrder()->limit(5)`.
2. **Práctica Focalizada por Materia/Tema (`mode: practice` - Sprint E4.6):**
   - **Propósito:** Entrenamiento intensivo por área o subtema.
   - **Regla de negocio:** Exige seleccionar **1 materia específica** (`subject_ids: [id]`) y opcionalmente un `topic_id`.
   - **Cantidad de preguntas:** Exactamente **25 preguntas** (`$targetCount = 25`, `ExamService.php` línea 87).
   - **Algoritmo de selección atómica:** Implementado en `ExamService::selectPracticeQuestions` y `findExactUnitsSubset`. Identifica los grupos de preguntas que comparten un `stimulus_id` (por ejemplo, textos de Lectura Crítica con 2 a 4 preguntas asociadas) y **nunca los fragmenta**. Si una materia no alcanza 25 preguntas completas sin romper un texto, lanza `InsufficientQuestionsException`.
   - **Snapshot Inmutable:** Al crear el intento, persiste inmediatamente las 25 tuplas en `attempt_answers` con todo el contenido en `snapshot_data`, garantizando que futuras ediciones en el banco de preguntas jamás alteren las respuestas históricas del estudiante.
3. **Simulacro Completo Normal (`mode: full`):**
   - **Propósito:** Evaluación exhaustiva tipo examen ICFES real.
   - **Cantidad:** 20 preguntas (modo base) o configurable por el usuario.

### 9.4 Cálculo de Rendimiento y Progreso del Estudiante
- Al invocar `POST /api/exams/{id}/finish` (`ExamService::finishExam`):
  1. Es **idempotente**: si el intento ya figura como `completed`, devuelve los resultados persistidos sin recalcular.
  2. Calcula:
     $$\text{score} = \text{round}\left(\frac{\text{correct\_answers}}{\text{total\_questions}} \times 100\right)$$
  3. Actualiza o crea el registro en `user_topic_performance` para cada `topic_id` evaluado:
     - Incrementa `correct_count`, `incorrect_count` u `omitted_count`.
     - Recalcula el nivel de maestría del tema:
       $$\text{mastery\_score} = \text{round}\left(\frac{\text{correct\_count}}{\text{correct\_count} + \text{incorrect\_count}}, 2\right)$$
- `ProgressService.php` recopila estos datos en `getGeneralStats`, `getSubjectPerformance` y `getHistory` para alimentar los gráficos de React (`statistics.jsx`, `dashboard.jsx`).

### 9.5 Controladores y Servicios Asociados
- **Controladores:**
  - `app/Http/Controllers/Exam/ExamController.php`: `configure`, `getQuestions`, `submitAnswer`, `finish`, `results`.
  - `app/Http/Controllers/Progress/ProgressController.php`: `history`, `generalStats`, `subjectPerformance`, `weaknesses`.
  - `app/Http/Controllers/Admin/QuestionController.php`, `SubjectController.php`, `TopicController.php`.
  - `app/Http/Controllers/TestDbController.php`: Endpoints de auditoría e integridad interna.
- **Servicios:**
  - `app/Services/ExamService.php`: Orquestador principal de 1.010 líneas con los algoritmos combinatorios y transaccionales.
  - `app/Services/ProgressService.php`: Lógica de consolidación estadística y percentiles.

---

## 10. Estado Actual de Supabase

| Aspecto Evaluado | Estado Actual | Evidencia |
| :--- | :--- | :--- |
| **Configuración en `.env` (Laravel)** | **NO CONFIGURADO** | No existen variables `SUPABASE_*` ni cadenas de conexión a Supabase en `.env` de Laravel. |
| **Configuración en Frontend (React)** | **NO CONFIGURADO** | No existen archivos `.env` con variables `VITE_SUPABASE_*` en `ATHENAreact`. |
| **Librería cliente en React** | **NO INSTALADA** | `package.json` no incluye `@supabase/supabase-js`. |
| **CLI de Supabase en el sistema** | **NO INSTALADO / NO EN PATH** | El comando `supabase` no se encuentra registrado en el sistema. |
| **Driver PostgreSQL en PHP 8.5.8** | **DESHABILITADO** | En `C:\laragon\bin\php\php-8.5.8-Win32-vs17-x64\php.ini`, las líneas `extension=pdo_pgsql` y `extension=pgsql` están comentadas con punto y coma (`;`). |
| **Proyecto Supabase en la nube** | **NO VERIFICADO** | No se dispone de URLs de proyecto, IDs (`project-ref`) ni claves anon/service_role documentadas en los repositorios. |
| **Tablas en Supabase** | **NO VERIFICADO / NO CREADAS** | Al no existir enlace ni credenciales activas, no hay evidencia de tablas creadas en Supabase para ATHENA. |

---

## 11. Diferencias entre MySQL y PostgreSQL que Afectan la Migración

Migrar las tablas de preguntas, opciones, simulacros y progreso desde MySQL 8 a PostgreSQL (Supabase) implica considerar las siguientes discrepancias estructurales:

| Característica | MySQL 8 (Actual) | PostgreSQL / Supabase (Destino) | Impacto y Adaptación Requerida |
| :--- | :--- | :--- | :--- |
| **Identificadores numéricos sin signo** | `bigint unsigned`, `int unsigned`, `tinyint unsigned` | PostgreSQL **no soporta tipos `unsigned`**. | En PostgreSQL deben mapearse a `bigint`, `integer` o `smallint`, añadiendo restricciones `CHECK (xp >= 0)`. |
| **Auto-incremento** | `AUTO_INCREMENT` | `BIGINT GENERATED ALWAYS AS IDENTITY` o `BIGSERIAL`. | Las migraciones estándar de Laravel manejan `$table->id()` mapeándolo a `bigserial` / `identity` automáticamente en PostgreSQL. |
| **Valores ENUM** | Definidos en la columna: `enum('easy','medium','hard')`. | PostgreSQL maneja enums como tipos de datos independientes (`CREATE TYPE ...`) o restricciones `CHECK`. | Laravel compila `$table->enum(...)` como una columna `VARCHAR` con una restricción `CHECK (col IN (...))`. Es 100% compatible pero difiere del DDL nativo. |
| **Tipo JSON** | Tipo `json` básico. | `jsonb` (Binary JSON). | En PostgreSQL se debe utilizar `jsonb` para permitir indexación eficiente GIN en `attempt_answers.snapshot_data` y `stimuli.metadata`. |
| **Valores Booleanos** | `tinyint(1)` (valores `1` y `0`). | Tipo `boolean` nativo (`TRUE` y `FALSE`). | Al migrar datos existentes, los valores `1`/`0` de `is_correct` y `explanation_viewed` deben convertirse a booleanos nativos. |
| **Sensibilidad a mayúsculas y comillas** | Emplea backticks (`` `col` ``); insensible en Windows. | Emplea comillas dobles (`"col"`). Por defecto convierte todo a minúsculas (`lowercase`). | No se pueden ejecutar volcados directos generados con `mysqldump` en PostgreSQL. Se debe migrar vía script DDL adaptado o mediante seeders/migraciones de Laravel. |
| **Ordenamiento aleatorio** | `ORDER BY RAND()` | `ORDER BY RANDOM()` | Laravel Eloquent abstrae `inRandomOrder()` a `RANDOM()` automáticamente, pero cualquier consulta SQL directa debe adaptarse. |
| **Timestamps y zonas horarias** | `timestamp` local / UTC de sesión. | `timestamptz` (`timestamp with time zone`). | Recomendado para evitar desfases horarios en el registro de inicio y fin de simulacros. |
| **Arquitectura de Autenticación y IDs de Usuario** | `users.id` es `bigint unsigned` (1 al 10). | Supabase Auth utiliza `UUID` en su esquema `auth.users`. | **Riesgo arquitectónico mayor:** Si se pretende usar Supabase Auth nativo, todos los `user_id` de las tablas relacionales deberían migrar a `UUID`. Si se mantiene Laravel Sanctum como API y Supabase solo como motor de base de datos PostgreSQL, se preservan los IDs enteros sin problema. |

---

## 12. Trabajo ya Realizado

1. ✅ Respaldo integral `backup_athena_prelimpieza.sql` generado en el hito preliminar (463.172 bytes, 28/10/2026).
2. ✅ Restauración y verificación en la base de datos réplica `athena_backup_verify` con coincidencia matemática del 100% de los registros.
3. ✅ Identificación analítica de los 1.025 registros de prueba que componen las tablas académicas y de simulación.
4. ✅ Desarrollo y blindaje del algoritmo de selección de preguntas de práctica por materias (Sprint E4.6) en `ExamService.php`, con respeto a la indivisibilidad de estímulos y snapshots inmutables.
5. ✅ Diagnóstico completo del frontend en `diagnostico-estilos.md` sobre Tailwind CSS y Vanilla CSS.
6. ✅ Confirmación de compatibilidad del entorno local: PHP 8.5.8 activo para resolver las restricciones de plataforma de `composer.lock` impuestas por `symfony/yaml: v8.0.12`.
7. ✅ Preservación total de los datos: ninguna tabla ha sido alterada ni vaciada en este computador.

---

## 13. Trabajo Pendiente

1. ⏳ **Decisión arquitectónica de Supabase:** Definir si Supabase actuará como:
   - *Escenario A (Recomendado para continuidad):* Base de datos PostgreSQL externa conectada a Laravel mediante PDO (`DB_CONNECTION=pgsql`), preservando Sanctum, Eloquent y los controladores actuales.
   - *Escenario B (BaaS Puro):* Migración completa hacia Supabase Auth + Supabase Client en React con políticas RLS, lo que requeriría reestructurar las claves foráneas de usuario a `UUID` y reescribir la lógica de calificación en Edge Functions o RPCs de PostgreSQL.
2. ⏳ **Habilitación de drivers:** Descomentar `extension=pdo_pgsql` y `extension=pgsql` en el archivo `php.ini` de PHP 8.5.8 en Laragon.
3. ⏳ **Configuración del proyecto Supabase:** Crear el proyecto en Supabase, obtener las credenciales (`host`, `port`, `database`, `user`, `password`, `sslmode`) e integrarlas en el archivo `.env` de Laravel.
4. ⏳ **Ejecución autorizada de la limpieza:** Aplicar la eliminación controlada de los 1.025 registros de prueba en MySQL (o prescindir de ellos al migrar el esquema vacío y limpio a Supabase).
5. ⏳ **Migración de esquema a Supabase PostgreSQL:** Ejecutar las migraciones de Laravel apuntando a la conexión `pgsql` de Supabase para generar las tablas con tipos y restricciones nativas de PostgreSQL.
6. ⏳ **Traspaso de datos conservados:** Migrar de forma selectiva los 10 usuarios, las 5 materias, los 15 temas y los 4 logros a la base de datos Supabase.
7. ⏳ **Poblado del Banco Real:** Cargar las preguntas, opciones y lecturas oficiales del ICFES utilizando el formato estructurado con soporte de estímulos múltiples.

---

## 14. Riesgos Identificados

| Riesgo | Severidad | Mitigación Arquitectónica |
| :--- | :---: | :--- |
| **Incompatibilidad de IDs (BigInt vs UUID en Supabase)** | **ALTA** | Si se mezcla Supabase Auth con las tablas actuales, las claves foráneas `user_id` fallarán. Se recomienda mantener Laravel Sanctum para la gestión de usuarios y usar Supabase como base de datos PostgreSQL estándar. |
| **Ejecución inadvertida de `composer update`** | **ALTA** | No ejecutar `composer update`. Si se requiere instalar dependencias, utilizar `composer install` respetando el `composer.lock` bajo PHP 8.5.8 / 8.4. |
| **Falta de extensión `pdo_pgsql` en PHP** | **MEDIA** | Cualquier comando de Artisan como `migrate --database=pgsql` fallará inmediatamente en Laragon hasta que se habilite la extensión en `php.ini`. |
| **Rotura de grupos de lectura en preguntas ICFES** | **MEDIA** | La lógica de selección de preguntas no debe simplificarse a un `ORDER BY RANDOM() LIMIT N` directo, pues dejaría lecturas huérfanas o fragmentadas. Debe mantenerse el algoritmo atómico de `ExamService`. |
| **Exposición de credenciales de base de datos** | **MEDIA** | Mantener las llaves y contraseñas de Supabase estrictamente en el entorno del servidor (`.env` del backend) con `DB_SSLMODE=require`. |

---

## 15. Próximo Paso Recomendado (Sin Ejecutarlo)

> [!IMPORTANT]
> **Paso sugerido para cuando autorices continuar:**
> 1. **Definir formalmente el rol de Supabase:** Confirmar si la arquitectura mantendrá a Laravel como API intermediaria conectada a PostgreSQL de Supabase (Opción A, menor fricción y cero rotura de lógica existente).
> 2. **Habilitar `pdo_pgsql`:** Abrir el `php.ini` activo de Laragon (`C:\laragon\bin\php\php-8.5.8-Win32-vs17-x64\php.ini`) y remover el punto y coma de `;extension=pdo_pgsql`.
> 3. **Probar conexión remota:** Configurar una conexión de prueba `supabase` en `config/database.php` y verificar la conectividad SSL contra Supabase sin tocar la base MySQL local.
> 4. **No ejecutar limpiezas hasta tener el destino Supabase verificado y conectado.**

