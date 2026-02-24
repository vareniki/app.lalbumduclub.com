# CLAUDE.md

Guía para Claude Code al trabajar en este proyecto.

## Proyecto

**L'Album du Club** — Aplicación web WordPress para la administración de un club deportivo. Idioma del proyecto: español.

- URL: https://app.lalbumduclub.com
- Autor: David Monje
- WordPress 6.9

## Stack

- **Backend:** WordPress (PHP 8.x), MySQL 8.0
- **Frontend del plugin:** Tailwind CSS 4 (compilado) + CSS propio
- **Plugins de soporte:** Advanced Custom Fields Pro (ACF Pro), Elementor (no tocar)

## Estructura del proyecto

El desarrollo activo se centra exclusivamente en el plugin `jugadores-club`. El resto de plugins y el tema no se tocan.

```
/ (raíz WordPress)
├── wp-config.php
└── wp-content/
    └── plugins/
        └── jugadores-club/          # ← DESARROLLO PRINCIPAL
            ├── jugadores-club.php   # Entrada del plugin: constantes, activación, init
            ├── includes/
            │   ├── class-jugadores-club.php   # Clase principal: shortcode, AJAX handlers
            │   └── class-uploadcare.php       # Integración Uploadcare
            ├── assets/
            │   ├── css/
            │   │   ├── tailwind.css           # Tailwind compilado (no editar directamente)
            │   │   └── jugadores-club.css     # CSS propio del plugin
            │   └── js/
            │       └── club-sortable.js       # JS: drag & drop, edición, AJAX
            ├── src/
            │   └── style.css                  # Fuente Tailwind → compila a assets/css/tailwind.css
            └── package.json                   # Scripts npm (Tailwind)
```

## Convenciones del plugin

- **Text domain:** `jugadores-club`
- **Prefijo acciones AJAX:** `album_`
- **Constantes:** `JC_VERSION`, `JC_DIR`, `JC_URI`
- **Clase principal:** `Jugadores_Club`
- **Shortcode:** `[jugadores-club id="X"]`

## Comandos de desarrollo

Ejecutar desde `wp-content/plugins/jugadores-club/`:

```bash
npm install                # Instalar dependencias
npm run dev                # Watch Tailwind (desarrollo)
npm run build              # Build Tailwind (producción, minificado)
```

Tailwind compila `src/style.css` → `assets/css/tailwind.css`.

## Base de datos

- **Nombre:** `app-album`
- **Prefijo tablas:** `wp_`
- **Motor:** InnoDB, charset `utf8mb4`
- **MySQL path local:** `/usr/local/mysql/bin/`

### Tablas WordPress estándar

`wp_posts`, `wp_postmeta`, `wp_users`, `wp_usermeta`, `wp_comments`, `wp_commentmeta`, `wp_terms`, `wp_termmeta`, `wp_term_taxonomy`, `wp_term_relationships`, `wp_options`, `wp_links`

### Tablas custom

#### `wp_club_jugadores`
Jugadores del club, organizados por club y categoría.

| Campo          | Tipo            | Notas                                |
|----------------|-----------------|--------------------------------------|
| `id`           | bigint (PK, AI) |                                      |
| `club_id`      | bigint          | ID del club                          |
| `category_uid` | varchar(32)     | Identificador de categoría           |
| `nombre`       | varchar(64)     | Nombre de la persona                 |
| `apellidos`    | varchar(64)     | Apellidos de la persona              |
| `cargo`        | varchar(32)     | Cargo de la persona                  |
| `nombre_foto`  | varchar(32)     | Nombre de archivo de foto (opcional) |
| `foto_url`     | varchar(256)    | URL de la foto (Uploadcare)          |
| `menu_order`   | int             | Orden de visualización               |
| `created_at`   | datetime        | Auto                                 |
| `updated_at`   | datetime        | Auto on update                       |

Índices: `club_category_index(club_id, category_uid)`, `order_index(menu_order)`

## Entorno local

- macOS (darwin, ARM)
- MySQL 8.0 en localhost
- WP_DEBUG actualmente en `false` (cambiar a `true` para desarrollo en `wp-config.php`)

## Versionado del plugin

El plugin usa versionado semántico definido en `jugadores-club.php`:
```php
define( 'JC_VERSION', 'X.X.X' );
```

**Regla obligatoria:** Cada vez que realices cambios en el código del plugin, debes incrementar automáticamente el número de revisión (el tercer dígito). Por ejemplo: `1.0.3` → `1.0.4` → `1.0.5`. No es necesario que me lo confirmes, simplemente hazlo como parte de cada modificación.
