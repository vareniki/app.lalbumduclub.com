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
			ajax( 'album_delete_jugador', {
				club_id:    clubId,
				jugador_id: jugadorId,
			}, function ( res ) {
				if ( res.success ) {
					row.remove();
					updateCount( list );
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

		// Bulk add.
		target = e.target.closest( '.btn-bulk-add' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleBulkAdd( target );
			return;
		}
	} );

	// ─── Upload handler ────────────────────────────────────

	fileInput.addEventListener( 'change', function () {
		if ( ! fileInput.files.length || ! activeJugadorEl ) return;

		var file      = fileInput.files[0];
		var jugadorId = activeJugadorEl.dataset.jugadorId;
		var trigger   = activeJugadorEl.querySelector( '.jugador-foto-trigger' );

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

	// ─── Bulk add ──────────────────────────────────────────

	function handleBulkAdd( btn ) {
		var section     = btn.closest( 'section' );
		var categoryUid = btn.dataset.categoryUid;
		var textarea    = section.querySelector( '.bulk-add__input' );
		var raw         = ( textarea.value || '' ).trim();

		if ( ! raw ) return;

		var nombres = raw.split( /[,\n]/ ).map( function ( s ) {
			return s.trim();
		} ).filter( Boolean );

		if ( ! nombres.length ) return;

		btn.disabled = true;

		ajax( 'album_bulk_add_jugadores', {
			club_id:      clubId,
			category_uid: categoryUid,
			nombres:      JSON.stringify( nombres ),
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			var list = section.querySelector( '.club-jugadores' );

			res.data.forEach( function ( j ) {
				list.insertAdjacentHTML( 'beforeend', playerHTML( j ) );
			} );

			textarea.value = '';
			updateCount( list );
		} );
	}

	// ─── Helpers ───────────────────────────────────────────

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

		var hasFoto     = j.foto_url ? true : false;
		var toggleClass = hasFoto ? '' : ' hidden';
		var expandedImg = hasFoto ? '<img class="rounded-lg max-w-xs" src="' + escAttr( j.foto_url ) + '" alt="' + escAttr( j.nombre ) + '">' : '';

		return '<div class="club-jugador" data-jugador-id="' + j.id + '">'
			+ '<div class="club-jugador__row flex items-center gap-4 px-6 py-4 bg-white hover:bg-gray-50 transition-colors">'
			+ '<span class="drag-handle shrink-0 text-gray-300 hover:text-gray-500 transition-colors cursor-grab active:cursor-grabbing">'
			+ '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 8a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM7 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4zM13 14a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>'
			+ '</span>'
			+ '<div class="jugador-foto-trigger shrink-0 w-10 h-10 rounded-full overflow-hidden bg-gray-200 cursor-pointer ring-2 ring-transparent hover:ring-blue-400 transition-all" title="Subir foto">' + foto + '</div>'
			+ '<span class="text-sm font-medium text-gray-800 flex-1">' + escHTML( j.nombre ) + '</span>'
			+ '<button type="button" class="btn-toggle-foto shrink-0 text-gray-300 hover:text-blue-500 transition-colors' + toggleClass + '" title="Ver foto">'
			+ '<svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>'
			+ '</button>'
			+ '<button type="button" class="btn-delete-jugador shrink-0 text-gray-300 hover:text-red-500 transition-colors" data-jugador-id="' + j.id + '" title="Eliminar jugador">'
			+ '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'
			+ '</button>'
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
