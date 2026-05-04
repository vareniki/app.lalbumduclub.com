<?php
/**
 * Integración de los roles Gestor y Súpergestor con el panel de WordPress.
 *
 * - Gestor:      administra usuarios Club y sus clubs asignados.
 * - Súpergestor: administra todos los clubs, y puede crear/editar usuarios Gestor.
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

		// Encola jQuery UI Autocomplete solo en páginas de edición de usuario.
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_user_edit_assets' ) );

		// Panel de clubs asignados al editar un usuario Gestor.
		add_action( 'show_user_profile', array( __CLASS__, 'render_user_clubs_panel' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_user_clubs_panel' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_user_clubs_panel' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_user_clubs_panel' ) );

		// Meta boxes al editar un post de tipo 'club'.
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_gestores_meta_box' ) );
		add_action( 'save_post_club', array( __CLASS__, 'save_club_gestores' ), 10, 1 );
		add_action( 'save_post_club', array( __CLASS__, 'save_club_users_meta' ), 10, 1 );
	}

	/**
	 * Encola jQuery UI Autocomplete en las páginas de edición de usuario.
	 *
	 * @param string $hook Nombre de la página actual del admin.
	 */
	public static function enqueue_user_edit_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'user-edit.php', 'profile.php' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'jquery-ui-autocomplete' );
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
	 * Comprueba si el usuario actual tiene el rol Súpergestor.
	 */
	private static function is_supergestor(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return in_array( 'supergestor', (array) wp_get_current_user()->roles, true );
	}

	/**
	 * Filtra la consulta de usuarios en el admin según el rol del usuario actual.
	 *
	 * - Gestor:      solo ve usuarios con rol Club.
	 * - Súpergestor: ve usuarios con rol Club y Gestor.
	 */
	public static function filter_users_list( WP_User_Query $query ): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( self::is_supergestor() ) {
			$query->set( 'role__in', array( 'club', 'gestor' ) );
			return;
		}

		if ( self::is_gestor() ) {
			$query->set( 'role', 'club' );
		}
	}

	/**
	 * Restringe los roles asignables según el rol del usuario actual.
	 *
	 * - Gestor:      solo puede asignar el rol Club.
	 * - Súpergestor: puede asignar Club y Gestor.
	 *
	 * @param array $roles Roles disponibles en WordPress.
	 * @return array
	 */
	public static function filter_editable_roles( array $roles ): array {
		if ( self::is_supergestor() ) {
			return array_filter( $roles, static function ( $key ) {
				return in_array( $key, array( 'club', 'gestor' ), true );
			}, ARRAY_FILTER_USE_KEY );
		}

		if ( self::is_gestor() ) {
			return isset( $roles['club'] ) ? array( 'club' => $roles['club'] ) : array();
		}

		return $roles;
	}

	/**
	 * Restringe las capabilities de edición/eliminación de usuarios según el rol.
	 *
	 * - Gestor:      solo puede editar/eliminar usuarios Club.
	 * - Súpergestor: puede editar/eliminar usuarios Club y Gestor,
	 *                pero no administradores ni otros supergestores.
	 *
	 * @param string[] $caps    Capabilities requeridas.
	 * @param string   $cap     Capability que se está comprobando.
	 * @param int      $user_id ID del usuario que intenta la acción.
	 * @param array    $args    Argumentos (args[0] = ID del usuario objetivo).
	 * @return string[]
	 */
	public static function restrict_user_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, array( 'edit_user', 'delete_user' ), true ) ) {
			return $caps;
		}

		$current_user = get_user_by( 'id', $user_id );
		if ( ! $current_user ) {
			return $caps;
		}

		$current_roles = (array) $current_user->roles;
		$target_id     = (int) ( $args[0] ?? 0 );

		if ( ! $target_id || $target_id === $user_id ) {
			return $caps;
		}

		$target        = get_user_by( 'id', $target_id );
		$target_roles  = $target ? (array) $target->roles : array();

		if ( in_array( 'supergestor', $current_roles, true ) ) {
			$allowed_target_roles = array( 'club', 'gestor' );
			$has_allowed_role     = ! empty( array_intersect( $target_roles, $allowed_target_roles ) );
			if ( ! $target || ! $has_allowed_role ) {
				$caps[] = 'do_not_allow';
			}
			return $caps;
		}

		if ( in_array( 'gestor', $current_roles, true ) ) {
			if ( ! $target || ! in_array( 'club', $target_roles, true ) ) {
				$caps[] = 'do_not_allow';
			}
			return $caps;
		}

		return $caps;
	}

	/**
	 * Filtra las pestañas de la pantalla de usuarios según el rol.
	 *
	 * - Gestor:      "Todos" y "Club".
	 * - Súpergestor: "Todos", "Club" y "Gestor".
	 *
	 * @param array $views Pestañas de filtro disponibles.
	 * @return array
	 */
	public static function filter_user_views( array $views ): array {
		if ( self::is_supergestor() ) {
			return array_filter( $views, static function ( $key ) {
				return in_array( $key, array( 'all', 'club', 'gestor' ), true );
			}, ARRAY_FILTER_USE_KEY );
		}

		if ( self::is_gestor() ) {
			return array_filter( $views, static function ( $key ) {
				return in_array( $key, array( 'all', 'club' ), true );
			}, ARRAY_FILTER_USE_KEY );
		}

		return $views;
	}

	// ─── Asignación de clubs a usuarios ───────────────────────────────────────

	/**
	 * Renderiza el panel de asignación de clubs en la pantalla de edición de usuario.
	 * Solo visible para administradores.
	 *
	 * - Súpergestor: muestra aviso de acceso total.
	 * - Gestor:      selector múltiple filtrable.
	 * - Club:        selector simple de un único club.
	 *
	 * @param WP_User $user Usuario que se está editando.
	 */
	public static function render_user_clubs_panel( WP_User $user ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user_roles = (array) $user->roles;

		// Súpergestor: acceso total por definición, sin selector.
		if ( in_array( 'supergestor', $user_roles, true ) ) {
			?>
			<h2>Club asignado</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">Club</th>
					<td>
						<span style="display:inline-flex; align-items:center; gap:6px; background:#f0f6fc; border:1px solid #c3d7ee; color:#0a5299; padding:6px 12px; border-radius:4px; font-size:13px;">
							Este usuario tiene acceso a <strong>todos los clubs</strong> por su rol de Súpergestor.
						</span>
					</td>
				</tr>
			</table>
			<?php
			return;
		}

		// Usuario Club: selector simple de un único club.
		if ( in_array( 'club', $user_roles, true ) ) {
			self::render_club_user_assignment( $user );
			return;
		}

		if ( ! in_array( 'gestor', $user_roles, true ) ) {
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
	 * Renderiza el selector de club único para un usuario con rol Club.
	 * Usa jQuery UI Autocomplete para manejar listados grandes.
	 *
	 * @param WP_User $user Usuario que se está editando.
	 */
	private static function render_club_user_assignment( WP_User $user ): void {
		$assigned_id = jc_get_club_user_club( $user->ID );

		$clubs = get_posts( array(
			'post_type'      => 'club',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );

		$assigned_name = '';
		foreach ( $clubs as $club ) {
			if ( $club->ID === $assigned_id ) {
				$assigned_name = $club->post_title;
				break;
			}
		}

		$clubs_data = array_map( static function ( WP_Post $c ): array {
			return array( 'id' => $c->ID, 'label' => $c->post_title );
		}, $clubs );

		wp_nonce_field( 'jc_club_user_save', 'jc_club_user_nonce' );
		?>
		<h2>Club asignado</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="jc-club-autocomplete">Club</label></th>
				<td>
					<?php if ( empty( $clubs ) ) : ?>
						<p class="description">No hay clubs publicados todavía.</p>
					<?php else : ?>
						<div style="position:relative; display:inline-block; width:360px; max-width:100%;">
							<input type="text"
							       id="jc-club-autocomplete"
							       value="<?php echo esc_attr( $assigned_name ); ?>"
							       placeholder="Escribe para buscar un club…"
							       autocomplete="off"
							       style="width:100%; box-sizing:border-box;">
							<input type="hidden" id="jc_club_id" name="jc_club_id"
							       value="<?php echo esc_attr( $assigned_id ?? '' ); ?>">
						</div>
						<p class="description" style="margin-top:6px;">
							Un usuario Club solo puede estar asociado a un único club.<?php if ( $assigned_name ) : ?>
							Club actual: <strong><?php echo esc_html( $assigned_name ); ?></strong>.<?php endif; ?>
							Deja el campo vacío para desasignar.
						</p>
						<style>
						.ui-autocomplete{background:#fff;border:1px solid #8c8f94;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.15);max-height:240px;overflow-y:auto;overflow-x:hidden;padding:4px 0;z-index:999999;}
						.ui-autocomplete .ui-menu-item-wrapper{padding:6px 12px;font-size:13px;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
						.ui-autocomplete .ui-state-active,.ui-autocomplete .ui-state-focus{background:#2271b1;color:#fff;border:none;border-radius:0;margin:0;}
						</style>
						<script>
						jQuery( function ( $ ) {
							var clubs   = <?php echo wp_json_encode( $clubs_data ); ?>;
							var $text   = $( '#jc-club-autocomplete' );
							var $hidden = $( '#jc_club_id' );

							$text.autocomplete( {
								minLength: 0,
								source: function ( req, res ) {
									var term = req.term.toLowerCase();
									var hits = term
										? clubs.filter( function ( c ) { return c.label.toLowerCase().indexOf( term ) !== -1; } )
										: clubs;
									res( hits.slice( 0, 25 ) );
								},
								select: function ( e, ui ) {
									$text.val( ui.item.label );
									$hidden.val( ui.item.id );
									return false;
								},
								change: function ( e, ui ) {
									if ( ! ui.item ) {
										$text.val( '' );
										$hidden.val( '' );
									}
								},
							} ).on( 'focus', function () {
								if ( ! $text.val() ) {
									$text.autocomplete( 'search', '' );
								}
							} );
						} );
						</script>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Guarda la asignación de club(s) desde la pantalla de edición de usuario.
	 *
	 * @param int $user_id ID del usuario que se está guardando.
	 */
	public static function save_user_clubs_panel( int $user_id ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$user       = get_user_by( 'id', $user_id );
		$user_roles = $user ? (array) $user->roles : array();

		if ( ! $user ) {
			return;
		}

		// Usuario Club: guarda un único club_id.
		if ( in_array( 'club', $user_roles, true ) ) {
			if ( ! isset( $_POST['jc_club_user_nonce'] ) ||
			     ! wp_verify_nonce( $_POST['jc_club_user_nonce'], 'jc_club_user_save' ) ) {
				return;
			}
			$club_id = isset( $_POST['jc_club_id'] ) ? absint( $_POST['jc_club_id'] ) : null;
			jc_set_club_user_club( $user_id, $club_id ?: null );
			return;
		}

		// Gestor: guarda múltiples clubs asignados (no aplica a supergestores).
		if ( in_array( 'gestor', $user_roles, true ) && ! in_array( 'supergestor', $user_roles, true ) ) {
			if ( ! isset( $_POST['jc_gestor_clubs_nonce'] ) ||
			     ! wp_verify_nonce( $_POST['jc_gestor_clubs_nonce'], 'jc_gestor_clubs_save' ) ) {
				return;
			}
			$club_ids = isset( $_POST['jc_clubs_gestionados'] )
				? array_map( 'absint', (array) $_POST['jc_clubs_gestionados'] )
				: array();
			jc_set_gestor_clubs( $user_id, $club_ids );
		}
	}

	// ─── Meta boxes en el post de club ─────────────────────────────────────────

	/**
	 * Registra los meta boxes en los posts de tipo 'club'.
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

		add_meta_box(
			'jc-club-users',
			'Usuarios Club',
			array( __CLASS__, 'render_club_users_meta_box' ),
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

	/**
	 * Renderiza el meta box "Usuarios Club" en el post de tipo 'club'.
	 *
	 * Muestra todos los usuarios con rol Club. Los marcados son los que tienen
	 * este club asignado. Un usuario Club solo puede pertenecer a un club, por lo
	 * que asignarlo aquí lo desvincula automáticamente de cualquier club anterior.
	 *
	 * @param WP_Post $post Post de tipo 'club' que se está editando.
	 */
	public static function render_club_users_meta_box( WP_Post $post ): void {
		$club_id = $post->ID;

		$club_users = get_users( array(
			'role'    => 'club',
			'orderby' => 'display_name',
			'order'   => 'ASC',
		) );

		wp_nonce_field( 'jc_club_users_save', 'jc_club_users_nonce' );
		?>
		<p style="margin-top:0; color:#666; font-size:12px;">
			Usuarios Club con acceso a este club:
		</p>
		<?php if ( empty( $club_users ) ) : ?>
			<p style="color:#999; font-style:italic; font-size:12px;">No hay usuarios Club registrados.</p>
		<?php else : ?>
			<?php foreach ( $club_users as $u ) : ?>
				<label style="display:block; margin-bottom:5px; font-size:13px;">
					<input type="checkbox"
					       name="jc_club_users[]"
					       value="<?php echo esc_attr( $u->ID ); ?>"
					       <?php checked( jc_get_club_user_club( $u->ID ), $club_id ); ?>>
					<?php echo esc_html( $u->display_name ?: $u->user_login ); ?>
				</label>
			<?php endforeach; ?>
			<p style="margin-top:8px; color:#999; font-size:11px;">
				Asignar un usuario aquí lo desvincula de su club anterior.
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Guarda los usuarios Club asignados al club cuando se guarda el post.
	 *
	 * - Los usuarios marcados quedan asignados a este club (sobrescribiendo el anterior).
	 * - Los usuarios desmarcados que tenían ESTE club asignado quedan sin club.
	 *
	 * @param int $post_id ID del post de tipo 'club' que se está guardando.
	 */
	public static function save_club_users_meta( int $post_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['jc_club_users_nonce'] ) ||
		     ! wp_verify_nonce( $_POST['jc_club_users_nonce'], 'jc_club_users_save' ) ) {
			return;
		}

		$selected_user_ids = isset( $_POST['jc_club_users'] )
			? array_map( 'absint', (array) $_POST['jc_club_users'] )
			: array();

		$club_users = get_users( array( 'role' => 'club' ) );

		foreach ( $club_users as $u ) {
			if ( in_array( $u->ID, $selected_user_ids, true ) ) {
				jc_set_club_user_club( $u->ID, $post_id );
			} elseif ( jc_get_club_user_club( $u->ID ) === $post_id ) {
				// Solo desvincula si actualmente estaba asignado a ESTE club.
				jc_set_club_user_club( $u->ID, null );
			}
		}
	}
}