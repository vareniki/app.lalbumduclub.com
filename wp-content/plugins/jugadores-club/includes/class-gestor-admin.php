<?php
/**
 * Integración del rol Gestor con el panel de WordPress.
 *
 * Permite a los gestores administrar únicamente los usuarios con rol Club
 * desde wp-admin > Usuarios, sin poder ver ni tocar otros usuarios.
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gestor_Admin {

	public static function init(): void {
		// Filtra la lista de usuarios en wp-admin.
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users_list' ) );

		// Restringe los roles asignables a solo 'club'.
		add_filter( 'editable_roles', array( __CLASS__, 'filter_editable_roles' ) );

		// Impide editar o eliminar usuarios que no sean Club.
		add_filter( 'map_meta_cap', array( __CLASS__, 'restrict_user_caps' ), 10, 4 );

		// Limpia las pestañas de roles en la pantalla de usuarios.
		add_filter( 'views_users', array( __CLASS__, 'filter_user_views' ) );
	}

	/**
	 * Comprueba si el usuario actual tiene el rol Gestor.
	 */
	private static function is_gestor(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return in_array( 'gestor', (array) wp_get_current_user()->roles, true );
	}

	/**
	 * Filtra la consulta de usuarios en el admin para mostrar solo los de rol Club.
	 */
	public static function filter_users_list( WP_User_Query $query ): void {
		if ( ! is_admin() || ! self::is_gestor() ) {
			return;
		}
		$query->set( 'role', 'club' );
	}

	/**
	 * Restringe los roles asignables a solo 'club' para el Gestor.
	 * Esto impide que puedan crear o promover usuarios a cualquier otro rol.
	 *
	 * @param array $roles Roles disponibles en WordPress.
	 * @return array
	 */
	public static function filter_editable_roles( array $roles ): array {
		if ( ! self::is_gestor() ) {
			return $roles;
		}
		return isset( $roles['club'] ) ? array( 'club' => $roles['club'] ) : array();
	}

	/**
	 * Impide que el Gestor edite o elimine usuarios que no tengan rol Club.
	 *
	 * @param string[] $caps     Capabilities requeridas.
	 * @param string   $cap      Capability que se está comprobando.
	 * @param int      $user_id  ID del usuario que intenta la acción.
	 * @param array    $args     Argumentos (args[0] = ID del usuario objetivo).
	 * @return string[]
	 */
	public static function restrict_user_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, array( 'edit_user', 'delete_user' ), true ) ) {
			return $caps;
		}

		$current_user = get_user_by( 'id', $user_id );
		if ( ! $current_user || ! in_array( 'gestor', (array) $current_user->roles, true ) ) {
			return $caps;
		}

		$target_id = (int) ( $args[0] ?? 0 );

		// Un gestor nunca puede editarse a sí mismo desde la pantalla de usuarios.
		if ( ! $target_id || $target_id === $user_id ) {
			return $caps;
		}

		$target = get_user_by( 'id', $target_id );
		if ( ! $target || ! in_array( 'club', (array) $target->roles, true ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	/**
	 * Deja solo las pestañas "Todos" y "Club" en la pantalla de usuarios.
	 *
	 * @param array $views Pestañas de filtro disponibles.
	 * @return array
	 */
	public static function filter_user_views( array $views ): array {
		if ( ! self::is_gestor() ) {
			return $views;
		}

		$allowed = array();

		if ( isset( $views['all'] ) ) {
			$allowed['all'] = $views['all'];
		}

		if ( isset( $views['club'] ) ) {
			$allowed['club'] = $views['club'];
		}

		return $allowed;
	}
}