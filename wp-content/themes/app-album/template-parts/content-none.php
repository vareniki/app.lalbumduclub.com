<?php
/**
 * Template part: sin contenido.
 *
 * @package AlbumCustom
 */
?>

<section class="no-results not-found">
    <header class="page-header">
        <h1 class="page-title"><?php esc_html_e( 'Nada encontrado', 'app-album' ); ?></h1>
    </header>

    <div class="entry-content">
        <?php if ( is_search() ) : ?>
            <p><?php esc_html_e( 'No hay resultados para tu búsqueda. Intenta con otros términos.', 'app-album' ); ?></p>
        <?php else : ?>
            <p><?php esc_html_e( 'Parece que no hay contenido aquí. Prueba con una búsqueda.', 'app-album' ); ?></p>
        <?php endif; ?>

        <?php get_search_form(); ?>
    </div>
</section>
