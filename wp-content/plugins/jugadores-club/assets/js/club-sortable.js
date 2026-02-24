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
				ghostClass:      'opacity-30',
				dragClass:       'shadow-lg',
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
			expanded.classList.toggle( 'hidden' );
			icon.classList.toggle( 'rotate-180' );
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
			panel.classList.toggle( 'hidden' );
			if ( ! panel.classList.contains( 'hidden' ) ) {
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
			target.closest( '.jugador-edit-panel' ).classList.add( 'hidden' );
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

		var file             = fileInput.files[0];
		var jugadorId        = activeJugadorEl.dataset.jugadorId;
		var trigger          = activeJugadorEl.querySelector( '.jugador-foto-trigger' );
		var hadFotoYa        = !! activeJugadorEl.querySelector( '.jugador-foto-trigger img' ) || !! activeJugadorEl.dataset.nombreFoto;

		trigger.classList.add( 'opacity-50', 'pointer-events-none' );

		uploadToUploadcare( file, function ( cdnUrl ) {
			trigger.classList.remove( 'opacity-50', 'pointer-events-none' );

			if ( ! cdnUrl ) {
				alert( 'Error al subir la imagen.' );
				return;
			}

			// Añade a cdnUrl un pequeño parámetro de compresión.
			cdnUrl += '-/preview/1000x666/';

			// Actualizar avatar.
			trigger.innerHTML = '<img class="w-full h-full object-cover" src="' + escAttr( cdnUrl ) + '" alt="">';

			// Mostrar foto expandida.
			var expanded = activeJugadorEl.querySelector( '.jugador-foto-expanded' );
			expanded.innerHTML = '<img class="rounded-lg max-w-xs" src="' + escAttr( cdnUrl ) + '" alt="">';
			expanded.classList.remove( 'hidden' );

			// Mostrar toggle y marcar como abierto.
			var toggleBtn = activeJugadorEl.querySelector( '.btn-toggle-foto' );
			toggleBtn.classList.remove( 'hidden' );
			toggleBtn.querySelector( 'svg' ).classList.add( 'rotate-180' );

			// Guardar en BD.
			ajax( 'album_update_jugador_foto', {
				club_id:    clubId,
				jugador_id: jugadorId,
				foto_url:   cdnUrl,
			}, function ( res ) {
				if ( res.success && ! hadFotoYa ) {
					updateStats( 0, 1 );
				}
			} );

			activeJugadorEl = null;
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
			var displayEl       = jugadorEl.querySelector( '.flex-1.min-w-0' );

			if ( displayEl ) {
				var html = '<span class="jugador-nombre-display text-sm font-medium text-gray-800">' + escHTML( nombreCompleto ) + '</span>';
				if ( res.data.cargo ) {
					html += ' - <span class="jugador-cargo-display text-xs text-gray-400">' + escHTML( res.data.cargo ) + '</span>';
				}
				if ( nuevoNombreFoto ) {
					html += ' <span class="jugador-nombre-foto-display text-xs text-sky-800">(' + escHTML( nuevoNombreFoto ) + ')</span>';
				}
				displayEl.innerHTML = html;
			}

			// Actualizar data-nombre-foto y estadísticas.
			var hasFoto = !! jugadorEl.querySelector( '.jugador-foto-trigger img' ) || !! nuevoNombreFoto;
			if ( hadFoto !== hasFoto ) {
				updateStats( 0, hasFoto ? 1 : -1 );
			}
			jugadorEl.dataset.nombreFoto = nuevoNombreFoto;

			panel.classList.add( 'hidden' );
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
		[ 'bg-green-500', 'bg-blue-500', 'bg-amber-400', 'bg-red-400' ].forEach( function ( c ) { bar.classList.remove( c ); } );
		bar.classList.add( pct === 100 ? 'bg-green-500' : pct >= 80 ? 'bg-blue-500' : pct >= 50 ? 'bg-amber-400' : 'bg-red-400' );

		// Porcentaje.
		var pctEl = statsEl.querySelector( '.stats-porcentaje' );
		pctEl.textContent = pct + '%';
		[ 'text-green-600', 'text-blue-600', 'text-amber-500', 'text-red-500' ].forEach( function ( c ) { pctEl.classList.remove( c ); } );
		pctEl.classList.add( pct === 100 ? 'text-green-600' : pct >= 80 ? 'text-blue-600' : pct >= 50 ? 'text-amber-500' : 'text-red-500' );

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
			? '<img class="w-full h-full object-cover" src="' + escAttr( j.foto_url ) + '" alt="' + escAttr( j.nombre ) + '">'
			: '<svg class="w-full h-full text-gray-400 p-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/></svg>';

		var hasFoto       = j.foto_url ? true : false;
		var toggleClass   = hasFoto ? '' : ' hidden';
		var expandedImg   = hasFoto ? '<img class="rounded-lg max-w-xs" src="' + escAttr( j.foto_url ) + '" alt="' + escAttr( j.nombre ) + '">' : '';
		var nombreCompleto = escHTML( j.nombre ) + ( j.apellidos ? ' ' + escHTML( j.apellidos ) : '' );
		var cargoHTML      = j.cargo ? ' - <span class="jugador-cargo-display text-xs text-gray-400">' + escHTML( j.cargo ) + '</span>' : '';
	var nombreFotoHTML = j.nombre_foto ? ' <span class="jugador-nombre-foto-display text-xs text-sky-800">(' + escHTML( j.nombre_foto ) + ')</span>' : '';

		return '<div class="club-jugador" data-jugador-id="' + j.id + '" data-nombre-foto="' + escAttr( j.nombre_foto || '' ) + '">'
			+ '<div class="club-jugador__row flex items-center gap-4 px-6 py-4 bg-white hover:bg-gray-50 transition-colors">'
			+ '<span class="drag-handle shrink-0 text-gray-300 hover:text-gray-500 transition-colors cursor-grab active:cursor-grabbing">'
			+ '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>'
			+ '</span>'
			+ '<div class="jugador-foto-trigger shrink-0 w-10 h-10 rounded-full overflow-hidden bg-gray-200 cursor-pointer ring-2 ring-transparent hover:ring-blue-400 transition-all" title="Subir foto">' + foto + '</div>'
			+ '<div class="flex-1 min-w-0"><span class="jugador-nombre-display text-sm font-medium text-gray-800">' + nombreCompleto + '</span>' + cargoHTML + nombreFotoHTML + '</div>'
			+ '<button type="button" class="btn-toggle-foto shrink-0 text-gray-300 hover:text-blue-500 transition-colors' + toggleClass + '" title="Ver foto">'
			+ '<svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>'
			+ '</button>'
			+ '<button type="button" class="btn-edit-jugador shrink-0 text-gray-300 hover:text-amber-500 transition-colors" title="Editar jugador">'
			+ '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/></svg>'
			+ '</button>'
			+ '<button type="button" class="btn-delete-jugador shrink-0 text-gray-300 hover:text-red-500 transition-colors" data-jugador-id="' + j.id + '" title="Eliminar jugador">'
			+ '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'
			+ '</button>'
			+ '</div>'
			+ '<div class="jugador-edit-panel hidden border-t border-gray-100 px-6 py-4 bg-gray-50">'
			+ '<div class="grid grid-cols-1 sm:grid-cols-3 gap-3">'
			+ '<div><label class="block text-xs text-gray-500 mb-1">Nombre</label><input type="text" class="edit-nombre w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 outline-none" value="' + escAttr( j.nombre ) + '"></div>'
			+ '<div><label class="block text-xs text-gray-500 mb-1">Apellidos</label><input type="text" class="edit-apellidos w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 outline-none" value="' + escAttr( j.apellidos || '' ) + '"></div>'
			+ '<div><label class="block text-xs text-gray-500 mb-1">Cargo</label><input type="text" class="edit-cargo w-full border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 outline-none" value="' + escAttr( j.cargo || '' ) + '"></div>'
			+ '</div>'
			+ '<div class="mt-3">'
			+ '<label class="block text-xs text-gray-500 mb-1">Nombre foto</label>'
			+ '<input type="text" class="edit-nombre-foto w-full sm:w-64 border border-gray-300 rounded-lg px-3 py-1.5 text-sm text-gray-800 focus:border-blue-500 outline-none" maxlength="32" placeholder="ej. 9999.jpg" value="' + escAttr( j.nombre_foto || '' ) + '">'
			+ '<p class="mt-1 text-xs text-gray-400">Si la foto llega por otros medios, indica aquí su nombre de archivo (máx. 32 caracteres).</p>'
			+ '</div>'
			+ '<div class="flex gap-2 mt-3">'
			+ '<button type="button" class="btn-save-edit bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-1.5 rounded-lg transition-colors">Guardar</button>'
			+ '<button type="button" class="btn-cancel-edit text-gray-400 hover:text-gray-600 text-sm px-3 py-1.5 rounded-lg transition-colors">Cancelar</button>'
			+ '</div>'
			+ '</div>'
			+ '<div class="jugador-foto-expanded hidden px-6 py-4">' + expandedImg + '</div>'
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
