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

define( 'JC_VERSION', '1.0.98' );
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
 * - "Club"        — accede únicamente a su propio club (sin capabilities de WP).
 * - "Gestor"      — gestiona los clubs que tenga asignados y sus usuarios Club.
 * - "Súpergestor" — igual que Gestor pero con acceso a todos los clubs y puede
 *                   crear/editar/eliminar usuarios con rol Gestor.
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

	// Capabilities base compartidas por Gestor y Súpergestor.
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
		// Gestión de usuarios (restringida mediante hooks según el rol).
		'list_users'             => true,
		'create_users'           => true,
		'edit_users'             => true,
		'delete_users'           => true,
		'promote_users'          => true,
	);

	// Rol Gestor: gestiona únicamente sus clubs asignados y usuarios Club.
	if ( ! get_role( 'gestor' ) ) {
		add_role( 'gestor', __( 'Gestor', 'jugadores-club' ), $gestor_caps );
	} else {
		$gestor_role = get_role( 'gestor' );
		foreach ( $gestor_caps as $cap => $grant ) {
			if ( ! isset( $gestor_role->capabilities[ $cap ] ) ) {
				$gestor_role->add_cap( $cap, $grant );
			}
		}
	}

	// Rol Súpergestor: todas las caps del Gestor más gestión de usuarios Gestor.
	$supergestor_caps = array_merge( $gestor_caps, array(
		'manage_gestor_users' => true,
	) );

	if ( ! get_role( 'supergestor' ) ) {
		add_role( 'supergestor', __( 'Súpergestor', 'jugadores-club' ), $supergestor_caps );
	} else {
		$supergestor_role = get_role( 'supergestor' );
		foreach ( $supergestor_caps as $cap => $grant ) {
			if ( ! isset( $supergestor_role->capabilities[ $cap ] ) ) {
				$supergestor_role->add_cap( $cap, $grant );
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
	remove_role( 'supergestor' );
}

/**
 * Obtiene los IDs de clubs asignados a un gestor (múltiples).
 *
 * @param int $user_id ID del usuario gestor.
 * @return int[]
 */
function jc_get_gestor_clubs( int $user_id ): array {
	$val = get_user_meta( $user_id, 'jc_clubs_gestionados', true );
	return is_array( $val ) ? array_map( 'absint', $val ) : array();
}

/**
 * Guarda los IDs de clubs asignados a un gestor (múltiples).
 *
 * @param int   $user_id  ID del usuario gestor.
 * @param int[] $club_ids IDs de los posts de club.
 */
function jc_set_gestor_clubs( int $user_id, array $club_ids ): void {
	update_user_meta( $user_id, 'jc_clubs_gestionados', array_values( array_map( 'absint', array_filter( $club_ids ) ) ) );
}

/**
 * Obtiene el ID del club asignado a un usuario con rol Club (uno solo).
 *
 * @param int $user_id ID del usuario Club.
 * @return int|null  ID del club, o null si no tiene ninguno asignado.
 */
function jc_get_club_user_club( int $user_id ): ?int {
	$val = get_user_meta( $user_id, 'jc_club_id', true );
	return $val ? absint( $val ) : null;
}

/**
 * Guarda el club asignado a un usuario con rol Club.
 *
 * @param int      $user_id ID del usuario Club.
 * @param int|null $club_id ID del post de club, o null para desasignar.
 */
function jc_set_club_user_club( int $user_id, ?int $club_id ): void {
	if ( $club_id ) {
		update_user_meta( $user_id, 'jc_club_id', $club_id );
	} else {
		delete_user_meta( $user_id, 'jc_club_id' );
	}
}

// Inicializar el plugin.
Jugadores_Club::init();
Clubs_Controller::init();
//Gestor_Usuarios::init();
Gestor_Admin::init();
//JC_Hide_Login::init();
