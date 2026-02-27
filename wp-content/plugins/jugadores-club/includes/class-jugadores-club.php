<?php
/**
 * Clase principal del plugin Jugadores Club.
 *
 * Registra el shortcode [jugadores-club], encola assets
 * y maneja las peticiones AJAX.
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Jugadores_Club {

	/** @var bool Indica si el shortcode se usó en la página actual. */
	private static bool $enqueue = false;

	/**
	 * Registra hooks de WordPress.
	 */
	public static function init(): void {
		add_shortcode( 'jugadores-club', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_footer', array( __CLASS__, 'enqueue_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_album_reorder_jugadores', array( __CLASS__, 'ajax_reorder' ) );
		add_action( 'wp_ajax_album_delete_jugador', array( __CLASS__, 'ajax_delete' ) );
		add_action( 'wp_ajax_album_bulk_add_jugadores', array( __CLASS__, 'ajax_bulk_add' ) );
		add_action( 'wp_ajax_album_update_jugador_foto', array( __CLASS__, 'ajax_update_foto' ) );
		add_action( 'wp_ajax_album_update_jugador', array( __CLASS__, 'ajax_update_jugador' ) );
		add_action( 'wp_ajax_album_add_jugador', array( __CLASS__, 'ajax_add_jugador' ) );
		add_action( 'wp_ajax_album_export_csv', array( __CLASS__, 'ajax_export_csv' ) );
		add_action( 'wp_ajax_album_clear_jugador_foto', array( __CLASS__, 'ajax_clear_foto' ) );
	}

	/**
	 * Renderiza el shortcode [jugadores-club].
	 *
	 * @param array|string $atts Atributos del shortcode.
	 * @return string HTML de las categorías y jugadores.
	 */
	public static function render_shortcode( $atts ): string {
		$atts = shortcode_atts( array(
			'id' => 0,
		), $atts, 'jugadores-club' );

		$club_id = absint( $atts['id'] ) ?: get_the_ID();

		if ( ! $club_id ) {
			return '';
		}

		self::$enqueue = true;

		$categorias = get_field( 'categoria', $club_id );

		if ( ! $categorias ) {
			return '';
		}

		$download_url = add_query_arg( array(
			'action'  => 'album_export_csv',
			'nonce'   => wp_create_nonce( 'album_club_nonce' ),
			'club_id' => $club_id,
		), admin_url( 'admin-ajax.php' ) );

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		ob_start();
		?>
		<div class="tw:space-y-6" id="club-categorias" data-club-id="<?php echo esc_attr( $club_id ); ?>">
			<?php foreach ( $categorias as $cat ) :
				$category_uid  = sanitize_title( $cat['categoria'] );
				$category_name = esc_html( $cat['categoria'] );

				$jugadores = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$table}
					 WHERE club_id = %d AND category_uid = %s
					 ORDER BY menu_order ASC",
					$club_id,
					$category_uid
				) );
			?>

				<section class="tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:overflow-hidden">
					<!-- Cabecera categoría -->
					<div class="tw:bg-gray-50 tw:border-b tw:border-gray-200 tw:px-6 tw:py-4 tw:flex tw:items-center tw:justify-between">
						<h2 class="tw:text-lg tw:font-semibold tw:text-gray-800"><?php echo $category_name; ?></h2>
						<span class="club-jugadores-count tw:text-sm tw:text-gray-400"><?php echo count( $jugadores ); ?> jugador<?php echo count( $jugadores ) !== 1 ? 'es' : ''; ?></span>
					</div>

					<!-- Lista de jugadores (sortable) -->
					<div class="club-jugadores tw:divide-y tw:divide-gray-100 tw:min-h-12"
					     data-category-uid="<?php echo esc_attr( $category_uid ); ?>">
						<?php if ( $jugadores ) : ?>
							<?php foreach ( $jugadores as $jugador ) : ?>
								<div class="club-jugador"
								     data-jugador-id="<?php echo esc_attr( $jugador->id ); ?>"
								     data-nombre-foto="<?php echo esc_attr( $jugador->nombre_foto ?? '' ); ?>">
									<!-- Fila principal -->
									<div class="club-jugador__row tw:flex tw:items-center tw:gap-4 tw:px-6 tw:py-4 tw:bg-white tw:hover:bg-gray-50 tw:transition-colors">
										<!-- Handle -->
										<span class="drag-handle tw:shrink-0 tw:text-gray-300 tw:hover:text-gray-500 tw:transition-colors tw:cursor-grab tw:active:cursor-grabbing">
											<svg class="tw:w-5 tw:h-5" fill="currentColor" viewBox="0 0 20 20">
												<path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
											</svg>
										</span>
										<!-- Foto (clic para subir) -->
										<div class="jugador-foto-trigger tw:shrink-0 tw:w-10 tw:h-10 tw:rounded-full tw:overflow-hidden tw:bg-gray-200 tw:cursor-pointer tw:ring-2 tw:ring-transparent tw:hover:ring-blue-400 tw:transition-all"
										     title="Subir foto">
											<?php if ( $jugador->foto_url ) : ?>
												<img class="tw:w-full tw:h-full tw:object-cover"
												     src="<?php echo esc_url( $jugador->foto_url . '-/preview/100x66/' ); ?>"
												     alt="<?php echo esc_attr( $jugador->nombre ); ?>">
											<?php else : ?>
												<svg class="tw:w-full tw:h-full tw:text-gray-400 tw:p-2" fill="currentColor" viewBox="0 0 20 20">
													<path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/>
												</svg>
											<?php endif; ?>
										</div>
										<!-- Nombre, apellidos y cargo -->
										<div class="tw:flex-1 tw:min-w-0">
											<span class="jugador-nombre-display tw:text-sm tw:font-medium tw:text-gray-800"><?php echo esc_html( trim( $jugador->nombre . ' ' . $jugador->apellidos ) ); ?></span>
											<?php if ( $jugador->cargo ) : ?>
												- <span class="jugador-cargo-display tw:text-xs tw:text-gray-400"><?php echo esc_html( strtoupper( $jugador->cargo ) ); ?></span>
											<?php endif; ?>
											<?php if ( $jugador->nombre_foto ) : ?>
												<span class="jugador-nombre-foto-display tw:text-xs tw:text-sky-800">(<?php echo esc_html( $jugador->nombre_foto ); ?>)</span>
											<?php endif; ?>
										</div>
										<!-- Toggle foto expandida -->
										<button type="button"
										        class="btn-toggle-foto tw:shrink-0 tw:text-gray-300 tw:hover:text-blue-500 tw:transition-colors <?php echo $jugador->foto_url ? '' : 'tw:hidden'; ?>"
										        title="Ver foto">
											<svg class="tw:w-4 tw:h-4 tw:transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
												<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
											</svg>
										</button>
										<!-- Editar -->
										<button type="button"
										        class="btn-edit-jugador tw:shrink-0 tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors"
										        title="Editar jugador">
											<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
												<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/>
											</svg>
										</button>
										<!-- Eliminar -->
										<button type="button"
										        class="btn-delete-jugador tw:shrink-0 tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors"
										        data-jugador-id="<?php echo esc_attr( $jugador->id ); ?>"
										        title="Eliminar jugador">
											<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
												<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
											</svg>
										</button>
									</div>
									<!-- Panel de edición -->
									<div class="jugador-edit-panel tw:hidden tw:border-t tw:border-gray-100 tw:px-6 tw:py-4 tw:bg-gray-50">
										<div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-3 tw:gap-3">
											<div>
												<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre</label>
												<input type="text" class="edit-nombre tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
												       value="<?php echo esc_attr( $jugador->nombre ); ?>">
											</div>
											<div>
												<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Apellidos</label>
												<input type="text" class="edit-apellidos tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
												       value="<?php echo esc_attr( $jugador->apellidos ); ?>">
											</div>
											<div>
												<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Cargo</label>
												<input type="text" class="edit-cargo tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
												       value="<?php echo esc_attr( $jugador->cargo ); ?>">
											</div>
										</div>
										<div class="tw:mt-3">
											<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre foto</label>
											<input type="text" class="edit-nombre-foto tw:w-full tw:sm:w-64 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
											       maxlength="64"
											       placeholder="ej. 9999.jpg"
											       value="<?php echo esc_attr( $jugador->nombre_foto ?? '' ); ?>">
											<p class="tw:mt-1 tw:text-xs tw:text-gray-400">Si la foto llega por otros medios, indica aquí su nombre de archivo (máx. 32 caracteres).</p>
										</div>
										<div class="tw:flex tw:gap-2 tw:mt-3">
											<button type="button" class="btn-save-edit tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-1.5 tw:rounded-lg tw:transition-colors">
												Guardar
											</button>
											<button type="button" class="btn-cancel-edit tw:text-gray-400 tw:hover:text-gray-600 tw:text-sm tw:px-3 tw:py-1.5 tw:rounded-lg tw:transition-colors">
												Cancelar
											</button>
										</div>
									</div>
									<!-- Foto expandida -->
									<div class="jugador-foto-expanded tw:hidden tw:px-6 tw:py-4">
										<?php if ( $jugador->foto_url ) : ?>
											<img class="tw:rounded-lg tw:max-w-xl tw:w-full"
											     src="<?php echo esc_url( $jugador->foto_url ) . '-/preview/1000x666/'; ?>"
											     alt="<?php echo esc_attr( $jugador->nombre ); ?>">
											<button type="button"
											        class="btn-unassign-foto tw:mt-2 tw:inline-flex tw:items-center tw:gap-1 tw:text-sm tw:text-red-400 tw:hover:text-red-600 tw:transition-colors"
											        title="Quitar foto">
												<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
													<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
												</svg>
												Quitar foto
											</button>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Bulk add -->
					<div class="bulk-add tw:border-t tw:border-gray-200 tw:px-6 tw:py-4">
						<textarea class="bulk-add__input tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-2 tw:text-sm tw:text-gray-700 tw:placeholder-gray-400 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none tw:resize-y"
						          rows="2"
						          placeholder="Un jugador por línea: nombre, apellidos, cargo"
						          data-category-uid="<?php echo esc_attr( $category_uid ); ?>"></textarea>
						<button type="button"
						        class="btn-bulk-add tw:mt-2 tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors"
						        data-category-uid="<?php echo esc_attr( $category_uid ); ?>">
							<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
								<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
							</svg>
							Añadir en bulk
						</button>
					</div>
					<!-- Single add -->
					<div class="single-add tw:border-t tw:border-gray-200 tw:px-6 tw:py-4"
					     data-category-uid="<?php echo esc_attr( $category_uid ); ?>">
						<div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-3 tw:gap-3">
							<div>
								<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre</label>
								<input type="text" class="single-add__nombre tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none" placeholder="Nombre">
							</div>
							<div>
								<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Apellidos</label>
								<input type="text" class="single-add__apellidos tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none" placeholder="Apellidos">
							</div>
							<div>
								<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Cargo</label>
								<input type="text" class="single-add__cargo tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none" placeholder="Cargo">
							</div>
						</div>
						<div class="tw:mt-3">
							<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre foto</label>
							<input type="text" class="single-add__nombre-foto tw:w-full tw:sm:w-64 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none" maxlength="32" placeholder="ej. 9999.jpg">
							<p class="tw:mt-1 tw:text-xs tw:text-gray-400">Si la foto llega por otros medios, indica aquí su nombre de archivo (máx. 32 caracteres).</p>
						</div>
						<div class="tw:mt-3">
							<button type="button"
							        class="btn-single-add tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors">
								<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
								</svg>
								Añadir jugador
							</button>
						</div>
					</div>
				</section>

			<?php endforeach; ?>
		</div>

		<?php
		// ── Estadísticas de fotos ─────────────────────────────
		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total,
			        SUM( CASE WHEN foto_url != '' OR ( nombre_foto IS NOT NULL AND nombre_foto != '' ) THEN 1 ELSE 0 END ) AS con_foto
			 FROM {$table}
			 WHERE club_id = %d",
			$club_id
		) );

		$total      = (int) ( $stats->total ?? 0 );
		$con_foto   = (int) ( $stats->con_foto ?? 0 );
		$porcentaje = $total > 0 ? round( $con_foto / $total * 100 ) : 0;

		$bar_color  = $porcentaje === 100 ? 'tw:bg-green-500' : ( $porcentaje >= 80 ? 'tw:bg-blue-500' : ( $porcentaje >= 50 ? 'tw:bg-amber-400' : 'tw:bg-red-400' ) );
		$pct_color  = $porcentaje === 100 ? 'tw:text-green-600' : ( $porcentaje >= 80 ? 'tw:text-blue-600' : ( $porcentaje >= 50 ? 'tw:text-amber-500' : 'tw:text-red-500' ) );
		?>

		<?php if ( $total > 0 ) : ?>
		<div class="tw:mt-6 tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:px-6 tw:py-5"
		     id="club-stats-foto"
		     data-con-foto="<?php echo esc_attr( $con_foto ); ?>"
		     data-total="<?php echo esc_attr( $total ); ?>">
			<div class="tw:flex tw:items-center tw:justify-between tw:mb-3">
				<div class="tw:flex tw:items-center tw:gap-2 tw:text-gray-500">
					<svg class="tw:w-4 tw:h-4 tw:shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.664-.89l.812-1.22A2 2 0 0 1 10.07 4h3.86a2 2 0 0 1 1.664.89l.812 1.22A2 2 0 0 0 18.07 7H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/>
						<path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
					</svg>
					<span class="tw:text-sm tw:font-medium tw:text-gray-700">Fotos del álbum</span>
				</div>
				<span class="stats-porcentaje tw:text-2xl tw:font-bold <?php echo $pct_color; ?>"><?php echo $porcentaje; ?>%</span>
			</div>
			<div class="tw:w-full tw:bg-gray-100 tw:rounded-full tw:h-2.5 tw:overflow-hidden">
				<div class="stats-bar tw:h-2.5 tw:rounded-full tw:transition-all tw:duration-500 <?php echo $bar_color; ?>"
				     style="width: <?php echo $porcentaje; ?>%"></div>
			</div>
			<p class="stats-label tw:mt-2 tw:text-xs tw:text-gray-400">
				<span class="stats-con-foto tw:font-medium tw:text-gray-500"><?php echo $con_foto; ?></span>
				miembros de
				<span class="stats-total"><?php echo $total; ?></span>
				tienen foto
			</p>
		</div>
		<?php endif; ?>

		<div class="tw:mt-6 tw:mb-6 tw:flex tw:justify-end">
			<a href="<?php echo esc_url( $download_url ); ?>"
			   class="tw:inline-flex tw:items-center tw:gap-2 tw:bg-gray-700 tw:hover:bg-gray-800 tw:text-white tw:text-sm tw:font-medium tw:px-5 tw:py-2.5 tw:rounded-lg tw:transition-colors">
				<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
					<path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
				</svg>
				Descarga la información
			</a>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * Encola SortableJS + club-sortable.js solo si el shortcode se usó.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::$enqueue ) {
			return;
		}

		wp_enqueue_style(
			'jugadores-club',
			JC_URI . 'assets/css/jugadores-club.css',
			array(),
			JC_VERSION
		);

        wp_enqueue_style(
                'css-tailwind',
                JC_URI . 'assets/css/tailwind.css',
                array(),
                JC_VERSION
        );

        wp_enqueue_script(
			'sortablejs',
			'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js',
			array(),
			'1.15.6',
			true
		);

		wp_enqueue_script(
			'jc-club-sortable',
			JC_URI . 'assets/js/club-sortable.js',
			array( 'sortablejs' ),
			JC_VERSION,
			true
		);

		wp_localize_script( 'jc-club-sortable', 'albumClub', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'album_club_nonce' ),
			'ucPubKey' => defined( 'UPLOADCARE_PUBLIC_KEY' ) ? UPLOADCARE_PUBLIC_KEY : '',
		) );
	}

	// ─── AJAX handlers ─────────────────────────────────────

	/**
	 * Reordena jugadores tras drag & drop.
	 */
	public static function ajax_reorder(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$categories = json_decode( stripslashes( $_POST['categories'] ?? '' ), true );

		if ( ! $club_id || ! is_array( $categories ) ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		foreach ( $categories as $cat ) {
			$category_uid = sanitize_text_field( $cat['category_uid'] ?? '' );
			$jugador_ids  = $cat['jugador_ids'] ?? array();

			if ( ! $category_uid || ! is_array( $jugador_ids ) ) {
				continue;
			}

			foreach ( $jugador_ids as $order => $id ) {
				$wpdb->update(
					$table,
					array(
						'category_uid' => $category_uid,
						'menu_order'   => $order,
					),
					array(
						'id'      => absint( $id ),
						'club_id' => $club_id,
					),
					array( '%s', '%d' ),
					array( '%d', '%d' )
				);
			}
		}

		wp_send_json_success();
	}

	/**
	 * Elimina un jugador.
	 */
	public static function ajax_delete(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$jugador_id = absint( $_POST['jugador_id'] ?? 0 );

		if ( ! $club_id || ! $jugador_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'club_jugadores',
			array( 'id' => $jugador_id, 'club_id' => $club_id ),
			array( '%d', '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( 'Error al eliminar.' );
		}

		wp_send_json_success();
	}

	/**
	 * Añade jugadores en bulk.
	 */
	public static function ajax_bulk_add(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$category_uid = sanitize_text_field( $_POST['category_uid'] ?? '' );
		$jugadores    = json_decode( stripslashes( $_POST['jugadores'] ?? '' ), true );

		if ( ! $club_id || ! $category_uid || ! is_array( $jugadores ) || empty( $jugadores ) ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(menu_order), -1) FROM {$table} WHERE club_id = %d AND category_uid = %s",
			$club_id,
			$category_uid
		) );

		$inserted = array();

		foreach ( $jugadores as $jugador ) {
			$nombre    = sanitize_text_field( trim( $jugador['nombre'] ?? '' ) );
			$apellidos = sanitize_text_field( trim( $jugador['apellidos'] ?? '' ) );
			$cargo     = sanitize_text_field( trim( $jugador['cargo'] ?? '' ) );

			if ( '' === $nombre ) {
				continue;
			}

			$max_order++;

			$wpdb->insert( $table, array(
				'club_id'      => $club_id,
				'category_uid' => $category_uid,
				'nombre'       => $nombre,
				'apellidos'    => $apellidos,
				'cargo'        => $cargo,
				'foto_url'     => '',
				'menu_order'   => $max_order,
			), array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' ) );

			$inserted[] = array(
				'id'        => $wpdb->insert_id,
				'nombre'    => $nombre,
				'apellidos' => $apellidos,
				'cargo'     => $cargo,
				'foto_url'  => '',
			);
		}

		wp_send_json_success( $inserted );
	}

	/**
	 * Actualiza la foto de un jugador y opcionalmente su nombre_foto.
	 */
	public static function ajax_update_foto(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id     = absint( $_POST['club_id'] ?? 0 );
		$jugador_id  = absint( $_POST['jugador_id'] ?? 0 );
		$foto_url    = esc_url_raw( $_POST['foto_url'] ?? '' );
		$nombre_foto = sanitize_text_field( trim( $_POST['nombre_foto'] ?? '' ) );
		$nombre_foto = substr( $nombre_foto, 0, 64 );

		if ( ! $club_id || ! $jugador_id || ! $foto_url ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		$data    = array( 'foto_url' => $foto_url );
		$formats = array( '%s' );

		if ( '' !== $nombre_foto ) {
			$data['nombre_foto'] = $nombre_foto;
			$formats[]           = '%s';
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			$data,
			array( 'id' => $jugador_id, 'club_id' => $club_id ),
			$formats,
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success( array( 'foto_url' => $foto_url, 'nombre_foto' => $nombre_foto ) );
	}

	/**
	 * Desasigna la foto de un jugador (vacía foto_url).
	 */
	public static function ajax_clear_foto(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$jugador_id = absint( $_POST['jugador_id'] ?? 0 );

		if ( ! $club_id || ! $jugador_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			array( 'foto_url' => '' ),
			array( 'id' => $jugador_id, 'club_id' => $club_id ),
			array( '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success();
	}

	/**
	 * Actualiza nombre, apellidos y cargo de un jugador.
	 */
	public static function ajax_update_jugador(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$jugador_id = absint( $_POST['jugador_id'] ?? 0 );
		$nombre      = sanitize_text_field( trim( $_POST['nombre'] ?? '' ) );
		$apellidos   = sanitize_text_field( trim( $_POST['apellidos'] ?? '' ) );
		$cargo       = sanitize_text_field( trim( $_POST['cargo'] ?? '' ) );
		$nombre_foto = sanitize_text_field( trim( $_POST['nombre_foto'] ?? '' ) );
		$nombre_foto = substr( $nombre_foto, 0, 32 );

		if ( ! $club_id || ! $jugador_id || '' === $nombre ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			array(
				'nombre'      => $nombre,
				'apellidos'   => $apellidos,
				'cargo'       => $cargo,
				'nombre_foto' => '' !== $nombre_foto ? $nombre_foto : null,
			),
			array( 'id' => $jugador_id, 'club_id' => $club_id ),
			array( '%s', '%s', '%s', '' !== $nombre_foto ? '%s' : null ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success( array(
			'nombre'      => $nombre,
			'apellidos'   => $apellidos,
			'cargo'       => $cargo,
			'nombre_foto' => $nombre_foto,
		) );
	}

	/**
	 * Añade un único jugador con todos sus campos.
	 */
	public static function ajax_add_jugador(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$category_uid = sanitize_text_field( trim( $_POST['category_uid'] ?? '' ) );
		$nombre       = sanitize_text_field( trim( $_POST['nombre'] ?? '' ) );
		$apellidos    = sanitize_text_field( trim( $_POST['apellidos'] ?? '' ) );
		$cargo        = sanitize_text_field( trim( $_POST['cargo'] ?? '' ) );
		$nombre_foto  = sanitize_text_field( trim( $_POST['nombre_foto'] ?? '' ) );
		$nombre_foto  = substr( $nombre_foto, 0, 32 );

		if ( ! $club_id || ! $category_uid || '' === $nombre ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(menu_order), -1) FROM {$table} WHERE club_id = %d AND category_uid = %s",
			$club_id,
			$category_uid
		) );

		$wpdb->insert( $table, array(
			'club_id'      => $club_id,
			'category_uid' => $category_uid,
			'nombre'       => $nombre,
			'apellidos'    => $apellidos,
			'cargo'        => $cargo,
			'nombre_foto'  => '' !== $nombre_foto ? $nombre_foto : null,
			'foto_url'     => '',
			'menu_order'   => $max_order + 1,
		), array( '%d', '%s', '%s', '%s', '%s', '' !== $nombre_foto ? '%s' : null, '%s', '%d' ) );

		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( 'Error al insertar.' );
		}

		wp_send_json_success( array(
			'id'         => $wpdb->insert_id,
			'nombre'     => $nombre,
			'apellidos'  => $apellidos,
			'cargo'      => $cargo,
			'nombre_foto' => $nombre_foto,
			'foto_url'   => '',
		) );
	}

	/**
	 * Genera y descarga un CSV con todas las categorías y jugadores del club.
	 *
	 * Estructura del CSV:
	 *   [nombre de la categoría]
	 *   nombre_foto,foto_url,nombre,apellidos,cargo
	 *   [fila por jugador, ordenados por menu_order]
	 *   [línea en blanco entre categorías]
	 */
	public static function ajax_export_csv(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( 'Sin permisos.' );
		}

		$club_id = absint( $_GET['club_id'] ?? 0 );

		if ( ! $club_id ) {
			wp_die( 'Datos inválidos.' );
		}

		$categorias = get_field( 'categoria', $club_id );

		if ( ! $categorias ) {
			wp_die( 'Sin categorías.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		$post     = get_post( $club_id );
		$slug     = $post ? sanitize_file_name( $post->post_title ) : 'club';
		$filename = 'jugadores-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );

		// BOM UTF-8 para compatibilidad con Excel.
		fwrite( $output, "\xEF\xBB\xBF" );

		foreach ( $categorias as $cat ) {
			$category_uid  = sanitize_title( $cat['categoria'] );
			$category_name = $cat['categoria'];

			// Fila de categoría.
			fputcsv( $output, array( $category_name ) );

			// Cabecera de campos.
			fputcsv( $output, array( 'nombre_foto', 'foto_url', 'nombre', 'apellidos', 'cargo' ) );

			// Jugadores ordenados.
			$jugadores = $wpdb->get_results( $wpdb->prepare(
				"SELECT nombre_foto, foto_url, nombre, apellidos, cargo
				 FROM {$table}
				 WHERE club_id = %d AND category_uid = %s
				 ORDER BY menu_order ASC",
				$club_id,
				$category_uid
			) );

			foreach ( $jugadores as $jugador ) {
				fputcsv( $output, array(
					$jugador->nombre_foto ?? '',
					$jugador->foto_url,
					$jugador->nombre,
					$jugador->apellidos,
					$jugador->cargo,
				) );
			}

			// Línea en blanco entre categorías.
			fputcsv( $output, array() );
		}

		fclose( $output );
		exit;
	}
}
