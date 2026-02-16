<?php
/**
 * Plantilla de comentarios.
 *
 * @package AlbumCustom
 */

if ( post_password_required() ) {
    return;
}
?>

<div id="comments" class="comments-area">

    <?php if ( have_comments() ) : ?>
        <h2 class="comments-title">
            <?php
            $count = get_comments_number();
            printf(
                esc_html( _n( '%d comentario', '%d comentarios', $count, 'app-album' ) ),
                $count
            );
            ?>
        </h2>

        <ol class="comment-list">
            <?php
            wp_list_comments( array(
                'style'      => 'ol',
                'short_ping' => true,
                'avatar_size' => 48,
            ) );
            ?>
        </ol>

        <?php
        the_comments_navigation();
    endif;

    if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
        ?>
        <p class="no-comments"><?php esc_html_e( 'Los comentarios están cerrados.', 'app-album' ); ?></p>
    <?php endif; ?>

    <?php comment_form(); ?>

</div>
