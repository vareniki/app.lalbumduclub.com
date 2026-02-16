/**
 * Navegación responsive - toggle menú móvil.
 *
 * @package AlbumCustom
 */
( function() {
    const nav = document.getElementById( 'site-navigation' );
    if ( ! nav ) return;

    const toggle = nav.querySelector( '.menu-toggle' );
    if ( ! toggle ) return;

    const menu = nav.querySelector( 'ul' );
    if ( ! menu ) {
        toggle.style.display = 'none';
        return;
    }

    toggle.addEventListener( 'click', function() {
        nav.classList.toggle( 'toggled' );
        const expanded = toggle.getAttribute( 'aria-expanded' ) === 'true';
        toggle.setAttribute( 'aria-expanded', ! expanded );
    } );

    // Cerrar menú al hacer clic en un enlace (móvil)
    menu.querySelectorAll( 'a' ).forEach( function( link ) {
        link.addEventListener( 'click', function() {
            if ( window.innerWidth <= 768 ) {
                nav.classList.remove( 'toggled' );
                toggle.setAttribute( 'aria-expanded', 'false' );
            }
        } );
    } );

    // Cerrar menú al redimensionar a escritorio
    window.addEventListener( 'resize', function() {
        if ( window.innerWidth > 768 ) {
            nav.classList.remove( 'toggled' );
            toggle.setAttribute( 'aria-expanded', 'false' );
        }
    } );
} )();
