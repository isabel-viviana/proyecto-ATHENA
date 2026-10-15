# Documento 01 - Módulos Oficiales ATHENA
Versión: 1.0

Proyecto: ATHENA – Plataforma Inteligente de Preparación para las Pruebas ICFES

---

# 1. Introducción

ATHENA es una plataforma web diseñada para apoyar a los estudiantes en la preparación de las Pruebas Saber 11 (ICFES). El sistema permite presentar simulacros, medir el progreso académico y resolver dudas mediante un asistente de inteligencia artificial llamado ATHENA.

El proyecto se desarrolla con una arquitectura cliente-servidor utilizando React para el frontend y Laravel para el backend, comunicándose mediante una API REST.

Este documento define oficialmente los módulos que componen el sistema y el alcance del proyecto.

---

# 2. Objetivo General

Desarrollar una plataforma web que permita a los estudiantes practicar para las pruebas ICFES mediante simulacros personalizados, seguimiento del progreso e interacción con un asistente de inteligencia artificial que apoye el aprendizaje.

---

# 3. Objetivos Específicos

- Permitir el registro e inicio de sesión de estudiantes.
- Administrar usuarios mediante un panel para administradores.
- Gestionar el banco de preguntas del ICFES.
- Generar simulacros a partir del banco de preguntas.
- Calificar automáticamente cada intento.
- Mostrar estadísticas y progreso del estudiante.
- Integrar un asistente de inteligencia artificial para resolver dudas.
- Implementar un sistema básico de gamificación mediante experiencia (XP) y logros.

---

# 4. Alcance del MVP

La primera versión del proyecto incluirá únicamente las funcionalidades necesarias para demostrar el funcionamiento completo del sistema.

Se implementarán los siguientes módulos:

- Autenticación
- Administración
- Simulacros
- Progreso
- Perfil
- Inteligencia Artificial ATHENA
- Gamificación
- Notificaciones

No se implementarán en esta versión:

- Pasarelas de pago
- Suscripciones Premium
- Tienda Virtual
- Inventario
- Compras
- Personalización de mascota

Estos módulos permanecerán definidos en la base de datos para futuras versiones.

---

# 5. Tipos de Usuario

## Administrador

Es el encargado de administrar el contenido académico y gestionar los usuarios del sistema.

Funciones:

- Administrar usuarios.
- Asignar roles.
- Activar o desactivar cuentas.
- Crear materias.
- Crear temas.
- Crear preguntas.
- Crear opciones de respuesta.
- Definir la respuesta correcta.
- Agregar explicaciones de cada pregunta.
- Asignar dificultad.

El administrador NO presenta simulacros.

---

## Estudiante

Es el usuario principal del sistema.

Funciones:

- Registrarse e iniciar sesión.
- Configurar simulacros.
- Resolver preguntas.
- Consultar resultados.
- Ver estadísticas.
- Chatear con ATHENA.
- Editar su perfil.

---

# 6. Módulos Oficiales

## 6.1 Autenticación

Responsabilidad:

Gestionar el acceso seguro de los usuarios.

Funciones:

- Login
- Registro
- Cierre de sesión
- Recuperación de contraseña (versión futura)

---

## 6.2 Administración

Responsabilidad:

Administrar el contenido académico y los usuarios.

Incluye:

- Usuarios
- Materias
- Temas
- Preguntas
- Opciones

---

## 6.3 Simulacros

Responsabilidad:

Gestionar todo el ciclo de un simulacro.

Incluye:

- Configuración
- Inicio
- Desarrollo
- Envío de respuestas
- Calificación
- Resultados

Este será el módulo principal del sistema.

---

## 6.4 Progreso

Responsabilidad:

Mostrar la evolución académica del estudiante.

Incluye:

- Historial
- Estadísticas
- Rendimiento por materia
- Rendimiento por tema

---

## 6.5 Perfil

Responsabilidad:

Administrar la información personal del estudiante.

Incluye:

- Datos personales
- Nivel
- Experiencia (XP)
- Logros obtenidos

---

## 6.6 ATHENA IA

Responsabilidad:

Apoyar el aprendizaje del estudiante mediante inteligencia artificial.

ATHENA podrá:

- Resolver dudas.
- Explicar respuestas.
- Explicar conceptos.
- Recomendar temas para estudiar.

ATHENA no genera preguntas del examen.

Siempre trabajará utilizando el contenido almacenado en la base de datos.

---

## 6.7 Gamificación

Responsabilidad:

Motivar al estudiante mediante recompensas académicas.

Incluye:

- XP
- Niveles
- Logros

---

## 6.8 Notificaciones

Responsabilidad:

Informar eventos importantes al estudiante.

Ejemplos:

- Nuevo simulacro disponible.
- Logro desbloqueado.
- Recordatorios.

---

# 7. Flujo General del Sistema

Administrador

↓

Gestiona Usuarios

↓

Construye el Banco ICFES

↓

El sistema almacena la información

↓

Los estudiantes presentan simulacros

↓

ATHENA utiliza esa información para responder preguntas

↓

El estudiante consulta su progreso

---

# 8. Arquitectura General

Frontend

React

↓

API REST

↓

Laravel

↓

Base de Datos

↓

Motor de Inteligencia Artificial

---

# 9. Exclusiones del Proyecto

La versión MVP no contempla:

- Comercio electrónico.
- Cobros en línea.
- Marketplace.
- Personalización avanzada.
- Algoritmos adaptativos complejos.
- Integraciones externas diferentes a la IA.

---

# 10. Roadmap Futuro

Versiones posteriores podrán incluir:

- Simulacros adaptativos.
- Suscripciones Premium.
- Tienda Virtual.
- Personalización de mascota.
- Ranking nacional.
- Analítica avanzada.
- Panel para docentes.

---

# 11. Responsabilidades del Equipo

Backend

Implementar la lógica de negocio y la API REST.

Frontend

Desarrollar la interfaz de usuario e integrar la API.

Base de Datos

Diseñar, mantener y optimizar el modelo de datos.

Inteligencia Artificial

Integrar ATHENA y definir el contexto enviado al modelo de lenguaje.

Integración

Realizar pruebas funcionales y verificar la comunicación entre frontend y backend.

---

# 12. Estado del Documento

Versión: 1.0

Estado: Aprobado para iniciar el desarrollo de la arquitectura del sistema.

Cualquier modificación posterior deberá actualizar este documento antes de implementarse en el código.