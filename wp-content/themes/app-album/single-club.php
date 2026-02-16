<?php
/**
 * Plantilla para clubs (CPT: club).
 *
 * Muestra las categorías del club con sus jugadores.
 * Permite reordenar jugadores y moverlos entre categorías
 * mediante drag & drop (SortableJS).
 *
 * @package AlbumCustom
 */

require_once ALBUM_DIR . '/inc/class-album-uploadcare.php';

$uploadcare = new Album_Uploadcare();

get_header(); ?>

<div class="max-w-5xl mx-auto px-4 py-8">

	<?php while ( have_posts() ) : the_post(); ?>

		<?php
		$club_id    = get_the_ID();
		$categorias = get_field( 'categoria' );
		?>

		<header class="mb-8">
			<h1 class="text-3xl font-bold text-gray-900"><?php the_title(); ?></h1>
			<?php if ( get_the_content() ) : ?>
				<div class="mt-2 text-gray-600"><?php the_content(); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( $categorias ) : ?>
			<div class="space-y-6" id="club-categorias" data-club-id="<?php echo esc_attr( $club_id ); ?>">
				<?php foreach ( $categorias as $cat ) :
					$category_uid  = sanitize_title( $cat['categoria'] );
					$category_name = esc_html( $cat['categoria'] );

					global $wpdb;
					$jugadores = $wpdb->get_results( $wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}club_jugadores
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
											<!-- Nombre -->
											<span class="text-sm font-medium text-gray-800 flex-1"><?php echo esc_html( $jugador->nombre ); ?></span>
											<!-- Toggle foto expandida -->
											<button type="button"
											        class="btn-toggle-foto shrink-0 text-gray-300 hover:text-blue-500 transition-colors <?php echo $jugador->foto_url ? '' : 'hidden'; ?>"
											        title="Ver foto">
												<svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
													<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
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
							          placeholder="Nombres separados por coma o salto de línea..."
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
		<?php endif; ?>

	<?php endwhile; ?>

</div>

<?php get_footer(); ?>
