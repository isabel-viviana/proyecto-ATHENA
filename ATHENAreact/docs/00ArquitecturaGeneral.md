# Documento 00 - Arquitectura General del Proyecto ATHENA
Versión: 1.0

Proyecto: ATHENA – Plataforma Inteligente de Preparación para las Pruebas Saber 11 (ICFES)

---

# 1. Introducción

ATHENA es una plataforma web diseñada para apoyar a estudiantes que se preparan para las Pruebas Saber 11 (ICFES).

El sistema permite presentar simulacros, analizar el rendimiento académico y resolver dudas mediante un asistente de inteligencia artificial llamado ATHENA.

El proyecto sigue una arquitectura Cliente - Servidor, donde el Frontend consume una API REST desarrollada en Laravel.

---

# 2. Objetivo del Proyecto

Desarrollar una plataforma moderna que permita:

- Practicar mediante simulacros ICFES.
- Medir el progreso académico.
- Resolver dudas con inteligencia artificial.
- Facilitar la administración del banco de preguntas por parte del administrador.

---

# 3. Arquitectura General

La arquitectura del sistema está compuesta por cuatro capas principales.

```

React

↓

Laravel API REST

↓

MySQL

↓

Motor de Inteligencia Artificial

```

Cada capa tiene una responsabilidad específica y se comunica únicamente con la capa correspondiente.

---

# 4. Tecnologías Utilizadas

## Frontend

- React
- React Router
- Axios
- Vite
- Framer Motion

## Backend

- Laravel 12
- PHP 8.2
- Laravel Sanctum
- Eloquent ORM

## Base de Datos

- MySQL

## Inteligencia Artificial

- ATHENA IA
- Integración mediante API de un modelo de lenguaje (LLM)

---

# 5. Tipos de Usuario

## Administrador

Responsable de:

- Gestionar usuarios.
- Administrar el banco de preguntas ICFES.
- Gestionar materias, temas y preguntas.

## Estudiante

Responsable de:

- Presentar simulacros.
- Consultar estadísticas.
- Utilizar ATHENA IA.
- Administrar su perfil.

---

# 6. Módulos del Sistema

El proyecto está compuesto por los siguientes módulos:

- Autenticación
- Panel Administrativo
- Simulacros
- Progreso Académico
- Perfil
- ATHENA IA
- Gamificación
- Notificaciones

Los módulos de Tienda, Inventario, Suscripciones y Pagos quedan definidos para futuras versiones.

---

# 7. Flujo General

Administrador

↓

Construye el Banco ICFES

↓

Base de Datos

↓

Estudiante

↓

Genera Simulacro

↓

Resuelve Preguntas

↓

Sistema Califica

↓

Actualiza Progreso

↓

ATHENA IA utiliza el contexto académico

↓

Respuesta al estudiante

---

# 8. Organización del Proyecto

## Backend

Laravel mantiene la lógica de negocio.

Incluye:

- Modelos
- Controladores
- Requests
- Middleware
- API REST

---

## Frontend

React administra:

- Pantallas
- Componentes
- Navegación
- Consumo de API

---

## Base de Datos

MySQL almacena:

- Usuarios
- Banco ICFES
- Simulacros
- Resultados
- Historial
- IA

---

# 9. Comunicación del Sistema

Frontend

↓

Axios

↓

API Laravel

↓

Controladores

↓

Modelos

↓

Base de Datos

↓

Respuesta JSON

↓

Frontend

---

# 10. Documentación Oficial

Este proyecto se encuentra documentado mediante los siguientes archivos.

00-Arquitectura-General-ATHENA.md

01-Modulos-ATHENA.md

02-Arquitectura-Backend.md

03-Base-de-Datos.md

04-Contrato-API.md

05-Arquitectura-Frontend.md

06-Integracion-IA.md

07-Reglas-del-Proyecto.md

08-Plan-de-Desarrollo.md

09-Guia-del-Equipo.md

---

# 11. Principios del Proyecto

Todo el desarrollo deberá cumplir los siguientes principios:

- Arquitectura MVC.
- API REST.
- Separación de responsabilidades.
- Código reutilizable.
- Convenciones oficiales de Laravel.
- Componentes reutilizables en React.
- Comunicación mediante JSON.

---

# 12. Alcance del MVP

La primera versión del proyecto incluirá:

- Login.
- Registro.
- Panel Administrativo.
- Banco de preguntas.
- Simulacros.
- Resultados.
- Estadísticas.
- ATHENA IA.
- Perfil.

No incluirá:

- Pagos.
- Tienda.
- Suscripciones.
- Inventario.

---

# 13. Objetivo de la Arquitectura

Esta arquitectura busca garantizar que:

- Todo el equipo siga la misma organización.
- Backend y Frontend evolucionen de forma sincronizada.
- La integración sea sencilla.
- El sistema sea mantenible y escalable.

---

# 14. Estado del Documento

Versión: 1.0

Estado:

Documento aprobado como guía principal del proyecto ATHENA.

Todos los demás documentos dependen de esta arquitectura.