<?php
/**
 * Plantilla para página 404.
 *
 * @package AlbumCustom
 */

get_header(); ?>

<div class="container">
    <div class="content-area" style="text-align: center; padding: var(--spacing-xl) 0;">

        <header class="page-header">
            <h1 class="page-title"><?php esc_html_e( '404 - Página no encontrada', 'app-album' ); ?></h1>
        </header>

        <div class="entry-content">
            <p><?php esc_html_e( 'La página que buscas no existe o ha sido movida.', 'app-album' ); ?></p>
            <p style="margin-top: var(--spacing-md);">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="
                    display: inline-block;
                    padding: 0.75rem 2rem;
                    background: var(--color-primary);
                    color: white;
                    border-radius: 6px;
                    font-weight: 600;
                ">
                    <?php esc_html_e( 'Volver al inicio', 'app-album' ); ?>
                </a>
            </p>
        </div>

    </div>
</div>

<?php get_footer(); ?>
