# Documento 03 - Arquitectura Oficial de la Base de Datos
Versión: 1.0

Proyecto: ATHENA – Plataforma Inteligente de Preparación para las Pruebas ICFES

---

# 1. Objetivo

Este documento define la arquitectura oficial de la base de datos de ATHENA.

Su propósito es organizar toda la información necesaria para el funcionamiento del sistema, garantizando integridad, consistencia y escalabilidad.

Toda modificación futura deberá respetar esta estructura.

---

# 2. Motor de Base de Datos

El proyecto utilizará:

- MySQL
- Laravel Eloquent ORM
- Migraciones de Laravel

El acceso a la base de datos únicamente se realizará desde el backend mediante Eloquent.

El frontend nunca accederá directamente a la base de datos.

---

# 3. Principios de Diseño

La base de datos se diseñó siguiendo estos principios:

- Normalización.
- Integridad referencial.
- Evitar duplicidad de datos.
- Escalabilidad.
- Facilidad de mantenimiento.
- Compatibilidad con Laravel.

---

# 4. Organización por Módulos

Las tablas se agrupan por su responsabilidad dentro del sistema.

## Módulo de Usuarios

Responsabilidad:

Gestionar la información de acceso y perfil de los usuarios.

Tablas:

- users

---

## Módulo Banco ICFES

Responsabilidad:

Almacenar todo el contenido académico.

Tablas:

- subjects
- topics
- questions
- question_options

---

## Módulo Simulacros

Responsabilidad:

Gestionar la creación y desarrollo de simulacros.

Tablas:

- exam_configs
- exam_config_subject
- exam_attempts
- attempt_answers

---

## Módulo Progreso

Responsabilidad:

Registrar el rendimiento del estudiante.

Tablas:

- user_topic_performance

---

## Módulo Gamificación

Responsabilidad:

Administrar recompensas académicas.

Tablas:

- achievements
- user_achievements

---

## Módulo Perfil

Responsabilidad:

Administrar notificaciones del estudiante.

Tablas:

- notifications

---

## Módulo Inteligencia Artificial

Responsabilidad:

Guardar el historial de conversaciones.

Tablas:

- ai_chat_sessions
- ai_chat_messages

---

## Módulos Futuros

Estas tablas permanecerán en la base de datos pero no forman parte del MVP.

- store_items
- purchases
- inventories
- subscription_plans
- subscriptions
- payment_transactions

---

# 5. Descripción de las Tablas

## users

Almacena la información principal de cada usuario.

Incluye:

- Datos personales.
- Credenciales.
- Rol.
- XP.
- Nivel.
- Monedas.
- Racha.

---

## subjects

Representa las materias oficiales del ICFES.

Ejemplos:

- Matemáticas
- Lectura Crítica
- Ciencias Naturales
- Sociales
- Inglés

---

## topics

Representa los temas pertenecientes a una materia.

Ejemplo:

Materia:

Matemáticas

Tema:

Álgebra

---

## questions

Contiene el banco oficial de preguntas.

Cada pregunta almacena:

- Enunciado.
- Dificultad.
- Explicación.
- Materia.
- Tema.

---

## question_options

Almacena las opciones de respuesta.

Cada pregunta tendrá varias opciones y solo una será correcta.

---

## exam_configs

Guarda la configuración elegida para un simulacro.

Ejemplo:

- Tipo.
- Dificultad.
- Cantidad de preguntas.

---

## exam_config_subject

Relaciona una configuración con las materias seleccionadas.

---

## exam_attempts

Representa cada simulacro realizado por un estudiante.

Incluye:

- Fecha.
- Estado.
- Puntaje.
- Tiempo empleado.

---

## attempt_answers

Guarda cada respuesta enviada por el estudiante.

Incluye:

- Pregunta.
- Opción seleccionada.
- Tiempo empleado.
- Correcta o incorrecta.

---

## user_topic_performance

Resume el rendimiento del estudiante por tema.

Permite calcular fortalezas y debilidades.

---

## achievements

Define todos los logros existentes.

---

## user_achievements

Relaciona los logros obtenidos por cada estudiante.

---

## notifications

Guarda las notificaciones del sistema.

---

## ai_chat_sessions

Representa una conversación entre el estudiante y ATHENA.

---

## ai_chat_messages

Almacena cada mensaje enviado durante una conversación.

---

# 6. Relaciones Principales

User

↓

ExamAttempt

↓

AttemptAnswer

↓

Question

↓

Topic

↓

Subject

---

Question

↓

QuestionOption

---

User

↓

AiChatSession

↓

AiChatMessage

---

User

↓

UserAchievement

↓

Achievement

---

User

↓

Notification

---

# 7. Flujo de Información

Administrador

↓

Crea Materias

↓

Crea Temas

↓

Crea Preguntas

↓

Crea Opciones

↓

Banco ICFES

↓

Estudiante genera simulacro

↓

Responde preguntas

↓

Se registran respuestas

↓

Se calculan resultados

↓

Se actualiza progreso

↓

ATHENA consulta la información

↓

Responde al estudiante

---

# 8. Integridad Referencial

Todas las relaciones deberán utilizar claves foráneas.

Cuando corresponda se utilizará:

ON DELETE CASCADE

para evitar registros huérfanos.

No se permitirá información inconsistente.

---

# 9. Convenciones

Todas las tablas utilizarán:

- id
- timestamps
- claves foráneas
- nombres en inglés
- singular en modelos
- plural en tablas

Los modelos seguirán las convenciones oficiales de Laravel.

---

# 10. Datos Administrados

El Panel Administrativo será el único encargado de modificar:

- Materias
- Temas
- Preguntas
- Opciones

Los estudiantes únicamente podrán consultar esta información.

---

# 11. Datos Generados Automáticamente

El sistema generará automáticamente:

- Intentos de simulacro.
- Respuestas.
- Historial.
- Estadísticas.
- Rendimiento.
- Historial de chat.
- Logros.
- Notificaciones.

---

# 12. Tablas del MVP

Las siguientes tablas forman parte de la primera versión del proyecto:

✓ users

✓ subjects

✓ topics

✓ questions

✓ question_options

✓ exam_configs

✓ exam_config_subject

✓ exam_attempts

✓ attempt_answers

✓ user_topic_performance

✓ achievements

✓ user_achievements

✓ notifications

✓ ai_chat_sessions

✓ ai_chat_messages

---

# 13. Tablas para Versiones Futuras

Estas tablas permanecerán en el proyecto pero no se desarrollarán durante el MVP.

- store_items
- purchases
- inventories
- subscription_plans
- subscriptions
- payment_transactions

---

# 14. Estado del Documento

Versión: 1.0

Estado:

Aprobado como arquitectura oficial de la base de datos del proyecto ATHENA.

Toda modificación deberá reflejarse primero en este documento antes de implementarse mediante migraciones.