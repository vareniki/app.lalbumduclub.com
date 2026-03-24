<?php
/**
 * Controlador REST para clubs.
 *
 * Endpoints:
 *
 *   GET /wp-json/jugadores-club/v1/clubs
 *     Lista paginada de clubs con estadísticas de fotos.
 *     Parámetros: page (int, default 1), per_page (int, default 10, máx. 100).
 *
 *   GET /wp-json/jugadores-club/v1/album
 *   GET /wp-json/jugadores-club/v1/album?club_id=X
 *     Datos completos de todos los clubs (o de uno específico):
 *     categorías, jugadores y fotos de equipo anidados.
 *
 * Ambos endpoints son públicos.
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clubs_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'jugadores-club/v1';
		$this->rest_base = 'clubs';
	}

	/**
	 * Registra la acción rest_api_init y conecta las rutas.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', static function () {
			( new self() )->register_routes();
		} );
	}

	/**
	 * Registra las rutas REST del controlador.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/album',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_album' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'club_id' => array(
							'description'       => __( 'ID del club. Si se omite, devuelve todos los clubs.', 'jugadores-club' ),
							'type'              => 'integer',
							'minimum'           => 1,
							'sanitize_callback' => 'absint',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Endpoint público, sin restricción de acceso.
	 *
	 * @param WP_REST_Request $request
	 * @return true
	 */
	public function get_items_permissions_check( $request ): bool {
		return true;
	}

	/**
	 * Maneja la petición GET y devuelve los clubs paginados.
	 *
	 * Cabeceras de paginación estándar de la WP REST API:
	 *   X-WP-Total        → total de clubs
	 *   X-WP-TotalPages   → total de páginas
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$repo        = new Clubs_Repository();
		$total       = $repo->get_total();
		$total_pages = (int) ceil( $total / $per_page );

		if ( $total > 0 && $page > $total_pages ) {
			return new WP_Error(
				'rest_invalid_page_number',
				__( 'El número de página solicitado supera el total de páginas disponibles.', 'jugadores-club' ),
				array( 'status' => 400 )
			);
		}

		$clubs = $repo->get_clubs( $page, $per_page );

		$response = rest_ensure_response( $clubs );
		$response->header( 'X-WP-Total',      (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );

		return $response;
	}

	/**
	 * Devuelve los clubs con sus categorías, jugadores y fotos de equipo.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_album( WP_REST_Request $request ): WP_REST_Response {
		$club_id = $request->get_param( 'club_id' )
			? (int) $request->get_param( 'club_id' )
			: null;

		$repo = new Clubs_Repository();
		$data = $repo->get_album( $club_id );

		return rest_ensure_response( $data );
	}

	/**
	 * Define y valida los parámetros de la colección.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_collection_params(): array {
		return array(
			'page'     => array(
				'description'       => __( 'Página actual de la colección.', 'jugadores-club' ),
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
			'per_page' => array(
				'description'       => __( 'Número de resultados por página.', 'jugadores-club' ),
				'type'              => 'integer',
				'default'           => 10,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			),
		);
	}
}
