<?php
/**
 * Plugin Name: Jugadores Club
 * Description: Gestión de jugadores por categorías con drag & drop, upload de fotos y shortcode [jugadores-club].
 * Version:     1.0.76
 * Author:      David Monje
 * License:     GPL v2 or later
 * Text Domain: jugadores-club
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JC_VERSION', '1.0.88' );
define( 'JC_DIR', plugin_dir_path( __FILE__ ) );
define( 'JC_URI', plugin_dir_url( __FILE__ ) );

// API key para los endpoints REST. Puede sobreescribirse en wp-config.php.
if ( ! defined( 'JC_API_KEY' ) ) {
	define( 'JC_API_KEY', 'Lalbum_2026_04' );
}

require_once JC_DIR . 'includes/class-estadisticas.php';
require_once JC_DIR . 'includes/class-clubs-repository.php';
require_once JC_DIR . 'includes/class-clubs-controller.php';
require_once JC_DIR . 'includes/class-uploadcare.php';
require_once JC_DIR . 'includes/class-jugadores-club.php';
require_once JC_DIR . 'includes/class-gestor-admin.php';

/**
 * Crea la tabla wp_club_jugadores en la activación del plugin.
 */
function jc_activate() {
	global $wpdb;

	$table   = $wpdb->prefix . 'club_jugadores';
	$charset = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		club_id bigint(20) unsigned NOT NULL,
		category_uid varchar(32) NOT NULL DEFAULT '',
		nombre varchar(64) NOT NULL DEFAULT '',
		apellidos varchar(64) NOT NULL DEFAULT '',
		cargo varchar(32) NOT NULL DEFAULT '',
		nombre_foto varchar(64) NULL,
		foto_url varchar(256) NOT NULL DEFAULT '',
		menu_order int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY club_category_index (club_id, category_uid),
		KEY order_index (menu_order)
	) {$charset};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
register_activation_hook( __FILE__, 'jc_activate' );
register_deactivation_hook( __FILE__, 'jc_deactivate' );

/**
 * Registra los roles del plugin en cada carga de WordPress.
 *
 * - "Club"    — accede únicamente a su propio club (sin capabilities de WP).
 * - "Gestor"  — gestiona todos los clubs y sus miembros sin acceso completo
 *               al panel de administración de WordPress.
 */
function jc_register_roles() {
	// Rol Club: sin capabilities propias (el control de acceso es por club_slug).
	if ( ! get_role( 'club' ) ) {
		add_role(
			'club',
			__( 'Club', 'jugadores-club' ),
			array()
		);
	}

	// Capabilities necesarias para gestionar usuarios en wp-admin.
	$gestor_caps = array(
		'read'                   => true,
		'edit_posts'             => true,
		'edit_others_posts'      => true,
		'edit_published_posts'   => true,
		'publish_posts'          => true,
		'delete_posts'           => true,
		'delete_others_posts'    => true,
		'delete_published_posts' => true,
		'upload_files'           => true,
		'manage_club_users'      => true,
		// Gestión de usuarios (restringida a rol Club mediante hooks).
		'list_users'             => true,
		'create_users'           => true,
		'edit_users'             => true,
		'delete_users'           => true,
		'promote_users'          => true,
	);

	// Rol Gestor: gestión completa de posts (clubs) y usuarios Club.
	if ( ! get_role( 'gestor' ) ) {
		add_role( 'gestor', __( 'Gestor', 'jugadores-club' ), $gestor_caps );
	} else {
		// Asegurar que todas las capabilities están presentes en instalaciones previas.
		$gestor_role = get_role( 'gestor' );
		foreach ( $gestor_caps as $cap => $grant ) {
			if ( ! isset( $gestor_role->capabilities[ $cap ] ) ) {
				$gestor_role->add_cap( $cap, $grant );
			}
		}
	}
}
add_action( 'init', 'jc_register_roles' );

/**
 * Elimina los roles del plugin en la desactivación.
 */
function jc_deactivate() {
	remove_role( 'club' );
	remove_role( 'gestor' );
}

// Inicializar el plugin.
Jugadores_Club::init();
Clubs_Controller::init();
//Gestor_Usuarios::init();
Gestor_Admin::init();
//JC_Hide_Login::init();
