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

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		ob_start();
		?>
		<div class="space-y-6" id="club-categorias" data-club-id="<?php echo esc_attr( $club_id ); ?>">
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

				<section class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
					<!-- Cabecera categoría -->
					<div class="bg-gray-50 border-b border-gray-200 px-6 py-4 flex items-center justify-between">
						<h2 class="text-lg font-semibold text-gray-800"><?php echo $category_name; ?></h2>
						<span class="club-jugadores-count text-sm text-gray-400"><?php echo count( $jugadores ); ?> jugador<?php echo count( $jugadores ) !== 1 ? 'es' : ''; ?></span>
					</div>

					<!-- Lista de jugadores (sortable) -->
					<div class="club-jugadores divide-y divide-gray-100 min-h-12"
					     data-category-uid="<?php echo esc_attr( $category_uid ); ?>">
						<?php if ( $jugadores ) : ?>
							<?php foreach ( $jugadores as $jugador ) : ?>
								<div class="club-jugador"
								     data-jugador-id="<?php echo esc_attr( $jugador->id ); ?>">
									<!-- Fila principal -->
									<div class="club-jugador__row flex items-center gap-4 px-6 py-4 bg-white hover:bg-gray-50 transition-colors">
										<!-- Handle -->
										<span class="drag-handle shrink-0 text-gray-300 hover:text-gray-500 transition-colors cursor-grab active:cursor-grabbing">
											<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
												<path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
											</svg>
										</span>
										<!-- Foto (clic para subir) -->
										<div class="jugador-foto-trigger shrink-0 w-10 h-10 rounded-full overflow-hidden bg-gray-200 cursor-pointer ring-2 ring-transparent hover:ring-blue-400 transition-all"
										     title="Subir foto">
											<?php if ( $jugador->foto_url ) : ?>
												<img class="w-full h-full object-cover"
												     src="<?php echo esc_url( $jugador->foto_url ); ?>"
												     alt="<?php echo esc_attr( $jugador->nombre ); ?>">
											<?php else : ?>
												<svg class="w-full h-full text-gray-400 p-2" fill="currentColor" viewBox="0 0 20 20">
													<path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/>
												</svg>
											<?php endif; ?>
										</div>
										<!-- Nombre, apellidos y cargo -->
										<div class="flex-1 min-w-0">
											<span class="jugador-nombre-display text-sm font-medium text-gray-800"><?php echo esc_html( trim( $jugador->nombre . ' ' . $jugador->apellidos ) ); ?></span>
											<?php if ( $jugador->cargo ) : ?>
												- <span class="jugador-cargo-display block text-xs text-gray-400"><?php echo esc_html( strtoupper( $jugador->cargo ) ); ?></span>
											<?php endif; ?>
										</div>
										<!-- Toggle foto expandida -->
										<button type="button"
										        class="btn-toggle-foto shrink-0 text-gray-300 hover:text-blue-500 transition-colors <?php echo $jugador->foto_url ? '' : 'hidden'; ?>"
										        title="Ver foto">
											<svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
												<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
											</svg>
										</button>
										<!-- Editar -->
										<button type="button"
										        class="btn-edit-jugador shrink-0 text-gray-300 hover:text-amber-500 transition-colors"
										        title="Editar jugador">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
												<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/>
											</svg>
										</button>
										<!-- Eliminar -->
										<button type="button"
										        class="btn-delete-jugador shrink-0 text-gray-300 hover:text-red-500 transition-colors"
										        data-jugador-id="<?php echo esc_attr( $jugador->id ); ?>"
										        title="Eliminar jugador">
											<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
												<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
											</svg>
										</button>
									</div>
									<!-- Panel de edición -->
									<div class="jugador-edit-panel hidden border-t border-gray-100 px-6 py-4 bg-gray-50">
										<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
											<div>
												<label class="block text-xs text-gray-500 mb-1">Nombre</label>
												<input type="text" class="edit-nombre w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
												       value="<?php echo esc_attr( $jugador->nombre ); ?>">
											</div>
											<div>
												<label class="block text-xs text-gray-500 mb-1">Apellidos</label>
												<input type="text" class="edit-apellidos w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
												       value="<?php echo esc_attr( $jugador->apellidos ); ?>">
											</div>
											<div>
												<label class="block text-xs text-gray-500 mb-1">Cargo</label>
												<input type="text" class="edit-cargo w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
												       value="<?php echo esc_attr( $jugador->cargo ); ?>">
											</div>
										</div>
										<div class="flex gap-2 mt-3">
											<button type="button" class="btn-save-edit bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">
												Guardar
											</button>
											<button type="button" class="btn-cancel-edit text-gray-400 hover:text-gray-600 text-sm px-3 py-1.5 rounded-lg transition-colors">
												Cancelar
											</button>
										</div>
									</div>
									<!-- Foto expandida -->
									<div class="jugador-foto-expanded hidden px-6 py-4">
										<?php if ( $jugador->foto_url ) : ?>
											<img class="rounded-lg max-w-xs"
											     src="<?php echo esc_url( $jugador->foto_url ); ?>"
											     alt="<?php echo esc_attr( $jugador->nombre ); ?>">
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>

					<!-- Bulk add -->
					<div class="bulk-add border-t border-gray-200 px-6 py-4">
						<textarea class="bulk-add__input w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none resize-y"
						          rows="2"
						          placeholder="Un jugador por línea: nombre, apellidos, cargo"
						          data-category-uid="<?php echo esc_attr( $category_uid ); ?>"></textarea>
						<button type="button"
						        class="btn-bulk-add mt-2 inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
						        data-category-uid="<?php echo esc_attr( $category_uid ); ?>">
							<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
								<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
							</svg>
							Añadir en bulk
						</button>
					</div>
				</section>

			<?php endforeach; ?>
		</div>

		<?php
		// ── Estadísticas de fotos ─────────────────────────────
		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total,
			        SUM( CASE WHEN foto_url != '' THEN 1 ELSE 0 END ) AS con_foto
			 FROM {$table}
			 WHERE club_id = %d",
			$club_id
		) );

		$total      = (int) ( $stats->total ?? 0 );
		$con_foto   = (int) ( $stats->con_foto ?? 0 );
		$porcentaje = $total > 0 ? round( $con_foto / $total * 100 ) : 0;

		$bar_color  = $porcentaje === 100 ? 'bg-green-500' : ( $porcentaje >= 80 ? 'bg-blue-500' : ( $porcentaje >= 50 ? 'bg-amber-400' : 'bg-red-400' ) );
		$pct_color  = $porcentaje === 100 ? 'text-green-600' : ( $porcentaje >= 80 ? 'text-blue-600' : ( $porcentaje >= 50 ? 'text-amber-500' : 'text-red-500' ) );
		?>

		<?php if ( $total > 0 ) : ?>
		<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-5"
		     id="club-stats-foto"
		     data-con-foto="<?php echo esc_attr( $con_foto ); ?>"
		     data-total="<?php echo esc_attr( $total ); ?>">
			<div class="flex items-center justify-between mb-3">
				<div class="flex items-center gap-2 text-gray-500">
					<svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.664-.89l.812-1.22A2 2 0 0 1 10.07 4h3.86a2 2 0 0 1 1.664.89l.812 1.22A2 2 0 0 0 18.07 7H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/>
						<path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
					</svg>
					<span class="text-sm font-medium text-gray-700">Fotos del álbum</span>
				</div>
				<span class="stats-porcentaje text-2xl font-bold <?php echo $pct_color; ?>"><?php echo $porcentaje; ?>%</span>
			</div>
			<div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
				<div class="stats-bar h-2.5 rounded-full transition-all duration-500 <?php echo $bar_color; ?>"
				     style="width: <?php echo $porcentaje; ?>%"></div>
			</div>
			<p class="stats-label mt-2 text-xs text-gray-400">
				<span class="stats-con-foto font-medium text-gray-500"><?php echo $con_foto; ?></span>
				miembros de
				<span class="stats-total"><?php echo $total; ?></span>
				tienen foto
			</p>
		</div>
		<?php endif; ?>

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
	 * Actualiza la foto de un jugador.
	 */
	public static function ajax_update_foto(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$jugador_id = absint( $_POST['jugador_id'] ?? 0 );
		$foto_url   = esc_url_raw( $_POST['foto_url'] ?? '' );

		if ( ! $club_id || ! $jugador_id || ! $foto_url ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			array( 'foto_url' => $foto_url ),
			array( 'id' => $jugador_id, 'club_id' => $club_id ),
			array( '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success( array( 'foto_url' => $foto_url ) );
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
		$nombre     = sanitize_text_field( trim( $_POST['nombre'] ?? '' ) );
		$apellidos  = sanitize_text_field( trim( $_POST['apellidos'] ?? '' ) );
		$cargo      = sanitize_text_field( trim( $_POST['cargo'] ?? '' ) );

		if ( ! $club_id || ! $jugador_id || '' === $nombre ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			array(
				'nombre'    => $nombre,
				'apellidos' => $apellidos,
				'cargo'     => $cargo,
			),
			array( 'id' => $jugador_id, 'club_id' => $club_id ),
			array( '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success( array(
			'nombre'    => $nombre,
			'apellidos' => $apellidos,
			'cargo'     => $cargo,
		) );
	}
}
