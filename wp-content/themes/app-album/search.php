<?php
/**
 * Plantilla de resultados de búsqueda.
 *
 * @package AlbumCustom
 */

get_header(); ?>

<div class="container">
    <div class="content-area">

        <header class="page-header">
            <h1 class="page-title">
                <?php
                printf(
                    esc_html__( 'Resultados para: %s', 'app-album' ),
                    '<span>' . get_search_query() . '</span>'
                );
                ?>
            </h1>
        </header>

        <?php if ( have_posts() ) : ?>

            <?php while ( have_posts() ) : the_post(); ?>
                <?php get_template_part( 'template-parts/content', 'search' ); ?>
            <?php endwhile; ?>

            <?php album_pagination(); ?>

        <?php else : ?>
            <div class="no-results">
                <p><?php esc_html_e( 'No se encontraron resultados. Intenta con otros términos de búsqueda.', 'app-album' ); ?></p>
                <?php get_search_form(); ?>
            </div>
        <?php endif; ?>

    </div>

    <?php get_sidebar(); ?>
</div>

<?php get_footer(); ?>
