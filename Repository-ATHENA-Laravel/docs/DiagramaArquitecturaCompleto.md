# Diagrama de Arquitectura Completo y Profundo — Proyecto ATHENA

**Versión:** 1.0  
**Fecha:** Octubre 2026  
**Sistema:** Plataforma Inteligente de Preparación para Pruebas Saber 11 (ICFES)  

---

## 1. Convenciones y Leyenda del Estado del Proyecto

Para reflejar con precisión tanto lo implementado como lo planificado, los diagramas y tablas utilizan la siguiente codificación visual y de estado:

* 🟢 **[COMPLETO / NÚCLEO ACTIVO]**: Funcionalidad, modelo, servicio o endpoint completamente implementado en el backend y estructurado.
* 🟡 **[EN INTEGRACIÓN / PARCIAL]**: Componente o vista de frontend en proceso de cableado con los endpoints de Axios / lógica en desarrollo.
* 🔵 **[ROADMAP / FASE FUTURA]**: Entidades de base de datos y modelos ya preparados en el código (ej. Tienda, Suscripciones, Pagos) que se activarán post-MVP.

---

## 2. Macro-Arquitectura Global del Sistema

Este diagrama ilustra la separación de responsabilidades en 5 capas: **Presentación (React)**, **Comunicación (Axios)**, **API & Negocio (Laravel 12)**, **Persistencia Híbrida (MySQL + MongoDB)** y **Servicios Cognitivos (Google Gemini IA)**.

```mermaid
flowchart TB
    %% DEFINICIÓN DE SUBGRAFOS POR CAPAS

    subgraph FE[" CAPA FRONTEND (React 19 + Vite + React Router v7)"]
        direction TB
        subgraph FE_AUTH[" Módulo Autenticación"]
            UI_Login["🟢 Login / Registro\n(login_student / register_student)"]
            UI_AdminLogin["🟢 Login Administrativo\n(login_admin)"]
            UI_Forgot["🟡 Recuperar Contraseña\n(forgot_password / reset_password)"]
        end

        subgraph FE_STUDENT[" Módulo Estudiante (MainLayout)"]
            UI_Dash["🟡 Dashboard Estudiante\n(KPIs, racha, accesos rápidos)"]
            UI_Mock["🟡 Simulacros ICFES\n(config, quick, exam, results, review, history)"]
            UI_Practice["🟡 Prácticas por Materia/Tema\n(practice_config, practice_full, results)"]
            UI_AITutor["🟡 Chat Tutor ATHENA IA\n(chat.jsx - Interfaz conversacional)"]
            UI_Profile["🟡 Perfil & Logros\n(profile, edit, achievements, notifications)"]
            UI_Stats["🟡 Estadísticas & Ranking\n(statistics, analysis, ranking)"]
            UI_StoreSub["🔵 Tienda, Planes & Pagos [FUTURO]\n(store, subscriptions, payments)"]
        end

        subgraph FE_ADMIN[" Módulo Administrativo (AdminLayout)"]
            UI_AdminDash["🟢 Dashboards Gestión\n(SuperAdmin, Admin, Support)"]
            UI_AdminUsers["🟢 Gestión de Usuarios\n(users.jsx)"]
            UI_AdminAcademic["🟢 Banco ICFES\n(questions, subjects, exams)"]
        end

        subgraph FE_CLIENT[" Cliente HTTP Central"]
            AxiosClient["🟢 Axios Instance (api/axios.js)\nBaseURL: http://127.0.0.1:8000/api\nHeaders: Bearer Token, JSON"]
        end
    end

    subgraph API_GW[" CAPA DE ENTRADA & ENRUTAMIENTO (Laravel 12)"]
        RouteAPI["🟢 routes/api.php\n(Prefijos modulares)"]
        SanctumMW["🟢 Middleware Laravel Sanctum\n(auth:sanctum + CheckRoles)"]
    end

    subgraph BE_CTRL[" CAPA DE CONTROLADORES & FORM REQUESTS"]
        direction TB
        Ctrl_Auth["🟢 AuthController\n(login, register, logout, me)"]
        Ctrl_Exam["🟢 ExamController\n(configure, getQuestions, submitAnswer, finish)"]
        Ctrl_Progress["🟢 ProgressController\n(getSummary, getSubjectStats, getWeaknesses)"]
        Ctrl_AI["🟢 AIController\n(sendMessage, getHistory, getContext)"]
        Ctrl_Admin["🟢 Admin Controllers\n(UserController, QuestionController, SubjectController, TopicController)"]
        Ctrl_Profile["🟢 Profile & Notification Controllers\n(ProfileController, NotificationController)"]
    end

    subgraph BE_SERVICES[" CAPA DE SERVICIOS (Lógica de Negocio)"]
        direction TB
        Srv_Exam["🟢 ExamService\n- Selección algorítmica de preguntas\n- Calificación en tiempo real\n- Cálculo de percentiles & puntajes ICFES\n- Actualización de telemetría académica"]
        Srv_Progress["🟢 ProgressService\n- Cálculo de fortalezas y debilidades\n- Estadísticas por materia y subtema\n- KPIs de progreso global"]
        Srv_AI["🟢 AiService & AiContextService\n- Inyección de contexto del estudiante\n- Formateo de Prompt Pedagógico ICFES\n- Orquestación de sesiones y mensajes"]
        Srv_Gemini["🟢 GeminiService\n- Cliente de API Gemini 1.5/2.0 Flash\n- Manejo de cuotas, timeouts y streaming"]
    end

    subgraph PERSISTENCE[" CAPA DE PERSISTENCIA HÍBRIDA"]
        subgraph DB_REL[" Relacional: MySQL 8 (Eloquent ORM)"]
            T_Users["🟢 users\n(credenciales, rol, xp, nivel, monedas)"]
            T_Academic["🟢 subjects, topics, questions, question_options\n(Banco oficial ICFES con justificaciones)"]
            T_Exams["🟢 exam_configs, exam_config_subject,\nexam_attempts, attempt_answers\n(Trazabilidad completa de exámenes)"]
            T_Progress["🟢 user_topic_performance, achievements,\nuser_achievements, notifications"]
            T_Future["🔵 store_items, purchases, inventories,\nsubscription_plans, subscriptions, payment_transactions\n[ROADMAP MONETIZACIÓN]"]
        end

        subgraph DB_NOSQL[" NoSQL: MongoDB Atlas (Moloquent ODM)"]
            M_Conversations["🟢 ai_conversations\n(Sesiones de chat, metadatos, tags)"]
            M_Interactions["🟢 ai_interactions\n(Mensajes, tokens, latencia, feedback)"]
            M_Context["🟢 ai_learning_contexts\n(Instantáneas de debilidades y métricas)"]
        end
    end

    subgraph EXT_AI[" MOTOR DE INTELIGENCIA ARTIFICIAL EXTERNO"]
        GeminiAPI[" Google Gemini Flash API\n(google-gemini-php/laravel)"]
    end

    %% CONEXIONES
    FE_AUTH --> AxiosClient
    FE_STUDENT --> AxiosClient
    FE_ADMIN --> AxiosClient

    AxiosClient --> RouteAPI
    RouteAPI --> SanctumMW

    SanctumMW --> Ctrl_Auth
    SanctumMW --> Ctrl_Exam
    SanctumMW --> Ctrl_Progress
    SanctumMW --> Ctrl_AI
    SanctumMW --> Ctrl_Admin
    SanctumMW --> Ctrl_Profile

    Ctrl_Exam --> Srv_Exam
    Ctrl_Progress --> Srv_Progress
    Ctrl_AI --> Srv_AI
    Srv_AI --> Srv_Gemini

    Srv_Exam --> T_Academic
    Srv_Exam --> T_Exams
    Srv_Exam --> T_Progress
    Srv_Progress --> T_Progress
    Srv_Progress --> T_Exams

    Ctrl_Auth --> T_Users
    Ctrl_Admin --> T_Users
    Ctrl_Admin --> T_Academic
    Ctrl_Profile --> T_Users
    Ctrl_Profile --> T_Progress

    Srv_AI --> Srv_Progress
    Srv_AI --> M_Conversations
    Srv_AI --> M_Interactions
    Srv_AI --> M_Context
    Srv_Gemini --> GeminiAPI
```

---

## 3. Flujo Detallado de Datos: Petición ↔ Respuesta

Este diagrama de secuencia ilustra el ciclo de vida de una solicitud típica, desde que el usuario interactúa en la interfaz de React hasta la respuesta procesada.

```mermaid
sequenceDiagram
    autonumber
    actor Estudiante as 👤 Estudiante (Navegador)
    participant React as ⚛️ React Component (UI)
    participant Axios as 🌐 Axios Interceptor (api/axios.js)
    participant LaravelRoute as 🚦 Laravel Routing & Sanctum
    participant Controller as 🎮 ExamController
    participant FormReq as 🛡️ Form Request Validator
    participant Service as ⚙️ ExamService
    participant MySQL as 🗄️ MySQL (Eloquent)
    participant Mongo as 🍃 MongoDB Atlas

    Estudiante->>React: Selecciona materias y clic en "Iniciar Simulacro"
    React->>Axios: examsAPI.configureExam({ exam_type: 'quick', subjects: [1,2] })
    Axios->>LaravelRoute: POST /api/exams/configure (Header: Bearer Token)
    
    LaravelRoute->>LaravelRoute: Valida token con Laravel Sanctum
    LaravelRoute->>FormReq: Valida esquema de entrada (types, ids existentes)
    
    alt Datos Inválidos
        FormReq-->>Axios: 422 Unprocessable Entity (JSON Errors)
        Axios-->>React: Toast Notification con errores
    else Datos Válidos
        FormReq->>Controller: Petición saneada
        Controller->>Service: configureExam(userId, payload)
        
        activate Service
        Service->>MySQL: START TRANSACTION
        Service->>MySQL: INSERT INTO exam_configs (...)
        Service->>MySQL: INSERT INTO exam_attempts (status: 'in_progress', ...)
        Service->>MySQL: SELECT questions WHERE subject_id IN (...) ORDER BY RAND() LIMIT N
        Service->>MySQL: COMMIT TRANSACTION
        deactivate Service
        
        Service-->>Controller: DTO (Attempt + Questions sanitizadas sin 'is_correct')
        Controller-->>LaravelRoute: Response JSON (success: true, data: {...})
        LaravelRoute-->>Axios: HTTP 200 OK
        Axios-->>React: Renderiza pantalla de preguntas (MockExam.jsx) con temporizador
        React-->>Estudiante: Visualiza primera pregunta ICFES
    end
```

---

## 4. Orquestación del Motor Académico y Simulacros ICFES

Cómo se relacionan las materias, los temas, el banco de preguntas y la evaluación del estudiante:

```mermaid
erDiagram
    SUBJECTS ||--o{ TOPICS : "contiene"
    TOPICS ||--o{ QUESTIONS : "clasifica"
    QUESTIONS ||--|{ QUESTION_OPTIONS : "posee (4 opciones)"
    
    USERS ||--o{ EXAM_ATTEMPTS : "presenta"
    EXAM_CONFIGS ||--o{ EXAM_ATTEMPTS : "define parámetros"
    EXAM_CONFIGS }o--o{ SUBJECTS : "evalúa (exam_config_subject)"
    
    EXAM_ATTEMPTS ||--o{ ATTEMPT_ANSWERS : "registra"
    QUESTIONS ||--o{ ATTEMPT_ANSWERS : "es respondida en"
    QUESTION_OPTIONS ||--o{ ATTEMPT_ANSWERS : "opción elegida"
    
    USERS ||--o{ USER_TOPIC_PERFORMANCE : "acumula maestría en"
    TOPICS ||--o{ USER_TOPIC_PERFORMANCE : "mide rendimiento de"

    SUBJECTS {
        int id PK
        string name "Ej: Matemáticas, Lectura Crítica"
        string description
        string icon
    }
    TOPICS {
        int id PK
        int subject_id FK
        string name "Ej: Geometría, Álgebra, Trigonometría"
    }
    QUESTIONS {
        int id PK
        int topic_id FK
        text stem "Enunciado de la pregunta"
        text context_text "Texto o lectura base"
        string image_url "Gráfica o figura de apoyo"
        enum difficulty "easy, medium, hard"
        text explanation "Justificación pedagógica"
    }
    QUESTION_OPTIONS {
        int id PK
        int question_id FK
        text option_text "Texto de la opción"
        boolean is_correct "Solo 1 verdadera por pregunta"
    }
    EXAM_ATTEMPTS {
        int id PK
        int user_id FK
        int exam_config_id FK
        enum status "in_progress, completed, abandoned"
        int total_questions
        int correct_answers
        decimal score "Puntaje estandarizado 0 - 500 / 0 - 100"
        int time_spent_seconds
    }
    ATTEMPT_ANSWERS {
        int id PK
        int exam_attempt_id FK
        int question_id FK
        int selected_option_id FK
        boolean is_correct
        int time_spent_seconds
    }
```

---

## 5. Arquitectura del Asistente Cognitivo: ATHENA IA

La inteligencia artificial no responde como un chatbot genérico; utiliza una **inyección contextual dinámica** basada en el desempeño real del estudiante:

```mermaid
flowchart LR
    subgraph IN["1. Solicitud del Alumno"]
        Msg["💬 Pregunta del Estudiante:\n'¿Por qué me equivoqué en la\npregunta 4 sobre triángulos semejantes?'"]
    end

    subgraph CONTEXT_BUILDER["2. AiContextService (Extracción & Ensamblado)"]
        direction TB
        Q_Data[" Extrae pregunta, opción seleccionada,\nrespuesta correcta y justificación oficial"]
        User_Data[" Extrae debilidades del estudiante en MySQL\n(UserTopicPerformance: Geometría 34% acierto)"]
        Hist_Data[" Extrae últimos 5 mensajes de MongoDB\n(ai_conversations / ai_interactions)"]
        PromptAssembler["🔄 Ensambla System Prompt:\n- Rol: Tutora Experta ICFES Saber 11\n- Tono: Socrático y motivador\n- Regla: Enseñar razonamiento, no solo resultado\n- Restricción: No alucinar calificaciones"]
    end

    subgraph LLM_CALL["3. Inferencia de IA"]
        GeminiFlash["⚡ Google Gemini Flash API\n(Procesamiento con baja latencia)"]
    end

    subgraph OUT["4. Respuesta & Persistencia"]
        direction TB
        SaveMongo["🍃 Guarda en MongoDB Atlas:\n- Mensaje del usuario\n- Respuesta generada\n- Metadatos de contexto y tokens"]
        JSONResp["📦 Retorna JSON al Frontend:\n- Mensaje formateado en Markdown / KaTeX\n- Sugerencias de temas a reforzar"]
    end

    IN --> CONTEXT_BUILDER
    CONTEXT_BUILDER --> LLM_CALL
    LLM_CALL --> OUT
```

---

## 6. Mapa Completo de Componentes de Frontend (React)

Estructura de la aplicación en React según las rutas y layouts definidos en `App.jsx`:

```
ATHENAreact/src/
│
├── App.jsx (Enrutador Principal con React Router v7)
│
├── components/
│   ├── MainLayout.jsx (🟢 Layout del Estudiante con Navbar + Sidebar + Topbar)
│   ├── AdminLayout.jsx (🟢 Layout de Gestión con Sidebar Administrativo y Role Guard)
│   ├── Sidebar.jsx (Navegación lateral con control activo)
│   ├── header/ (Componentes de cabecera, usuario y notificaciones)
│   └── footer/ (Pie de página institucional)
│
├── api/ (Capa de integración con Axios)
│   ├── axios.js (🟢 Instancia central con interceptores de Token y Errores)
│   ├── exams.js (🟢 Métodos para configurar, responder y finalizar simulacros)
│   ├── progress.js (🟢 Métodos para consultar métricas, estadísticas y resumen)
│   └── ai.js (🟢 Métodos para enviar mensajes a ATHENA y cargar historial)
│
└── pages/
    ├── authentications/
    │   ├── login_student.jsx (🟢 Inicio de sesión estudiante)
    │   ├── register_student.jsx (🟢 Registro de nuevo estudiante)
    │   ├── forgot_password.jsx (🟡 Recuperación de clave)
    │   └── reset_password.jsx (🟡 Restablecimiento de clave)
    │
    ├── admin/
    │   ├── login_admin.jsx (🟢 Acceso panel administrativo)
    │   ├── dashboard_super.jsx / dashboard_admin.jsx / dashboard_support.jsx (🟢 Paneles por rol)
    │   ├── users.jsx (🟢 Gestión de cuentas de usuario)
    │   ├── questions.jsx (🟢 CRUD Banco de Preguntas)
    │   ├── subjects.jsx (🟢 Gestión de Materias y Temas)
    │   └── exams.jsx (🟢 Auditoría de simulacros globales)
    │
    ├── dashboards/
    │   └── dashboard.jsx (🟡 Dashboard principal del estudiante)
    │
    ├── mock_exams/ (Módulo de Simulacros)
    │   ├── MockLayout.jsx (Sub-layout de examen)
    │   ├── mock_config.jsx (🟡 Configuración personalizada)
    │   ├── mock_quick.jsx (🟡 Simulacro rápido de 10-20 preguntas)
    │   ├── mock_exam.jsx (🟡 Interfaz interactiva de presentación)
    │   ├── mock_results.jsx (🟡 Hoja de resultados y puntaje)
    │   ├── mock_review.jsx (🟡 Revisión pregunta a pregunta con justificaciones)
    │   └── mock_history.jsx (🟡 Historial de todos los intentos)
    │
    ├── ai_tutor/
    │   └── chat.jsx (🟡 Chat interactivo con la tutora virtual ATHENA)
    │
    ├── practices/ (Modo práctica por materias)
    │   ├── practice_config.jsx / practice_full.jsx / practice_results.jsx
    │
    ├── profiles/ (Perfil del estudiante)
    │   ├── ProfileLayout.jsx
    │   ├── profile.jsx / profile_edit.jsx
    │   ├── profile_achievements.jsx (🟡 Sistema de logros y medallas)
    │   └── profile_notifications.jsx (🟡 Bandeja de avisos)
    │
    ├── statistics/ & ranking/
    │   ├── statistics.jsx / analysis.jsx (🟡 Gráficos de evolución y debilidades)
    │   └── ranking.jsx (🟡 Tabla de clasificación de estudiantes)
    │
    └── store/ & subscriptions/ (🔵 ROADMAP FUTURO)
        ├── store.jsx (Tienda de items / avatares con monedas virtuales)
        ├── subscription.jsx (Planes Premium de preparación)
        └── payments.jsx (Pasarela de pagos)
```

---

## 7. Matriz de Auditoría y Estado de Desarrollo

| Componente / Módulo | Capa Backend (Laravel) | Capa Frontend (React) | Persistencia | Estado General |
| :--- | :--- | :--- | :--- | :--- |
| **Autenticación (Sanctum)** | `AuthController.php`, Tokens, Middleware de roles | `login_student.jsx`, `register_student.jsx` | `users` (MySQL) | 🟢 **Funcional / En cableado final** |
| **Banco ICFES (Admin)** | `SubjectController`, `TopicController`, `QuestionController` | `subjects.jsx`, `questions.jsx`, `users.jsx` | `subjects`, `topics`, `questions`, `options` | 🟢 **Backend 100% / UI Operativa** |
| **Simulacros & Prácticas** | `ExamController.php`, `ExamService.php` | `mock_config.jsx`, `mock_exam.jsx`, `mock_results.jsx` | `exam_attempts`, `attempt_answers` | 🟡 **Lógica completa / UI en integración** |
| **Estadísticas y Progreso** | `ProgressController.php`, `ProgressService.php` | `statistics.jsx`, `analysis.jsx`, `dashboard.jsx` | `user_topic_performance` | 🟡 **Servicios de cálculo listos / Integrando gráficos** |
| **ATHENA IA Tutor** | `AIController.php`, `AiService.php`, `GeminiService.php` | `ai_tutor/chat.jsx` | MongoDB Atlas + MySQL + Gemini API | 🟢 **Backend Conectado / UI enlazando stream** |
| **Gamificación & Perfil** | `ProfileController.php`, `NotificationController.php` | `profile.jsx`, `profile_achievements.jsx` | `achievements`, `user_achievements` | 🟡 **Modelos y tablas creados / Vistas maquetadas** |
| **Tienda, Planes y Pagos** | Modelos y migraciones listos en BD | `store.jsx`, `subscription.jsx`, `payments.jsx` | `store_items`, `subscriptions`, `purchases` | 🔵 **Roadmap Futuro (Post-MVP)** |

---

## 8. Resumen Ejecutivo de la Arquitectura

1. **Desacoplamiento Estricto:** El Frontend (React) nunca se conecta directamente a la base de datos; toda operación transita mediante la API REST de Laravel con respuestas JSON estandarizadas.
2. **Garantía de Integridad:** La lógica evaluativa crítica (calificación, aleatoriedad del banco de preguntas, cálculo de puntajes ICFES y cálculo de fortalezas/debilidades) reside exclusivamente en los **Services** de Laravel, asegurando que el cliente no pueda alterar sus resultados.
3. **Persistencia Especializada:** MySQL garantiza la consistencia transaccional y relacional de exámenes y usuarios, mientras que MongoDB Atlas proporciona escalabilidad y flexibilidad para los historiales de conversación y telemetría de IA.
4. **Inteligencia Artificial Contextualizada:** ATHENA IA no responde de forma aislada; consume en tiempo real el historial y las debilidades del estudiante desde MySQL para ofrecer tutoría personalizada y socrática.
