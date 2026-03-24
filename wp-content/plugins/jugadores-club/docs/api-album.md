# Endpoint: GET /jugadores-club/v1/album

## Descripción

Devuelve la estructura completa del álbum: todos los clubs publicados con sus categorías, jugadores y fotos de equipo anidados.

**Base URL:** `https://app.lalbumduclub.com/wp-json`

**Endpoint:** `GET /jugadores-club/v1/album`

**Acceso:** Público (sin autenticación requerida).

---

## Parámetros de consulta

| Parámetro | Tipo    | Requerido | Descripción                                                            |
|-----------|---------|-----------|------------------------------------------------------------------------|
| `club_id` | integer | No        | ID del club (post de WordPress). Si se omite, devuelve todos los clubs. Valor mínimo: `1`. |

---

## Respuesta

### Código HTTP

| Código | Descripción              |
|--------|--------------------------|
| `200`  | Éxito                    |

### Estructura del body

Array de objetos club. Si no hay clubs publicados (o no existe el `club_id` indicado), devuelve un array vacío `[]`.

```json
[
  {
    "club_id": 42,
    "nombre": "Club Ejemplo",
    "categorias": [
      {
        "id": 10,
        "descripcion": "Primer Equipo",
        "menu_order": 0,
        "equipo": [
          {
            "id": 3,
            "descripcion": "Foto de grupo 2024",
            "nombre_foto": "foto-grupo.jpg",
            "foto_url": "https://ucarecdn.com/uuid/",
            "menu_order": 0
          }
        ],
        "jugadores": [
          {
            "id": 101,
            "nombre": "Carlos",
            "apellidos": "García López",
            "cargo": "Entrenador",
            "nombre_foto": "carlos-garcia.jpg",
            "foto_url": "https://ucarecdn.com/uuid/",
            "menu_order": 0
          },
          {
            "id": 102,
            "nombre": "María",
            "apellidos": "Fernández",
            "cargo": null,
            "nombre_foto": "",
            "foto_url": "",
            "menu_order": 1
          }
        ]
      }
    ]
  }
]
```

### Campos de la respuesta

#### Club

| Campo        | Tipo   | Descripción                                      |
|--------------|--------|--------------------------------------------------|
| `club_id`    | int    | ID del post WordPress de tipo `club`.            |
| `nombre`     | string | Título del post del club (`post_title`).         |
| `categorias` | array  | Lista de categorías del club, ordenadas por `menu_order` ASC. |

#### Categoría

| Campo        | Tipo   | Descripción                                                  |
|--------------|--------|--------------------------------------------------------------|
| `id`         | int    | ID de la categoría (`wp_club_categorias.id`).                |
| `descripcion`| string | Nombre de la categoría.                                      |
| `menu_order` | int    | Orden de visualización.                                      |
| `equipo`     | array  | Fotos de grupo vinculadas a esta categoría, ordenadas por `menu_order` ASC. |
| `jugadores`  | array  | Jugadores vinculados a esta categoría, ordenados por `menu_order` ASC. |

#### Foto de equipo (`equipo[]`)

| Campo        | Tipo   | Descripción                                             |
|--------------|--------|---------------------------------------------------------|
| `id`         | int    | ID de la foto (`wp_club_equipo.id`).                    |
| `descripcion`| string | Texto descriptivo de la foto.                           |
| `nombre_foto`| string | Nombre del archivo de la foto. Puede estar vacío.       |
| `foto_url`   | string | URL de la foto en Uploadcare. Cadena vacía si no hay foto. |
| `menu_order` | int    | Orden de visualización.                                 |

#### Jugador (`jugadores[]`)

| Campo        | Tipo        | Descripción                                             |
|--------------|-------------|---------------------------------------------------------|
| `id`         | int         | ID del jugador (`wp_club_jugadores.id`).                |
| `nombre`     | string      | Nombre del jugador.                                     |
| `apellidos`  | string      | Apellidos del jugador.                                  |
| `cargo`      | string\|null | Cargo o rol del jugador. `null` si no tiene cargo.     |
| `nombre_foto`| string      | Nombre del archivo de foto. Cadena vacía si no hay foto.|
| `foto_url`   | string      | URL de la foto en Uploadcare. Cadena vacía si no hay foto. |
| `menu_order` | int         | Orden de visualización.                                 |

---

## Ejemplos

### Obtener todos los clubs

```
GET /wp-json/jugadores-club/v1/album
```

### Obtener un club específico

```
GET /wp-json/jugadores-club/v1/album?club_id=42
```

### cURL

```bash
# Todos los clubs
curl https://app.lalbumduclub.com/wp-json/jugadores-club/v1/album

# Un club específico
curl "https://app.lalbumduclub.com/wp-json/jugadores-club/v1/album?club_id=42"
```

### JavaScript (fetch)

```js
// Todos los clubs
const res = await fetch('/wp-json/jugadores-club/v1/album');
const album = await res.json();

// Un club específico
const res = await fetch('/wp-json/jugadores-club/v1/album?club_id=42');
const club = await res.json();
```

---

## Notas de implementación

- La respuesta se construye con **4 queries planas** (clubs → categorías → fotos de equipo → jugadores) que se ensamblan en PHP, evitando el problema N+1.
- Los clubs se ordenan por `post_title ASC`.
- Las categorías se ordenan por `menu_order ASC` dentro de cada club.
- Los jugadores y fotos de equipo se ordenan por `menu_order ASC` dentro de cada categoría.
- Solo se incluyen clubs con `post_status = 'publish'`.
- Controlador: `Clubs_Controller::get_album()` — `includes/class-clubs-controller.php`
- Repositorio: `Clubs_Repository::get_album()` — `includes/class-clubs-repository.php`