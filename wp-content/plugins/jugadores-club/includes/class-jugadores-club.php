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

		// AJAX handlers — jugadores.
		add_action( 'wp_ajax_album_reorder_jugadores',  array( __CLASS__, 'ajax_reorder' ) );
		add_action( 'wp_ajax_album_delete_jugador',     array( __CLASS__, 'ajax_delete' ) );
		add_action( 'wp_ajax_album_bulk_add_jugadores', array( __CLASS__, 'ajax_bulk_add' ) );
		add_action( 'wp_ajax_album_update_jugador_foto',array( __CLASS__, 'ajax_update_foto' ) );
		add_action( 'wp_ajax_album_update_jugador',     array( __CLASS__, 'ajax_update_jugador' ) );
		add_action( 'wp_ajax_album_add_jugador',        array( __CLASS__, 'ajax_add_jugador' ) );
		add_action( 'wp_ajax_album_export_csv',         array( __CLASS__, 'ajax_export_csv' ) );
		add_action( 'wp_ajax_album_export_zip',         array( __CLASS__, 'ajax_export_zip' ) );
		add_action( 'wp_ajax_album_clear_jugador_foto', array( __CLASS__, 'ajax_clear_foto' ) );

		// AJAX handlers — categorías.
		add_action( 'wp_ajax_album_add_categoria',        array( __CLASS__, 'ajax_add_categoria' ) );
		add_action( 'wp_ajax_album_delete_categoria',     array( __CLASS__, 'ajax_delete_categoria' ) );
		add_action( 'wp_ajax_album_rename_categoria',     array( __CLASS__, 'ajax_rename_categoria' ) );
		add_action( 'wp_ajax_album_reorder_categorias',   array( __CLASS__, 'ajax_reorder_categorias' ) );
		add_action( 'wp_ajax_album_move_jugador',         array( __CLASS__, 'ajax_move_jugador' ) );
		add_action( 'wp_ajax_album_duplicate_jugador',    array( __CLASS__, 'ajax_duplicate_jugador' ) );
		add_action( 'wp_ajax_album_sort_alfabetico',      array( __CLASS__, 'ajax_sort_alfabetico' ) );

		// AJAX handlers — equipo.
		add_action( 'wp_ajax_album_add_equipo',         array( __CLASS__, 'ajax_add_equipo' ) );
		add_action( 'wp_ajax_album_delete_equipo',       array( __CLASS__, 'ajax_delete_equipo' ) );
		add_action( 'wp_ajax_album_update_equipo',       array( __CLASS__, 'ajax_update_equipo' ) );
		add_action( 'wp_ajax_album_update_equipo_foto',  array( __CLASS__, 'ajax_update_equipo_foto' ) );
		add_action( 'wp_ajax_album_clear_equipo_foto',   array( __CLASS__, 'ajax_clear_equipo_foto' ) );
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

		if ( ! self::user_can_access_club( $club_id ) ) {
			return '';
		}

		self::$enqueue = true;

		$user    = wp_get_current_user();
		$can_bulk_add = ! in_array( 'club', (array) $user->roles, true );

		global $wpdb;
		$t_cat    = $wpdb->prefix . 'club_categorias';
		$t_jug    = $wpdb->prefix . 'club_jugadores';
		$t_equipo = $wpdb->prefix . 'club_equipo';

		$categorias = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t_cat} WHERE post_id = %d ORDER BY menu_order ASC, id ASC",
			$club_id
		) );

		$download_url = add_query_arg( array(
			'action'  => 'album_export_csv',
			'nonce'   => wp_create_nonce( 'album_club_nonce' ),
			'club_id' => $club_id,
		), admin_url( 'admin-ajax.php' ) );

		$zip_url = add_query_arg( array(
			'action'  => 'album_export_zip',
			'nonce'   => wp_create_nonce( 'album_club_nonce' ),
			'club_id' => $club_id,
		), admin_url( 'admin-ajax.php' ) );

		ob_start();
		?>
		<div class="tw:space-y-10" id="club-categorias" data-club-id="<?php echo esc_attr( $club_id ); ?>">

			<!-- Añadir categoría -->
			<div class="add-categoria-form tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:px-6 tw:py-4 tw:flex tw:items-center tw:gap-3">
				<input type="text"
				       class="add-categoria__input tw:flex-1 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
				       placeholder="Nueva categoría (ej. Infantil A)">
				<button type="button"
				        class="btn-add-categoria tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors">
					<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
					</svg>
					Añadir categoría
				</button>
			</div>

			<?php if ( ! $categorias ) : ?>
				<p class="tw:text-sm tw:text-gray-400 tw:text-center tw:py-8">No hay categorías todavía. Añade la primera.</p>
			<?php else : ?>
			<?php foreach ( $categorias as $cat ) :
				$categoria_id  = (int) $cat->id;
				$category_name = esc_html( $cat->descripcion );

				$jugadores = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$t_jug}
					 WHERE categoria_id = %d
					 ORDER BY menu_order ASC",
					$categoria_id
				) );
				$num_jugadores = count( $jugadores );

				$equipos = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$t_equipo} WHERE categoria_id = %d ORDER BY menu_order ASC",
					$categoria_id
				) );
			?>

			<section class="tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:overflow-hidden"
			         data-categoria-id="<?php echo esc_attr( $categoria_id ); ?>">

				<!-- Cabecera categoría -->
				<div class="tw:bg-gray-50 tw:border-b tw:border-gray-200 tw:flex tw:items-center">
					<span class="drag-handle-categoria tw:pl-4 tw:pr-1 tw:shrink-0 tw:text-gray-300 tw:hover:text-gray-500 tw:transition-colors tw:cursor-grab tw:active:cursor-grabbing" title="Arrastrar para reordenar">
						<svg class="tw:w-4 tw:h-4" fill="currentColor" viewBox="0 0 20 20">
							<path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>
						</svg>
					</span>
					<button type="button"
					        class="btn-toggle-categoria tw:flex-1 tw:px-6 tw:py-4 tw:flex tw:items-center tw:justify-between tw:text-left tw:hover:bg-gray-100 tw:transition-colors">
						<h2 class="tw:text-lg tw:font-semibold tw:text-gray-800"><?php echo $category_name; ?></h2>
						<div class="tw:flex tw:items-center tw:gap-3">
							<span class="club-jugadores-count tw:text-sm tw:text-gray-400"><?php echo $num_jugadores; ?> jugador<?php echo $num_jugadores !== 1 ? 'es' : ''; ?></span>
							<svg class="categoria-chevron tw:w-4 tw:h-4 tw:text-gray-400 tw:transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
								<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
							</svg>
						</div>
					</button>
					<div class="tw:flex tw:items-center tw:gap-1 tw:pr-4">
						<button type="button"
						        class="btn-rename-categoria tw:p-2 tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors tw:rounded"
						        title="Renombrar categoría">
							<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
								<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/>
							</svg>
						</button>
						<button type="button"
						        class="btn-sort-alfabetico tw:p-2 tw:text-gray-300 tw:hover:text-blue-500 tw:transition-colors tw:rounded"
						        title="Ordenar alfabéticamente">
							<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
								<path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h14.25M3 9h9.75M3 13.5h9.75m4.5-4.5v12m0 0-3.75-3.75M17.25 21 21 17.25"/>
							</svg>
						</button>
						<button type="button"
						        class="btn-delete-categoria tw:p-2 tw:transition-colors tw:rounded <?php echo $num_jugadores > 0 ? 'tw:text-gray-200 tw:cursor-not-allowed' : 'tw:text-gray-300 tw:hover:text-red-500'; ?>"
						        title="<?php echo $num_jugadores > 0 ? 'No se puede eliminar: tiene jugadores' : 'Eliminar categoría'; ?>"
						        <?php echo $num_jugadores > 0 ? 'disabled' : ''; ?>>
							<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
								<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
							</svg>
						</button>
					</div>
				</div>

				<!-- Panel de renombrado (oculto) -->
				<div class="categoria-rename-panel tw:hidden tw:border-b tw:border-gray-200 tw:bg-gray-50 tw:px-6 tw:py-3 tw:flex tw:items-center tw:gap-3">
					<input type="text"
					       class="rename-descripcion tw:flex-1 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
					       value="<?php echo esc_attr( $cat->descripcion ); ?>">
					<button type="button" class="btn-save-rename tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-1.5 tw:rounded-lg tw:transition-colors">Guardar</button>
					<button type="button" class="btn-cancel-rename tw:text-gray-400 tw:hover:text-gray-600 tw:text-sm tw:px-3 tw:py-1.5 tw:rounded-lg tw:transition-colors">Cancelar</button>
				</div>

				<!-- Cuerpo de la categoría (colapsable) -->
				<div class="category-body tw:hidden">

				<!-- Fotos de grupo -->
				<div class="club-equipo-section tw:px-6 tw:pt-4 tw:pb-3 tw:border-b tw:border-gray-100">
					<h3 class="tw:text-xs tw:font-semibold tw:text-gray-400 tw:uppercase tw:tracking-wide tw:mb-3">Fotos de grupo</h3>
					<div class="club-equipo tw:grid tw:grid-cols-2 tw:sm:grid-cols-4 tw:gap-4"
					     data-categoria-id="<?php echo esc_attr( $categoria_id ); ?>">
						<?php foreach ( $equipos as $equipo ) : ?>
						<div class="club-equipo-item" data-equipo-id="<?php echo esc_attr( $equipo->id ); ?>"
					     data-nombre-foto="<?php echo esc_attr( $equipo->nombre_foto ?? '' ); ?>">
							<div class="equipo-foto-trigger tw:aspect-video tw:rounded-lg tw:overflow-hidden tw:bg-gray-100 tw:cursor-pointer tw:ring-2 tw:ring-transparent tw:hover:ring-blue-400 tw:transition-all" title="Subir foto">
								<?php if ( $equipo->foto_url ) : ?>
									<img class="tw:w-full tw:h-full tw:object-cover"
									     src="<?php echo esc_url( $equipo->foto_url ) . '-/preview/1000x666/'; ?>"
									     alt="<?php echo esc_attr( $equipo->descripcion ); ?>">
								<?php else : ?>
									<div class="tw:w-full tw:h-full tw:flex tw:items-center tw:justify-center tw:text-gray-300">
										<svg class="tw:w-8 tw:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
											<path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.776 48.776 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
											<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
										</svg>
									</div>
								<?php endif; ?>
							</div>
							<?php if ( $equipo->foto_url ) : ?>
							<button type="button"
							        class="btn-clear-equipo-foto tw:mt-1 tw:inline-flex tw:items-center tw:gap-1 tw:text-xs tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors"
							        data-equipo-id="<?php echo esc_attr( $equipo->id ); ?>"
							        title="Quitar foto">
								<svg class="tw:w-3 tw:h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
									<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
								</svg>
								Quitar foto
							</button>
							<?php endif; ?>
							<div class="tw:mt-1.5 tw:flex-col tw:items-start tw:justify-between tw:gap-1">
								<span class="equipo-descripcion-display tw:w-full tw:text-xs tw:font-medium tw:text-gray-700 tw:uppercase tw:leading-snug"><?php echo esc_html( $equipo->descripcion ); ?></span>
						<?php if ( $equipo->nombre_foto ) : ?>
						<span class="equipo-nombre-foto-display tw:w-full tw:block tw:text-xs tw:text-gray-400"><?php echo esc_html( $equipo->nombre_foto ); ?></span>
						<?php endif; ?>
								<div class="tw:flex tw:items-center tw:shrink-0">
									<button type="button" class="btn-edit-equipo tw:p-1 tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors" title="Editar descripción">
										<svg class="tw:w-3.5 tw:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
											<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/>
										</svg>
									</button>
									<button type="button" class="btn-delete-equipo tw:p-1 tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors" data-equipo-id="<?php echo esc_attr( $equipo->id ); ?>" title="Eliminar">
										<svg class="tw:w-3.5 tw:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
											<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
										</svg>
									</button>
								</div>
							</div>
							<div class="equipo-edit-panel tw:hidden tw:mt-2">
								<input type="text" class="edit-equipo-descripcion tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-2 tw:py-1 tw:text-xs tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" value="<?php echo esc_attr( $equipo->descripcion ); ?>">
								<div class="tw:flex tw:gap-2 tw:mt-1.5">
									<button type="button" class="btn-save-equipo-edit tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-xs tw:font-medium tw:px-3 tw:py-1 tw:rounded-lg tw:transition-colors">Guardar</button>
									<button type="button" class="btn-cancel-equipo-edit tw:text-gray-400 tw:hover:text-gray-600 tw:text-xs tw:px-2 tw:py-1 tw:rounded-lg tw:transition-colors">Cancelar</button>
								</div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
					<!-- Formulario añadir foto de grupo -->
					<div class="equipo-add-form tw:mt-3 tw:flex tw:items-center tw:gap-3">
						<input type="text" class="equipo-add__descripcion tw:flex-1 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none" placeholder="Descripción (ej. Foto oficial temporada)" value="Foto de Grupo">
						<button type="button" class="btn-add-equipo tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors" data-categoria-id="<?php echo esc_attr( $categoria_id ); ?>">
							<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
								<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
							</svg>
							Añadir foto
						</button>
					</div>
				</div>

				<!-- Fotos de los miembros -->
				<div class="tw:px-6 tw:pt-4 tw:pb-2">
					<h3 class="tw:text-xs tw:font-semibold tw:text-gray-400 tw:uppercase tw:tracking-wide">Fotos de los miembros</h3>
				</div>

				<!-- Lista de jugadores (sortable) -->
				<div class="club-jugadores tw:divide-y tw:divide-gray-100 tw:min-h-12"
				     data-categoria-id="<?php echo esc_attr( $categoria_id ); ?>">
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
									<!-- Mover a otra categoría -->
									<button type="button"
									        class="btn-move-jugador tw:shrink-0 tw:text-gray-300 tw:hover:text-indigo-500 tw:transition-colors"
									        title="Mover a otra categoría">
										<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
											<path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
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
								<!-- Panel de movimiento/copia -->
								<div class="jugador-move-panel tw:hidden tw:border-t tw:border-gray-100 tw:px-6 tw:py-3 tw:bg-gray-50 tw:flex tw:items-center tw:gap-3">
									<label class="tw:text-xs tw:text-gray-500 tw:shrink-0">Categoría:</label>
									<select class="move-categoria-select tw:flex-1 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none tw:bg-white"></select>
									<button type="button" class="btn-confirm-move tw:bg-indigo-600 tw:hover:bg-indigo-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-1.5 tw:rounded-lg tw:transition-colors">Mover</button>
									<button type="button" class="btn-confirm-copy tw:bg-teal-600 tw:hover:bg-teal-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-1.5 tw:rounded-lg tw:transition-colors">Copiar</button>
									<button type="button" class="btn-cancel-move tw:text-gray-400 tw:hover:text-gray-600 tw:text-sm tw:px-3 tw:py-1.5 tw:rounded-lg tw:transition-colors">Cancelar</button>
								</div>
								<!-- Foto expandida -->
								<div class="jugador-foto-expanded tw:hidden tw:px-6 tw:py-4">
									<?php if ( $jugador->foto_url ) : ?>
										<img class="tw:rounded-lg tw:max-w-xl tw:w-full"
										     src="<?php echo esc_url( $jugador->foto_url ); ?>"
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

				<!-- Separador añadir jugadores -->
				<div class="tw:border-t-2 tw:border-gray-200 tw:px-6 tw:py-3 tw:bg-gray-50">
					<h3 class="tw:text-xs tw:font-semibold tw:text-gray-400 tw:uppercase tw:tracking-wide">Añadir jugadores</h3>
				</div>
				<!-- Single add -->
				<div class="single-add tw:border-t tw:border-gray-200 tw:px-6 tw:py-4"
				     data-categoria-id="<?php echo esc_attr( $categoria_id ); ?>">
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
				<?php if ( $can_bulk_add ) : ?>
				<!-- Bulk add -->
				<div class="bulk-add tw:border-t tw:border-gray-200 tw:px-6 tw:py-4">
					<textarea class="bulk-add__input tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-2 tw:text-sm tw:text-gray-700 tw:placeholder-gray-400 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none tw:resize-y"
					          rows="2"
					          placeholder="Un jugador por línea: nombre, apellidos, cargo"
					          data-categoria-id="<?php echo esc_attr( $categoria_id ); ?>"></textarea>
					<button type="button"
					        class="btn-bulk-add tw:mt-2 tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors"
					        data-categoria-id="<?php echo esc_attr( $categoria_id ); ?>">
						<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
						</svg>
						Añadir en bulk
					</button>
				</div>
				<?php endif; ?>
				</div><!-- /.category-body -->
			</section>

			<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<?php
		// ── Estadísticas de fotos ─────────────────────────────
		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS total,
			        SUM( CASE WHEN j.foto_url != '' OR ( j.nombre_foto IS NOT NULL AND j.nombre_foto != '' ) THEN 1 ELSE 0 END ) AS con_foto
			 FROM {$t_jug} j
			 JOIN {$t_cat} c ON j.categoria_id = c.id
			 WHERE c.post_id = %d",
			$club_id
		) );

		$total      = (int) ( $stats->total ?? 0 );
		$con_foto   = (int) ( $stats->con_foto ?? 0 );
		$porcentaje = $total > 0 ? round( $con_foto / $total * 100 ) : 0;

		$bar_color = $porcentaje === 100 ? 'tw:bg-green-500' : ( $porcentaje >= 80 ? 'tw:bg-blue-500' : ( $porcentaje >= 50 ? 'tw:bg-amber-400' : 'tw:bg-red-400' ) );
		$pct_color = $porcentaje === 100 ? 'tw:text-green-600' : ( $porcentaje >= 80 ? 'tw:text-blue-600' : ( $porcentaje >= 50 ? 'tw:text-amber-500' : 'tw:text-red-500' ) );
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

		<div class="tw:mt-6 tw:mb-6 tw:flex tw:justify-end tw:gap-3">
			<a href="<?php echo esc_url( $download_url ); ?>"
			   class="tw:inline-flex tw:items-center tw:gap-2 tw:bg-gray-700 tw:hover:bg-gray-800 tw:text-white tw:text-sm tw:font-medium tw:px-5 tw:py-2.5 tw:rounded-lg tw:transition-colors">
				<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
					<path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
				</svg>
				Descarga la información
			</a>
			<a href="<?php echo esc_url( $zip_url ); ?>"
			   class="tw:inline-flex tw:items-center tw:gap-2 tw:bg-indigo-600 tw:hover:bg-indigo-700 tw:text-white tw:text-sm tw:font-medium tw:px-5 tw:py-2.5 tw:rounded-lg tw:transition-colors">
				<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
					<path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
				</svg>
				Descarga Info y Fotos
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

		$current_user = wp_get_current_user();

		wp_localize_script( 'jc-club-sortable', 'albumClub', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'album_club_nonce' ),
			'ucPubKey'   => defined( 'UPLOADCARE_PUBLIC_KEY' ) ? UPLOADCARE_PUBLIC_KEY : '',
			'canBulkAdd' => ! in_array( 'club', (array) $current_user->roles, true ),
		) );
	}

	// ─── Control de acceso por club_slug ───────────────────

	/**
	 * Comprueba si el usuario actual puede acceder al club indicado.
	 */
	private static function user_can_access_club( int $club_id ): bool {
		$user = wp_get_current_user();

		if ( ! in_array( 'club', (array) $user->roles, true ) ) {
			return true;
		}

		$club_slug = get_field( 'club_slug', 'user_' . $user->ID );

		if ( empty( $club_slug ) ) {
			return false;
		}

		$post = get_post( $club_id );

		if ( ! $post ) {
			return false;
		}

		return str_ends_with( $post->post_name, $club_slug );
	}

	/**
	 * Obtiene el post_id de una categoría (para control de acceso).
	 */
	private static function get_post_id_by_categoria( int $categoria_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->prefix}club_categorias WHERE id = %d",
			$categoria_id
		) );
	}

	/**
	 * Verifica que un jugador pertenece a una categoría del club dado.
	 */
	private static function jugador_belongs_to_club( int $jugador_id, int $club_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT j.id
			 FROM {$wpdb->prefix}club_jugadores j
			 JOIN {$wpdb->prefix}club_categorias c ON j.categoria_id = c.id
			 WHERE j.id = %d AND c.post_id = %d",
			$jugador_id,
			$club_id
		) );
	}

	/**
	 * Verifica que un equipo pertenece a una categoría del club dado.
	 */
	private static function equipo_belongs_to_club( int $equipo_id, int $club_id ): bool {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT e.id FROM {$wpdb->prefix}club_equipo e
			 JOIN {$wpdb->prefix}club_categorias c ON e.categoria_id = c.id
			 WHERE e.id = %d AND c.post_id = %d",
			$equipo_id, $club_id
		) );
	}

	// ─── AJAX handlers — jugadores ─────────────────────────

	/**
	 * Reordena jugadores tras drag & drop.
	 */
	public static function ajax_reorder(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$categories = json_decode( stripslashes( $_POST['categories'] ?? '' ), true );

		if ( ! $club_id || ! is_array( $categories ) ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_jug = $wpdb->prefix . 'club_jugadores';
		$t_cat = $wpdb->prefix . 'club_categorias';

		foreach ( $categories as $cat ) {
			$categoria_id = absint( $cat['categoria_id'] ?? 0 );
			$jugador_ids  = $cat['jugador_ids'] ?? array();

			if ( ! $categoria_id || ! is_array( $jugador_ids ) ) {
				continue;
			}

			// Verificar que la categoría pertenece al club.
			$cat_post_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$t_cat} WHERE id = %d",
				$categoria_id
			) );

			if ( $cat_post_id !== $club_id ) {
				continue;
			}

			foreach ( $jugador_ids as $order => $id ) {
				$wpdb->update(
					$t_jug,
					array(
						'categoria_id' => $categoria_id,
						'menu_order'   => $order,
					),
					array( 'id' => absint( $id ) ),
					array( '%d', '%d' ),
					array( '%d' )
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

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$jugador_id = absint( $_POST['jugador_id'] ?? 0 );

		if ( ! $club_id || ! $jugador_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::jugador_belongs_to_club( $jugador_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'club_jugadores',
			array( 'id' => $jugador_id ),
			array( '%d' )
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

		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );
		$jugadores    = json_decode( stripslashes( $_POST['jugadores'] ?? '' ), true );

		if ( ! $categoria_id || ! is_array( $jugadores ) || empty( $jugadores ) ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		$post_id = self::get_post_id_by_categoria( $categoria_id );
		if ( ! $post_id || ! self::user_can_access_club( $post_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(menu_order), -1) FROM {$table} WHERE categoria_id = %d",
			$categoria_id
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
				'categoria_id' => $categoria_id,
				'nombre'       => $nombre,
				'apellidos'    => $apellidos,
				'cargo'        => $cargo,
				'nombre_foto'  => '',
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

		if ( ! is_user_logged_in() ) {
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

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::jugador_belongs_to_club( $jugador_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$data    = array( 'foto_url' => $foto_url );
		$formats = array( '%s' );

		if ( '' !== $nombre_foto ) {
			$data['nombre_foto'] = $nombre_foto;
			$formats[]           = '%s';
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			$data,
			array( 'id' => $jugador_id ),
			$formats,
			array( '%d' )
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

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id    = absint( $_POST['club_id'] ?? 0 );
		$jugador_id = absint( $_POST['jugador_id'] ?? 0 );

		if ( ! $club_id || ! $jugador_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::jugador_belongs_to_club( $jugador_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			array( 'foto_url' => '', 'nombre_foto' => '' ),
			array( 'id' => $jugador_id ),
			array( '%s', '%s' ),
			array( '%d' )
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

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id     = absint( $_POST['club_id'] ?? 0 );
		$jugador_id  = absint( $_POST['jugador_id'] ?? 0 );
		$nombre      = sanitize_text_field( trim( $_POST['nombre'] ?? '' ) );
		$apellidos   = sanitize_text_field( trim( $_POST['apellidos'] ?? '' ) );
		$cargo       = sanitize_text_field( trim( $_POST['cargo'] ?? '' ) );
		$nombre_foto = sanitize_text_field( trim( $_POST['nombre_foto'] ?? '' ) );
		$nombre_foto = substr( $nombre_foto, 0, 32 );

		if ( ! $club_id || ! $jugador_id || '' === $nombre ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::jugador_belongs_to_club( $jugador_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_jugadores',
			array(
				'nombre'      => $nombre,
				'apellidos'   => $apellidos,
				'cargo'       => $cargo,
				'nombre_foto' => $nombre_foto,
			),
			array( 'id' => $jugador_id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
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

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );
		$nombre       = sanitize_text_field( trim( $_POST['nombre'] ?? '' ) );
		$apellidos    = sanitize_text_field( trim( $_POST['apellidos'] ?? '' ) );
		$cargo        = sanitize_text_field( trim( $_POST['cargo'] ?? '' ) );
		$nombre_foto  = sanitize_text_field( trim( $_POST['nombre_foto'] ?? '' ) );
		$nombre_foto  = substr( $nombre_foto, 0, 32 );

		if ( ! $categoria_id || '' === $nombre ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		$post_id = self::get_post_id_by_categoria( $categoria_id );
		if ( ! $post_id || ! self::user_can_access_club( $post_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'club_jugadores';

		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(menu_order), -1) FROM {$table} WHERE categoria_id = %d",
			$categoria_id
		) );

		$wpdb->insert( $table, array(
			'categoria_id' => $categoria_id,
			'nombre'       => $nombre,
			'apellidos'    => $apellidos,
			'cargo'        => $cargo,
			'nombre_foto'  => $nombre_foto,
			'foto_url'     => '',
			'menu_order'   => $max_order + 1,
		), array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' ) );

		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( 'Error al insertar.' );
		}

		wp_send_json_success( array(
			'id'          => $wpdb->insert_id,
			'nombre'      => $nombre,
			'apellidos'   => $apellidos,
			'cargo'       => $cargo,
			'nombre_foto' => $nombre_foto,
			'foto_url'    => '',
		) );
	}

	/**
	 * Genera y descarga un CSV con todas las categorías y jugadores del club.
	 */
	public static function ajax_export_csv(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_die( 'Sin permisos.' );
		}

		$club_id = absint( $_GET['club_id'] ?? 0 );

		if ( ! $club_id ) {
			wp_die( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_die( 'Sin permisos.' );
		}

		global $wpdb;
		$t_cat = $wpdb->prefix . 'club_categorias';
		$t_jug = $wpdb->prefix . 'club_jugadores';
		$t_equ = $wpdb->prefix . 'club_equipo';

		$categorias = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t_cat} WHERE post_id = %d ORDER BY menu_order ASC, id ASC",
			$club_id
		) );

		if ( ! $categorias ) {
			wp_die( 'Sin categorías.' );
		}

		$post     = get_post( $club_id );
		$slug     = $post ? sanitize_file_name( $post->post_title ) : 'club';
		$filename = 'jugadores-' . $slug . '-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );

		foreach ( $categorias as $cat ) {
			// Nombre de la categoría.
			fputcsv( $output, array( $cat->descripcion ) );

			// Fotos de grupo.
			fputcsv( $output, array( 'Fotos de grupo' ) );
			fputcsv( $output, array( 'nombre_foto', 'foto_url', 'descripcion' ) );

			$equipos = $wpdb->get_results( $wpdb->prepare(
				"SELECT nombre_foto, foto_url, descripcion
				 FROM {$t_equ}
				 WHERE categoria_id = %d
				 ORDER BY menu_order ASC",
				(int) $cat->id
			) );

			foreach ( $equipos as $equipo ) {
				fputcsv( $output, array(
					$equipo->nombre_foto ?? '',
					$equipo->foto_url,
					$equipo->descripcion,
				) );
			}

			// Miembros.
			fputcsv( $output, array( 'Miembros' ) );
			fputcsv( $output, array( 'nombre_foto', 'foto_url', 'nombre', 'apellidos', 'cargo' ) );

			$jugadores = $wpdb->get_results( $wpdb->prepare(
				"SELECT nombre_foto, foto_url, nombre, apellidos, cargo
				 FROM {$t_jug}
				 WHERE categoria_id = %d
				 ORDER BY menu_order ASC",
				(int) $cat->id
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

			fputcsv( $output, array() );
		}

		fclose( $output );
		exit;
	}

	/**
	 * Genera y descarga un ZIP con el CSV + fotos organizadas por categoría.
	 */
	public static function ajax_export_zip(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_die( 'Sin permisos.' );
		}

		$club_id = absint( $_GET['club_id'] ?? 0 );

		if ( ! $club_id ) {
			wp_die( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_die( 'Sin permisos.' );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_die( 'ZipArchive no está disponible en este servidor.' );
		}

		set_time_limit( 300 );

		global $wpdb;
		$t_cat = $wpdb->prefix . 'club_categorias';
		$t_jug = $wpdb->prefix . 'club_jugadores';
		$t_equ = $wpdb->prefix . 'club_equipo';

		$categorias = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$t_cat} WHERE post_id = %d ORDER BY menu_order ASC, id ASC",
			$club_id
		) );

		if ( ! $categorias ) {
			wp_die( 'Sin categorías.' );
		}

		$post     = get_post( $club_id );
		$slug     = $post ? sanitize_file_name( $post->post_title ) : 'club';
		$date     = gmdate( 'Y-m-d' );
		$zip_name = 'jugadores-' . $slug . '-' . $date . '.zip';

		$tmp = wp_tempnam( $zip_name );

		$zip = new ZipArchive();
		if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
			wp_die( 'Error al crear el archivo ZIP.' );
		}

		// Generar contenido CSV.
		$csv_stream = fopen( 'php://temp', 'r+' );
		fwrite( $csv_stream, "\xEF\xBB\xBF" );

		foreach ( $categorias as $cat ) {
			fputcsv( $csv_stream, array( $cat->descripcion ) );

			fputcsv( $csv_stream, array( 'Fotos de grupo' ) );
			fputcsv( $csv_stream, array( 'nombre_foto', 'foto_url', 'descripcion' ) );

			$equipos = $wpdb->get_results( $wpdb->prepare(
				"SELECT nombre_foto, foto_url, descripcion
				 FROM {$t_equ}
				 WHERE categoria_id = %d
				 ORDER BY menu_order ASC",
				(int) $cat->id
			) );

			foreach ( $equipos as $equipo ) {
				fputcsv( $csv_stream, array(
					$equipo->nombre_foto ?? '',
					$equipo->foto_url,
					$equipo->descripcion,
				) );
			}

			fputcsv( $csv_stream, array( 'Miembros' ) );
			fputcsv( $csv_stream, array( 'nombre_foto', 'foto_url', 'nombre', 'apellidos', 'cargo' ) );

			$jugadores = $wpdb->get_results( $wpdb->prepare(
				"SELECT nombre_foto, foto_url, nombre, apellidos, cargo
				 FROM {$t_jug}
				 WHERE categoria_id = %d
				 ORDER BY menu_order ASC",
				(int) $cat->id
			) );

			foreach ( $jugadores as $jugador ) {
				fputcsv( $csv_stream, array(
					$jugador->nombre_foto ?? '',
					$jugador->foto_url,
					$jugador->nombre,
					$jugador->apellidos,
					$jugador->cargo,
				) );
			}

			fputcsv( $csv_stream, array() );
		}

		rewind( $csv_stream );
		$zip->addFromString( 'jugadores-' . $slug . '-' . $date . '.csv', stream_get_contents( $csv_stream ) );
		fclose( $csv_stream );

		// Añadir fotos al ZIP.
		$index = 1;
		foreach ( $categorias as $cat ) {
			$prefix     = str_pad( $index, 2, '0', STR_PAD_LEFT );
			$cat_folder = $prefix . '_' . self::normalize_for_filename( $cat->descripcion );

			$equipos = $wpdb->get_results( $wpdb->prepare(
				"SELECT nombre_foto, foto_url, descripcion
				 FROM {$t_equ}
				 WHERE categoria_id = %d
				 ORDER BY menu_order ASC",
				(int) $cat->id
			) );

			foreach ( $equipos as $equipo ) {
				if ( empty( $equipo->foto_url ) ) {
					continue;
				}
				$url = self::clean_uploadcare_url( $equipo->foto_url );
				$ext = pathinfo( $equipo->nombre_foto ?? '', PATHINFO_EXTENSION ) ?: 'jpg';
				$filename = self::normalize_for_filename( $equipo->descripcion ) . '.' . strtolower( $ext );
				$body     = self::download_file( $url );
				if ( null !== $body ) {
					$zip->addFromString( $cat_folder . '/Equipo/' . $filename, $body );
				}
			}

			$jugadores = $wpdb->get_results( $wpdb->prepare(
				"SELECT nombre_foto, foto_url
				 FROM {$t_jug}
				 WHERE categoria_id = %d
				 ORDER BY menu_order ASC",
				(int) $cat->id
			) );

			foreach ( $jugadores as $jugador ) {
				if ( empty( $jugador->foto_url ) ) {
					continue;
				}
				$url      = self::clean_uploadcare_url( $jugador->foto_url );
				$filename = self::normalize_for_filename( $jugador->nombre_foto ?? 'foto' );
				if ( ! pathinfo( $filename, PATHINFO_EXTENSION ) ) {
					$filename .= '.jpg';
				}
				$body = self::download_file( $url );
				if ( null !== $body ) {
					$zip->addFromString( $cat_folder . '/Jugadores/' . $filename, $body );
				}
			}

			$index++;
		}

		$zip->close();

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $zip_name . '"' );
		header( 'Content-Length: ' . filesize( $tmp ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		readfile( $tmp );
		unlink( $tmp );
		exit;
	}

	/**
	 * Normaliza una cadena para usarla como nombre de archivo o carpeta.
	 * Transliterara acentos, elimina caracteres incompatibles.
	 */
	private static function normalize_for_filename( string $name ): string {
		// Eliminar caracteres incompatibles con sistemas de archivos: / \ : * ? " < > | y control chars.
		$name = preg_replace( '/[\/\\\\:*?"<>|\x00-\x1f]/', '_', $name );
		$name = preg_replace( '/_+/', '_', $name );
		return trim( $name, ' _' ) ?: 'archivo';
	}

	/**
	 * Limpia una URL de Uploadcare para obtener la imagen original
	 * eliminando los parámetros de procesamiento (/-/preview/..., etc.).
	 */
	private static function clean_uploadcare_url( string $url ): string {
		if ( preg_match( '#^(https?://[^/]+/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/)#i', $url, $m ) ) {
			return $m[1];
		}
		return $url;
	}

	/**
	 * Descarga el contenido de una URL vía la API HTTP de WordPress.
	 * Devuelve null si hay error o la respuesta no es 200.
	 */
	private static function download_file( string $url ): ?string {
		if ( empty( $url ) ) {
			return null;
		}
		$response = wp_remote_get( $url, array(
			'timeout'   => 30,
			'sslverify' => true,
		) );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		return wp_remote_retrieve_body( $response );
	}

	// ─── AJAX handlers — categorías ────────────────────────

	/**
	 * Añade una categoría al club actual.
	 */
	public static function ajax_add_categoria(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id     = absint( $_POST['club_id'] ?? 0 );
		$descripcion = sanitize_text_field( trim( $_POST['descripcion'] ?? '' ) );

		if ( ! $club_id || '' === $descripcion ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_cat     = $wpdb->prefix . 'club_categorias';
		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(menu_order), -1) FROM {$t_cat} WHERE post_id = %d",
			$club_id
		) );

		$wpdb->insert(
			$t_cat,
			array(
				'post_id'     => $club_id,
				'descripcion' => $descripcion,
				'menu_order'  => $max_order + 1,
			),
			array( '%d', '%s', '%d' )
		);

		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( 'Error al insertar.' );
		}

		wp_send_json_success( array(
			'id'          => $wpdb->insert_id,
			'descripcion' => $descripcion,
		) );
	}

	/**
	 * Elimina una categoría solo si no tiene jugadores.
	 */
	public static function ajax_delete_categoria(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );

		if ( ! $club_id || ! $categoria_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_cat = $wpdb->prefix . 'club_categorias';
		$t_jug = $wpdb->prefix . 'club_jugadores';

		// Verificar que la categoría pertenece al club.
		$post_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$t_cat} WHERE id = %d",
			$categoria_id
		) );

		if ( $post_id !== $club_id ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		// No eliminar si tiene jugadores.
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$t_jug} WHERE categoria_id = %d",
			$categoria_id
		) );

		if ( $count > 0 ) {
			wp_send_json_error( 'La categoría tiene jugadores y no puede eliminarse.' );
		}

		$deleted = $wpdb->delete( $t_cat, array( 'id' => $categoria_id ), array( '%d' ) );

		if ( false === $deleted ) {
			wp_send_json_error( 'Error al eliminar.' );
		}

		wp_send_json_success();
	}

	/**
	 * Renombra la descripción de una categoría.
	 */
	public static function ajax_rename_categoria(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );
		$descripcion  = sanitize_text_field( trim( $_POST['descripcion'] ?? '' ) );

		if ( ! $club_id || ! $categoria_id || '' === $descripcion ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_cat = $wpdb->prefix . 'club_categorias';

		// Verificar que la categoría pertenece al club.
		$post_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$t_cat} WHERE id = %d",
			$categoria_id
		) );

		if ( $post_id !== $club_id ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$updated = $wpdb->update(
			$t_cat,
			array( 'descripcion' => $descripcion ),
			array( 'id' => $categoria_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success( array( 'descripcion' => $descripcion ) );
	}

	/**
	 * Reordena categorías tras drag & drop.
	 */
	public static function ajax_reorder_categorias(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$categoria_ids = json_decode( stripslashes( $_POST['categoria_ids'] ?? '' ), true );

		if ( ! $club_id || ! is_array( $categoria_ids ) ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_cat = $wpdb->prefix . 'club_categorias';

		foreach ( $categoria_ids as $order => $id ) {
			$id = absint( $id );
			if ( ! $id ) {
				continue;
			}

			// Verificar que la categoría pertenece al club.
			$post_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT post_id FROM {$t_cat} WHERE id = %d",
				$id
			) );

			if ( $post_id !== $club_id ) {
				continue;
			}

			$wpdb->update(
				$t_cat,
				array( 'menu_order' => $order ),
				array( 'id' => $id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		wp_send_json_success();
	}

	/**
	 * Mueve un jugador a otra categoría del mismo club.
	 */
	public static function ajax_move_jugador(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$jugador_id   = absint( $_POST['jugador_id'] ?? 0 );
		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );

		if ( ! $club_id || ! $jugador_id || ! $categoria_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::jugador_belongs_to_club( $jugador_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_jug = $wpdb->prefix . 'club_jugadores';
		$t_cat = $wpdb->prefix . 'club_categorias';

		// Verificar que la categoría destino pertenece al club.
		$cat_post_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$t_cat} WHERE id = %d",
			$categoria_id
		) );

		if ( $cat_post_id !== $club_id ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		// Calcular menu_order en la categoría destino.
		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(menu_order), -1) FROM {$t_jug} WHERE categoria_id = %d",
			$categoria_id
		) );

		$updated = $wpdb->update(
			$t_jug,
			array(
				'categoria_id' => $categoria_id,
				'menu_order'   => $max_order + 1,
			),
			array( 'id' => $jugador_id ),
			array( '%d', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al mover.' );
		}

		wp_send_json_success();
	}

	/**
	 * Duplica un jugador en otra categoría (o en la misma).
	 */
	public static function ajax_duplicate_jugador(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$jugador_id   = absint( $_POST['jugador_id'] ?? 0 );
		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );

		if ( ! $club_id || ! $jugador_id || ! $categoria_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::jugador_belongs_to_club( $jugador_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_jug = $wpdb->prefix . 'club_jugadores';
		$t_cat = $wpdb->prefix . 'club_categorias';

		// Verificar que la categoría destino pertenece al club.
		$cat_post_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$t_cat} WHERE id = %d",
			$categoria_id
		) );

		if ( $cat_post_id !== $club_id ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		// Obtener datos del jugador original.
		$original = $wpdb->get_row( $wpdb->prepare(
			"SELECT nombre, apellidos, cargo, nombre_foto, foto_url FROM {$t_jug} WHERE id = %d",
			$jugador_id
		) );

		if ( ! $original ) {
			wp_send_json_error( 'Jugador no encontrado.' );
		}

		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(menu_order), -1) FROM {$t_jug} WHERE categoria_id = %d",
			$categoria_id
		) );

		$wpdb->insert(
			$t_jug,
			array(
				'categoria_id' => $categoria_id,
				'nombre'       => $original->nombre,
				'apellidos'    => $original->apellidos,
				'cargo'        => $original->cargo,
				'nombre_foto'  => $original->nombre_foto,
				'foto_url'     => $original->foto_url,
				'menu_order'   => $max_order + 1,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( 'Error al duplicar.' );
		}

		wp_send_json_success( array(
			'id'          => $wpdb->insert_id,
			'nombre'      => $original->nombre,
			'apellidos'   => $original->apellidos,
			'cargo'       => $original->cargo,
			'nombre_foto' => $original->nombre_foto,
			'foto_url'    => $original->foto_url,
			'categoria_id'=> $categoria_id,
		) );
	}

	/**
	 * Ordena los jugadores de una categoría por apellidos y nombre (A→Z).
	 */
	public static function ajax_sort_alfabetico(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id      = absint( $_POST['club_id'] ?? 0 );
		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );

		if ( ! $club_id || ! $categoria_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$t_jug = $wpdb->prefix . 'club_jugadores';
		$t_cat = $wpdb->prefix . 'club_categorias';

		// Verificar que la categoría pertenece al club.
		$cat_post_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$t_cat} WHERE id = %d",
			$categoria_id
		) );

		if ( $cat_post_id !== $club_id ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		// Obtener IDs ordenados alfabéticamente por apellidos, nombre.
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM {$t_jug}
			 WHERE categoria_id = %d
			 ORDER BY apellidos COLLATE utf8mb4_unicode_ci ASC,
			          nombre    COLLATE utf8mb4_unicode_ci ASC",
			$categoria_id
		) );

		// Actualizar menu_order según el nuevo orden.
		foreach ( $ids as $order => $id ) {
			$wpdb->update(
				$t_jug,
				array( 'menu_order' => $order ),
				array( 'id' => (int) $id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		wp_send_json_success( array_map( 'intval', $ids ) );
	}

	// ─── AJAX handlers — equipo ────────────────────────────

	/**
	 * Añade una foto de grupo (equipo) a una categoría.
	 */
	public static function ajax_add_equipo(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$categoria_id = absint( $_POST['categoria_id'] ?? 0 );
		$descripcion  = sanitize_text_field( trim( $_POST['descripcion'] ?? '' ) );

		if ( ! $categoria_id || '' === $descripcion ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		$post_id = self::get_post_id_by_categoria( $categoria_id );
		if ( ! $post_id || ! self::user_can_access_club( $post_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'club_equipo';

		$wpdb->insert( $table, array(
			'categoria_id' => $categoria_id,
			'descripcion'  => $descripcion,
			'foto_url'     => '',
			'menu_order'   => 0,
		), array( '%d', '%s', '%s', '%d' ) );

		if ( ! $wpdb->insert_id ) {
			wp_send_json_error( 'Error al insertar.' );
		}

		wp_send_json_success( array(
			'id'          => $wpdb->insert_id,
			'descripcion' => $descripcion,
			'foto_url'    => '',
		) );
	}

	/**
	 * Elimina una foto de grupo (equipo).
	 */
	public static function ajax_delete_equipo(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id   = absint( $_POST['club_id'] ?? 0 );
		$equipo_id = absint( $_POST['equipo_id'] ?? 0 );

		if ( ! $club_id || ! $equipo_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::equipo_belongs_to_club( $equipo_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$deleted = $wpdb->delete(
			$wpdb->prefix . 'club_equipo',
			array( 'id' => $equipo_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			wp_send_json_error( 'Error al eliminar.' );
		}

		wp_send_json_success();
	}

	/**
	 * Actualiza la descripción de una foto de grupo (equipo).
	 */
	public static function ajax_update_equipo(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id     = absint( $_POST['club_id'] ?? 0 );
		$equipo_id   = absint( $_POST['equipo_id'] ?? 0 );
		$descripcion = sanitize_text_field( trim( $_POST['descripcion'] ?? '' ) );

		if ( ! $club_id || ! $equipo_id || '' === $descripcion ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::equipo_belongs_to_club( $equipo_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_equipo',
			array( 'descripcion' => $descripcion ),
			array( 'id' => $equipo_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success( array( 'descripcion' => $descripcion ) );
	}

	/**
	 * Actualiza la foto de una foto de grupo (equipo).
	 */
	public static function ajax_update_equipo_foto(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id     = absint( $_POST['club_id'] ?? 0 );
		$equipo_id   = absint( $_POST['equipo_id'] ?? 0 );
		$foto_url    = esc_url_raw( $_POST['foto_url'] ?? '' );
		$nombre_foto = sanitize_text_field( trim( $_POST['nombre_foto'] ?? '' ) );
		$nombre_foto = substr( $nombre_foto, 0, 64 );

		if ( ! $club_id || ! $equipo_id || ! $foto_url ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::equipo_belongs_to_club( $equipo_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$data    = array( 'foto_url' => $foto_url );
		$formats = array( '%s' );

		if ( '' !== $nombre_foto ) {
			$data['nombre_foto'] = $nombre_foto;
			$formats[]           = '%s';
		}

		$updated = $wpdb->update(
			$wpdb->prefix . 'club_equipo',
			$data,
			array( 'id' => $equipo_id ),
			$formats,
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success( array( 'foto_url' => $foto_url, 'nombre_foto' => $nombre_foto ) );
	}

	/**
	 * Elimina la foto de una foto de grupo (equipo).
	 */
	public static function ajax_clear_equipo_foto(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$club_id   = absint( $_POST['club_id'] ?? 0 );
		$equipo_id = absint( $_POST['equipo_id'] ?? 0 );

		if ( ! $club_id || ! $equipo_id ) {
			wp_send_json_error( 'Datos inválidos.' );
		}

		if ( ! self::user_can_access_club( $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		if ( ! self::equipo_belongs_to_club( $equipo_id, $club_id ) ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->prefix . 'club_equipo',
			array( 'foto_url' => '', 'nombre_foto' => '' ),
			array( 'id' => $equipo_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			wp_send_json_error( 'Error al actualizar.' );
		}

		wp_send_json_success();
	}
}
