/**
 * Gestión de jugadores del club.
 *
 * - Drag & drop entre categorías (SortableJS)
 * - Añadir / eliminar / editar jugadores
 * - Añadir / renombrar / eliminar categorías
 * - Fotos de grupo (equipo): añadir / editar / eliminar / subir foto
 * - Subida de fotos a UploadCare
 * - Toggle foto expandida y categorías colapsables
 */
(function () {
	'use strict';

	var container = document.getElementById( 'club-categorias' );
	if ( ! container ) return;

	var clubId = container.dataset.clubId;

	// Input de archivo compartido — jugadores.
	var fileInput           = document.createElement( 'input' );
	fileInput.type          = 'file';
	fileInput.accept        = 'image/*';
	fileInput.style.display = 'none';
	document.body.appendChild( fileInput );

	// Input de archivo — equipo.
	var fileInputEquipo           = document.createElement( 'input' );
	fileInputEquipo.type          = 'file';
	fileInputEquipo.accept        = 'image/*';
	fileInputEquipo.style.display = 'none';
	document.body.appendChild( fileInputEquipo );

	var activeJugadorEl = null;
	var activeEquipoEl  = null;

	var UNASSIGN_BTN_HTML = '<button type="button" class="btn-unassign-foto tw:mt-2 tw:inline-flex tw:items-center tw:gap-1 tw:text-sm tw:text-red-400 tw:hover:text-red-600 tw:transition-colors" title="Quitar foto">'
		+ '<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">'
		+ '<path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>'
		+ '</svg>Quitar foto</button>';

	var CAMERA_PLACEHOLDER_HTML = '<div class="tw:w-full tw:h-full tw:flex tw:items-center tw:justify-center tw:text-gray-300">'
		+ '<svg class="tw:w-8 tw:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">'
		+ '<path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.776 48.776 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>'
		+ '<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>'
		+ '</svg></div>';

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
			var categoriaId = parseInt( list.dataset.categoriaId, 10 );
			var items       = list.querySelectorAll( '.club-jugador' );
			var ids         = [];

			items.forEach( function ( item ) {
				ids.push( parseInt( item.dataset.jugadorId, 10 ) );
			} );

			categories.push( { categoria_id: categoriaId, jugador_ids: ids } );
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

		// Toggle categoría (colapsar/expandir).
		target = e.target.closest( '.btn-toggle-categoria' );
		if ( target ) {
			e.preventDefault();
			var body    = target.closest( 'section' ).querySelector( '.category-body' );
			var chevron = target.querySelector( '.categoria-chevron' );
			body.classList.toggle( 'tw:hidden' );
			chevron.classList.toggle( 'tw:rotate-180' );
			return;
		}

		// Renombrar categoría — abrir panel.
		target = e.target.closest( '.btn-rename-categoria' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			var section = target.closest( 'section' );
			var panel   = section.querySelector( '.categoria-rename-panel' );
			panel.classList.toggle( 'tw:hidden' );
			if ( ! panel.classList.contains( 'tw:hidden' ) ) {
				panel.querySelector( '.rename-descripcion' ).focus();
			}
			return;
		}

		// Renombrar categoría — guardar.
		target = e.target.closest( '.btn-save-rename' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleRenameCategoria( target );
			return;
		}

		// Renombrar categoría — cancelar.
		target = e.target.closest( '.btn-cancel-rename' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			target.closest( '.categoria-rename-panel' ).classList.add( 'tw:hidden' );
			return;
		}

		// Eliminar categoría.
		target = e.target.closest( '.btn-delete-categoria' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			if ( target.disabled ) return;
			handleDeleteCategoria( target );
			return;
		}

		// Añadir categoría.
		target = e.target.closest( '.btn-add-categoria' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleAddCategoria( target );
			return;
		}

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

		// Quitar foto jugador.
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
				var trigger = jugadorEl.querySelector( '.jugador-foto-trigger' );
				trigger.innerHTML = '<svg class="tw:w-full tw:h-full tw:text-gray-400 tw:p-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-7 9a7 7 0 1 1 14 0H3z" clip-rule="evenodd"/></svg>';
				var expanded = jugadorEl.querySelector( '.jugador-foto-expanded' );
				expanded.innerHTML = '';
				expanded.classList.add( 'tw:hidden' );
				var toggleBtn = jugadorEl.querySelector( '.btn-toggle-foto' );
				toggleBtn.classList.add( 'tw:hidden' );
				toggleBtn.querySelector( 'svg' ).classList.remove( 'tw:rotate-180' );
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
					updateDeleteCategoriaBtn( list.closest( 'section' ) );
				}
			} );
			return;
		}

		// Subir foto jugador.
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

		// ─── Equipo ────────────────────────────────────────

		// Añadir foto de grupo.
		target = e.target.closest( '.btn-add-equipo' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleAddEquipo( target );
			return;
		}

		// Subir foto de equipo.
		target = e.target.closest( '.equipo-foto-trigger' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			activeEquipoEl       = target.closest( '.club-equipo-item' );
			fileInputEquipo.value = '';
			fileInputEquipo.click();
			return;
		}

		// Editar descripción equipo — abrir panel.
		target = e.target.closest( '.btn-edit-equipo' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			var item  = target.closest( '.club-equipo-item' );
			var panel = item.querySelector( '.equipo-edit-panel' );
			panel.classList.toggle( 'tw:hidden' );
			if ( ! panel.classList.contains( 'tw:hidden' ) ) {
				panel.querySelector( '.edit-equipo-descripcion' ).focus();
			}
			return;
		}

		// Editar descripción equipo — guardar.
		target = e.target.closest( '.btn-save-equipo-edit' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleSaveEquipoEdit( target );
			return;
		}

		// Editar descripción equipo — cancelar.
		target = e.target.closest( '.btn-cancel-equipo-edit' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			target.closest( '.equipo-edit-panel' ).classList.add( 'tw:hidden' );
			return;
		}

		// Eliminar equipo.
		target = e.target.closest( '.btn-delete-equipo' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleDeleteEquipo( target );
			return;
		}

		// Quitar foto equipo.
		target = e.target.closest( '.btn-clear-equipo-foto' );
		if ( target ) {
			e.preventDefault();
			e.stopPropagation();
			handleClearEquipoFoto( target );
			return;
		}
	} );

	// ─── Upload handler — jugadores ────────────────────────

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

			cdnUrl += '-/preview/1000x666/';

			trigger.innerHTML = '<img class="tw:w-full tw:h-full tw:object-cover" src="' + escAttr( cdnUrl ) + '" alt="">';

			var expanded = jugadorEl.querySelector( '.jugador-foto-expanded' );
			expanded.innerHTML = '<img class="tw:rounded-lg tw:max-w-xl tw:w-full" src="' + escAttr( cdnUrl ) + '-/preview/1000x666/" alt="">'
				+ UNASSIGN_BTN_HTML;
			expanded.classList.remove( 'tw:hidden' );

			var toggleBtn = jugadorEl.querySelector( '.btn-toggle-foto' );
			toggleBtn.classList.remove( 'tw:hidden' );
			toggleBtn.querySelector( 'svg' ).classList.add( 'tw:rotate-180' );

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

			var editInput = jugadorEl.querySelector( '.edit-nombre-foto' );
			if ( editInput ) {
				editInput.value = fileName;
			}

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

	// ─── Upload handler — equipo ───────────────────────────

	fileInputEquipo.addEventListener( 'change', function () {
		if ( ! fileInputEquipo.files.length || ! activeEquipoEl ) return;

		var file        = fileInputEquipo.files[0];
		var fileName    = file.name.substring( 0, 64 );
		var equipoEl    = activeEquipoEl;
		var equipoId    = parseInt( equipoEl.dataset.equipoId, 10 );
		var trigger     = equipoEl.querySelector( '.equipo-foto-trigger' );

		activeEquipoEl = null;

		trigger.classList.add( 'tw:opacity-50', 'tw:pointer-events-none' );

		uploadToUploadcare( file, function ( cdnUrl ) {
			trigger.classList.remove( 'tw:opacity-50', 'tw:pointer-events-none' );

			if ( ! cdnUrl ) {
				alert( 'Error al subir la imagen.' );
				return;
			}

			trigger.innerHTML = '<img class="tw:w-full tw:h-full tw:object-cover" src="' + escAttr( cdnUrl ) + '-/preview/1000x666/" alt="">';

			// Añadir o actualizar botón quitar foto.
			var clearBtn = equipoEl.querySelector( '.btn-clear-equipo-foto' );
			if ( ! clearBtn ) {
				trigger.insertAdjacentHTML( 'afterend',
					'<button type="button" class="btn-clear-equipo-foto tw:mt-1 tw:inline-flex tw:items-center tw:gap-1 tw:text-xs tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors" data-equipo-id="' + equipoId + '" title="Quitar foto">'
					+ '<svg class="tw:w-3 tw:h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'
					+ 'Quitar foto</button>'
				);
			}

			// Mostrar nombre_foto debajo de la descripción.
			var nombreFotoSpan = equipoEl.querySelector( '.equipo-nombre-foto-display' );
			if ( nombreFotoSpan ) {
				nombreFotoSpan.textContent = fileName;
			} else {
				var descSpan = equipoEl.querySelector( '.equipo-descripcion-display' );
				if ( descSpan ) {
					descSpan.insertAdjacentHTML( 'afterend', '<span class="equipo-nombre-foto-display tw:block tw:text-xs tw:text-gray-400">' + escHTML( fileName ) + '</span>' );
				}
			}
			equipoEl.dataset.nombreFoto = fileName;

			ajax( 'album_update_equipo_foto', {
				club_id:     clubId,
				equipo_id:   equipoId,
				foto_url:    cdnUrl,
				nombre_foto: fileName,
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

	// ─── Categorías ────────────────────────────────────────

	function handleAddCategoria( btn ) {
		var form        = btn.closest( '.add-categoria-form' );
		var input       = form.querySelector( '.add-categoria__input' );
		var descripcion = input.value.trim();

		if ( ! descripcion ) {
			input.focus();
			return;
		}

		btn.disabled = true;

		ajax( 'album_add_categoria', {
			club_id:     clubId,
			descripcion: descripcion,
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			// Insertar la nueva sección antes del bloque de estadísticas/descarga.
			var statsEl = document.getElementById( 'club-stats-foto' );
			var anchor  = statsEl || form.closest( '#club-categorias' ).querySelector( '.tw\\:mt-6.tw\\:mb-6' );

			var html = categoriaHTML( res.data.id, res.data.descripcion );

			if ( anchor ) {
				anchor.insertAdjacentHTML( 'beforebegin', html );
			} else {
				container.insertAdjacentHTML( 'beforeend', html );
			}

			initSortables();
			input.value = '';
			input.focus();
		} );
	}

	function handleRenameCategoria( btn ) {
		var panel       = btn.closest( '.categoria-rename-panel' );
		var section     = panel.closest( 'section' );
		var categoriaId = parseInt( section.dataset.categoriaId, 10 );
		var descripcion = panel.querySelector( '.rename-descripcion' ).value.trim();

		if ( ! descripcion ) return;

		btn.disabled = true;

		ajax( 'album_rename_categoria', {
			club_id:      clubId,
			categoria_id: categoriaId,
			descripcion:  descripcion,
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			// Actualizar el título en la cabecera.
			section.querySelector( '.btn-toggle-categoria h2' ).textContent = res.data.descripcion;
			// Actualizar el input del panel.
			panel.querySelector( '.rename-descripcion' ).value = res.data.descripcion;
			panel.classList.add( 'tw:hidden' );
		} );
	}

	function handleDeleteCategoria( btn ) {
		var section     = btn.closest( 'section' );
		var categoriaId = parseInt( section.dataset.categoriaId, 10 );

		if ( ! confirm( '¿Eliminar esta categoría?' ) ) return;

		btn.disabled = true;

		ajax( 'album_delete_categoria', {
			club_id:      clubId,
			categoria_id: categoriaId,
		}, function ( res ) {
			btn.disabled = false;

			if ( res.success ) {
				section.remove();
			}
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
		var categoriaId = parseInt( btn.dataset.categoriaId, 10 );
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
			categoria_id: categoriaId,
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
			updateDeleteCategoriaBtn( section );
		} );
	}

	// ─── Single add ───────────────────────────────────────

	function handleSingleAdd( btn ) {
		var formEl      = btn.closest( '.single-add' );
		var categoriaId = parseInt( formEl.dataset.categoriaId, 10 );
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
			categoria_id: categoriaId,
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
			updateDeleteCategoriaBtn( section );

			formEl.querySelector( '.single-add__nombre' ).value      = '';
			formEl.querySelector( '.single-add__apellidos' ).value   = '';
			formEl.querySelector( '.single-add__cargo' ).value       = '';
			formEl.querySelector( '.single-add__nombre-foto' ).value = '';
			formEl.querySelector( '.single-add__nombre' ).focus();
		} );
	}

	// ─── Equipo ────────────────────────────────────────────

	function handleAddEquipo( btn ) {
		var form        = btn.closest( '.equipo-add-form' );
		var categoriaId = parseInt( btn.dataset.categoriaId, 10 );
		var descripcion = form.querySelector( '.equipo-add__descripcion' ).value.trim();

		if ( ! descripcion ) {
			form.querySelector( '.equipo-add__descripcion' ).focus();
			return;
		}

		btn.disabled = true;

		ajax( 'album_add_equipo', {
			categoria_id: categoriaId,
			descripcion:  descripcion,
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			var grid = btn.closest( '.club-equipo-section' ).querySelector( '.club-equipo' );
			grid.insertAdjacentHTML( 'beforeend', equipoItemHTML( res.data ) );
			form.querySelector( '.equipo-add__descripcion' ).value = '';
		} );
	}

	function handleDeleteEquipo( btn ) {
		var item      = btn.closest( '.club-equipo-item' );
		var equipoId  = parseInt( btn.dataset.equipoId, 10 );

		if ( ! confirm( '¿Eliminar esta foto de grupo?' ) ) return;

		btn.disabled = true;

		ajax( 'album_delete_equipo', {
			club_id:   clubId,
			equipo_id: equipoId,
		}, function ( res ) {
			if ( res.success ) {
				item.remove();
			} else {
				btn.disabled = false;
			}
		} );
	}

	function handleSaveEquipoEdit( btn ) {
		var panel       = btn.closest( '.equipo-edit-panel' );
		var item        = panel.closest( '.club-equipo-item' );
		var equipoId    = parseInt( item.dataset.equipoId, 10 );
		var descripcion = panel.querySelector( '.edit-equipo-descripcion' ).value.trim();

		if ( ! descripcion ) return;

		btn.disabled = true;

		ajax( 'album_update_equipo', {
			club_id:     clubId,
			equipo_id:   equipoId,
			descripcion: descripcion,
		}, function ( res ) {
			btn.disabled = false;

			if ( ! res.success ) return;

			item.querySelector( '.equipo-descripcion-display' ).textContent = res.data.descripcion;
			panel.querySelector( '.edit-equipo-descripcion' ).value = res.data.descripcion;
			panel.classList.add( 'tw:hidden' );
		} );
	}

	function handleClearEquipoFoto( btn ) {
		var item     = btn.closest( '.club-equipo-item' );
		var equipoId = parseInt( btn.dataset.equipoId, 10 );

		btn.disabled = true;

		ajax( 'album_clear_equipo_foto', {
			club_id:   clubId,
			equipo_id: equipoId,
		}, function ( res ) {
			if ( res.success ) {
				var trigger = item.querySelector( '.equipo-foto-trigger' );
				trigger.innerHTML = CAMERA_PLACEHOLDER_HTML;
				btn.remove();
				var nfSpan = item.querySelector( '.equipo-nombre-foto-display' );
				if ( nfSpan ) nfSpan.remove();
				item.dataset.nombreFoto = '';
			} else {
				btn.disabled = false;
			}
		} );
	}

	// ─── Helpers ───────────────────────────────────────────

	/**
	 * Actualiza el estado del botón de eliminar categoría según si tiene jugadores.
	 */
	function updateDeleteCategoriaBtn( section ) {
		var btn = section.querySelector( '.btn-delete-categoria' );
		if ( ! btn ) return;

		var count = section.querySelectorAll( '.club-jugador' ).length;

		if ( count > 0 ) {
			btn.disabled = true;
			btn.className = btn.className
				.replace( 'tw:text-gray-300', 'tw:text-gray-200' )
				.replace( 'tw:hover:text-red-500', '' );
			btn.classList.add( 'tw:cursor-not-allowed' );
			btn.title = 'No se puede eliminar: tiene jugadores';
		} else {
			btn.disabled = false;
			btn.className = btn.className
				.replace( 'tw:text-gray-200', 'tw:text-gray-300' )
				.replace( 'tw:cursor-not-allowed', '' );
			btn.classList.add( 'tw:hover:text-red-500' );
			btn.title = 'Eliminar categoría';
		}
	}

	function updateStats( deltaTotal, deltaConFoto ) {
		var statsEl = document.getElementById( 'club-stats-foto' );
		if ( ! statsEl ) return;

		var conFoto = parseInt( statsEl.dataset.conFoto, 10 ) + deltaConFoto;
		var total   = parseInt( statsEl.dataset.total, 10 ) + deltaTotal;
		var pct     = total > 0 ? Math.round( conFoto / total * 100 ) : 0;

		statsEl.dataset.conFoto = conFoto;
		statsEl.dataset.total   = total;

		var bar = statsEl.querySelector( '.stats-bar' );
		bar.style.width = pct + '%';
		[ 'tw:bg-green-500', 'tw:bg-blue-500', 'tw:bg-amber-400', 'tw:bg-red-400' ].forEach( function ( c ) { bar.classList.remove( c ); } );
		bar.classList.add( pct === 100 ? 'tw:bg-green-500' : pct >= 80 ? 'tw:bg-blue-500' : pct >= 50 ? 'tw:bg-amber-400' : 'tw:bg-red-400' );

		var pctEl = statsEl.querySelector( '.stats-porcentaje' );
		pctEl.textContent = pct + '%';
		[ 'tw:text-green-600', 'tw:text-blue-600', 'tw:text-amber-500', 'tw:text-red-500' ].forEach( function ( c ) { pctEl.classList.remove( c ); } );
		pctEl.classList.add( pct === 100 ? 'tw:text-green-600' : pct >= 80 ? 'tw:text-blue-600' : pct >= 50 ? 'tw:text-amber-500' : 'tw:text-red-500' );

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

		var hasFoto         = !! j.foto_url;
		var toggleClass     = hasFoto ? '' : ' tw:hidden';
		var expandedContent = hasFoto ? '<img class="tw:rounded-lg tw:max-w-xl tw:w-full" src="' + escAttr( j.foto_url ) + '-/preview/1000x666/" alt="' + escAttr( j.nombre ) + '">' + UNASSIGN_BTN_HTML : '';
		var nombreCompleto  = escHTML( j.nombre ) + ( j.apellidos ? ' ' + escHTML( j.apellidos ) : '' );
		var cargoHTML       = j.cargo ? ' - <span class="jugador-cargo-display tw:text-xs tw:text-gray-400">' + escHTML( j.cargo ) + '</span>' : '';
		var nombreFotoHTML  = j.nombre_foto ? ' <span class="jugador-nombre-foto-display tw:text-xs tw:text-sky-800">(' + escHTML( j.nombre_foto ) + ')</span>' : '';

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

	function equipoItemHTML( j ) {
		var EDIT_ICON   = '<svg class="tw:w-3.5 tw:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/></svg>';
		var DELETE_ICON = '<svg class="tw:w-3.5 tw:h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
		var CLEAR_ICON  = '<svg class="tw:w-3 tw:h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';

		var fotoContent = j.foto_url
			? '<img class="tw:w-full tw:h-full tw:object-cover" src="' + escAttr( j.foto_url ) + '" alt="' + escAttr( j.descripcion ) + '">'
			: CAMERA_PLACEHOLDER_HTML;

		var clearBtnHTML = j.foto_url
			? '<button type="button" class="btn-clear-equipo-foto tw:mt-1 tw:inline-flex tw:items-center tw:gap-1 tw:text-xs tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors" data-equipo-id="' + j.id + '" title="Quitar foto">'
				+ CLEAR_ICON + 'Quitar foto</button>'
			: '';

		return '<div class="club-equipo-item" data-equipo-id="' + j.id + '">'
			+ '<div class="equipo-foto-trigger tw:aspect-video tw:rounded-lg tw:overflow-hidden tw:bg-gray-100 tw:cursor-pointer tw:ring-2 tw:ring-transparent tw:hover:ring-blue-400 tw:transition-all" title="Subir foto">'
			+ fotoContent
			+ '</div>'
			+ clearBtnHTML
			+ '<div class="tw:mt-1.5 tw:flex-col tw:items-start tw:justify-between tw:gap-1">'
			+ '<span class="equipo-descripcion-display tw:w-full tw:text-xs tw:font-medium tw:text-gray-700 tw:uppercase tw:leading-snug">' + escHTML( j.descripcion ) + '</span>'
			+ ( j.nombre_foto ? '<span class="equipo-nombre-foto-display tw:w-full tw:block tw:text-xs tw:text-gray-400">' + escHTML( j.nombre_foto ) + '</span>' : '' )
			+ '<div class="tw:flex tw:items-center tw:shrink-0">'
			+ '<button type="button" class="btn-edit-equipo tw:p-1 tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors" title="Editar descripción">' + EDIT_ICON + '</button>'
			+ '<button type="button" class="btn-delete-equipo tw:p-1 tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors" data-equipo-id="' + j.id + '" title="Eliminar">' + DELETE_ICON + '</button>'
			+ '</div>'
			+ '</div>'
			+ '<div class="equipo-edit-panel tw:hidden tw:mt-2">'
			+ '<input type="text" class="edit-equipo-descripcion tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-2 tw:py-1 tw:text-xs tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" value="' + escAttr( j.descripcion ) + '">'
			+ '<div class="tw:flex tw:gap-2 tw:mt-1.5">'
			+ '<button type="button" class="btn-save-equipo-edit tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-xs tw:font-medium tw:px-3 tw:py-1 tw:rounded-lg tw:transition-colors">Guardar</button>'
			+ '<button type="button" class="btn-cancel-equipo-edit tw:text-gray-400 tw:hover:text-gray-600 tw:text-xs tw:px-2 tw:py-1 tw:rounded-lg tw:transition-colors">Cancelar</button>'
			+ '</div>'
			+ '</div>'
			+ '</div>';
	}

	function categoriaHTML( id, descripcion ) {
		var RENAME_ICON = '<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 0 1 2.828 2.828L11.828 15.828a2 2 0 0 1-1.414.586H7v-3.414a2 2 0 0 1 .586-1.414z"/></svg>';
		var DELETE_ICON = '<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
		var PLUS_ICON   = '<svg class="tw:w-4 tw:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>';
		var CHEVRON     = '<svg class="categoria-chevron tw:w-4 tw:h-4 tw:text-gray-400 tw:transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>';

		var equipoSection = '<div class="club-equipo-section tw:px-6 tw:pt-4 tw:pb-3 tw:border-b tw:border-gray-100">'
			+ '<h3 class="tw:text-xs tw:font-semibold tw:text-gray-400 tw:uppercase tw:tracking-wide tw:mb-3">Fotos de grupo</h3>'
			+ '<div class="club-equipo tw:grid tw:grid-cols-2 tw:sm:grid-cols-4 tw:gap-4" data-categoria-id="' + id + '"></div>'
			+ '<div class="equipo-add-form tw:mt-3 tw:flex tw:items-center tw:gap-3">'
			+ '<input type="text" class="equipo-add__descripcion tw:flex-1 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:focus:ring-1 tw:focus:ring-blue-500 tw:outline-none" placeholder="Descripción (ej. Foto oficial temporada)">'
			+ '<button type="button" class="btn-add-equipo tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors" data-categoria-id="' + id + '">'
			+ PLUS_ICON + 'Añadir foto</button>'
			+ '</div>'
			+ '</div>';

		var membersHeading = '<div class="tw:px-6 tw:pt-4 tw:pb-0">'
			+ '<h3 class="tw:text-xs tw:font-semibold tw:text-gray-400 tw:uppercase tw:tracking-wide">Fotos de los miembros</h3>'
			+ '</div>';

		return '<section class="tw:bg-white tw:rounded-xl tw:shadow-sm tw:border tw:border-gray-200 tw:overflow-hidden" data-categoria-id="' + id + '">'
			+ '<div class="tw:bg-gray-50 tw:border-b tw:border-gray-200 tw:flex tw:items-center">'
			+ '<button type="button" class="btn-toggle-categoria tw:flex-1 tw:px-6 tw:py-4 tw:flex tw:items-center tw:justify-between tw:text-left tw:hover:bg-gray-100 tw:transition-colors">'
			+ '<h2 class="tw:text-lg tw:font-semibold tw:text-gray-800">' + escHTML( descripcion ) + '</h2>'
			+ '<div class="tw:flex tw:items-center tw:gap-3"><span class="club-jugadores-count tw:text-sm tw:text-gray-400">0 jugadores</span>' + CHEVRON + '</div>'
			+ '</button>'
			+ '<div class="tw:flex tw:items-center tw:gap-1 tw:pr-4">'
			+ '<button type="button" class="btn-rename-categoria tw:p-2 tw:text-gray-300 tw:hover:text-amber-500 tw:transition-colors tw:rounded" title="Renombrar categoría">' + RENAME_ICON + '</button>'
			+ '<button type="button" class="btn-delete-categoria tw:p-2 tw:text-gray-300 tw:hover:text-red-500 tw:transition-colors tw:rounded" title="Eliminar categoría">' + DELETE_ICON + '</button>'
			+ '</div>'
			+ '</div>'
			+ '<div class="categoria-rename-panel tw:hidden tw:border-b tw:border-gray-200 tw:bg-gray-50 tw:px-6 tw:py-3 tw:flex tw:items-center tw:gap-3">'
			+ '<input type="text" class="rename-descripcion tw:flex-1 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" value="' + escAttr( descripcion ) + '">'
			+ '<button type="button" class="btn-save-rename tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-1.5 tw:rounded-lg tw:transition-colors">Guardar</button>'
			+ '<button type="button" class="btn-cancel-rename tw:text-gray-400 tw:hover:text-gray-600 tw:text-sm tw:px-3 tw:py-1.5 tw:rounded-lg tw:transition-colors">Cancelar</button>'
			+ '</div>'
			+ '<div class="category-body tw:hidden">'
			+ equipoSection
			+ membersHeading
			+ '<div class="club-jugadores tw:divide-y tw:divide-gray-100 tw:min-h-12" data-categoria-id="' + id + '"></div>'
			+ ( albumClub.canBulkAdd
				? '<div class="bulk-add tw:border-t tw:border-gray-200 tw:px-6 tw:py-4">'
				+ '<textarea class="bulk-add__input tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-2 tw:text-sm tw:text-gray-700 tw:placeholder-gray-400 tw:focus:border-blue-500 tw:outline-none tw:resize-y" rows="2" placeholder="Un jugador por línea: nombre, apellidos, cargo" data-categoria-id="' + id + '"></textarea>'
				+ '<button type="button" class="btn-bulk-add tw:mt-2 tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors" data-categoria-id="' + id + '">' + PLUS_ICON + 'Añadir en bulk</button>'
				+ '</div>'
				: '' )
			+ '<div class="single-add tw:border-t tw:border-gray-200 tw:px-6 tw:py-4" data-categoria-id="' + id + '">'
			+ '<div class="tw:grid tw:grid-cols-1 tw:sm:grid-cols-3 tw:gap-3">'
			+ '<div><label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre</label><input type="text" class="single-add__nombre tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" placeholder="Nombre"></div>'
			+ '<div><label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Apellidos</label><input type="text" class="single-add__apellidos tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" placeholder="Apellidos"></div>'
			+ '<div><label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Cargo</label><input type="text" class="single-add__cargo tw:w-full tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" placeholder="Cargo"></div>'
			+ '</div>'
			+ '<div class="tw:mt-3"><label class="tw:block tw:text-xs tw:text-gray-500 tw:mb-1">Nombre foto</label>'
			+ '<input type="text" class="single-add__nombre-foto tw:w-full tw:sm:w-64 tw:border tw:border-gray-300 tw:rounded-lg tw:px-3 tw:py-1.5 tw:text-sm tw:text-gray-800 tw:focus:border-blue-500 tw:outline-none" maxlength="32" placeholder="ej. 9999.jpg">'
			+ '<p class="tw:mt-1 tw:text-xs tw:text-gray-400">Si la foto llega por otros medios, indica aquí su nombre de archivo (máx. 32 caracteres).</p></div>'
			+ '<div class="tw:mt-3"><button type="button" class="btn-single-add tw:inline-flex tw:items-center tw:gap-1.5 tw:bg-blue-600 tw:hover:bg-blue-700 tw:text-white tw:text-sm tw:font-medium tw:px-4 tw:py-2 tw:rounded-lg tw:transition-colors">' + PLUS_ICON + 'Añadir jugador</button></div>'
			+ '</div>'
			+ '</div>'
			+ '</section>';
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
