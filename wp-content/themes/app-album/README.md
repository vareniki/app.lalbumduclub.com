# Mi Tema Custom - WordPress Theme

Plantilla WordPress personalizada desde cero, sin constructores visuales.

## Instalación

1. Descomprime `mi-tema-wp.zip`
2. Sube la carpeta `mi-tema-wp/` a `wp-content/themes/`
3. En el admin de WordPress: **Apariencia → Temas → Activar**

## Estructura de archivos

```
mi-tema-wp/
├── style.css                  ← Estilos + metadatos del tema
├── functions.php              ← Setup, menús, widgets, scripts
├── header.php                 ← Cabecera (logo, nav)
├── footer.php                 ← Pie de página (widgets, copyright)
├── index.php                  ← Plantilla principal / fallback
├── single.php                 ← Post individual
├── page.php                   ← Página estática
├── page-full-width.php        ← Template: ancho completo (sin sidebar)
├── archive.php                ← Archivos (categoría, etiqueta, fecha)
├── search.php                 ← Resultados de búsqueda
├── 404.php                    ← Página no encontrada
├── sidebar.php                ← Sidebar
├── comments.php               ← Comentarios
├── searchform.php             ← Formulario de búsqueda
├── template-parts/
│   ├── content.php            ← Card de post en listados
│   ├── content-search.php     ← Card en resultados de búsqueda
│   └── content-none.php       ← Sin resultados
└── assets/
    └── js/
        └── navigation.js      ← Menú hamburguesa móvil
```

## Qué incluye

- **2 menús**: Principal (header) y Footer
- **4 áreas de widgets**: Sidebar + 3 columnas en footer
- **Responsive**: Menú hamburguesa en móvil, grid adaptable
- **Template parts**: Componentes reutilizables
- **Custom logo**: Soporte nativo desde el Personalizador
- **Variables CSS**: Fácil personalización de colores y espaciado
- **Template de página**: "Página Ancho Completo" sin sidebar

## Personalización rápida

Edita las variables CSS en `style.css`:

```css
:root {
    --color-primary: #2563eb;      /* Color principal */
    --color-text: #1f2937;          /* Texto */
    --color-bg: #ffffff;            /* Fondo */
    --max-width: 1200px;            /* Ancho máximo del sitio */
    --max-width-content: 780px;     /* Ancho del contenido */
}
```

## Siguientes pasos sugeridos

- Añadir `screenshot.png` (1200×900px) para la miniatura del tema
- Crear `front-page.php` si necesitas un homepage personalizado
- Añadir Custom Post Types en functions.php
- Integrar ACF (Advanced Custom Fields) para campos personalizados
- Crear `inc/` para modularizar funciones (customizer, SEO, etc.)
