document.addEventListener( 'DOMContentLoaded', function() { jQuery( document ).ready( function( $ ) {
	function remoji_keycode( num ) {
		var num = num || 13 ;
		var code = window.event ? event.keyCode : event.which ;
		if( num == code ) return true ;
		return false ;
	}

	function remoji_display_tab(tab) {
		jQuery('[data-remoji-tab]').removeClass('nav-tab-active');
		jQuery('[data-remoji-tab="'+tab+'"]').addClass('nav-tab-active');
		jQuery('[data-remoji-layout]').hide();
		jQuery('[data-remoji-layout="'+tab+'"]').show();
	}

	/*** Admin Panel JS ***/
	// page tab switch functionality
	if($('[data-remoji-tab]').length > 0){
		// display default tab
		var remoji_tab_current = document.cookie.replace(/(?:(?:^|.*;\s*)remoji_tab\s*\=\s*([^;]*).*$)|^.*$/, "$1") ;
		if(window.location.hash.substr(1)) {
			remoji_tab_current = window.location.hash.substr(1) ;
		}
		if(!remoji_tab_current || !$('[data-remoji-tab="'+remoji_tab_current+'"]').length) {
			remoji_tab_current = $('[data-remoji-tab]').first().data('remoji-tab') ;
		}
		remoji_display_tab(remoji_tab_current) ;
		// tab switch
		$('[data-remoji-tab]').click(function(event) {
			remoji_display_tab($(this).data('remoji-tab')) ;
			document.cookie = 'remoji_tab='+$(this).data('remoji-tab') ;
			$(this).blur() ;
		}) ;
	}

	/** Accesskey **/
	$( '[remoji-accesskey]' ).map( function() {
		var thiskey = $( this ).attr( 'remoji-accesskey' ) ;
		$( this ).attr( 'title', 'Shortcut : ' + thiskey.toLocaleUpperCase() ) ;
		var that = this ;
		$( document ).on( 'keydown', function( e ) {
			if( $(":input:focus").length > 0 ) return ;
			if( event.metaKey ) return ;
			if( event.ctrlKey ) return ;
			if( event.altKey ) return ;
			if( event.shiftKey ) return ;
			if( remoji_keycode( thiskey.charCodeAt( 0 ) ) ) $( that )[ 0 ].click() ;
		});
	});


} ); } );