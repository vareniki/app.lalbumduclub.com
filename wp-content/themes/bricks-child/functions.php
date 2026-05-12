<?php 
/**
 * Register/enqueue custom scripts and styles
 */
add_action( 'wp_enqueue_scripts', function() {
	// Enqueue your files on the canvas & frontend, not the builder panel. Otherwise custom CSS might affect builder)
	if ( ! bricks_is_builder_main() ) {
		wp_enqueue_style( 'bricks-child', get_stylesheet_uri(), ['bricks-frontend'], filemtime( get_stylesheet_directory() . '/style.css' ) );
	}
} );

/**
 * Register custom elements
 */
add_action( 'init', function() {
  $element_files = [
    __DIR__ . '/elements/title.php',
  ];

  foreach ( $element_files as $file ) {
    \Bricks\Elements::register_element( $file );
  }
}, 11 );

/**
 * Add text strings to builder
 */
add_filter( 'bricks/builder/i18n', function( $i18n ) {
  // For element category 'custom'
  $i18n['custom'] = esc_html__( 'Custom', 'bricks' );

  return $i18n;
} );

add_filter( 'bricks/code/echo_function_names', function() {
	return [
		'@^brx_' ];
} );

add_filter( 'show_admin_bar', function( bool $show ): bool {
	$user = wp_get_current_user();
	if ( in_array( 'club', $user->roles, true ) ) {
		return false;
	}
	return $show;
} );

function brx_is_archive() : int {
	if ( is_archive() ) {
		return 1;
	}

	return 0;
}

function brx_can_show_club_info() {
	$user    = wp_get_current_user();
	$post_id = get_queried_object_id();

	if ( in_array( 'administrator', $user->roles, true ) ) {
		return 1;
	}

	if ( in_array( 'supergestor', $user->roles, true ) ) {
		return 1;
	}

	if ( in_array( 'gestor', $user->roles, true ) ) {
		$clubs_asignados = jc_get_gestor_clubs( $user->ID );
		if ( $post_id && in_array( $post_id, $clubs_asignados, true ) ) {
			return 1;
		}
	}

	if ( in_array( 'club', $user->roles, true ) ) {
		$club_slug = get_field( 'club_slug', 'user_' . $user->ID );
		if ( $club_slug && str_contains( $_SERVER['REQUEST_URI'], '/' . $club_slug . '/' ) ) {
			return 1;
		}
	}

	return 0;
}

function brx_show_club_info() {

}

/**
 * Devuelve la URL de redirección tras el login según el rol del usuario.
 *
 * Uso en Bricks Builder: {echo:brx_after_login}
 *
 * - administrador → panel de administración de WordPress.
 * - gestor        → /clubs/ (URL absoluta).
 * - club          → /club/{club_slug}/ (URL absoluta).
 * - otros         → página de inicio.
 *
 * @return string URL absoluta de redirección.
 */
function brx_after_login(): string {
	$user = wp_get_current_user();

	if ( in_array( 'administrator', $user->roles, true ) ) {
		return admin_url();
	}

	if ( in_array( 'gestor', $user->roles, true ) ) {
		return home_url( '/clubs/' );
	}

	if ( in_array( 'club', $user->roles, true ) ) {
		$club_slug = get_field( 'club_slug', 'user_' . $user->ID );
		if ( $club_slug ) {
			return home_url( '/club/' . $club_slug . '/' );
		}
	}

	return home_url( '/' );
}