<?php
/**
 * Album Custom - functions.php
 *
 * Configuración principal del tema.
 *
 * @package AlbumCustom
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'ALBUM_VERSION', '1.0.3' );
define( 'ALBUM_DIR', get_template_directory() );
define( 'ALBUM_URI', get_template_directory_uri() );

/**
 * ========================================
 * SETUP DEL TEMA
 * ========================================
 */
function album_setup() {
    // Soporte para título dinámico en <title>
    add_theme_support( 'title-tag' );

    // Imágenes destacadas
    add_theme_support( 'post-thumbnails' );

    // Tamaños de imagen personalizados
    add_image_size( 'album-featured', 1200, 630, true );
    add_image_size( 'album-thumbnail', 600, 400, true );

    // Logo personalizado
    add_theme_support( 'custom-logo', array(
        'width'       => 250,
        'height'      => 80,
        'flex-width'  => true,
        'flex-height' => true,
    ) );

    // HTML5
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ) );

    // Alineaciones anchas para el editor de bloques
    add_theme_support( 'align-wide' );

    // Estilos del editor
    add_theme_support( 'editor-styles' );

    // RSS automático
    add_theme_support( 'automatic-feed-links' );

    // Registrar menús de navegación
    register_nav_menus( array(
        'primary' => __( 'Menú Principal', 'app-album' ),
        'footer'  => __( 'Menú Footer', 'app-album' ),
    ) );

    // Traducciones
    load_theme_textdomain( 'app-album', ALBUM_DIR . '/languages' );
}
add_action( 'after_setup_theme', 'album_setup' );

/**
 * ========================================
 * SCRIPTS Y ESTILOS
 * ========================================
 */
function album_scripts() {
    // Estilos principales
    wp_enqueue_style(
        'album-style',
        get_stylesheet_uri(),
        array(),
        ALBUM_VERSION
    );

    // Google Fonts (Inter)
    wp_enqueue_style(
        'album-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap',
        array(),
        null
    );

    // Script de navegación
    wp_enqueue_script(
        'album-navigation',
        ALBUM_URI . '/assets/js/navigation.js',
        array(),
        ALBUM_VERSION,
        true
    );

    wp_enqueue_style(
        'app-album-tailwind',
        get_template_directory_uri() . '/assets/css/style.css',
        array(),
        filemtime(get_template_directory() . '/assets/css/style.css')
    );

    // Comentarios hilados
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }

    // Scripts para single-club: SortableJS + drag & drop.
    if ( is_singular( 'club' ) ) {
        wp_enqueue_script(
            'sortablejs',
            'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
            array(),
            '1.15.6',
            true
        );

        wp_enqueue_script(
            'album-club-sortable',
            ALBUM_URI . '/assets/js/club-sortable.js',
            array( 'sortablejs' ),
            ALBUM_VERSION,
            true
        );

        wp_localize_script( 'album-club-sortable', 'albumClub', array(
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'album_club_nonce' ),
            'ucPubKey'  => defined( 'UPLOADCARE_PUBLIC_KEY' ) ? UPLOADCARE_PUBLIC_KEY : '',
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'album_scripts' );

/**
 * ========================================
 * WIDGETS
 * ========================================
 */
function album_widgets_init() {
    register_sidebar( array(
        'name'          => __( 'Sidebar', 'app-album' ),
        'id'            => 'sidebar-1',
        'description'   => __( 'Barra lateral principal.', 'app-album' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer 1', 'app-album' ),
        'id'            => 'footer-1',
        'description'   => __( 'Primera columna del footer.', 'app-album' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer 2', 'app-album' ),
        'id'            => 'footer-2',
        'description'   => __( 'Segunda columna del footer.', 'app-album' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    register_sidebar( array(
        'name'          => __( 'Footer 3', 'app-album' ),
        'id'            => 'footer-3',
        'description'   => __( 'Tercera columna del footer.', 'app-album' ),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'album_widgets_init' );

/**
 * ========================================
 * FUNCIONES HELPER
 * ========================================
 */

/**
 * Muestra la fecha de publicación formateada.
 */
function album_posted_on() {
    $time_string = '<time class="entry-date" datetime="%1$s">%2$s</time>';

    $time_string = sprintf(
        $time_string,
        esc_attr( get_the_date( DATE_W3C ) ),
        esc_html( get_the_date() )
    );

    printf(
        '<span class="posted-on">%s</span>',
        $time_string
    );
}

/**
 * Muestra el autor del post.
 */
function album_posted_by() {
    printf(
        '<span class="byline"> · %s</span>',
        sprintf(
            '<a href="%s">%s</a>',
            esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
            esc_html( get_the_author() )
        )
    );
}

/**
 * Muestra las categorías del post.
 */
function album_entry_categories() {
    if ( 'post' === get_post_type() ) {
        $categories = get_the_category_list( ', ' );
        if ( $categories ) {
            printf( '<span class="cat-links"> · %s</span>', $categories );
        }
    }
}

/**
 * Paginación numérica personalizada.
 */
function album_pagination() {
    the_posts_pagination( array(
        'mid_size'  => 2,
        'prev_text' => '&laquo;',
        'next_text' => '&raquo;',
    ) );
}

/**
 * Extracto personalizado.
 */
function album_custom_excerpt_length( $length ) {
    return 30;
}
add_filter( 'excerpt_length', 'album_custom_excerpt_length' );

function album_custom_excerpt_more( $more ) {
    return '&hellip;';
}
add_filter( 'excerpt_more', 'album_custom_excerpt_more' );

/**
 * Body classes adicionales.
 */
function album_body_classes( $classes ) {
    if ( is_active_sidebar( 'sidebar-1' ) && ! is_page_template( 'page-full-width.php' ) ) {
        $classes[] = 'has-sidebar';
    }

    if ( is_singular() ) {
        $classes[] = 'is-singular';
    }

    return $classes;
}
add_filter( 'body_class', 'album_body_classes' );

/**
 * ========================================
 * PANTALLA DE LOGIN PERSONALIZADA
 * ========================================
 */

/**
 * Encolar estilos en la pantalla de login.
 */
function album_login_enqueue_scripts() {
    wp_enqueue_style(
        'album-google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
        array(),
        null
    );

    wp_enqueue_style(
        'album-tailwind',
        get_template_directory_uri() . '/assets/css/style.css',
        array(),
        filemtime( get_template_directory() . '/assets/css/style.css' )
    );
}
add_action( 'login_enqueue_scripts', 'album_login_enqueue_scripts' );

/**
 * Inyectar CSS inline para restylear el login.
 */
function album_login_head() {
    $custom_logo_url = '';
    $custom_logo_id  = get_theme_mod( 'custom_logo' );
    if ( $custom_logo_id ) {
        $image = wp_get_attachment_image_src( $custom_logo_id, 'medium' );
        if ( $image ) {
            $custom_logo_url = $image[0];
        }
    }
    ?>
    <style>
        /* === Fondo gradiente === */
        body.login {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* === Contenedor login como tarjeta === */
        #login {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            padding: 40px 36px 32px;
            width: 400px;
            max-width: 90vw;
        }

        /* === Logo === */
        #login h1 a {
            <?php if ( $custom_logo_url ) : ?>
            background-image: url('<?php echo esc_url( $custom_logo_url ); ?>');
            <?php endif; ?>
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
            width: 100%;
            height: 70px;
            margin-bottom: 16px;
        }

        /* === Formulario === */
        .login form {
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .login form .input,
        .login form input[type="text"],
        .login form input[type="password"] {
            border: 1px solid #d1d5db !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            font-size: 15px !important;
            font-family: 'Inter', sans-serif !important;
            background: #f9fafb !important;
            box-shadow: none !important;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .login form .input:focus,
        .login form input[type="text"]:focus,
        .login form input[type="password"]:focus {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important;
            outline: none !important;
            background: #ffffff !important;
        }

        .login label {
            font-size: 14px !important;
            font-weight: 500 !important;
            color: #374151 !important;
        }

        /* === Botón submit === */
        .wp-core-ui .button-primary {
            background: #2563eb !important;
            border: none !important;
            border-radius: 8px !important;
            padding: 10px 20px !important;
            font-size: 15px !important;
            font-weight: 600 !important;
            font-family: 'Inter', sans-serif !important;
            width: 100%;
            text-shadow: none !important;
            box-shadow: none !important;
            transition: background 0.2s;
            height: auto !important;
            line-height: 1.5 !important;
        }

        .wp-core-ui .button-primary:hover,
        .wp-core-ui .button-primary:focus {
            background: #1d4ed8 !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.3) !important;
        }

        /* === Checkbox "Recuérdame" === */
        .login .forgetmenot {
            margin-top: 8px;
        }

        .login .forgetmenot label {
            font-size: 13px !important;
            color: #6b7280 !important;
        }

        /* === Mensajes de error/éxito === */
        #login_error,
        .login .message,
        .login .success {
            border-radius: 8px !important;
            margin-bottom: 16px !important;
            padding: 12px 16px !important;
            font-size: 14px !important;
            box-shadow: none !important;
        }

        #login_error {
            border-left: 4px solid #dc2626 !important;
            background: #fef2f2 !important;
            color: #991b1b !important;
        }

        .login .message {
            border-left: 4px solid #2563eb !important;
            background: #eff6ff !important;
            color: #1e40af !important;
        }

        .login .success {
            border-left: 4px solid #16a34a !important;
            background: #f0fdf4 !important;
            color: #166534 !important;
        }

        /* === Links debajo del formulario === */
        #login #nav,
        #login #backtoblog {
            text-align: center;
            padding: 0;
        }

        #login #nav a,
        #login #backtoblog a {
            color: rgba(255, 255, 255, 0.85) !important;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s;
        }

        #login #nav a:hover,
        #login #backtoblog a:hover {
            color: #ffffff !important;
            text-decoration: underline;
        }

        /* === Mover links fuera de la tarjeta visualmente === */
        #login #nav,
        #login #backtoblog {
            margin-top: 20px;
            background: transparent;
        }

        /* === Ocultar el enlace "Powered by WordPress" === */
        .login .privacy-policy-page-link {
            display: none;
        }

        /* === Responsivo === */
        @media (max-width: 480px) {
            #login {
                padding: 28px 20px 24px;
            }
        }
    </style>
    <?php
}
add_action( 'login_head', 'album_login_head' );

/**
 * Logo del login enlaza al sitio.
 */
function album_login_headerurl() {
    return home_url();
}
add_filter( 'login_headerurl', 'album_login_headerurl' );

/**
 * Texto del logo en el login.
 */
function album_login_headertext() {
    return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'album_login_headertext' );

/**
 * ========================================
 * AJAX: REORDENAR JUGADORES DEL CLUB
 * ========================================
 */

/**
 * Actualiza category_uid y menu_order de los jugadores tras drag & drop.
 */
function album_reorder_jugadores() {
    check_ajax_referer( 'album_club_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Sin permisos.' );
    }

    $club_id    = absint( $_POST['club_id'] ?? 0 );
    $categories = json_decode( stripslashes( $_POST['categories'] ?? '' ), true );

    if ( ! $club_id || ! is_array( $categories ) ) {
        wp_send_json_error( 'Datos inválidos.' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'club_jugadores';

    foreach ( $categories as $cat ) {
        $category_uid = sanitize_text_field( $cat['category_uid'] ?? '' );
        $jugador_ids  = $cat['jugador_ids'] ?? array();

        if ( ! $category_uid || ! is_array( $jugador_ids ) ) {
            continue;
        }

        foreach ( $jugador_ids as $order => $id ) {
            $wpdb->update(
                $table,
                array(
                    'category_uid' => $category_uid,
                    'menu_order'   => $order,
                ),
                array(
                    'id'      => absint( $id ),
                    'club_id' => $club_id,
                ),
                array( '%s', '%d' ),
                array( '%d', '%d' )
            );
        }
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_album_reorder_jugadores', 'album_reorder_jugadores' );

/**
 * Elimina un jugador del club.
 */
function album_delete_jugador() {
    check_ajax_referer( 'album_club_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Sin permisos.' );
    }

    $club_id    = absint( $_POST['club_id'] ?? 0 );
    $jugador_id = absint( $_POST['jugador_id'] ?? 0 );

    if ( ! $club_id || ! $jugador_id ) {
        wp_send_json_error( 'Datos inválidos.' );
    }

    global $wpdb;
    $deleted = $wpdb->delete(
        $wpdb->prefix . 'club_jugadores',
        array( 'id' => $jugador_id, 'club_id' => $club_id ),
        array( '%d', '%d' )
    );

    if ( false === $deleted ) {
        wp_send_json_error( 'Error al eliminar.' );
    }

    wp_send_json_success();
}
add_action( 'wp_ajax_album_delete_jugador', 'album_delete_jugador' );

/**
 * Añade jugadores en bulk a una categoría del club.
 */
function album_bulk_add_jugadores() {
    check_ajax_referer( 'album_club_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Sin permisos.' );
    }

    $club_id      = absint( $_POST['club_id'] ?? 0 );
    $category_uid = sanitize_text_field( $_POST['category_uid'] ?? '' );
    $nombres      = json_decode( stripslashes( $_POST['nombres'] ?? '' ), true );

    if ( ! $club_id || ! $category_uid || ! is_array( $nombres ) || empty( $nombres ) ) {
        wp_send_json_error( 'Datos inválidos.' );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'club_jugadores';

    // Obtener el mayor menu_order actual de esta categoría.
    $max_order = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(MAX(menu_order), -1) FROM {$table} WHERE club_id = %d AND category_uid = %s",
        $club_id,
        $category_uid
    ) );

    $inserted = array();

    foreach ( $nombres as $nombre ) {
        $nombre = sanitize_text_field( trim( $nombre ) );
        if ( '' === $nombre ) {
            continue;
        }

        $max_order++;

        $wpdb->insert( $table, array(
            'club_id'      => $club_id,
            'category_uid' => $category_uid,
            'nombre'       => $nombre,
            'foto_url'     => '',
            'menu_order'   => $max_order,
        ), array( '%d', '%s', '%s', '%s', '%d' ) );

        $inserted[] = array(
            'id'       => $wpdb->insert_id,
            'nombre'   => $nombre,
            'foto_url' => '',
        );
    }

    wp_send_json_success( $inserted );
}
add_action( 'wp_ajax_album_bulk_add_jugadores', 'album_bulk_add_jugadores' );

/**
 * Actualiza la foto de un jugador (URL de UploadCare).
 */
function album_update_jugador_foto() {
    check_ajax_referer( 'album_club_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Sin permisos.' );
    }

    $club_id    = absint( $_POST['club_id'] ?? 0 );
    $jugador_id = absint( $_POST['jugador_id'] ?? 0 );
    $foto_url   = esc_url_raw( $_POST['foto_url'] ?? '' );

    if ( ! $club_id || ! $jugador_id || ! $foto_url ) {
        wp_send_json_error( 'Datos inválidos.' );
    }

    global $wpdb;
    $updated = $wpdb->update(
        $wpdb->prefix . 'club_jugadores',
        array( 'foto_url' => $foto_url ),
        array( 'id' => $jugador_id, 'club_id' => $club_id ),
        array( '%s' ),
        array( '%d', '%d' )
    );

    if ( false === $updated ) {
        wp_send_json_error( 'Error al actualizar.' );
    }

    wp_send_json_success( array( 'foto_url' => $foto_url ) );
}
add_action( 'wp_ajax_album_update_jugador_foto', 'album_update_jugador_foto' );
