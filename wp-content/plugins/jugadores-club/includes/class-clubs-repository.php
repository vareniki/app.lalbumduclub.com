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
	private string $table_categorias;
	private string $table_equipo;

	public function __construct() {
		global $wpdb;
		$this->wpdb             = $wpdb;
		$this->table            = $wpdb->prefix . 'club_jugadores';
		$this->table_categorias = $wpdb->prefix . 'club_categorias';
		$this->table_equipo     = $wpdb->prefix . 'club_equipo';
	}

	/**
	 * Normaliza los filtros de IDs en un único array o null.
	 *
	 * - Si $club_id y $only_ids son null → null (sin filtro de IDs).
	 * - Si solo uno está presente → devuelve ese filtro como array.
	 * - Si ambos están presentes → devuelve la intersección.
	 * - Si la intersección está vacía → devuelve [] (sin resultados).
	 *
	 * @param int|null   $club_id  ID concreto de club, o null.
	 * @param int[]|null $only_ids Lista de IDs permitidos, o null.
	 * @return int[]|null
	 */
	private function build_ids_filter( ?int $club_id, ?array $only_ids ): ?array {
		if ( $club_id === null && $only_ids === null ) {
			return null;
		}

		if ( $club_id !== null && $only_ids === null ) {
			return array( $club_id );
		}

		if ( $club_id === null ) {
			return $only_ids;
		}

		// Ambos presentes: intersección.
		return in_array( $club_id, $only_ids, true ) ? array( $club_id ) : array();
	}

	/**
	 * Devuelve el total de posts publicados de tipo 'club'.
	 * Se usa para calcular la paginación.
	 *
	 * @param int|null   $club_id  Si se indica, cuenta solo ese club.
	 * @param int[]|null $only_ids Si se indica, restringe el conteo a esos IDs.
	 * @return int
	 */
	public function get_total( ?int $club_id = null, ?array $only_ids = null ): int {
		// Calcula la intersección: si hay both filtros, usa el más restrictivo.
		$ids_filter = $this->build_ids_filter( $club_id, $only_ids );

		if ( $ids_filter !== null ) {
			if ( empty( $ids_filter ) ) {
				return 0;
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids_filter ), '%d' ) );
			return (int) $this->wpdb->get_var(
				$this->wpdb->prepare(
					"SELECT COUNT(*)
					 FROM {$this->wpdb->posts}
					 WHERE post_type   = 'club'
					   AND post_status = 'publish'
					   AND ID IN ({$placeholders})",
					...$ids_filter
				)
			);
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
	 * @param int        $page     Página actual (base 1).
	 * @param int        $per_page Resultados por página.
	 * @param int|null   $club_id  Si se indica, devuelve solo ese club.
	 * @param int[]|null $only_ids Si se indica, restringe a esos IDs (p.ej. clubs de un gestor).
	 * @return array<array<string,mixed>>
	 */
	public function get_clubs( int $page = 1, int $per_page = 10, ?int $club_id = null, ?array $only_ids = null ): array {
		$offset = ( $page - 1 ) * $per_page;

		$ids_filter  = $this->build_ids_filter( $club_id, $only_ids );
		$club_filter = '';

		if ( $ids_filter !== null ) {
			if ( empty( $ids_filter ) ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids_filter ), '%d' ) );
			$club_filter  = $this->wpdb->prepare( "AND p.ID IN ({$placeholders})", ...$ids_filter );
		}

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
			 LEFT JOIN {$this->table_categorias} cc
			     ON cc.post_id = p.ID
			 LEFT JOIN {$this->table} cj
			     ON cj.categoria_id = cc.id
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

		return array_map( function ( object $row ): array {
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

	/**
	 * Devuelve todos los clubs con sus categorías, jugadores y fotos de equipo.
	 *
	 * Usa 4 queries planas y ensambla la estructura en PHP para evitar N+1.
	 *
	 * Estructura de respuesta por club:
	 *   - club_id        (int)
	 *   - nombre         (string)
	 *   - categorias     (array)  ordenadas por menu_order
	 *     - id           (int)
	 *     - descripcion  (string)
	 *     - menu_order   (int)
	 *     - equipo       (array)  fotos de grupo, ordenadas por menu_order
	 *     - jugadores    (array)  ordenados por menu_order
	 *
	 * @param int|null   $club_id  Si se indica, filtra por ese club.
	 * @param int[]|null $only_ids Si se indica, restringe a esos IDs.
	 * @return array<array<string,mixed>>
	 */
	public function get_album( ?int $club_id = null, ?array $only_ids = null ): array {
		// 1. Clubs.
		$ids_filter = $this->build_ids_filter( $club_id, $only_ids );

		if ( $ids_filter !== null ) {
			if ( empty( $ids_filter ) ) {
				return array();
			}
			$placeholders = implode( ',', array_fill( 0, count( $ids_filter ), '%d' ) );
			$clubs_rows   = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT p.ID AS club_id, p.post_title AS nombre
					 FROM {$this->wpdb->posts} p
					 WHERE p.post_type   = 'club'
					   AND p.post_status = 'publish'
					   AND p.ID IN ({$placeholders})
					 ORDER BY p.post_title ASC",
					...$ids_filter
				)
			);
		} else {
			$clubs_rows = $this->wpdb->get_results(
				"SELECT p.ID AS club_id, p.post_title AS nombre
				 FROM {$this->wpdb->posts} p
				 WHERE p.post_type   = 'club'
				   AND p.post_status = 'publish'
				 ORDER BY p.post_title ASC"
			);
		}

		if ( ! $clubs_rows ) {
			return array();
		}

		$club_ids    = array_map( static fn( $r ) => (int) $r->club_id, $clubs_rows );
		$ph_clubs    = implode( ',', array_fill( 0, count( $club_ids ), '%d' ) );

		// 2. Categorías de esos clubs.
		$cat_rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, post_id, descripcion, menu_order
				 FROM {$this->table_categorias}
				 WHERE post_id IN ({$ph_clubs})
				 ORDER BY post_id ASC, menu_order ASC",
				...$club_ids
			)
		);

		// Índices: [club_id => [categorias]] y [cat_id => &categoria].
		$cats_by_club = array();
		$cat_index    = array();

		foreach ( $cat_rows as $cat ) {
			$entry = array(
				'id'          => (int) $cat->id,
				'descripcion' => $cat->descripcion,
				'menu_order'  => (int) $cat->menu_order,
				'equipo'      => array(),
				'jugadores'   => array(),
			);
			$cats_by_club[ (int) $cat->post_id ][] = $entry;
			// Referencia al último elemento insertado para poder añadir equipo/jugadores.
			$cat_index[ (int) $cat->id ] = &$cats_by_club[ (int) $cat->post_id ][ count( $cats_by_club[ (int) $cat->post_id ] ) - 1 ];
		}

		$cat_ids = array_map( static fn( $r ) => (int) $r->id, $cat_rows );

		if ( $cat_ids ) {
			$ph_cats = implode( ',', array_fill( 0, count( $cat_ids ), '%d' ) );

			// 3. Fotos de equipo.
			$equipo_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT id, categoria_id, descripcion, nombre_foto, foto_url, menu_order
					 FROM {$this->table_equipo}
					 WHERE categoria_id IN ({$ph_cats})
					 ORDER BY categoria_id ASC, menu_order ASC",
					...$cat_ids
				)
			);

			foreach ( $equipo_rows as $e ) {
				$cid = (int) $e->categoria_id;
				if ( isset( $cat_index[ $cid ] ) ) {
					$cat_index[ $cid ]['equipo'][] = array(
						'id'          => (int) $e->id,
						'descripcion' => $e->descripcion,
						'nombre_foto' => $e->nombre_foto,
						'foto_url'    => $e->foto_url,
						'menu_order'  => (int) $e->menu_order,
					);
				}
			}

			// 4. Jugadores.
			$jugador_rows = $this->wpdb->get_results(
				$this->wpdb->prepare(
					"SELECT id, categoria_id, nombre, apellidos, cargo, nombre_foto, foto_url, menu_order
					 FROM {$this->table}
					 WHERE categoria_id IN ({$ph_cats})
					 ORDER BY categoria_id ASC, menu_order ASC",
					...$cat_ids
				)
			);

			foreach ( $jugador_rows as $j ) {
				$cid = (int) $j->categoria_id;
				if ( isset( $cat_index[ $cid ] ) ) {
					$cat_index[ $cid ]['jugadores'][] = array(
						'id'          => (int) $j->id,
						'nombre'      => $j->nombre,
						'apellidos'   => $j->apellidos,
						'cargo'       => $j->cargo,
						'nombre_foto' => $j->nombre_foto,
						'foto_url'    => $j->foto_url,
						'menu_order'  => (int) $j->menu_order,
					);
				}
			}
		}

		// 5. Ensamblar respuesta final.
		return array_map( static function ( object $club ) use ( $cats_by_club ): array {
			$cid = (int) $club->club_id;
			return array(
				'club_id'    => $cid,
				'nombre'     => $club->nombre,
				'categorias' => $cats_by_club[ $cid ] ?? array(),
			);
		}, $clubs_rows );
	}
}
