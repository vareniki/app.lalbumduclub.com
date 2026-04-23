<?php
/**
 * Integración del rol Gestor con el panel de WordPress.
 *
 * Permite a los gestores administrar únicamente los usuarios con rol Club
 * desde wp-admin > Usuarios, sin poder ver ni tocar otros usuarios.
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gestor_Admin {

	public static function init(): void {
		// Filtra la lista de usuarios en wp-admin.
		add_action( 'pre_get_users', array( __CLASS__, 'filter_users_list' ) );

		// Restringe los roles asignables a solo 'club'.
		add_filter( 'editable_roles', array( __CLASS__, 'filter_editable_roles' ) );

		// Impide editar o eliminar usuarios que no sean Club.
		add_filter( 'map_meta_cap', array( __CLASS__, 'restrict_user_caps' ), 10, 4 );

		// Limpia las pestañas de roles en la pantalla de usuarios.
		add_filter( 'views_users', array( __CLASS__, 'filter_user_views' ) );

		// Panel de clubs asignados al editar un usuario Gestor.
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_clubs_panel' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_clubs_panel' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_clubs_panel' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_clubs_panel' ) );

		// Meta box de gestores al editar un post de tipo 'club'.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_gestores_meta_box' ) );
		add_action( 'save_post_club', array( __CLASS__, 'save_club_gestores' ), 10, 1 );
	}

	/**
	 * Comprueba si el usuario actual tiene el rol Gestor.
	 */
	private static function is_gestor(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return in_array( 'gestor', (array) wp_get_current_user()->roles, true );
	}

	/**
	 * Filtra la consulta de usuarios en el admin para mostrar solo los de rol Club.
	 */
	public static function filter_users_list( WP_User_Query $query ): void {
		if ( ! is_admin() || ! self::is_gestor() ) {
			return;
		}
		$query->set( 'role', 'club' );
	}

	/**
	 * Restringe los roles asignables a solo 'club' para el Gestor.
	 * Esto impide que puedan crear o promover usuarios a cualquier otro rol.
	 *
	 * @param array $roles Roles disponibles en WordPress.
	 * @return array
	 */
	public static function filter_editable_roles( array $roles ): array {
		if ( ! self::is_gestor() ) {
			return $roles;
		}
		return isset( $roles['club'] ) ? array( 'club' => $roles['club'] ) : array();
	}

	/**
	 * Impide que el Gestor edite o elimine usuarios que no tengan rol Club.
	 *
	 * @param string[] $caps     Capabilities requeridas.
	 * @param string   $cap      Capability que se está comprobando.
	 * @param int      $user_id  ID del usuario que intenta la acción.
	 * @param array    $args     Argumentos (args[0] = ID del usuario objetivo).
	 * @return string[]
	 */
	public static function restrict_user_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, array( 'edit_user', 'delete_user' ), true ) ) {
			return $caps;
		}

		$current_user = get_user_by( 'id', $user_id );
		if ( ! $current_user || ! in_array( 'gestor', (array) $current_user->roles, true ) ) {
			return $caps;
		}

		$target_id = (int) ( $args[0] ?? 0 );

		// Un gestor nunca puede editarse a sí mismo desde la pantalla de usuarios.
		if ( ! $target_id || $target_id === $user_id ) {
			return $caps;
		}

		$target = get_user_by( 'id', $target_id );
		if ( ! $target || ! in_array( 'club', (array) $target->roles, true ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	/**
	 * Deja solo las pestañas "Todos" y "Club" en la pantalla de usuarios.
	 *
	 * @param array $views Pestañas de filtro disponibles.
	 * @return array
	 */
	public static function filter_user_views( array $views ): array {
		if ( ! self::is_gestor() ) {
			return $views;
		}

		$allowed = array();

		if ( isset( $views['all'] ) ) {
			$allowed['all'] = $views['all'];
		}

		if ( isset( $views['club'] ) ) {
			$allowed['club'] = $views['club'];
		}

		return $allowed;
	}

	// ─── Asignación de clubs a Gestores ────────────────────────────────────────

	/**
	 * Renderiza el panel "Clubs asignados" en la pantalla de edición de un usuario Gestor.
	 * Solo visible para administradores.
	 *
	 * @param WP_User $user Usuario que se está editando.
	 */
	public static function render_user_clubs_panel( WP_User $user ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! in_array( 'gestor', (array) $user->roles, true ) ) {
			return;
		}

		$assigned_ids = jc_get_gestor_clubs( $user->ID );

		$clubs = get_posts( array(
			'post_type'      => 'club',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$total_clubs    = count( $clubs );
		$total_assigned = count( array_intersect( $assigned_ids, array_column( $clubs, 'ID' ) ) );

		wp_nonce_field( 'jc_gestor_clubs_save', 'jc_gestor_clubs_nonce' );
		?>
		<h2>Clubs que puede gestionar</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Clubs asignados</th>
				<td>
					<?php if ( empty( $clubs ) ) : ?>
						<p class="description">No hay clubs publicados todavía.</p>
					<?php else : ?>
						<div id="jc-clubs-picker" style="max-width:480px;">

							<!-- Buscador y acciones rápidas -->
							<div style="display:flex; gap:8px; align-items:center; margin-bottom:6px;">
								<input type="search"
								       id="jc-clubs-search"
								       placeholder="Buscar club…"
								       autocomplete="off"
								       style="flex:1; padding:5px 10px; border:1px solid #8c8f94; border-radius:4px; font-size:13px; line-height:1.5; box-shadow:inset 0 1px 2px rgba(0,0,0,.07);">
								<button type="button" id="jc-clubs-all"
								        class="button button-small"
								        style="white-space:nowrap;">Todos</button>
								<button type="button" id="jc-clubs-none"
								        class="button button-small"
								        style="white-space:nowrap;">Ninguno</button>
							</div>

							<!-- Lista scrollable -->
							<fieldset style="border:1px solid #8c8f94; border-radius:4px; padding:0; margin:0;">
								<legend class="screen-reader-text">Clubs asignados al gestor</legend>
								<div id="jc-clubs-list"
								     style="max-height:240px; overflow-y:auto; padding:8px 12px; box-sizing:border-box;">
									<?php foreach ( $clubs as $club ) : ?>
										<label class="jc-club-label"
										       style="display:flex; align-items:center; gap:7px; padding:3px 0; font-size:13px; cursor:pointer;"
										       data-name="<?php echo esc_attr( mb_strtolower( $club->post_title ) ); ?>">
											<input type="checkbox"
											       name="jc_clubs_gestionados[]"
											       value="<?php echo esc_attr( $club->ID ); ?>"
											       <?php checked( in_array( $club->ID, $assigned_ids, true ) ); ?>>
											<?php echo esc_html( $club->post_title ); ?>
										</label>
									<?php endforeach; ?>
									<p id="jc-clubs-empty"
									   style="display:none; margin:8px 0; color:#999; font-style:italic; font-size:13px;">
										Ningún club coincide con la búsqueda.
									</p>
								</div>
							</fieldset>

							<!-- Contador -->
							<p id="jc-clubs-counter"
							   class="description"
							   style="margin-top:6px;">
								<span id="jc-clubs-selected-count"><?php echo esc_html( $total_assigned ); ?></span>
								de <?php echo esc_html( $total_clubs ); ?> clubs seleccionados
							</p>
						</div>

						<script>
						( function () {
							const search   = document.getElementById( 'jc-clubs-search' );
							const list     = document.getElementById( 'jc-clubs-list' );
							const empty    = document.getElementById( 'jc-clubs-empty' );
							const counter  = document.getElementById( 'jc-clubs-selected-count' );
							const labels   = list.querySelectorAll( '.jc-club-label' );
							const checks   = list.querySelectorAll( 'input[type="checkbox"]' );

							function updateCounter() {
								let n = 0;
								checks.forEach( c => { if ( c.checked ) n++; } );
								counter.textContent = n;
							}

							function applyFilter( q ) {
								let visible = 0;
								labels.forEach( label => {
									const match = ! q || label.dataset.name.includes( q );
									label.style.display = match ? '' : 'none';
									if ( match ) visible++;
								} );
								empty.style.display = visible === 0 ? '' : 'none';
							}

							search.addEventListener( 'input', () =>
								applyFilter( search.value.trim().toLowerCase() )
							);

							document.getElementById( 'jc-clubs-all' ).addEventListener( 'click', () => {
								labels.forEach( label => {
									if ( label.style.display !== 'none' ) {
										label.querySelector( 'input' ).checked = true;
									}
								} );
								updateCounter();
							} );

							document.getElementById( 'jc-clubs-none' ).addEventListener( 'click', () => {
								labels.forEach( label => {
									if ( label.style.display !== 'none' ) {
										label.querySelector( 'input' ).checked = false;
									}
								} );
								updateCounter();
							} );

							checks.forEach( c => c.addEventListener( 'change', updateCounter ) );
						} )();
						</script>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Guarda los clubs asignados al gestor desde la pantalla de edición de usuario.
	 *
	 * @param int $user_id ID del usuario que se está guardando.
	 */
	public static function save_user_clubs_panel( int $user_id ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['jc_gestor_clubs_nonce'] ) ||
		     ! wp_verify_nonce( $_POST['jc_gestor_clubs_nonce'], 'jc_gestor_clubs_save' ) ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( 'gestor', (array) $user->roles, true ) ) {
			return;
		}

		$club_ids = isset( $_POST['jc_clubs_gestionados'] )
			? array_map( 'absint', (array) $_POST['jc_clubs_gestionados'] )
			: array();

		jc_set_gestor_clubs( $user_id, $club_ids );
	}

	// ─── Meta box en el post de club ────────────────────────────────────────────

	/**
	 * Registra el meta box "Gestores asignados" en los posts de tipo 'club'.
	 */
	public static function add_gestores_meta_box(): void {
		add_meta_box(
			'jc-gestores-club',
			'Gestores asignados',
			array( __CLASS__, 'render_gestores_meta_box' ),
			'club',
			'side',
			'default'
		);
	}

	/**
	 * Renderiza el contenido del meta box de gestores.
	 *
	 * @param WP_Post $post Post de tipo 'club' que se está editando.
	 */
	public static function render_gestores_meta_box( WP_Post $post ): void {
		$club_id = $post->ID;

		$gestores = get_users( array(
			'role'    => 'gestor',
			'orderby' => 'display_name',
			'order'   => 'ASC',
		) );

		// Determina qué gestores tienen este club asignado.
		$assigned_gestor_ids = array();
		foreach ( $gestores as $gestor ) {
			if ( in_array( $club_id, jc_get_gestor_clubs( $gestor->ID ), true ) ) {
				$assigned_gestor_ids[] = $gestor->ID;
			}
		}

		wp_nonce_field( 'jc_club_gestores_save', 'jc_club_gestores_nonce' );
		?>
		<p style="margin-top:0; color:#666; font-size:12px;">
			Gestores con acceso a este club:
		</p>
		<?php if ( empty( $gestores ) ) : ?>
			<p style="color:#999; font-style:italic; font-size:12px;">No hay gestores registrados.</p>
		<?php else : ?>
			<?php foreach ( $gestores as $gestor ) : ?>
				<label style="display:block; margin-bottom:5px; font-size:13px;">
					<input type="checkbox"
					       name="jc_club_gestores[]"
					       value="<?php echo esc_attr( $gestor->ID ); ?>"
					       <?php checked( in_array( $gestor->ID, $assigned_gestor_ids, true ) ); ?>>
					<?php echo esc_html( $gestor->display_name ?: $gestor->user_login ); ?>
				</label>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Guarda los gestores asignados al club cuando se guarda el post.
	 *
	 * Actualiza el user_meta de cada gestor para reflejar el estado del checkbox:
	 * añade el club si está marcado, lo elimina si no lo está.
	 *
	 * @param int $post_id ID del post de tipo 'club' que se está guardando.
	 */
	public static function save_club_gestores( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['jc_club_gestores_nonce'] ) ||
		     ! wp_verify_nonce( $_POST['jc_club_gestores_nonce'], 'jc_club_gestores_save' ) ) {
			return;
		}

		$selected_gestor_ids = isset( $_POST['jc_club_gestores'] )
			? array_map( 'absint', (array) $_POST['jc_club_gestores'] )
			: array();

		$gestores = get_users( array( 'role' => 'gestor' ) );

		foreach ( $gestores as $gestor ) {
			$clubs = jc_get_gestor_clubs( $gestor->ID );

			if ( in_array( $gestor->ID, $selected_gestor_ids, true ) ) {
				if ( ! in_array( $post_id, $clubs, true ) ) {
					$clubs[] = $post_id;
					jc_set_gestor_clubs( $gestor->ID, $clubs );
				}
			} else {
				$clubs = array_values( array_filter( $clubs, static function ( $id ) use ( $post_id ) {
					return $id !== $post_id;
				} ) );
				jc_set_gestor_clubs( $gestor->ID, $clubs );
			}
		}
	}
}