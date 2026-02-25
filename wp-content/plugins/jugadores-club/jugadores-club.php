<?php
/**
 * Plugin Name: Jugadores Club
 * Description: Gestión de jugadores por categorías con drag & drop, upload de fotos y shortcode [jugadores-club].
 * Version:     1.0.0
 * Author:      David Monje
 * License:     GPL v2 or later
 * Text Domain: jugadores-club
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JC_VERSION', '1.0.23' );
define( 'JC_DIR', plugin_dir_path( __FILE__ ) );
define( 'JC_URI', plugin_dir_url( __FILE__ ) );

require_once JC_DIR . 'includes/class-uploadcare.php';
require_once JC_DIR . 'includes/class-jugadores-club.php';
//require_once JC_DIR . 'includes/class-hide-login.php';

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
		nombre_foto varchar(32) NULL,
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
 * Registra el rol "Club" en la activación del plugin.
 */
function jc_register_club_role() {
	add_role(
		'club',
		__( 'Club', 'jugadores-club' ),
		array()
	);
}
add_action( 'init', 'jc_register_club_role' );

/**
 * Elimina el rol "Club" en la desactivación del plugin.
 */
function jc_deactivate() {
	remove_role( 'club' );
}

// Inicializar el plugin.
Jugadores_Club::init();
//JC_Hide_Login::init();
