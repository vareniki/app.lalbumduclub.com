<?php
/**
 * Gestión de usuarios con rol Club para el Gestor.
 *
 * Registra el shortcode [gestor-usuarios] y los handlers AJAX para que el
 * rol Gestor pueda crear, modificar y eliminar usuarios con rol Club.
 *
 * @package JugadoresClub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Gestor_Usuarios {

	/** @var bool Indica si el shortcode se usó en la página actual. */
	private static bool $enqueue = false;

	/** @var array Lista de clubs cargados en el shortcode (para pasarlos al JS). */
	private static array $clubs_data = array();

	/**
	 * Registra hooks de WordPress.
	 */
	public static function init(): void {
		add_shortcode( 'gestor-usuarios', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'wp_footer', array( __CLASS__, 'enqueue_assets' ) );

		add_action( 'wp_ajax_album_create_club_user', array( __CLASS__, 'ajax_create_user' ) );
		add_action( 'wp_ajax_album_update_club_user', array( __CLASS__, 'ajax_update_user' ) );
		add_action( 'wp_ajax_album_delete_club_user', array( __CLASS__, 'ajax_delete_user' ) );
	}

	/**
	 * Comprueba si el usuario actual puede gestionar usuarios Club.
	 */
	private static function can_manage(): bool {
		return current_user_can( 'manage_club_users' ) || current_user_can( 'manage_options' );
	}

	/**
	 * Obtiene el club_slug de un usuario (a través de ACF si está disponible).
	 */
	private static function get_club_slug( int $user_id ): string {
		if ( function_exists( 'get_field' ) ) {
			return (string) get_field( 'club_slug', 'user_' . $user_id );
		}
		return (string) get_user_meta( $user_id, 'club_slug', true );
	}

	/**
	 * Guarda el club_slug de un usuario (a través de ACF si está disponible).
	 */
	private static function set_club_slug( int $user_id, string $value ): void {
		if ( function_exists( 'update_field' ) ) {
			update_field( 'club_slug', $value, 'user_' . $user_id );
		} else {
			update_user_meta( $user_id, 'club_slug', $value );
		}
	}

	/**
	 * Obtiene los posts de tipo 'club' publicados.
	 *
	 * @return WP_Post[]
	 */
	private static function get_club_posts(): array {
		return get_posts( array(
			'post_type'      => 'club',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		) );
	}

	/**
	 * Construye el HTML del select de clubs.
	 */
	private static function clubs_select_options( string $selected_slug, array $clubs ): string {
		$html = '<option value="">— Sin asignar —</option>';
		foreach ( $clubs as $club ) {
			$html .= sprintf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $club->post_name ),
				selected( $selected_slug, $club->post_name, false ),
				esc_html( $club->post_title )
			);
		}
		return $html;
	}

	/**
	 * Renderiza el shortcode [gestor-usuarios].
	 *
	 * @param array|string $atts Atributos del shortcode.
	 * @return string HTML de la gestión de usuarios.
	 */
	public static function render_shortcode( $atts ): string {
		if ( ! self::can_manage() ) {
			return '';
		}

		self::$enqueue = true;

		$clubs = self::get_club_posts();

		// Guardar para el JS (enqueue_assets se ejecuta después).
		self::$clubs_data = array_map( fn( $c ) => array(
			'slug'   => $c->post_name,
			'nombre' => $c->post_title,
		), $clubs );

		$users = get_users( array(
			'role'    => 'club',
			'orderby' => 'display_name',
			'order'   => 'ASC',
		) );

		ob_start();
		?>
		<div id="gestor-usuarios-app" class="tw:space-y-4">

			<!-- Cabecera -->
			<div class="tw:flex tw:items-center tw:justify-between tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:px-6 tw:py-4">
				<h2 class="tw:text-lg tw:font-semibold tw:text-gray-800">Usuarios Club</h2>
				<button type="button" id="btn-nuevo-usuario"
				        class="tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors">
					<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
					</svg>
					Nuevo usuario
				</button>
			</div>

			<!-- Lista de usuarios -->
			<div id="usuarios-lista" class="tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:overflow-hidden">
				<?php if ( empty( $users ) ) : ?>
					<p id="usuarios-lista-empty" class="tw:px-6 tw:py-8 tw:text-center tw:text-gray-400 tw:text-sm">
						No hay usuarios con rol Club todavía.
					</p>
				<?php else : ?>
					<!-- Cabecera tabla (solo escritorio) -->
					<div class="tw:hidden tw:sm:grid tw:grid-cols-[1fr_1fr_1fr_auto] tw:gap-4 tw:px-6 tw:py-3 tw:bg-gray-50 tw:border-b tw:border-gray-200 tw:text-xs tw:font-medium tw:text-gray-500 tw:uppercase tw:tracking-wide">
						<span>Nombre / Usuario</span>
						<span>Email</span>
						<span>Club asignado</span>
						<span></span>
					</div>
					<?php foreach ( $users as $user ) :
						$club_slug   = self::get_club_slug( $user->ID );
						$club_nombre = '';
						foreach ( $clubs as $club ) {
							if ( $club->post_name === $club_slug ) {
								$club_nombre = $club->post_title;
								break;
							}
						}
					?>
					<div class="usuario-item tw:border-b tw:border-gray-100 last:tw:border-b-0"
					     data-user-id="<?php echo esc_attr( $user->ID ); ?>">

						<!-- Fila resumen -->
						<div class="usuario-row tw:grid tw:grid-cols-[1fr_auto] tw:sm:grid-cols-[1fr_1fr_1fr_auto] tw:gap-4 tw:items-center tw:px-6 tw:py-4 tw:hover:bg-gray-50 tw:transition-colors">
							<div>
								<span class="usuario-display-name tw:text-sm tw:font-medium tw:text-gray-800"><?php echo esc_html( $user->display_name ); ?></span>
								<span class="tw:text-xs tw:text-gray-400 tw:block">@<?php echo esc_html( $user->user_login ); ?></span>
							</div>
							<div class="tw:hidden tw:sm:block">
								<span class="usuario-email tw:text-sm tw:text-gray-600"><?php echo esc_html( $user->user_email ); ?></span>
							</div>
							<div class="tw:hidden tw:sm:block">
								<?php if ( $club_nombre ) : ?>
									<span class="usuario-club tw:inline-flex tw:items-center tw:px-2.5 tw:py-0.5 tw:rounded-full tw:text-xs tw:font-medium tw:bg-blue-100 tw:text-blue-800">
										<?php echo esc_html( $club_nombre ); ?>
									</span>
								<?php elseif ( $club_slug ) : ?>
									<span class="usuario-club tw:text-sm tw:text-gray-400"><?php echo esc_html( $club_slug ); ?></span>
								<?php else : ?>
									<span class="usuario-club tw:text-xs tw:text-gray-300">Sin asignar</span>
								<?php endif; ?>
							</div>
							<div class="tw:flex tw:items-center tw:gap-2">
								<button type="button" class="btn-edit-usuario tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors" title="Editar usuario">
									<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
										<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/>
									</svg>
								</button>
								<button type="button" class="btn-delete-usuario tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors"
								        data-user-id="<?php echo esc_attr( $user->ID ); ?>"
								        data-nombre="<?php echo esc_attr( $user->display_name ?: $user->user_login ); ?>"
								        title="Eliminar usuario">
									<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
										<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
									</svg>
								</button>
							</div>
						</div>

						<!-- Panel de edición -->
						<div class="usuario-edit-panel tw:hidden tw:border-t tw:border-gray-100 tw:px-6 tw:py-4 tw:bg-gray-50">
							<div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-2 tw:gap-3">
								<div>
									<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre para mostrar</label>
									<input type="text" class="edit-display-name tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
									       value="<?php echo esc_attr( $user->display_name ); ?>">
								</div>
								<div>
									<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Email</label>
									<input type="email" class="edit-email tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
									       value="<?php echo esc_attr( $user->user_email ); ?>">
								</div>
								<div>
									<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">
										Nueva contraseña
										<span class="tw:text-gray-400 tw:font-normal">(vacío = no cambiar)</span>
									</label>
									<input type="password" class="edit-password tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
									       placeholder="••••••••" autocomplete="new-password">
								</div>
								<div>
									<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Club asignado</label>
									<select class="edit-club-slug tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none tw:bg-white">
										<?php echo self::clubs_select_options( $club_slug, $clubs ); ?>
									</select>
								</div>
							</div>
							<div class="usuario-edit-error tw:hidden tw:mt-2 tw:text-sm tw:text-red-600"></div>
							<div class="tw:flex tw:gap-2 tw:mt-3">
								<button type="button" class="btn-save-usuario tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-1.5 tw:rounded-lg tw:transition-colors">
									Guardar
								</button>
								<button type="button" class="btn-cancel-usuario tw:text-gray-400 tw:hover:text-gray-600 tw:text-sm tw:px-3 tw:py-1.5 tw:rounded-lg tw:transition-colors">
									Cancelar
								</button>
							</div>
						</div>

					</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<!-- Panel nuevo usuario -->
			<div id="nuevo-usuario-panel" class="tw:hidden tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:px-6 tw:py-5">
				<h3 class="tw:text-base tw:font-semibold tw:text-gray-800 tw:mb-4">Nuevo usuario Club</h3>
				<div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-2 tw:gap-3">
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">
							Usuario <span class="tw:text-red-400">*</span>
						</label>
						<input type="text" id="new-username"
						       class="tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
						       placeholder="nombre.usuario" autocomplete="off">
					</div>
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre para mostrar</label>
						<input type="text" id="new-display-name"
						       class="tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
						       placeholder="Nombre completo">
					</div>
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">
							Email <span class="tw:text-red-400">*</span>
						</label>
						<input type="email" id="new-email"
						       class="tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
						       placeholder="correo@ejemplo.com">
					</div>
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">
							Contraseña <span class="tw:text-red-400">*</span>
						</label>
						<input type="password" id="new-password"
						       class="tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
						       placeholder="••••••••" autocomplete="new-password">
					</div>
					<div class="tw:sm:col-span-2">
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Club asignado</label>
						<select id="new-club-slug"
						        class="tw:w-full tw:sm:w-72 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none tw:bg-white">
							<?php echo self::clubs_select_options( '', $clubs ); ?>
						</select>
					</div>
				</div>
				<div id="nuevo-usuario-error" class="tw:hidden tw:mt-3 tw:text-sm tw:text-red-600"></div>
				<div class="tw:flex tw:gap-2 tw:mt-4">
					<button type="button" id="btn-crear-usuario"
					        class="tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors">
						Crear usuario
					</button>
					<button type="button" id="btn-cancel-nuevo"
					        class="tw:text-gray-400 tw:hover:text-gray-600 tw:text-sm tw:px-3 tw:py-2 tw:rounded-lg tw:transition-colors">
						Cancelar
					</button>
				</div>
			</div>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Encola assets CSS y JS solo si el shortcode se usó.
	 */
	public static function enqueue_assets(): void {
		if ( ! self::$enqueue ) {
			return;
		}

		if ( ! wp_style_is( 'jugadores-club', 'enqueued' ) ) {
			wp_enqueue_style(
				'jugadores-club',
				JC_URI . 'assets/css/jugadores-club.css',
				array(),
				JC_VERSION
			);
		}

		if ( ! wp_style_is( 'css-tailwind', 'enqueued' ) ) {
			wp_enqueue_style(
				'css-tailwind',
				JC_URI . 'assets/css/tailwind.css',
				array(),
				JC_VERSION
			);
		}

		wp_enqueue_script(
			'jc-gestor-usuarios',
			JC_URI . 'assets/js/gestor-usuarios.js',
			array(),
			JC_VERSION,
			true
		);

		wp_localize_script( 'jc-gestor-usuarios', 'albumGestor', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'album_club_nonce' ),
			'clubs'   => self::$clubs_data,
		) );
	}

	// ─── AJAX handlers ─────────────────────────────────────

	/**
	 * Crea un nuevo usuario con rol Club.
	 */
	public static function ajax_create_user(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! self::can_manage() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$username     = sanitize_user( trim( $_POST['username'] ?? '' ) );
		$email        = sanitize_email( trim( $_POST['email'] ?? '' ) );
		$password     = $_POST['password'] ?? '';
		$display_name = sanitize_text_field( trim( $_POST['display_name'] ?? '' ) );
		$club_slug    = sanitize_text_field( trim( $_POST['club_slug'] ?? '' ) );

		if ( ! $username || ! $email || ! $password ) {
			wp_send_json_error( 'Usuario, email y contraseña son obligatorios.' );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( 'El email no es válido.' );
		}

		if ( username_exists( $username ) ) {
			wp_send_json_error( 'El nombre de usuario ya existe.' );
		}

		if ( email_exists( $email ) ) {
			wp_send_json_error( 'El email ya está registrado.' );
		}

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $display_name ?: $username,
			'role'         => 'club',
		) );

		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( $user_id->get_error_message() );
		}

		if ( $club_slug ) {
			self::set_club_slug( $user_id, $club_slug );
		}

		wp_send_json_success( array(
			'id'           => $user_id,
			'login'        => $username,
			'email'        => $email,
			'display_name' => $display_name ?: $username,
			'club_slug'    => $club_slug,
		) );
	}

	/**
	 * Actualiza un usuario con rol Club.
	 */
	public static function ajax_update_user(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! self::can_manage() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$user_id      = absint( $_POST['user_id'] ?? 0 );
		$display_name = sanitize_text_field( trim( $_POST['display_name'] ?? '' ) );
		$email        = sanitize_email( trim( $_POST['email'] ?? '' ) );
		$password     = $_POST['password'] ?? '';
		$club_slug    = sanitize_text_field( trim( $_POST['club_slug'] ?? '' ) );

		if ( ! $user_id ) {
			wp_send_json_error( 'ID de usuario inválido.' );
		}

		// Solo permite modificar usuarios con rol 'club'.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( 'club', (array) $user->roles, true ) ) {
			wp_send_json_error( 'Usuario no encontrado o sin rol Club.' );
		}

		if ( $email && ! is_email( $email ) ) {
			wp_send_json_error( 'El email no es válido.' );
		}

		if ( $email && $email !== $user->user_email && email_exists( $email ) ) {
			wp_send_json_error( 'El email ya está registrado por otro usuario.' );
		}

		$userdata = array( 'ID' => $user_id );
		if ( $display_name ) {
			$userdata['display_name'] = $display_name;
		}
		if ( $email ) {
			$userdata['user_email'] = $email;
		}
		if ( $password ) {
			$userdata['user_pass'] = $password;
		}

		$result = wp_update_user( $userdata );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		self::set_club_slug( $user_id, $club_slug );

		wp_send_json_success( array(
			'id'           => $user_id,
			'email'        => $email ?: $user->user_email,
			'display_name' => $display_name ?: $user->display_name,
			'club_slug'    => $club_slug,
		) );
	}

	/**
	 * Elimina un usuario con rol Club.
	 */
	public static function ajax_delete_user(): void {
		check_ajax_referer( 'album_club_nonce', 'nonce' );

		if ( ! self::can_manage() ) {
			wp_send_json_error( 'Sin permisos.' );
		}

		$user_id = absint( $_POST['user_id'] ?? 0 );

		if ( ! $user_id ) {
			wp_send_json_error( 'ID de usuario inválido.' );
		}

		// Solo permite eliminar usuarios con rol 'club'.
		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( 'club', (array) $user->roles, true ) ) {
			wp_send_json_error( 'Usuario no encontrado o sin rol Club.' );
		}

		require_once ABSPATH . 'wp-admin/includes/user.php';
		$deleted = wp_delete_user( $user_id );

		if ( ! $deleted ) {
			wp_send_json_error( 'Error al eliminar el usuario.' );
		}

		wp_send_json_success();
	}
}