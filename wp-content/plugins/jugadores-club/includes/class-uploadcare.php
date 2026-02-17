<?php
/**
 * Integración con UploadCare.
 *
 * Gestiona subida, consulta y eliminación de archivos
 * mediante la Upload API y REST API de UploadCare.
 *
 * Requiere en wp-config.php:
 *   define( 'UPLOADCARE_PUBLIC_KEY', '...' );
 *   define( 'UPLOADCARE_SECRET_KEY', '...' );
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class JC_Uploadcare {

	private const UPLOAD_BASE = 'https://upload.uploadcare.com/';
	private const REST_BASE   = 'https://api.uploadcare.com/';
	private const CDN_BASE    = 'https://y9vl0yvu4z.ucarecd.net/';

	private string $public_key;
	private string $secret_key;

	public function __construct() {
		if ( ! defined( 'UPLOADCARE_PUBLIC_KEY' ) || ! defined( 'UPLOADCARE_SECRET_KEY' ) ) {
			wp_die( 'UploadCare: faltan las constantes UPLOADCARE_PUBLIC_KEY y/o UPLOADCARE_SECRET_KEY en wp-config.php' );
		}

		$this->public_key = UPLOADCARE_PUBLIC_KEY;
		$this->secret_key = UPLOADCARE_SECRET_KEY;
	}

	/**
	 * Devuelve la clave pública (para el widget frontend).
	 */
	public function get_public_key(): string {
		return $this->public_key;
	}

	/**
	 * Sube un archivo desde una URL externa a UploadCare.
	 *
	 * @param string $url URL pública del archivo a subir.
	 * @return array|WP_Error Datos del archivo subido o error.
	 */
	public function upload_from_url( string $url ) {
		$response = wp_remote_post( self::UPLOAD_BASE . 'from_url/', array(
			'body' => array(
				'pub_key'    => $this->public_key,
				'source_url' => $url,
				'store'      => '1',
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['token'] ) ) {
			return new WP_Error( 'uploadcare_upload', 'No se obtuvo token de subida.', $body );
		}

		return $this->poll_upload_status( $body['token'] );
	}

	/**
	 * Obtiene información de un archivo por su UUID.
	 *
	 * @param string $uuid UUID del archivo.
	 * @return array|WP_Error Datos del archivo o error.
	 */
	public function get_file_info( string $uuid ) {
		$response = wp_remote_get( self::REST_BASE . 'files/' . $uuid . '/', array(
			'headers' => $this->rest_headers(),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 ) {
			return new WP_Error( 'uploadcare_file_info', "Error al obtener info del archivo (HTTP {$code}).", $body );
		}

		return $body;
	}

	/**
	 * Elimina un archivo por su UUID.
	 *
	 * @param string $uuid UUID del archivo.
	 * @return true|WP_Error True si se eliminó o error.
	 */
	public function delete_file( string $uuid ) {
		$response = wp_remote_request( self::REST_BASE . 'files/' . $uuid . '/', array(
			'method'  => 'DELETE',
			'headers' => $this->rest_headers(),
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			return new WP_Error( 'uploadcare_delete', "Error al eliminar archivo (HTTP {$code}).", $body );
		}

		return true;
	}

	/**
	 * Genera la URL CDN de un archivo.
	 *
	 * @param string $uuid UUID del archivo.
	 * @return string URL CDN.
	 */
	public function get_cdn_url( string $uuid ): string {
		return self::CDN_BASE . $uuid . '/';
	}

	/**
	 * Headers de autenticación para la REST API.
	 */
	private function rest_headers(): array {
		return array(
			'Authorization' => 'Uploadcare.Simple ' . $this->public_key . ':' . $this->secret_key,
			'Accept'        => 'application/vnd.uploadcare-v0.7+json',
		);
	}

	/**
	 * Polling del estado de subida desde URL.
	 *
	 * @param string $token Token de la operación from_url.
	 * @return array|WP_Error Datos del archivo o error.
	 */
	private function poll_upload_status( string $token ) {
		$max_attempts = 30;
		$delay        = 1;

		for ( $i = 0; $i < $max_attempts; $i++ ) {
			sleep( $delay );

			$response = wp_remote_get( self::UPLOAD_BASE . 'from_url/status/', array(
				'body'    => array( 'token' => $token ),
				'timeout' => 15,
			) );

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( empty( $body['status'] ) ) {
				return new WP_Error( 'uploadcare_poll', 'Respuesta inesperada del polling.', $body );
			}

			switch ( $body['status'] ) {
				case 'success':
					return $body;

				case 'error':
					return new WP_Error( 'uploadcare_upload_failed', $body['error'] ?? 'Error en la subida.', $body );

				case 'progress':
				case 'waiting':
					continue 2;

				default:
					return new WP_Error( 'uploadcare_unknown_status', "Estado desconocido: {$body['status']}", $body );
			}
		}

		return new WP_Error( 'uploadcare_timeout', 'Timeout esperando la subida del archivo.' );
	}
}
