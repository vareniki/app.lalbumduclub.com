# Endpoint: GET /jugadores-club/v1/clubs

## Descripción

Devuelve una lista paginada de clubs publicados con estadísticas de cobertura fotográfica.

**Base URL:** `https://app.lalbumduclub.com/wp-json`

**Endpoint:** `GET /jugadores-club/v1/clubs`

**Acceso:** Público (sin autenticación requerida).

---

## Parámetros de consulta

| Parámetro  | Tipo    | Requerido | Por defecto | Descripción                                      |
|------------|---------|-----------|-------------|--------------------------------------------------|
| `page`     | integer | No        | `1`         | Número de página. Valor mínimo: `1`.             |
| `per_page` | integer | No        | `10`        | Resultados por página. Mínimo: `1`, máximo: `100`. |

---

## Respuesta

### Códigos HTTP

| Código | Descripción                                                                 |
|--------|-----------------------------------------------------------------------------|
| `200`  | Éxito.                                                                      |
| `400`  | El número de página solicitado supera el total de páginas disponibles.      |

### Cabeceras de paginación

| Cabecera          | Tipo | Descripción                         |
|-------------------|------|-------------------------------------|
| `X-WP-Total`      | int  | Total de clubs publicados.          |
| `X-WP-TotalPages` | int  | Total de páginas según `per_page`.  |

### Estructura del body

Array de objetos club. Si no hay clubs publicados, devuelve un array vacío `[]`.

```json
[
  {
    "club_id": 42,
    "nombre": "Club Ejemplo",
    "extracto": "Breve descripción del club.",
    "url": "https://app.lalbumduclub.com/club/club-ejemplo/",
    "imagen_destacada": "https://app.lalbumduclub.com/wp-content/uploads/foto.jpg",
    "total_miembros": 25,
    "total_fotos": 20,
    "total_fotos_vacias": 5,
    "porcentaje_fotos": 80.0
  }
]
```

### Campos de la respuesta

| Campo                | Tipo   | Descripción                                                                                |
|----------------------|--------|--------------------------------------------------------------------------------------------|
| `club_id`            | int    | ID del post WordPress de tipo `club`.                                                      |
| `nombre`             | string | Título del post del club (`post_title`).                                                   |
| `extracto`           | string | Extracto del post (`post_excerpt`). Cadena vacía si no tiene extracto.                     |
| `url`                | string | Permalink del post del club.                                                               |
| `imagen_destacada`   | string | URL de la imagen destacada del post. Cadena vacía si no tiene imagen destacada.            |
| `total_miembros`     | int    | Número total de jugadores registrados en el club (todas sus categorías).                   |
| `total_fotos`        | int    | Número de jugadores que tienen `nombre_foto` relleno.                                      |
| `total_fotos_vacias` | int    | Jugadores sin foto (`total_miembros − total_fotos`).                                       |
| `porcentaje_fotos`   | float  | Porcentaje de cobertura fotográfica (`total_fotos / total_miembros × 100`, 1 decimal). `0` si no hay miembros. |

---

## Respuesta de error (400)

Cuando `page` supera el total de páginas disponibles:

```json
{
  "code": "rest_invalid_page_number",
  "message": "El número de página solicitado supera el total de páginas disponibles.",
  "data": {
    "status": 400
  }
}
```

---

## Ejemplos

### Primera página con 10 resultados (por defecto)

```
GET /wp-json/jugadores-club/v1/clubs
```

### Segunda página con 5 resultados por página

```
GET /wp-json/jugadores-club/v1/clubs?page=2&per_page=5
```

### cURL

```bash
# Primera página
curl https://app.lalbumduclub.com/wp-json/jugadores-club/v1/clubs

# Con paginación y cabeceras de respuesta
curl -i "https://app.lalbumduclub.com/wp-json/jugadores-club/v1/clubs?page=2&per_page=5"
```

### JavaScript (fetch)

```js
const res = await fetch('/wp-json/jugadores-club/v1/clubs?page=1&per_page=10');
const total      = res.headers.get('X-WP-Total');
const totalPages = res.headers.get('X-WP-TotalPages');
const clubs      = await res.json();
```

---

## Notas de implementación

- Se incluyen todos los clubs con `post_status = 'publish'`, tengan o no miembros registrados (LEFT JOIN).
- Los clubs se ordenan por `post_title ASC`.
- `porcentaje_fotos` se redondea a 1 decimal.
- Las estadísticas (`total_miembros`, `total_fotos`, `total_fotos_vacias`) se calculan en una única query SQL mediante `COUNT` y `SUM(CASE ...)`.
- Controlador: `Clubs_Controller::get_items()` — `includes/class-clubs-controller.php`
- Repositorio: `Clubs_Repository::get_clubs()` — `includes/class-clubs-repository.php`