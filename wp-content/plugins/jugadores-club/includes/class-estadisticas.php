<?php
/**
 * Clase de utilidades para estadísticas y datos del club.
 *
 * Usa el patrón singleton para reutilizar la misma instancia durante
 * el request. estadisticas() cachea su resultado en memoria para no
 * repetir la consulta aunque se llame varias veces.
 *
 * Uso: Estadisticas_Club::get()->estadisticas()
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Estadisticas_Club {

	/** @var self|null Instancia única por request. */
	private static ?self $instance = null;

	/** @var string|null Resultado cacheado de estadisticas(). */
	private ?string $estadisticas_cache = null;

	private function __construct() {}

	/**
	 * Devuelve la instancia singleton.
	 *
	 * @return self
	 */
	public static function get(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -----------------------------------------------------------------------
	// Club
	// -----------------------------------------------------------------------

	/**
	 * Devuelve el ID del club (post) en contexto.
	 *
	 * @return int
	 */
	public function club_id(): int {
		return (int) get_the_ID();
	}

	/**
	 * Devuelve el nombre (título) del club.
	 *
	 * @param int $club_id ID del club. 0 = post en contexto.
	 * @return string
	 */
	public function club_nombre( int $club_id = 0 ): string {
		$id = $club_id ?: $this->club_id();
		if ( ! $id ) {
			return '';
		}
		return get_the_title( $id );
	}

	// -----------------------------------------------------------------------
	// Categorías
	// -----------------------------------------------------------------------

	/**
	 * Devuelve las categorías de un club como array de objetos.
	 *
	 * Cada elemento tiene las propiedades:
	 *   ->uid    (string) Slug identificador de la categoría.
	 *   ->nombre (string) Nombre legible de la categoría.
	 *
	 * @param int $club_id ID del club. 0 = post en contexto.
	 * @return array<object>
	 */
	public function get_categorias( int $club_id = 0 ): array {
		$id = $club_id ?: $this->club_id();
		if ( ! $id ) {
			return array();
		}

		$raw = get_field( 'categoria', $id );
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$result = array();
		foreach ( $raw as $cat ) {
			if ( empty( $cat['categoria'] ) ) {
				continue;
			}
			$result[] = (object) array(
				'uid'    => sanitize_title( $cat['categoria'] ),
				'nombre' => $cat['categoria'],
			);
		}

		return $result;
	}

	// -----------------------------------------------------------------------
	// Jugadores
	// -----------------------------------------------------------------------

	/**
	 * Devuelve los jugadores de un club, opcionalmente filtrados por categoría.
	 *
	 * Cada elemento del array es un objeto con los campos de wp_club_jugadores:
	 *   id, club_id, category_uid, nombre, apellidos, cargo,
	 *   nombre_foto, foto_url, menu_order.
	 *
	 * @param int    $club_id      ID del club. 0 = post en contexto.
	 * @param string $category_uid Slug de categoría. '' = todas las categorías.
	 * @return array<object>
	 */
	public function get_jugadores( int $club_id = 0, string $category_uid = '' ): array {
		global $wpdb;

		$id = $club_id ?: $this->club_id();
		if ( ! $id ) {
			return array();
		}

		$table = $wpdb->prefix . 'club_jugadores';

		if ( $category_uid !== '' ) {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE club_id = %d AND category_uid = %s
				 ORDER BY menu_order ASC",
				$id,
				$category_uid
			) );
		} else {
			$results = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE club_id = %d
				 ORDER BY category_uid ASC, menu_order ASC",
				$id
			) );
		}

		return $results ?: array();
	}

	/**
	 * Devuelve el nombre completo de un jugador (nombre + apellidos).
	 *
	 * @param object|array $jugador Fila de wp_club_jugadores.
	 * @return string
	 */
	public function jugador_nombre_completo( $jugador ): string {
		$j = is_array( $jugador ) ? (object) $jugador : $jugador;
		return trim( ( $j->nombre ?? '' ) . ' ' . ( $j->apellidos ?? '' ) );
	}

	/**
	 * Devuelve la URL de la foto de un jugador con transformación Uploadcare opcional.
	 *
	 * Si el jugador no tiene foto, devuelve cadena vacía.
	 *
	 * @param object|array $jugador Fila de wp_club_jugadores.
	 * @param int          $width   Ancho en píxeles (0 = sin transformación).
	 * @param int          $height  Alto en píxeles (0 = sin transformación).
	 * @return string URL de la foto o cadena vacía.
	 */
	public function jugador_foto_url( $jugador, int $width = 0, int $height = 0 ): string {
		$j = is_array( $jugador ) ? (object) $jugador : $jugador;

		$url = $j->foto_url ?? '';
		if ( ! $url ) {
			return '';
		}

		if ( $width > 0 && $height > 0 ) {
			$url = rtrim( $url, '/' ) . "/-/preview/{$width}x{$height}/";
		} elseif ( $width > 0 ) {
			$url = rtrim( $url, '/' ) . "/-/resize/{$width}x/";
		}

		return esc_url( $url );
	}

	// -----------------------------------------------------------------------
	// Estadísticas globales
	// -----------------------------------------------------------------------

	/**
	 * Devuelve un JSON con estadísticas globales y desglose por club.
	 *
	 * El resultado se cachea en memoria: la consulta SQL solo se ejecuta
	 * una vez por request aunque el método se llame múltiples veces.
	 *
	 * Estructura devuelta:
	 * {
	 *   "global": {
	 *     "total_clubs":        int,
	 *     "total_miembros":     int,
	 *     "total_fotos":        int,
	 *     "total_fotos_faltan": int
	 *   },
	 *   "clubs": [
	 *     {
	 *       "club_id":            int,
	 *       "total_categorias":   int,
	 *       "total_miembros":     int,
	 *       "total_fotos":        int,
	 *       "total_fotos_vacias": int
	 *     },
	 *     ...
	 *   ]
	 * }
	 *
	 * @return string JSON codificado.
	 */
	public function estadisticas(): string {
		if ( $this->estadisticas_cache !== null ) {
			return $this->estadisticas_cache;
		}

		global $wpdb;

		$table = $wpdb->prefix . 'club_jugadores';

		$rows = $wpdb->get_results(
			"SELECT
				club_id,
				COUNT(DISTINCT category_uid)                                                   AS total_categorias,
				COUNT(*)                                                                       AS total_miembros,
				SUM(CASE WHEN nombre_foto IS NOT NULL AND nombre_foto != '' THEN 1 ELSE 0 END) AS total_fotos
			 FROM {$table}
			 GROUP BY club_id
			 ORDER BY club_id ASC"
		);

		$global_miembros = 0;
		$global_fotos    = 0;
		$clubs           = array();

		foreach ( $rows as $row ) {
			$miembros     = (int) $row->total_miembros;
			$fotos        = (int) $row->total_fotos;
			$fotos_vacias = $miembros - $fotos;

			$global_miembros += $miembros;
			$global_fotos    += $fotos;

			$clubs[] = array(
				'club_id'            => (int) $row->club_id,
				'total_categorias'   => (int) $row->total_categorias,
				'total_miembros'     => $miembros,
				'total_fotos'        => $fotos,
				'total_fotos_vacias' => $fotos_vacias,
			);
		}

		$data = array(
			'global' => array(
				'total_clubs'        => count( $clubs ),
				'total_miembros'     => $global_miembros,
				'total_fotos'        => $global_fotos,
				'total_fotos_faltan' => $global_miembros - $global_fotos,
			),
			'clubs' => $clubs,
		);

		$this->estadisticas_cache = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		return $this->estadisticas_cache;
	}
}
