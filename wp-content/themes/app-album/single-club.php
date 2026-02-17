<?php
/**
 * Plantilla para entradas individuales (posts).
 *
 * @package AlbumCustom
 */

get_header(); ?>

<div class="container">
    <div class="content-area">

        <?php while ( have_posts() ) : the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class( 'post-entry' ); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                    <div class="entry-meta">
                        <?php album_posted_on(); ?>
                        <?php album_posted_by(); ?>
                        <?php album_entry_categories(); ?>
                    </div>
                </header>

                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="entry-thumbnail">
                        <?php the_post_thumbnail( 'album-featured' ); ?>
                    </div>
                <?php endif; ?>

                <div class="entry-content">
                    <?php
                    the_content();

                    echo do_shortcode('[jugadores-club]');

                    wp_link_pages( array(
                        'before' => '<div class="page-links">' . __( 'Páginas:', 'app-album' ),
                        'after'  => '</div>',
                    ) );
                    ?>
                </div>

                <footer class="entry-footer">
                    <?php
                    $tags = get_the_tag_list( '', ', ' );
                    if ( $tags ) {
                        printf( '<div class="entry-tags"><strong>%s</strong> %s</div>',
                            esc_html__( 'Etiquetas:', 'app-album' ),
                            $tags
                        );
                    }
                    ?>
                </footer>
            </article>

            <?php
            // Navegación entre posts
            the_post_navigation( array(
                'prev_text' => '<span class="nav-subtitle">' . esc_html__( 'Anterior:', 'app-album' ) . '</span> <span class="nav-title">%title</span>',
                'next_text' => '<span class="nav-subtitle">' . esc_html__( 'Siguiente:', 'app-album' ) . '</span> <span class="nav-title">%title</span>',
            ) );

            // Comentarios
            if ( comments_open() || get_comments_number() ) {
                comments_template();
            }
            ?>

        <?php endwhile; ?>

    </div>

    <?php get_sidebar(); ?>
</div>

<?php get_footer(); ?>
