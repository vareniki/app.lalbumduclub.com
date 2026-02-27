/**
 * Gestión de jugadores del club.
 *
 * - Drag & drop entre categorías (SortableJS)
 * - Eliminar jugador
 * - Añadir jugadores en bulk
 * - Subida de fotos a UploadCare
 * - Toggle foto expandida
 */
(function () {
	'use strict';

	var container = document.getElementById( 'club-categorias' );
	if ( ! container ) return;

	var clubId = container.dataset.clubId;

	// Input de archivo compartido.
	var fileInput           = document.createElement( 'input' );
	fileInput.type          = 'file';
	fileInput.accept        = 'image/*';
	fileInput.style.display = 'none';
	document.body.appendChild( fileInput );

	var activeJugadorEl = null;

	var UNASSIGN_BTN_HTML = '<button type="button" class="btn-unassign-foto tw:mt-2 tw:inline-flex tw:items-center tw:gap-1 tw:text-sm tw:text-red-400 tw:hover:text-red-600 tw:transition-colors" title="Quitar foto">'
		+ '<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">'
		+ '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>'
		+ '</svg>Quitar foto</button>';

	// ─── Sortable ──────────────────────────────────────────

	initSortables();

	function initSortables() {
		container.querySelectorAll( '.club-jugadores' ).forEach( function ( list ) {
			if ( list._sortable ) return;
			list._sortable = Sortable.create( list, {
				group:           'jugadores',
				handle:          '.drag-handle',
				filter:          'button, .jugador-foto-trigger',
				preventOnFilter: false,
				animation:       200,
				ghostClass:      'tw:opacity-30',
				dragClass:       'tw:shadow-lg',
				onEnd:           onSortEnd,
			} );
		} );
	}

	function onSortEnd() {
		var categories = [];

		container.querySelectorAll( '.club-jugadores' ).forEach( function ( list ) {
			var uid   = list.dataset.categoryUid;
			var items = list.querySelectorAll( '.club-jugador' );
			var ids   = [];

			items.forEach( function ( item ) {
				ids.push( parseInt( item.dataset.jugadorId, 10 ) );
			} );

			categories.push( { category_uid: uid, jugador_ids: ids } );
			updateCount( list );
		} );

		ajax( 'album_reorder_jugadores', {
			club_id:    clubId,
			categories: JSON.stringify( categories ),
		} );
	}

	// ─── Click handler único ───────────────────────────────

	container.addEventListener( 'click', function ( e ) {
		var target;

		// Toggle foto expandida.
		target = e.target.closest( '.btn-toggle-foto' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			var jugador  = target.closest( '.club-jugador' );
			var expanded = jugador.querySelector( '.jugador-foto-expanded' );
			var icon     = target.querySelector( 'svg' );
			expanded.classList.toggle( 'tw:hidden' );
			icon.classList.toggle( 'tw:rotate-180' );
			return;
		}

		// Quitar foto.
		target = e.target.closest( '.btn-unassign-foto' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			var jugadorEl     = target.closest( '.club-jugador' );
			var jugadorId     = jugadorEl.dataset.jugadorId;
			var hadNombreFoto = !! jugadorEl.dataset.nombreFoto;
			ajax( 'album_clear_jugador_foto', {
				club_id:    clubId,
				jugador_id: jugadorId,
			}, function ( res ) {
				if ( ! res.success ) return;
				// Resetear avatar.
				var trigger = jugadorEl.querySelector( '.jugador-foto-trigger' );
				trigger.innerHTML = '<svg class="tw:w-full tw:h-full tw:text-gray-400 tw:p-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/></svg>';
				// Vaciar y ocultar foto expandida.
				var expanded = jugadorEl.querySelector( '.jugador-foto-expanded' );
				expanded.innerHTML = '';
				expanded.classList.add( 'tw:hidden' );
				// Ocultar toggle.
				var toggleBtn = jugadorEl.querySelector( '.btn-toggle-foto' );
				toggleBtn.classList.add( 'tw:hidden' );
				toggleBtn.querySelector( 'svg' ).classList.remove( 'tw:rotate-180' );
				// Actualizar estadísticas solo si no había nombre_foto manual.
				if ( ! hadNombreFoto ) {
					updateStats( 0, -1 );
				}
			} );
			return;
		}

		// Eliminar jugador.
		target = e.target.closest( '.btn-delete-jugador' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			var jugadorId = target.dataset.jugadorId;
			var row       = target.closest( '.club-jugador' );
			var list      = target.closest( '.club-jugadores' );
			if ( ! confirm( '¿Eliminar este jugador?' ) ) return;
			var hadFoto = !! row.querySelector( '.jugador-foto-trigger img' ) || !! row.dataset.nombreFoto;
			ajax( 'album_delete_jugador', {
				club_id:    clubId,
				jugador_id: jugadorId,
			}, function ( res ) {
				if ( res.success ) {
					row.remove();
					updateCount( list );
					updateStats( -1, hadFoto ? -1 : 0 );
				}
			} );
			return;
		}

		// Subir foto.
		target = e.target.closest( '.jugador-foto-trigger' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			activeJugadorEl = target.closest( '.club-jugador' );
			fileInput.value = '';
			fileInput.click();
			return;
		}

		// Editar jugador — abrir panel.
		target = e.target.closest( '.btn-edit-jugador' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			var jugadorEl = target.closest( '.club-jugador' );
			var panel     = jugadorEl.querySelector( '.jugador-edit-panel' );
			panel.classList.toggle( 'tw:hidden' );
			if ( ! panel.classList.contains( 'tw:hidden' ) ) {
				panel.querySelector( '.edit-nombre' ).focus();
			}
			return;
		}

		// Editar jugador — guardar.
		target = e.target.closest( '.btn-save-edit' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleSaveEdit( target );
			return;
		}

		// Editar jugador — cancelar.
		target = e.target.closest( '.btn-cancel-edit' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			target.closest( '.jugador-edit-panel' ).classList.add( 'tw:hidden' );
			return;
		}

		// Bulk add.
		target = e.target.closest( '.btn-bulk-add' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleBulkAdd( target );
			return;
		}

		// Single add.
		target = e.target.closest( '.btn-single-add' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleSingleAdd( target );
			return;
		}
	} );

	// ─── Upload handler ────────────────────────────────────

	fileInput.addEventListener( 'change', function () {
		if ( ! fileInput.files.length || ! activeJugadorEl ) return;

		var file          = fileInput.files[0];
		var fileName      = file.name.substring( 0, 64 );
		var jugadorEl     = activeJugadorEl;
		var jugadorId     = jugadorEl.dataset.jugadorId;
		var trigger       = jugadorEl.querySelector( '.jugador-foto-trigger' );
		var hadFotoYa     = !! jugadorEl.querySelector( '.jugador-foto-trigger img' ) || !! jugadorEl.dataset.nombreFoto;

		activeJugadorEl = null;

		trigger.classList.add( 'tw:opacity-50', 'tw:pointer-events-none' );

		uploadToUploadcare( file, function ( cdnUrl ) {
			trigger.classList.remove( 'tw:opacity-50', 'tw:pointer-events-none' );

			if ( ! cdnUrl ) {
				alert( 'Error al subir la imagen.' );
				return;
			}

			// Añade a cdnUrl un pequeño parámetro de compresión.
			cdnUrl += '-/preview/1000x666/';

			// Actualizar avatar.
			trigger.innerHTML = '<img class="tw:w-full tw:h-full tw:object-cover" src="' + escAttr( cdnUrl ) + '" alt="">';

			// Mostrar foto expandida con botón para quitar.
			var expanded = jugadorEl.querySelector( '.jugador-foto-expanded' );
			expanded.innerHTML = '<img class="tw:rounded-lg tw:max-w-xl tw:w-full" src="' + escAttr( cdnUrl ) + '-/preview/1000x666/" alt="">'
				+ UNASSIGN_BTN_HTML;
			expanded.classList.remove( 'tw:hidden' );

			// Mostrar toggle y marcar como abierto.
			var toggleBtn = jugadorEl.querySelector( '.btn-toggle-foto' );
			toggleBtn.classList.remove( 'tw:hidden' );
			toggleBtn.querySelector( 'svg' ).classList.add( 'tw:rotate-180' );

			// Actualizar nombre_foto en la fila.
			var nombreFotoSpan = jugadorEl.querySelector( '.jugador-nombre-foto-display' );
			if ( nombreFotoSpan ) {
				nombreFotoSpan.textContent = '(' + fileName + ')';
			} else {
				var displayEl = jugadorEl.querySelector( '.tw\\:flex-1.tw\\:min-w-0' ) || jugadorEl.querySelector( '[class*="flex-1"]' );
				if ( displayEl ) {
					displayEl.insertAdjacentHTML( 'beforeend', ' <span class="jugador-nombre-foto-display tw:text-xs tw:text-sky-800">(' + escHTML( fileName ) + ')</span>' );
				}
			}
			jugadorEl.dataset.nombreFoto = fileName;

			// Actualizar el input del panel de edición.
			var editInput = jugadorEl.querySelector( '.edit-nombre-foto' );
			if ( editInput ) {
				editInput.value = fileName;
			}

			// Guardar en BD.
			ajax( 'album_update_jugador_foto', {
				club_id:     clubId,
				jugador_id:  jugadorId,
				foto_url:    cdnUrl,
				nombre_foto: fileName,
			}, function ( res ) {
				if ( res.success && ! hadFotoYa ) {
					updateStats( 0, 1 );
				}
			} );
		} );
	} );

	function uploadToUploadcare( file, callback ) {
		var formData = new FormData();
		formData.append( 'UPLOADCARE_PUB_KEY', albumClub.ucPubKey );
		formData.append( 'UPLOADCARE_STORE', '1' );
		formData.append( 'file', file );

		fetch( 'https://upload.uploadcare.com/base/', {
			method: 'POST',
			body:   formData,
		} )
		.then( function ( res ) { return res.json(); } )
		.then( function ( res ) {
			if ( res.file ) {
				callback( 'https://y9vl0yvu4z.ucarecd.net/' + res.file + '/' );
			} else {
				callback( null );
			}
		} )
		.catch( function () {
			callback( null );
		} );
	}

	// ─── Edición inline ────────────────────────────────────

	function handleSaveEdit( btn ) {
		var panel      = btn.closest( '.jugador-edit-panel' );
		var jugadorEl  = panel.closest( '.club-jugador' );
		var jugadorId  = jugadorEl.dataset.jugadorId;
		var nombre     = panel.querySelector( '.edit-nombre' ).value.trim();
		var apellidos  = panel.querySelector( '.edit-apellidos' ).value.trim();
		var cargo      = panel.querySelector( '.edit-cargo' ).value.trim();
		var nombreFoto = panel.querySelector( '.edit-nombre-foto' ).value.trim();

		if ( ! nombre ) return;

		var hadFoto = !! jugadorEl.querySelector( '.jugador-foto-trigger img' ) || !! jugadorEl.dataset.nombreFoto;

		btn.disabled = true;

		ajax( 'album_update_jugador', {
			club_id:     clubId,
			jugador_id:  jugadorId,
			nombre:      nombre,
			apellidos:   apellidos,
			cargo:       cargo,
			nombre_foto: nombreFoto,
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			// Actualizar texto visible en la fila.
			var nombreCompleto  = res.data.nombre + ( res.data.apellidos ? ' ' + res.data.apellidos : '' );
			var nuevoNombreFoto = res.data.nombre_foto || '';
			var displayEl       = jugadorEl.querySelector( '.tw\\:flex-1.tw\\:min-w-0' ) || jugadorEl.querySelector( '[class*="flex-1"]' );

			if ( displayEl ) {
				var html = '<span class="jugador-nombre-display tw:text-sm tw:font-medium tw:text-gray-800">' + escHTML( nombreCompleto ) + '</span>';
				if ( res.data.cargo ) {
					html += ' - <span class="jugador-cargo-display tw:text-xs tw:text-gray-400">' + escHTML( res.data.cargo ) + '</span>';
				}
				if ( nuevoNombreFoto ) {
					html += ' <span class="jugador-nombre-foto-display tw:text-xs tw:text-sky-800">(' + escHTML( nuevoNombreFoto ) + ')</span>';
				}
				displayEl.innerHTML = html;
			}

			// Actualizar data-nombre-foto y estadísticas.
			var hasFoto = !! jugadorEl.querySelector( '.jugador-foto-trigger img' ) || !! nuevoNombreFoto;
			if ( hadFoto !== hasFoto ) {
				updateStats( 0, hasFoto ? 1 : -1 );
			}
			jugadorEl.dataset.nombreFoto = nuevoNombreFoto;

			panel.classList.add( 'tw:hidden' );
		} );
	}

	// ─── Bulk add ──────────────────────────────────────────

	function handleBulkAdd( btn ) {
		var section     = btn.closest( 'section' );
		var categoryUid = btn.dataset.categoryUid;
		var textarea    = section.querySelector( '.bulk-add__input' );
		var raw         = ( textarea.value || '' ).trim();

		if ( ! raw ) return;

		var jugadores = raw.split( /\n/ ).map( function ( linea ) {
			var partes = linea.split( ',' ).map( function ( s ) { return s.trim(); } );
			return {
				nombre:    partes[0] || '',
				apellidos: partes[1] || '',
				cargo:     partes[2] || '',
			};
		} ).filter( function ( j ) { return j.nombre !== ''; } );

		if ( ! jugadores.length ) return;

		btn.disabled = true;

		ajax( 'album_bulk_add_jugadores', {
			club_id:      clubId,
			category_uid: categoryUid,
			jugadores:    JSON.stringify( jugadores ),
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			var list = section.querySelector( '.club-jugadores' );

			res.data.forEach( function ( j ) {
				list.insertAdjacentHTML( 'beforeend', playerHTML( j ) );
			} );

			textarea.value = '';
			updateCount( list );
			updateStats( res.data.length, 0 );
		} );
	}

	// ─── Single add ───────────────────────────────────────

	function handleSingleAdd( btn ) {
		var formEl      = btn.closest( '.single-add' );
		var categoryUid = formEl.dataset.categoryUid;
		var nombre      = formEl.querySelector( '.single-add__nombre' ).value.trim();
		var apellidos   = formEl.querySelector( '.single-add__apellidos' ).value.trim();
		var cargo       = formEl.querySelector( '.single-add__cargo' ).value.trim();
		var nombreFoto  = formEl.querySelector( '.single-add__nombre-foto' ).value.trim();

		if ( ! nombre ) {
			formEl.querySelector( '.single-add__nombre' ).focus();
			return;
		}

		btn.disabled = true;

		ajax( 'album_add_jugador', {
			club_id:      clubId,
			category_uid: categoryUid,
			nombre:       nombre,
			apellidos:    apellidos,
			cargo:        cargo,
			nombre_foto:  nombreFoto,
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			var section = formEl.closest( 'section' );
			var list    = section.querySelector( '.club-jugadores' );

			list.insertAdjacentHTML( 'beforeend', playerHTML( res.data ) );
			initSortables();
			updateCount( list );
			updateStats( 1, res.data.nombre_foto || res.data.foto_url ? 1 : 0 );

			// Limpiar formulario.
			formEl.querySelector( '.single-add__nombre' ).value      = '';
			formEl.querySelector( '.single-add__apellidos' ).value   = '';
			formEl.querySelector( '.single-add__cargo' ).value       = '';
			formEl.querySelector( '.single-add__nombre-foto' ).value = '';
			formEl.querySelector( '.single-add__nombre' ).focus();
		} );
	}

	// ─── Helpers ───────────────────────────────────────────

	function updateStats( deltaTotal, deltaConFoto ) {
		var statsEl = document.getElementById( 'club-stats-foto' );
		if ( ! statsEl ) return;

		var conFoto = parseInt( statsEl.dataset.conFoto, 10 ) + deltaConFoto;
		var total   = parseInt( statsEl.dataset.total, 10 ) + deltaTotal;
		var pct     = total > 0 ? Math.round( conFoto / total * 100 ) : 0;

		statsEl.dataset.conFoto = conFoto;
		statsEl.dataset.total   = total;

		// Barra.
		var bar = statsEl.querySelector( '.stats-bar' );
		bar.style.width = pct + '%';
		[ 'tw:bg-green-500', 'tw:bg-blue-500', 'tw:bg-amber-400', 'tw:bg-red-400' ].forEach( function ( c ) { bar.classList.remove( c ); } );
		bar.classList.add( pct === 100 ? 'tw:bg-green-500' : pct >= 80 ? 'tw:bg-blue-500' : pct >= 50 ? 'tw:bg-amber-400' : 'tw:bg-red-400' );

		// Porcentaje.
		var pctEl = statsEl.querySelector( '.stats-porcentaje' );
		pctEl.textContent = pct + '%';
		[ 'tw:text-green-600', 'tw:text-blue-600', 'tw:text-amber-500', 'tw:text-red-500' ].forEach( function ( c ) { pctEl.classList.remove( c ); } );
		pctEl.classList.add( pct === 100 ? 'tw:text-green-600' : pct >= 80 ? 'tw:text-blue-600' : pct >= 50 ? 'tw:text-amber-500' : 'tw:text-red-500' );

		// Texto.
		statsEl.querySelector( '.stats-con-foto' ).textContent = conFoto;
		statsEl.querySelector( '.stats-total' ).textContent    = total;
	}

	function updateCount( list ) {
		var section = list.closest( 'section' );
		if ( ! section ) return;
		var badge = section.querySelector( '.club-jugadores-count' );
		if ( ! badge ) return;
		var n = list.querySelectorAll( '.club-jugador' ).length;
		badge.textContent = n + ' jugador' + ( n !== 1 ? 'es' : '' );
	}

	function playerHTML( j ) {
		var foto = j.foto_url
			? '<img class="tw:w-full tw:h-full tw:object-cover" src="' + escAttr( j.foto_url ) + '" alt="' + escAttr( j.nombre ) + '">'
			: '<svg class="tw:w-full tw:h-full tw:text-gray-400 tw:p-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/></svg>';

		var hasFoto       = j.foto_url ? true : false;
		var toggleClass   = hasFoto ? '' : ' tw:hidden';
		var expandedContent = hasFoto ? '<img class="tw:rounded-lg tw:max-w-xl tw:w-full" src="' + escAttr( j.foto_url ) + '-/preview/1000x666/" alt="' + escAttr( j.nombre ) + '">' + UNASSIGN_BTN_HTML : '';
		var nombreCompleto = escHTML( j.nombre ) + ( j.apellidos ? ' ' + escHTML( j.apellidos ) : '' );
		var cargoHTML      = j.cargo ? ' - <span class="jugador-cargo-display tw:text-xs tw:text-gray-400">' + escHTML( j.cargo ) + '</span>' : '';
		var nombreFotoHTML = j.nombre_foto ? ' <span class="jugador-nombre-foto-display tw:text-xs tw:text-sky-800">(' + escHTML( j.nombre_foto ) + ')</span>' : '';

		return '<div class="club-jugador" data-jugador-id="' + j.id + '" data-nombre-foto="' + escAttr( j.nombre_foto || '' ) + '">'
			+ '<div class="club-jugador__row tw:flex tw:items-center tw:gap-4 tw:px-6 tw:py-4 tw:bg-white tw:hover:bg-gray-50 tw:transition-colors">'
			+ '<span class="drag-handle tw:shrink-0 tw:text-gray-300 tw:hover:text-gray-500 tw:transition-colors tw:cursor-grab tw:active:cursor-grabbing">'
			+ '<svg class="tw:w-5 tw:h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>'
			+ '</span>'
			+ '<div class="jugador-foto-trigger tw:shrink-0 tw:w-10 tw:h-10 tw:rounded-full tw:overflow-hidden tw:bg-gray-200 tw:cursor-pointer tw:ring-2 tw:ring-transparent tw:hover:ring-blue-400 tw:transition-all" title="Subir foto">' + foto + '</div>'
			+ '<div class="tw:flex-1 tw:min-w-0"><span class="jugador-nombre-display tw:text-sm tw:font-medium tw:text-gray-800">' + nombreCompleto + '</span>' + cargoHTML + nombreFotoHTML + '</div>'
			+ '<button type="button" class="btn-toggle-foto tw:shrink-0 tw:text-gray-300 tw:hover:text-blue-500 tw:transition-colors' + toggleClass + '" title="Ver foto">'
			+ '<svg class="tw:w-4 tw:h-4 tw:transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>'
			+ '</button>'
			+ '<button type="button" class="btn-edit-jugador tw:shrink-0 tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors" title="Editar jugador">'
			+ '<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/></svg>'
			+ '</button>'
			+ '<button type="button" class="btn-delete-jugador tw:shrink-0 tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors" data-jugador-id="' + j.id + '" title="Eliminar jugador">'
			+ '<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'
			+ '</button>'
			+ '</div>'
			+ '<div class="jugador-edit-panel tw:hidden tw:border-t tw:border-gray-100 tw:px-6 tw:py-4 tw:bg-gray-50">'
			+ '<div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-3 tw:gap-3">'
			+ '<div><label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre</label><input type="text" class="edit-nombre tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" value="' + escAttr( j.nombre ) + '"></div>'
			+ '<div><label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Apellidos</label><input type="text" class="edit-apellidos tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" value="' + escAttr( j.apellidos || '' ) + '"></div>'
			+ '<div><label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Cargo</label><input type="text" class="edit-cargo tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" value="' + escAttr( j.cargo || '' ) + '"></div>'
			+ '</div>'
			+ '<div class="tw:mt-3">'
			+ '<label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre foto</label>'
			+ '<input type="text" class="edit-nombre-foto tw:w-full tw:sm:w-64 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" maxlength="64" placeholder="ej. 9999.jpg" value="' + escAttr( j.nombre_foto || '' ) + '">'
			+ '<p class="tw:mt-1 tw:text-xs tw:text-gray-400">Si la foto llega por otros medios, indica aquí su nombre de archivo (máx. 32 caracteres).</p>'
			+ '</div>'
			+ '<div class="tw:flex tw:gap-2 tw:mt-3">'
			+ '<button type="button" class="btn-save-edit tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-1.5 tw:rounded-lg tw:transition-colors">Guardar</button>'
			+ '<button type="button" class="btn-cancel-edit tw:text-gray-400 tw:hover:text-gray-600 tw:text-sm tw:px-3 tw:py-1.5 tw:rounded-lg tw:transition-colors">Cancelar</button>'
			+ '</div>'
			+ '</div>'
			+ '<div class="jugador-foto-expanded tw:hidden tw:px-6 tw:py-4">' + expandedContent + '</div>'
			+ '</div>';
	}

	function escHTML( str ) {
		var div = document.createElement( 'div' );
		div.textContent = str;
		return div.innerHTML;
	}

	function escAttr( str ) {
		return str.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' );
	}

	function ajax( action, params, callback ) {
		var data = new FormData();
		data.append( 'action', action );
		data.append( 'nonce', albumClub.nonce );

		Object.keys( params ).forEach( function ( key ) {
			data.append( key, params[ key ] );
		} );

		fetch( albumClub.ajaxUrl, {
			method:      'POST',
			credentials: 'same-origin',
			body:        data,
		} )
		.then( function ( res ) { return res.json(); } )
		.then( function ( res ) {
			if ( ! res.success ) {
				console.error( 'Error AJAX (' + action + '):', res.data );
			}
			if ( callback ) callback( res );
		} )
		.catch( function ( err ) {
			console.error( 'Error AJAX:', err );
		} );
	}

})();
