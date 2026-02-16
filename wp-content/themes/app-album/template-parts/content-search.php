<?php
/**
 * Template part: resultado de búsqueda.
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
        </div>
    </header>

    <div class="entry-content">
        <?php the_excerpt(); ?>
    </div>
</article>
