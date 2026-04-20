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

### Tablas personalizadas

#### `wp_club_categorias`
Categorías de un club (equivale a lo que antes era ACF `categoria`). Cada fila pertenece a un post (club) mediante `post_id`.

| Campo         | Tipo         | Notas                           |
|---------------|--------------|---------------------------------|
| `id`          | int (PK, AI) |                                 |
| `post_id`     | int          | ID del post del club (wp_posts) |
| `descripcion` | varchar(64)  | Nombre de la categoría          |

#### `wp_club_jugadores`
Jugadores del club, vinculados a una categoría mediante FK.

| Campo          | Tipo         | Notas                                   |
|----------------|--------------|-----------------------------------------|
| `id`           | int (PK, AI) |                                         |
| `categoria_id` | int          | FK → `wp_club_categorias.id` (nullable) |
| `nombre`       | varchar(64)  | NOT NULL                                |
| `apellidos`    | varchar(64)  | NOT NULL                                |
| `nombre_foto`  | varchar(64)  | NOT NULL                                |
| `cargo`        | varchar(32)  | nullable                                |
| `foto_url`     | varchar(256) | URL Uploadcare, default ''              |
| `menu_order`   | int          | Orden de visualización, default 0       |
| `created_at`   | datetime     | Auto (CURRENT_TIMESTAMP)                |
| `updated_at`   | datetime     | Auto on update (CURRENT_TIMESTAMP)      |

Índices: `order_index(menu_order)`. FK: `wp_club_jugadores_wp_club_categorias_id_fk(categoria_id)`. Collation: `utf8mb4_unicode_ci`.

#### `wp_club_equipo`
Fotos de grupo por categoría. Cada fila representa una foto de equipo vinculada a una categoría.

| Campo          | Tipo           | Notas                                   |
|----------------|----------------|-----------------------------------------|
| `id`           | int (PK, AI)   |                                         |
| `categoria_id` | int            | FK → `wp_club_categorias.id` (nullable) |
| `descripcion`  | varchar(64)    | Descripción de la foto (NOT NULL)       |
| `nombre_foto`  | varchar(64)    | Nombre de archivo de foto               |
| `foto_url`     | varchar(256)   | URL de la foto (Uploadcare), default '' |
| `menu_order`   | int            | Orden de visualización, default 0       |
| `created_at`   | datetime       | Auto                                    |
| `updated_at`   | datetime       | Auto on update                          |


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
