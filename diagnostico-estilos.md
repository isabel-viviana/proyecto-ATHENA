# SENA - Centro de Comercio y Servicios
## Programa: Análisis y Desarrollo de Software (ADSO)
### Diseñar la Solución de Software – Rap 3
### Guía de Aprendizaje: Tailwind CSS en proyectos React

---

# Diagnóstico de Estilos: Proyecto ATHENA

**Proyecto formativo:** ATHENA - Plataforma Web de Preparación ICFES Saber 11  
**Repositorio frontend analizado:** `ATHENAreact` (React 19 + Vite)  
**Fecha de elaboración:** 29 de octubre de 2026  

---

## 4.1 Inventario de estilos

A continuación se presenta el levantamiento cuantitativo y cualitativo de la arquitectura de estilos del frontend del proyecto formativo ATHENA:

| Pregunta | Respuesta del equipo |
| :--- | :--- |
| **¿Cuántos archivos .css tiene el proyecto?** | **40 archivos .css** en el frontend (`ATHENAreact/src/`). *(41 si se incluye el archivo base `resources/css/app.css` del backend Laravel).* |
| **¿Cuántas clases CSS distintas hay, aproximadamente?** | **Aproximadamente 986 clases CSS únicas** (~1.000 clases), con más de 2.270 declaraciones en total a lo largo de las hojas de estilo. |
| **¿Qué colores usa la marca del proyecto? (códigos hexadecimales)** | • **Azul ATHENA (Principal):** `#1a3a8a` / `#2563eb` / `#3b82f6`<br>• **Acento Amarillo/Dorado (Mascota/Marca):** `#f6ad55` / `#facc15` / `#f59e0b`<br>• **Fondo Oscuro Base (Dark Navy):** `#050a18`<br>• **Fondo Tarjetas / Superficies:** `#0a1128` y `#0d1734`<br>• **Bordes y divisores:** `#1a2238` / `#1a274c`<br>• **Verde Éxito / Admin:** `#48bb78` / `#22c55e`<br>• **Rojo Alerta / Error:** `#ef4444`<br>• **Texto:** `#ffffff` (principal) y `#a0aec0` / `#94a3b8` (secundario) |
| **¿Qué fuentes usa?** | • **Inter** como tipografía base del sistema (`"Inter", system-ui, "Segoe UI", Roboto, sans-serif`).<br>• `monospace` para bloques técnicos y cronómetros.<br>• **Font Awesome 6.5.0** (importada en `index.html`) para iconografía vectorial. |
| **¿Se ve bien en celular? (sí / parcialmente / no)** | **Parcialmente.** |
| **¿Hay estilos repetidos en varios archivos? ¿Cuáles?** | **Sí** (110 clases repetidas en 2 o más archivos). Destacan:<br>• **Botones:** `.btn-primary`, `.btn--primary`, `.btn-outline`<br>• **Encabezados:** `.page-header`, `.page-header__info`, `.page-header__stats`<br>• **Formularios de autenticación:** `.input-field`, `.divider`, `.mascot-container`, `.logo-top`<br>• **Contenedores:** `.main`, `.main__content`<br>• **Exámenes y preguntas:** `.option`, `.quiz-footer`, `.assistant__bot` |
| **¿Usan algún framework de estilos? (Bootstrap, ninguno, otro)** | **Otro: Tailwind CSS** (versión 4.3.3 con `@tailwindcss/vite` e `@import "tailwindcss"` en `index.css`), coexistiendo actualmente con una arquitectura mayoritaria de **CSS puro / modular (Vanilla CSS)** por vista. |

### Justificación técnica del inventario:
1. **Archivos CSS:** En `ATHENAreact/src/` existen 40 archivos `.css` distribuidos en subcarpetas modulares (`components/`, `pages/admin/`, `pages/authentications/`, `pages/dashboards/`, `pages/mock_exams/`, `pages/practices/`, `pages/profiles/`, `pages/ranking/`, `pages/statistics/`, `pages/store/`, `pages/subscriptions/`).
2. **Responsividad:** 32 de los 40 archivos CSS incluyen reglas `@media` adaptativas (a 768px, 600px y 480px) y el panel de administración dispone de un menú drawer colapsable. Sin embargo, en el portal principal del estudiante (`MainLayout.jsx` y `Sidebar.css`), la barra lateral conserva un ancho fijo de 250px sin colapso a botón hamburguesa, lo que comprime las tarjetas y preguntas en celulares pequeños.
3. **Redundancia:** Al haberse creado archivos CSS separados por cada página sin un sistema de diseño centralizado, se reescribieron los mismos estilos de botones, campos de texto y cabeceras una y otra vez.

---

## 4.2 Mapa de componentes

Comparación de los componentes base con los componentes equivalentes en la arquitectura React de ATHENA:

| Componente del ejemplo | Componente equivalente en nuestro proyecto | Archivo CSS que usa hoy |
| :--- | :--- | :--- |
| **Header** | `Header` (`src/components/header/Header.jsx`)<br>*(y `PracticeHeader` en `src/pages/practices/components/`)* | `Header.css` (`src/components/header/Header.css`)<br>*(y `practice_full.css` en prácticas)* |
| **Navbar** | `Navbar` (`src/components/navbar/Navbar.jsx`)<br>*(y `Sidebar` en `src/components/Sidebar.jsx` para navegación lateral)* | `Navbar.css` (`src/components/navbar/Navbar.css`)<br>*(y `Sidebar.css` para el menú lateral)* |
| **Main** | `MainLayout` (`src/components/MainLayout.jsx` con `<main className="main-content">`)<br>*(y `AdminLayout` con `<main className="admin-content">`)* | `index.css`<br>*(y `AdminLayout.css` para el panel administrativo)* |
| **Section** | `DashboardWelcomeCard` (`src/pages/dashboards/components/DashboardWelcomeCard.jsx`)<br>*(y componentes como `DashboardStatsOverview`, `DashboardRecommended`)* | Clases de **Tailwind CSS** en `index.css`<br>y `dashboard.css` |
| **Footer** | `Footer` (`src/components/footer/Footer.jsx`)<br>*(y `PracticeFooter` en `src/pages/practices/components/`)* | `Footer.css` (`src/components/footer/Footer.css`)<br>*(y `practice_full.css` en prácticas)* |

---

## 4.3 Preguntas para el equipo

### 1. ¿Qué componente de su proyecto tiene el CSS más difícil de mantener? ¿Por qué?
**Componente:** `questions.jsx` (*Banco ICFES / Gestión de Preguntas de Administración*) y su hoja de estilos asociada `questions.css` (junto a vistas complejas análogas como `dashboard_super.css` y `subjects.css`).

**Motivos técnicos:**
1. **Volumen excesivo:** `questions.css` tiene **750 líneas de código CSS** concentradas en un solo archivo, lo que dificulta la lectura, localización de errores y depuración.
2. **Variables locales duplicadas:** En lugar de heredar los tokens globales de `index.css`, declara un bloque `:root` independiente con 12 variables locales (`--qst-green`, `--qst-surface`, `--qst-card`, `--qst-border`, etc.), generando inconsistencias con el resto de la aplicación.
3. **Alta densidad y acoplamiento:** En el mismo archivo conviven reglas para ventanas modales flotantes (`.qst-modal`), formularios densos con editores de texto e imágenes para enunciados ICFES, tablas de datos tabulares, badges de dificultad/área, filtros de búsqueda y paginadores.
4. **Fragilidad ante cambios:** Un cambio en el `padding`, `margin` o `flex-basis` para alinear una columna de la tabla rompe el espaciado en los modales o desborda los filtros en pantallas medianas.
5. **Reescritura de estilos globales:** Redefine clases de botones (`.qst-btn`, `.qst-btn--primary`), inputs y tarjetas con prefijos ad-hoc en lugar de usar un sistema de diseño común.

---

### 2. El cliente o usuario de su proyecto, ¿qué esperaría ver en un celular?
El público objetivo de ATHENA son **estudiantes de educación media y egresados que se preparan para las pruebas de Estado Saber 11** desde sus teléfonos inteligentes (pantallas táctiles de 360px a 430px de ancho). Un estudiante espera:

1. **Navegación tipo App Móvil (Mobile-First):**
   - No ver una barra lateral fija de 250px que reduzca a la mitad el área visible de la pantalla.
   - En su lugar, espera un **menú hamburguesa deslizante** o una **barra de navegación inferior fija (Bottom Navigation Bar)** con accesos directos al Dashboard, Simulacros, Ranking y Tutor IA.
2. **Experiencia de resolución de preguntas táctil y fluida:**
   - Opciones de respuesta (A, B, C, D) en forma de botones grandes con un área de contacto táctil de al menos 48px de altura, fáciles de presionar con el pulgar sin pulsar opciones equivocadas.
   - Tipografía adaptada y legible en enunciados de lectura crítica sin tener que hacer zoom manual con los dedos.
   - Gráficas y tablas de problemas con ancho automático (`max-width: 100%`) y posibilidad de tocar para ampliar.
   - Cronómetro de tiempo restante compacto y visible en la parte superior sin tapar la pregunta.
   - Botones "Anterior" y "Siguiente" accesibles con una sola mano en la parte inferior de la pantalla.
3. **Métricas y Dashboard vertical:**
   - Estadísticas resumidas (porcentaje global, simulacros completados) apiladas en tarjetas verticales en una sola columna, sin deslizamiento horizontal incómodo.
4. **Chat con el Tutor IA a pantalla completa:**
   - Una interfaz conversacional similar a WhatsApp o Telegram, donde el teclado del celular no tape el mensaje que se está redactando.

---

### 3. Si mañana el cliente pide cambiar el color principal, ¿cuántos archivos tendrían que modificar?
**Respuesta:** En el estado actual del proyecto tendrían que modificarse **entre 28 y más de 35 archivos**.

**Desglose del impacto:**
- **28 archivos `.css`:** Tienen códigos hexadecimales de color primario quemados directamente en sus reglas (por ejemplo `#1a3a8a`, `#2563eb`, `#3b82f6`, `#6366f1` o `#22c55e`), en lugar de referenciar una variable CSS compartida.
- **8 archivos `.css`:** Poseen declaraciones `:root` propias y aisladas (`index.css`, `AdminLayout.css`, `dashboard_super.css`, `exams.css`, `questions.css`, `subjects.css`, `users.css`, `mock_config.css`) que tendrían que editarse individualmente.
- **13 componentes `.jsx`:** Tienen estilos en línea (`style={{ color: '#6366f1' }}`) o nombres de clases fijas de Tailwind (como `bg-indigo-600`, `text-indigo-400`, `bg-[#0a1128]`) en lugar de usar un token semántico como `bg-primary`.

**Conclusión pedagógica:**
Esto demuestra una notable **deuda técnica por falta de centralización de tokens de diseño**. Si el proyecto completara la migración a Tailwind CSS con configuración de colores semánticos en `theme.colors.primary` (o mediante variables CSS unificadas en `index.css`), el mismo cambio requeriría modificar **1 solo archivo**.

---

### 4. ¿Qué componente migrarían primero a Tailwind como prueba? Justifiquen la elección.
**Componente seleccionado para la prueba piloto:** **`Footer`** (`src/components/footer/Footer.jsx` y `src/components/footer/Footer.css`).  
*(Candidato alternativo de alto impacto funcional: `Sidebar.jsx`).*

**Justificación técnica de la elección:**
1. **Bajo riesgo y cero dependencias:** Es un componente 100% presentacional de solo 19 líneas de JSX y 41 líneas de CSS. No realiza peticiones asíncronas a endpoints ni depende del estado global de Zustand. Si se comete un error durante la migración, la funcionalidad crítica del sistema (simulacros, autenticación, calificaciones) permanece 100% intacta.
2. **Validación rápida del stack Tailwind:** Permite verificar inmediatamente la correcta compilación de utilidades esenciales:
   - Layout flexbox responsivo (`flex flex-col sm:flex-row justify-between items-center`).
   - Espaciado y relleno (`py-5 px-6 gap-3`).
   - Tipografía y colores (`text-xs sm:text-sm text-slate-400 bg-[#0a1128] border-t border-slate-800`).
   - Pseudoclases de hover (`hover:underline text-indigo-400`).
3. **Eliminación limpia de deuda técnica:** Permite borrar inmediatamente el archivo `Footer.css`, reduciendo de forma tangible el conteo de archivos CSS del repositorio.
4. **Camino comprobado:** Da continuidad al patrón ya iniciado en `DashboardWelcomeCard.jsx`, preparando al equipo para migrar sucesivamente componentes con mayor impacto visual como `Navbar`, `Header` y finalmente el `Sidebar`.

