# ATHENA Question Bank

**Microservicio Independiente para la Gestión y Distribución del Banco de Preguntas ICFES**

---

## 1. ¿Qué es ATHENA Question Bank?

**ATHENA Question Bank** es un microservicio backend independiente diseñado exclusivamente para:
- Administrar el catálogo de materias y temas oficiales del ICFES.
- Recibir, resguardar y catalogar cuadernillos y documentos oficiales en formato PDF.
- Procesar documentos mediante inteligencia artificial multimodal (Google Gemini) para extraer enunciados, lecturas base (estímulos), opciones de respuesta, fórmulas, gráficas y solucionarios.
- Proveer una interfaz administrativa de moderación, edición y verificación humana de claves correctas.
- Distribuir paquetes balanceados de preguntas (bundles) mediante una API REST versionada (`/api/v1/`), preservando la indivisibilidad de grupos de preguntas asociados a lecturas críticas o infografías.
- Gestionar consumidores autorizados mediante protocolo de autenticación M2M (Machine-to-Machine).

---

## 2. Relación con ATHENA Core

Este servicio se encuentra completamente **desacoplado** del backend principal de ATHENA (`Repository-ATHENA-Laravel`):

| Responsabilidad | ATHENA Core (`Repository-ATHENA-Laravel`) | ATHENA Question Bank (`athena-question-bank`) |
| :--- | :---: | :---: |
| Usuarios, autenticación y roles |  Sí | ❌ No |
| Configuración de simulacros |  Sí | ❌ No |
| Intentos y respuestas de alumnos |  Sí | ❌ No |
| Historial, racha y gamificación |  Sí | ❌ No |
| Tutor IA (MongoDB Atlas) |  Sí | ❌ No |
| Gestión de Cuadernillos PDF | ❌ No |  Sí |
| Extracción Multimodal con Gemini | ❌ No |  Sí |
| Banco Oficial de Reactivos y Opciones | ❌ No |  Sí (PostgreSQL Supabase) |
| Almacenamiento de Documentos y Gráficas | ❌ No |  Sí (Storage Supabase) |
| Generación de Bundles Indivisibles | ❌ No |  Sí (`/api/v1/bundles/generate`) |

---

## 3. Requisitos del Entorno

- **PHP:** `^8.4` o `^8.5` (probado y verificado en PHP 8.5.8 CLI en Laragon).
- **Composer:** `^2.10` o superior.
- **Base de Datos:** PostgreSQL en Supabase (Proyecto: `ATHENA-question-bank`).
- **Almacenamiento:** Buckets privados de Supabase Storage (`icfes-documents` e `icfes-media`).
- **Motor IA:** Google Gemini API (para extracción multimodal).

---

## 4. Cómo Iniciar el Servidor en el Puerto 8001

Para no colisionar con el backend principal de ATHENA (que utiliza el puerto 8000), este microservicio debe ejecutarse en el puerto **8001**:

```bash
cd "C:\laragon\www\proyecto ATHENA\athena-question-bank"
php artisan serve --port=8001
```

El servicio quedará disponible en:
`http://127.0.0.1:8001`

---

## 5. Cómo Comprobar el Endpoint Health

Con el servidor en ejecución, realiza una petición `GET` a:

```bash
curl -X GET http://127.0.0.1:8001/api/v1/health
```

O en PowerShell:
```powershell
Invoke-RestMethod -Uri "http://127.0.0.1:8001/api/v1/health" -Method Get
```

### Respuesta Esperada:
```json
{
  "success": true,
  "service": "ATHENA Question Bank",
  "version": "1.0.0",
  "status": "running"
}
```

*Nota: Este endpoint es autónomo y no consulta bases de datos ni servicios externos.*

---

## 6. Estructura Básica de Carpetas

```
athena-question-bank/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/
│   │   │           ├── HealthController.php      # Verificación de disponibilidad
│   │   │           └── Admin/                    # Controladores de administración (Fase 2+)
│   │   └── Middleware/                           # Middlewares M2M y verificación de firma
│   ├── Models/                                   # Modelos Eloquent con UUID
│   └── Services/                                 # Lógica de bundles, Gemini y Storage
├── bootstrap/
│   └── app.php                                   # Configuración de rutas web y api
├── config/                                       # Configuraciones de app, database, filesystems
├── database/                                     # Migraciones, factories y seeders
├── routes/
│   ├── api.php                                   # Rutas versionadas /api/v1/
│   ├── console.php                               # Comandos de consola
│   └── web.php                                   # Rutas web
├── storage/                                      # Logs y archivos temporales
├── .env.example                                  # Plantilla de variables de entorno
└── composer.json                                 # Laravel 12.71.0 sobre PHP 8.5
```
