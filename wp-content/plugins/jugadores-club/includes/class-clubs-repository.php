<?php
/**
 * Repositorio de datos para el listado de clubs con sus estadísticas.
 *
 * Encapsula todas las consultas SQL necesarias para el endpoint REST.
 * Parte desde wp_posts (post_type=club) para incluir también los clubs
 * sin miembros registrados en wp_club_jugadores.
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clubs_Repository {

	private wpdb $wpdb;
	private string $table;

	public function __construct() {
		global $wpdb;
		$this->wpdb  = $wpdb;
		$this->table = $wpdb->prefix . 'club_jugadores';
	}

	/**
	 * Devuelve el total de posts publicados de tipo 'club'.
	 * Se usa para calcular la paginación.
	 *
	 * @param int|null $club_id Si se indica, cuenta solo ese club.
	 * @return int
	 */
	public function get_total( ?int $club_id = null ): int {
		if ( $club_id !== null ) {
			return (int) $this->wpdb->get_var( $this->wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$this->wpdb->posts}
				 WHERE post_type   = 'club'
				   AND post_status = 'publish'
				   AND ID = %d",
				$club_id
			) );
		}

		return (int) $this->wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$this->wpdb->posts}
			 WHERE post_type   = 'club'
			   AND post_status = 'publish'"
		);
	}

	/**
	 * Devuelve un array de clubs paginado.
	 *
	 * Incluye todos los posts publicados de tipo 'club', tengan o no miembros.
	 *
	 * Cada elemento contiene:
	 *   - club_id            (int)
	 *   - nombre             (string)  post_title del club.
	 *   - extracto           (string)  post_excerpt del club.
	 *   - url                (string)  Permalink del club.
	 *   - imagen_destacada   (string)  URL de la imagen destacada, o cadena vacía.
	 *   - total_miembros     (int)
	 *   - total_fotos        (int)     filas con nombre_foto relleno.
	 *   - total_fotos_vacias (int)     total_miembros − total_fotos.
	 *   - porcentaje_fotos   (float)   total_fotos / total_miembros × 100.
	 *
	 * @param int      $page     Página actual (base 1).
	 * @param int      $per_page Resultados por página.
	 * @param int|null $club_id  Si se indica, devuelve solo ese club.
	 * @return array<array<string,mixed>>
	 */
	public function get_clubs( int $page = 1, int $per_page = 10, ?int $club_id = null ): array {
		$offset = ( $page - 1 ) * $per_page;

		$club_filter = $club_id !== null
			? $this->wpdb->prepare( 'AND p.ID = %d', $club_id )
			: '';

		$rows = $this->wpdb->get_results( $this->wpdb->prepare(
			"SELECT
				p.ID                                                                                  AS club_id,
				p.post_title                                                                          AS nombre,
				p.post_excerpt                                                                        AS extracto,
				pm.meta_value                                                                         AS thumbnail_id,
				COUNT(cj.id)                                                                          AS total_miembros,
				SUM(CASE WHEN cj.nombre_foto IS NOT NULL AND cj.nombre_foto != '' THEN 1 ELSE 0 END) AS total_fotos,
				SUM(CASE WHEN cj.nombre_foto IS NULL     OR  cj.nombre_foto  = '' THEN 1 ELSE 0 END) AS total_fotos_vacias
			 FROM {$this->wpdb->posts} p
			 LEFT JOIN {$this->table} cj
			     ON cj.club_id = p.ID
			 LEFT JOIN {$this->wpdb->postmeta} pm
			     ON pm.post_id = p.ID
			    AND pm.meta_key = '_thumbnail_id'
			 WHERE p.post_type   = 'club'
			   AND p.post_status = 'publish'
			   {$club_filter}
			 GROUP BY p.ID, p.post_title, p.post_excerpt, pm.meta_value
			 ORDER BY p.post_title ASC
			 LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );

		if ( ! $rows ) {
			return array();
		}

		return array_map( static function ( object $row ): array {
			$thumbnail_id     = (int) $row->thumbnail_id;
			$imagen_destacada = $thumbnail_id ? (string) wp_get_attachment_url( $thumbnail_id ) : '';

			$miembros          = (int) $row->total_miembros;
			$fotos             = (int) $row->total_fotos;
			$porcentaje_fotos  = $miembros > 0 ? round( $fotos / $miembros * 100, 1 ) : 0.0;

			return array(
				'club_id'            => (int) $row->club_id,
				'nombre'             => $row->nombre,
				'extracto'           => $row->extracto,
				'url'                => get_permalink( (int) $row->club_id ),
				'imagen_destacada'   => $imagen_destacada,
				'total_miembros'     => $miembros,
				'total_fotos'        => $fotos,
				'total_fotos_vacias' => $miembros - $fotos,
				'porcentaje_fotos'   => $porcentaje_fotos,
			);
		}, $rows );
	}
}
