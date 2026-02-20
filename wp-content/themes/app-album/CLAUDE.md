# CLAUDE.md

Guía para Claude Code al trabajar en el tema app-album.

## Tema

**app-album (AlbumAdmin)** — Tema WordPress custom para la administración de L'Album du Club. Codificado a mano, sin page builders. Idioma del proyecto: español.

- URL: https://app.lalbumduclub.com
- Autor: David Monje
- Versión: 1.0.0
- Licencia: GPL v2+
- Repo git propio (remote: `admin-alfainmo3`)

## Stack

- **PHP 8.x** sobre WordPress 6.9
- **CSS puro** (custom properties) + **Tailwind CSS 4** (utilities)
- **ACF Pro** (Advanced Custom Fields Pro) como plugin
- **Fuente:** Inter (Google Fonts, pesos 400–800)
- **MySQL 8.0** (base de datos `app-album`, prefijo `wp_`)

## Estructura del tema

```
app-album/
├── style.css              # CSS base: reset, custom properties, layout, componentes
├── functions.php          # Setup, scripts, widgets, helpers, login custom
├── src/
│   └── style.css          # Fuente Tailwind (editar aquí)
├── assets/
│   ├── css/style.css      # Tailwind compilado (NO editar, se genera con npm)
│   ├── js/navigation.js   # Script de navegación
│   └── img/               # Imágenes del tema
├── template-parts/
│   ├── content.php        # Contenido en listados
│   ├── content-search.php # Contenido en búsqueda
│   └── content-none.php   # Sin resultados
├── header.php             # Cabecera
├── footer.php             # Pie
├── sidebar.php            # Barra lateral
├── index.php              # Listado principal
├── single.php             # Post individual
├── page.php               # Página estándar
├── page-full-width.php    # Página sin sidebar (template)
├── archive.php            # Archivo
├── search.php             # Resultados de búsqueda
├── searchform.php         # Formulario de búsqueda
├── 404.php                # Página no encontrada
├── comments.php           # Comentarios
└── package.json           # Scripts npm (Tailwind)
```

## Convenciones

- **Text domain:** `app-album`
- **Prefijo funciones PHP:** `album_`
- **Constantes:** `ALBUM_VERSION`, `ALBUM_DIR`, `ALBUM_URI`
- **Tamaños de imagen:** `album-featured` (1200x630), `album-thumbnail` (600x400)
- **Menús registrados:** `primary` (header), `footer`
- **Widget areas:** `sidebar-1`, `footer-1`, `footer-2`, `footer-3`

### Helpers disponibles en functions.php

| Función                  | Uso                                    |
|--------------------------|----------------------------------------|
| `album_posted_on()`      | Muestra fecha de publicación           |
| `album_posted_by()`      | Muestra autor con enlace               |
| `album_entry_categories()` | Muestra categorías del post          |
| `album_pagination()`     | Paginación numérica                    |

## Comandos de desarrollo

Ejecutar desde este directorio (`wp-content/themes/app-album/`):

```bash
npm install                # Instalar dependencias
npm run dev                # Watch Tailwind (desarrollo)
npm run build              # Build Tailwind (producción, minificado)
```

Tailwind compila `src/style.css` → `assets/css/style.css`.

## CSS: estrategia dual

1. **`style.css`** (raíz del tema) — CSS tradicional con custom properties, reset, layout, tipografía, componentes completos
2. **`assets/css/style.css`** — Tailwind 4 utilities (compilado desde `src/style.css`)

Ambas hojas se enqueue en `functions.php` via `album_scripts()`.

### Design tokens (en `style.css` `:root`)

```css
:root {
    --color-primary: #2563eb;
    --color-primary-dark: #1d4ed8;
    --color-text: #1f2937;
    --color-text-light: #6b7280;
    --color-bg: #ffffff;
    --max-width: 1200px;
    --max-width-content: 780px;
}
```

### Tailwind custom

Componentes Tailwind van en `src/style.css` bajo `@layer components`:

```css
@import "tailwindcss";

@layer components {
    .btn-primary {
        @apply bg-blue-500 text-white px-4 py-2 rounded;
    }
}
```

## Login personalizado

El tema sobreescribe la pantalla de login de WordPress (`functions.php`):
- Fondo gradiente azul (`#2563eb` → `#1d4ed8`)
- Formulario en tarjeta blanca con bordes redondeados
- Usa los design tokens del tema e Inter como fuente
- Logo custom via `custom_logo` de WordPress

## Base de datos

### Tablas WordPress estándar

`wp_posts`, `wp_postmeta`, `wp_users`, `wp_usermeta`, `wp_comments`, `wp_commentmeta`, `wp_terms`, `wp_termmeta`, `wp_term_taxonomy`, `wp_term_relationships`, `wp_options`, `wp_links`

### Tabla custom: `wp_club_jugadores`

Jugadores del club, organizados por club y categoría.

| Campo          | Tipo            | Notas                      |
|----------------|-----------------|----------------------------|
| `id`           | bigint (PK, AI) |                            |
| `club_id`      | bigint          | ID del club                |
| `category_uid` | varchar(32)     | Identificador de categoría |
| `nombre`       | varchar(64)     | Nombre de la persona       |
| `apellidos`    | varchar(64)     | Apellido de la persona     |
| `cargo`        | varchar(32)     | Cargo de la persona        |
| `foto_url`     | varchar(256)    | URL de la foto             |
| `menu_order`   | int             | Orden de visualización     |
| `created_at`   | datetime        | Auto                       |
| `updated_at`   | datetime        | Auto on update             |

Índices: `club_category_index(club_id, category_uid)`, `order_index(menu_order)`

## Entorno local

- macOS (ARM)
- MySQL 8.0 en localhost (path: `/usr/local/mysql/bin/`)
- WP_DEBUG en `false` (cambiar a `true` en `wp-config.php` para desarrollo)