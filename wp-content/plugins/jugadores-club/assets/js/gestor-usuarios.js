/* global albumGestor */

( function () {
	'use strict';

	const app = document.getElementById( 'gestor-usuarios-app' );
	if ( ! app ) return;

	const { ajaxUrl, nonce, clubs } = albumGestor;

	// ── Helpers ──────────────────────────────────────────────

	function escHTML( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	async function ajax( action, data = {} ) {
		const fd = new FormData();
		fd.append( 'action', action );
		fd.append( 'nonce', nonce );
		for ( const [ k, v ] of Object.entries( data ) ) {
			fd.append( k, v );
		}
		const res = await fetch( ajaxUrl, { method: 'POST', body: fd } );
		return res.json();
	}

	function buildClubSelect( selectedSlug = '' ) {
		let html = '<option value="">— Sin asignar —</option>';
		for ( const club of clubs ) {
			const sel = club.slug === selectedSlug ? ' selected' : '';
			html += `<option value="${ escHTML( club.slug ) }"${ sel }>${ escHTML( club.nombre ) }</option>`;
		}
		return html;
	}

	function clubNombre( slug ) {
		const found = clubs.find( c => c.slug === slug );
		return found ? found.nombre : slug;
	}

	// ── Panel nuevo usuario ───────────────────────────────────

	document.getElementById( 'btn-nuevo-usuario' )?.addEventListener( 'click', () => {
		const panel = document.getElementById( 'nuevo-usuario-panel' );
		panel.classList.toggle( 'tw:hidden' );
		if ( ! panel.classList.contains( 'tw:hidden' ) ) {
			document.getElementById( 'new-username' )?.focus();
		}
	} );

	document.getElementById( 'btn-cancel-nuevo' )?.addEventListener( 'click', () => {
		document.getElementById( 'nuevo-usuario-panel' ).classList.add( 'tw:hidden' );
		clearNewForm();
	} );

	document.getElementById( 'btn-crear-usuario' )?.addEventListener( 'click', async () => {
		const btn      = document.getElementById( 'btn-crear-usuario' );
		const errorEl  = document.getElementById( 'nuevo-usuario-error' );
		const username = document.getElementById( 'new-username' ).value.trim();
		const email    = document.getElementById( 'new-email' ).value.trim();
		const password = document.getElementById( 'new-password' ).value;
		const display  = document.getElementById( 'new-display-name' ).value.trim();
		const clubSlug = document.getElementById( 'new-club-slug' ).value;

		errorEl.classList.add( 'tw:hidden' );
		errorEl.textContent = '';

		if ( ! username || ! email || ! password ) {
			errorEl.textContent = 'Usuario, email y contraseña son obligatorios.';
			errorEl.classList.remove( 'tw:hidden' );
			return;
		}

		btn.disabled    = true;
		btn.textContent = 'Creando...';

		try {
			const res = await ajax( 'album_create_club_user', {
				username,
				email,
				password,
				display_name: display,
				club_slug:    clubSlug,
			} );

			if ( ! res.success ) {
				errorEl.textContent = res.data || 'Error al crear el usuario.';
				errorEl.classList.remove( 'tw:hidden' );
				return;
			}

			appendUserRow( res.data );
			document.getElementById( 'nuevo-usuario-panel' ).classList.add( 'tw:hidden' );
			clearNewForm();

			// Eliminar mensaje vacío si existe.
			document.getElementById( 'usuarios-lista-empty' )?.remove();

		} catch ( e ) {
			errorEl.textContent = 'Error de conexión.';
			errorEl.classList.remove( 'tw:hidden' );
		} finally {
			btn.disabled    = false;
			btn.textContent = 'Crear usuario';
		}
	} );

	function clearNewForm() {
		[ 'new-username', 'new-display-name', 'new-email', 'new-password' ].forEach( id => {
			const el = document.getElementById( id );
			if ( el ) el.value = '';
		} );
		const slug = document.getElementById( 'new-club-slug' );
		if ( slug ) slug.value = '';
		const err = document.getElementById( 'nuevo-usuario-error' );
		if ( err ) {
			err.classList.add( 'tw:hidden' );
			err.textContent = '';
		}
	}

	// ── Delegación de eventos en lista ────────────────────────

	document.getElementById( 'usuarios-lista' )?.addEventListener( 'click', async ( e ) => {

		// Abrir / cerrar panel de edición.
		if ( e.target.closest( '.btn-edit-usuario' ) ) {
			const item   = e.target.closest( '.usuario-item' );
			if ( ! item ) return;
			const panel  = item.querySelector( '.usuario-edit-panel' );
			const isOpen = ! panel.classList.contains( 'tw:hidden' );

			// Cerrar todos los panels abiertos.
			app.querySelectorAll( '.usuario-edit-panel' ).forEach( p => p.classList.add( 'tw:hidden' ) );

			if ( ! isOpen ) {
				panel.classList.remove( 'tw:hidden' );
				panel.querySelector( '.edit-display-name' )?.focus();
			}
			return;
		}

		// Cancelar edición.
		if ( e.target.closest( '.btn-cancel-usuario' ) ) {
			e.target.closest( '.usuario-item' )?.querySelector( '.usuario-edit-panel' )?.classList.add( 'tw:hidden' );
			return;
		}

		// Guardar edición.
		if ( e.target.closest( '.btn-save-usuario' ) ) {
			const item = e.target.closest( '.usuario-item' );
			if ( item ) await saveUser( item );
			return;
		}

		// Eliminar usuario.
		if ( e.target.closest( '.btn-delete-usuario' ) ) {
			const btn    = e.target.closest( '.btn-delete-usuario' );
			const userId = btn.dataset.userId;
			const nombre = btn.dataset.nombre;

			if ( ! confirm( `¿Eliminar el usuario "${ nombre }"? Esta acción no se puede deshacer.` ) ) return;
			await deleteUser( btn, userId );
			return;
		}
	} );

	async function saveUser( item ) {
		const userId      = item.dataset.userId;
		const displayName = item.querySelector( '.edit-display-name' ).value.trim();
		const email       = item.querySelector( '.edit-email' ).value.trim();
		const password    = item.querySelector( '.edit-password' ).value;
		const clubSlug    = item.querySelector( '.edit-club-slug' ).value;
		const errorEl     = item.querySelector( '.usuario-edit-error' );
		const btn         = item.querySelector( '.btn-save-usuario' );

		errorEl.classList.add( 'tw:hidden' );
		errorEl.textContent = '';

		btn.disabled    = true;
		btn.textContent = 'Guardando...';

		try {
			const res = await ajax( 'album_update_club_user', {
				user_id:      userId,
				display_name: displayName,
				email,
				password,
				club_slug:    clubSlug,
			} );

			if ( ! res.success ) {
				errorEl.textContent = res.data || 'Error al guardar.';
				errorEl.classList.remove( 'tw:hidden' );
				return;
			}

			// Actualizar valores mostrados en la fila.
			const row = item.querySelector( '.usuario-row' );
			if ( res.data.display_name ) {
				row.querySelector( '.usuario-display-name' ).textContent = res.data.display_name;
			}
			if ( res.data.email ) {
				row.querySelector( '.usuario-email' ).textContent = res.data.email;
			}

			const clubEl    = row.querySelector( '.usuario-club' );
			const clubLabel = res.data.club_slug ? clubNombre( res.data.club_slug ) : 'Sin asignar';
			if ( clubEl ) {
				clubEl.textContent = clubLabel;
				if ( res.data.club_slug ) {
					clubEl.className = 'usuario-club tw:inline-flex tw:items-center tw:px-2.5 tw:py-0.5 tw:rounded-full tw:text-xs tw:font-medium tw:bg-blue-100 tw:text-blue-800';
				} else {
					clubEl.className = 'usuario-club tw:text-xs tw:text-gray-300';
				}
			}

			// Limpiar contraseña y cerrar panel.
			item.querySelector( '.edit-password' ).value = '';
			item.querySelector( '.usuario-edit-panel' ).classList.add( 'tw:hidden' );

		} catch ( e ) {
			errorEl.textContent = 'Error de conexión.';
			errorEl.classList.remove( 'tw:hidden' );
		} finally {
			btn.disabled    = false;
			btn.textContent = 'Guardar';
		}
	}

	async function deleteUser( btn, userId ) {
		btn.disabled = true;

		try {
			const res = await ajax( 'album_delete_club_user', { user_id: userId } );

			if ( ! res.success ) {
				alert( res.data || 'Error al eliminar.' );
				btn.disabled = false;
				return;
			}

			btn.closest( '.usuario-item' )?.remove();

			// Mostrar mensaje vacío si ya no quedan usuarios.
			const lista = document.getElementById( 'usuarios-lista' );
			if ( lista && ! lista.querySelector( '.usuario-item' ) ) {
				const p = document.createElement( 'p' );
				p.id        = 'usuarios-lista-empty';
				p.className = 'tw:px-6 tw:py-8 tw:text-center tw:text-gray-400 tw:text-sm';
				p.textContent = 'No hay usuarios con rol Club todavía.';
				lista.appendChild( p );
			}

		} catch ( e ) {
			alert( 'Error de conexión.' );
			btn.disabled = false;
		}
	}

	// ── Añadir fila tras creación ─────────────────────────────

	function appendUserRow( user ) {
		const lista = document.getElementById( 'usuarios-lista' );
		if ( ! lista ) return;

		// Añadir cabecera si era la primera fila.
		if ( ! lista.querySelector( '.grid[class*="sm:grid-cols-"]' ) ) {
			const header = document.createElement( 'div' );
			header.className = 'tw:hidden tw:sm:grid tw:grid-cols-[1fr_1fr_1fr_auto] tw:gap-4 tw:px-6 tw:py-3 tw:bg-gray-50 tw:border-b tw:border-gray-200 tw:text-xs tw:font-medium tw:text-gray-500 tw:uppercase tw:tracking-wide';
			header.innerHTML = '<span>Nombre / Usuario</span><span>Email</span><span>Club asignado</span><span></span>';
			lista.insertBefore( header, lista.firstChild );
		}

		const clubLabel = user.club_slug ? clubNombre( user.club_slug ) : '';
		const clubBadge = user.club_slug
			? `<span class="usuario-club tw:inline-flex tw:items-center tw:px-2.5 tw:py-0.5 tw:rounded-full tw:text-xs tw:font-medium tw:bg-blue-100 tw:text-blue-800">${ escHTML( clubLabel ) }</span>`
			: `<span class="usuario-club tw:text-xs tw:text-gray-300">Sin asignar</span>`;

		const div = document.createElement( 'div' );
		div.className        = 'usuario-item tw:border-b tw:border-gray-100 last:tw:border-b-0';
		div.dataset.userId   = user.id;
		div.innerHTML = `
			<div class="usuario-row tw:grid tw:grid-cols-[1fr_auto] tw:sm:grid-cols-[1fr_1fr_1fr_auto] tw:gap-4 tw:items-center tw:px-6 tw:py-4 tw:hover:bg-gray-50 tw:transition-colors">
				<div>
					<span class="usuario-display-name tw:text-sm tw:font-medium tw:text-gray-800">${ escHTML( user.display_name ) }</span>
					<span class="tw:text-xs tw:text-gray-400 tw:block">@${ escHTML( user.login ) }</span>
				</div>
				<div class="tw:hidden tw:sm:block">
					<span class="usuario-email tw:text-sm tw:text-gray-600">${ escHTML( user.email ) }</span>
				</div>
				<div class="tw:hidden tw:sm:block">
					${ clubBadge }
				</div>
				<div class="tw:flex tw:items-center tw:gap-2">
					<button type="button" class="btn-edit-usuario tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors" title="Editar usuario">
						<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/>
						</svg>
					</button>
					<button type="button" class="btn-delete-usuario tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors"
					        data-user-id="${ escHTML( String( user.id ) ) }"
					        data-nombre="${ escHTML( user.display_name || user.login ) }"
					        title="Eliminar usuario">
						<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
							<path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
						</svg>
					</button>
				</div>
			</div>
			<div class="usuario-edit-panel tw:hidden tw:border-t tw:border-gray-100 tw:px-6 tw:py-4 tw:bg-gray-50">
				<div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-2 tw:gap-3">
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre para mostrar</label>
						<input type="text" class="edit-display-name tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
						       value="${ escHTML( user.display_name ) }">
					</div>
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Email</label>
						<input type="email" class="edit-email tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
						       value="${ escHTML( user.email ) }">
					</div>
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nueva contraseña <span class="tw:text-gray-400 tw:font-normal">(vacío = no cambiar)</span></label>
						<input type="password" class="edit-password tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none"
						       placeholder="••••••••" autocomplete="new-password">
					</div>
					<div>
						<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Club asignado</label>
						<select class="edit-club-slug tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none tw:bg-white">
							${ buildClubSelect( user.club_slug || '' ) }
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
		`;

		lista.appendChild( div );
	}

} )();
