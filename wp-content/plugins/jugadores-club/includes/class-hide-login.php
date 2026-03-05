<?php
/**
 * Oculta la URL de login de WordPress y la expone en /album-admin.
 *
 * - Intercepta peticiones a /album-admin y sirve wp-login.php.
 * - Bloquea el acceso directo a /wp-login.php (redirige al home).
 * - Reemplaza wp-login.php en todas las URLs internas de WordPress
 *   (login, logout, recuperación de contraseña, formularios…).
 * - No interfiere con wp-admin/admin-ajax.php ni con la REST API.
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JC_Hide_Login {

	const SLUG = 'album-admin';

	public static function init(): void {
		add_action( 'plugins_loaded', array( __CLASS__, 'handle_request' ), 1 );
		add_filter( 'site_url', array( __CLASS__, 'filter_site_url' ), 10, 4 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_network_site_url' ), 10, 3 );
		add_filter( 'login_url', array( __CLASS__, 'filter_login_url' ), 10, 3 );
	}

	/**
	 * Gestiona la petición entrante:
	 *   · /album-admin[/...] → sirve wp-login.php con la URL personalizada.
	 *   · /wp-login.php directamente → redirige al home (acceso bloqueado).
	 */
	public static function handle_request(): void {
		global $pagenow;

		$path = self::request_path();

		// Acceso directo a wp-login.php → bloquear
		if ( 'wp-login.php' === $pagenow && ! defined( 'JC_CUSTOM_LOGIN' ) ) {
			// Permitir wp-cron (no llega por wp-login.php, pero por si acaso)
			if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
				return;
			}
			wp_redirect( home_url( '/' ) );
			exit;
		}

		// URL personalizada → servir el login
		if ( self::SLUG === $path || str_starts_with( $path, self::SLUG . '/' ) ) {
			define( 'JC_CUSTOM_LOGIN', true );
			$GLOBALS['pagenow'] = 'wp-login.php';
			require ABSPATH . 'wp-login.php';
			exit;
		}
	}

	/**
	 * Devuelve la ruta de la petición actual, normalizada y sin la ruta base del
	 * sitio (necesario en instalaciones WordPress en subdirectorio).
	 */
	private static function request_path(): string {
		$uri  = $_SERVER['REQUEST_URI'] ?? '';
		$path = trim( (string) parse_url( $uri, PHP_URL_PATH ), '/' );

		$base = trim( (string) parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( $base && str_starts_with( $path, $base ) ) {
			$path = trim( substr( $path, strlen( $base ) ), '/' );
		}

		return $path;
	}

	/**
	 * Reemplaza wp-login.php por el slug personalizado en site_url().
	 *
	 * Solo actúa cuando el scheme es 'login' o 'login_post' para no tocar
	 * otras URLs internas de WordPress (incluido admin-ajax.php).
	 *
	 * @param string      $url     URL generada.
	 * @param string      $path    Ruta solicitada.
	 * @param string|null $scheme  Scheme (login, login_post, …).
	 * @param int|null    $blog_id Blog ID (multisitio).
	 */
	public static function filter_site_url( string $url, string $path, ?string $scheme, ?int $blog_id = null ): string {
		if (
			in_array( $scheme, array( 'login', 'login_post' ), true )
			&& str_contains( $url, 'wp-login.php' )
		) {
			$url = str_replace( 'wp-login.php', self::SLUG, $url );
		}

		return $url;
	}

	/**
	 * Mismo reemplazo para network_site_url (multisitio, por compatibilidad).
	 *
	 * @param string      $url    URL generada.
	 * @param string      $path   Ruta solicitada.
	 * @param string|null $scheme Scheme.
	 */
	public static function filter_network_site_url( string $url, string $path, ?string $scheme ): string {
		return self::filter_site_url( $url, $path, $scheme );
	}

	/**
	 * Filtro de seguridad sobre login_url(): garantiza el slug correcto aunque
	 * algún código externo haya llamado a wp_login_url() por otra vía.
	 *
	 * @param string $url          URL de login (normalmente ya contiene el slug).
	 * @param string $redirect     URL de redirección tras el login.
	 * @param bool   $force_reauth Si se fuerza re-autenticación.
	 */
	public static function filter_login_url( string $url, string $redirect, bool $force_reauth ): string {
		if ( str_contains( $url, 'wp-login.php' ) ) {
			$url = str_replace( 'wp-login.php', self::SLUG, $url );
		}

		return $url;
	}
}
