# Documento 02 - Arquitectura Oficial del Backend
Versión: 1.0

Proyecto: ATHENA – Plataforma Inteligente de Preparación para las Pruebas ICFES

---

# 1. Objetivo

Este documento define la arquitectura oficial del backend de ATHENA.

Su propósito es establecer una estructura clara, mantenible y escalable basada en Laravel, garantizando que todos los integrantes del equipo desarrollen bajo los mismos criterios.

---

# 2. Tecnologías

El backend utilizará las siguientes tecnologías:

- PHP 8.2+
- Laravel 12
- Laravel Sanctum
- Eloquent ORM
- MySQL
- API REST
- Composer

---

# 3. Patrón de Arquitectura

ATHENA seguirá la arquitectura MVC (Model - View - Controller) propia de Laravel.

Cada capa tendrá una única responsabilidad.

Modelo (Model)
↓
Controlador (Controller)
↓
Respuesta JSON
↓
Frontend React

---

# 4. Principios del Proyecto

El backend seguirá los siguientes principios:

- Responsabilidad única.
- Código reutilizable.
- Separación de responsabilidades.
- Convenciones oficiales de Laravel.
- Comunicación mediante API REST.

No se crearán estructuras personalizadas que rompan la arquitectura estándar de Laravel.

---

# 5. Organización de Carpetas

app/

├── Http/
│
├── Models/
│
├── Providers/
│
├── Policies/
│
├── Exceptions/
│
└── ...

Dentro de Http se organizará así:

Http/

Controllers/

Requests/

Middleware/

Resources/

---

# 6. Organización de Controladores

Los controladores estarán agrupados por módulo.

Controllers/

Auth/

Admin/

Exam/

Profile/

Progress/

AI/

Notification/

Cada controlador deberá tener una única responsabilidad.

---

# 7. Controladores Oficiales

## AuthController

Responsabilidad:

Gestionar la autenticación.

Funciones:

- Login
- Registro
- Logout
- Usuario autenticado

---

## UserController

Responsabilidad:

Administración de usuarios.

Funciones:

- Listar usuarios
- Crear usuarios
- Editar usuarios
- Eliminar usuarios
- Cambiar rol
- Activar o desactivar cuenta

Acceso:

Solo administrador.

---

## SubjectController

Responsabilidad:

Administrar materias.

CRUD completo.

---

## TopicController

Responsabilidad:

Administrar temas.

CRUD completo.

---

## QuestionController

Responsabilidad:

Administrar preguntas del banco ICFES.

CRUD completo.

Incluye:

- Pregunta
- Opciones
- Respuesta correcta
- Explicación
- Dificultad

---

## ExamController

Responsabilidad:

Gestionar los simulacros.

Funciones:

- Configurar simulacro
- Obtener preguntas
- Guardar respuestas
- Finalizar simulacro
- Calcular resultados

---

## ProgressController

Responsabilidad:

Consultar el progreso del estudiante.

Funciones:

- Historial
- Estadísticas
- Rendimiento por materia
- Rendimiento por tema

---

## ProfileController

Responsabilidad:

Administrar el perfil del estudiante.

Funciones:

- Consultar perfil
- Editar perfil

---

## AIController

Responsabilidad:

Gestionar la comunicación con ATHENA.

Funciones:

- Enviar mensaje
- Consultar historial

No contendrá la lógica del modelo de IA.

Solo recibirá la petición y devolverá la respuesta.

---

## NotificationController

Responsabilidad:

Administrar notificaciones del usuario.

---

# 8. Organización de Requests

Todas las validaciones deberán implementarse mediante Form Request.

Ejemplo:

Requests/

Auth/

LoginRequest

RegisterRequest

Admin/

StoreUserRequest

UpdateUserRequest

StoreSubjectRequest

UpdateSubjectRequest

StoreQuestionRequest

UpdateQuestionRequest

Exam/

StartExamRequest

SubmitAnswerRequest

FinishExamRequest

AI/

SendMessageRequest

Nunca se realizarán validaciones extensas dentro de los controladores.

---

# 9. Modelos Oficiales

Los modelos permanecerán con los nombres actuales.

No se modificarán sin aprobación del equipo.

## Autenticación

User

---

## Banco ICFES

Subject

Topic

Question

QuestionOption

---

## Simulacros

ExamConfig

ExamConfigSubject

ExamAttempt

AttemptAnswer

---

## Progreso

UserTopicPerformance

---

## Gamificación

Achievement

UserAchievement

---

## Perfil

Notification

---

## Inteligencia Artificial

AiChatSession

AiChatMessage

---

## Versiones futuras

StoreItem

Purchase

Inventory

SubscriptionPlan

Subscription

PaymentTransaction

---

# 10. Organización de Rutas

Las rutas se organizarán por módulos.

Ejemplo:

api.php

/auth

/users

/subjects

/topics

/questions

/exams

/profile

/progress

/ai

/notifications

Cada grupo utilizará prefijos y middleware correspondientes.

---

# 11. Middleware

El proyecto utilizará middleware para:

- Autenticación.
- Roles.
- Protección de rutas.
- Verificación de permisos.

Ejemplo:

auth:sanctum

role:admin

role:student

---

# 12. Respuestas de la API

Toda respuesta deberá generarse en formato JSON.

Ejemplo:

{
    "success": true,
    "message": "Operación realizada correctamente.",
    "data": {}
}

En caso de error:

{
    "success": false,
    "message": "Descripción del error."
}

Todas las respuestas seguirán la misma estructura.

---

# 13. Reglas de Desarrollo

Todo controlador tendrá una única responsabilidad.

No se permitirá duplicación de lógica.

Los modelos únicamente representarán entidades y relaciones.

Las validaciones se implementarán mediante Form Requests.

Toda comunicación con React será mediante JSON.

No se accederá directamente a la base de datos desde el frontend.

---

# 14. Seguridad

El backend utilizará:

- Laravel Sanctum.
- Hash de contraseñas.
- Validación de solicitudes.
- Middleware de autenticación.
- Control de roles.

Todas las rutas privadas deberán estar protegidas.

---

# 15. Estado del Documento

Versión: 1.0

Estado:

Aprobado como arquitectura oficial del Backend para ATHENA.

Toda implementación futura deberá respetar este documento.