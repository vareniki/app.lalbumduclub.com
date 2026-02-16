<?php
/**
 * Template part: contenido de post en listados.
 *
 * @package AlbumCustom
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-entry' ); ?>>
    <header class="entry-header">
        <h2 class="entry-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h2>
        <div class="entry-meta">
            <?php album_posted_on(); ?>
            <?php album_posted_by(); ?>
            <?php album_entry_categories(); ?>
        </div>
    </header>

    <?php if ( has_post_thumbnail() ) : ?>
        <div class="entry-thumbnail">
            <a href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail( 'album-thumbnail' ); ?>
            </a>
        </div>
    <?php endif; ?>

    <div class="entry-content">
        <?php the_excerpt(); ?>
    </div>

    <a href="<?php the_permalink(); ?>" class="read-more">
        <?php esc_html_e( 'Leer más →', 'app-album' ); ?>
    </a>
</article>
