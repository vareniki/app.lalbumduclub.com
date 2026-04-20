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
 *   GET /wp-json/jugadores-club/v1/backup
 *     Descarga un ZIP con tres archivos SQL (INSERT statements) de las tablas
 *     wp_club_categorias, wp_club_jugadores y wp_club_equipo.
 *
 * Todos los endpoints requieren la cabecera:
 *   X-API-Key: <valor de la constante JC_API_KEY>
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
					'permission_callback' => array( $this, 'check_api_key' ),
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
					'permission_callback' => array( $this, 'check_api_key' ),
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

		register_rest_route(
			$this->namespace,
			'/backup',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_backup' ),
					'permission_callback' => array( $this, 'check_api_key' ),
				),
			)
		);
	}

	/**
	 * Valida la API key enviada en la cabecera X-API-Key.
	 *
	 * @param WP_REST_Request $request
	 * @return true|WP_Error
	 */
	public function check_api_key( WP_REST_Request $request ) {
		$key = $request->get_header( 'X-API-Key' );

		if ( ! $key || ! hash_equals( JC_API_KEY, $key ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'API key inválida o ausente.', 'jugadores-club' ),
				array( 'status' => 401 )
			);
		}

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

	/**
	 * Genera un ZIP con tres archivos SQL (INSERT statements) y lo descarga.
	 *
	 * @param WP_REST_Request $request
	 * @return void
	 */
	public function get_backup( WP_REST_Request $request ): void {
		global $wpdb;

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error( array( 'message' => 'La extensión ZipArchive no está disponible en el servidor.' ), 500 );
		}

		$tables = array(
			$wpdb->prefix . 'club_categorias',
			$wpdb->prefix . 'club_jugadores',
			$wpdb->prefix . 'club_equipo',
		);

		$tmp_file = tempnam( sys_get_temp_dir(), 'jc_backup_' ) . '.zip';
		$zip      = new ZipArchive();

		if ( $zip->open( $tmp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			wp_send_json_error( array( 'message' => 'No se pudo crear el archivo ZIP temporal.' ), 500 );
		}

		foreach ( $tables as $table ) {
			$zip->addFromString( $table . '.sql', self::generate_sql_inserts( $table ) );
		}

		$zip->close();

		$filename = 'backup_lalbumduclub_' . gmdate( 'Y-m-d_H-i-s' ) . '.zip';

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $tmp_file ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Evitar que la REST API sobreescriba las cabeceras con JSON.
		remove_all_filters( 'rest_pre_serve_request' );

		readfile( $tmp_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		unlink( $tmp_file );   // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
		exit;
	}

	/**
	 * Genera el contenido SQL con sentencias INSERT para una tabla.
	 *
	 * @param string $table Nombre completo de la tabla (con prefijo).
	 * @return string
	 */
	private static function generate_sql_inserts( string $table ): string {
		global $wpdb;

		$generated_at = gmdate( 'Y-m-d H:i:s' );

		// Obtener el DDL real de la tabla via SHOW CREATE TABLE.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$create_row = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N );
		$create_ddl = $create_row ? $create_row[1] : "-- No se pudo obtener el DDL de `{$table}`";

		// Obtener todas las filas. Tabla controlada internamente, sin riesgo de inyección.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A );

		$lines = array(
			"-- Backup de `{$table}`",
			"-- Generado: {$generated_at} UTC",
			'',
			"DROP TABLE IF EXISTS `{$table}`;",
			$create_ddl . ';',
			'',
		);

		if ( empty( $rows ) ) {
			$lines[] = "-- La tabla está vacía, no hay INSERTs.";
			return implode( "\n", $lines ) . "\n";
		}

		$columns = '`' . implode( '`, `', array_keys( $rows[0] ) ) . '`';

		foreach ( $rows as $row ) {
			$values = array_map(
				static function ( $val ): string {
					if ( $val === null ) {
						return 'NULL';
					}
					return "'" . esc_sql( $val ) . "'";
				},
				array_values( $row )
			);

			$lines[] = "INSERT INTO `{$table}` ({$columns}) VALUES (" . implode( ', ', $values ) . ");";
		}

		return implode( "\n", $lines ) . "\n";
	}
}
