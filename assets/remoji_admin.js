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

	/** 2.6.3: "Limit Emojis" live preview **/
	function remoji_has( obj, k ) {
		return Object.prototype.hasOwnProperty.call( obj, k ) ;
	}

	// Mirrors GUI::to_char()
	function remoji_to_char( v ) {
		v = String( v || '' ).trim() ;
		if ( ! v ) return '' ;

		if ( /^(?:u\+)?[0-9a-f]{1,6}(?:[\s_\-]+(?:u\+)?[0-9a-f]{1,6})*$/i.test( v ) ) {
			var cps = [] ;
			var parts = v.split( /[\s_\-]+/ ) ;
			for ( var i = 0 ; i < parts.length ; i++ ) {
				var cp = parseInt( parts[ i ].replace( /^u\+/i, '' ), 16 ) ;
				if ( cp < 0x20 || ( cp >= 0x7F && cp < 0xA0 ) || cp > 0x10FFFF || ( cp >= 0xD800 && cp <= 0xDFFF ) || ( cp >= 0xE000 && cp <= 0xF8FF ) ) return '' ;
				cps.push( cp ) ;
			}
			if ( cps.length > 16 ) return '' ;
			if ( cps.length === 1 && cps[ 0 ] < 0x80 ) return '' ;
			if ( cps.length === 1 && cps[ 0 ] < 0x1F300 ) {
				cps.push( 0xFE0F ) ;
			} else if ( cps.length === 2 && cps[ 1 ] === 0x20E3 ) {
				cps.splice( 1, 0, 0xFE0F ) ;
			}
			return String.fromCodePoint.apply( String, cps ) ;
		}

		// Literal emoji: valid UTF-8, max 64 bytes, contains non-ASCII, no markup/whitespace
		var bytes ;
		try {
			bytes = unescape( encodeURIComponent( v ) ).length ;
		} catch ( e ) {
			return '' ;
		}
		if ( bytes > 64 || ! /[^\x00-\x7F]/.test( v ) || /[\x00-\x20<>&"'\x7F]/.test( v ) ) return '' ;

		return v ;
	}

	// Mirrors GUI::parse_custom() + GUI::init(), so unsaved "Custom Emojis" lines count too
	function remoji_custom_map( raw, base ) {
		var out = {} ;
		String( raw || '' ).split( /\r\n|\r|\n/ ).forEach( function( line ) {
			line = line.trim() ;
			if ( ! line || line.charAt( 0 ) === '#' ) return ;

			var m = line.match( /^(.*?)\s*[=:]\s*(.*)$/ ) ;
			var name = ( m ? m[ 1 ] : line ).trim().toLowerCase() ;
			var value = m ? m[ 2 ].trim() : '' ;
			if ( ! /^[a-z0-9_+\-]{1,64}$/i.test( name ) ) return ;

			var ch = value ? remoji_to_char( value ) : ( remoji_has( base, name ) ? base[ name ] : '' ) ;
			if ( ch ) out[ name ] = ch ;
		} ) ;
		return out ;
	}

	var $remoji_wl = $( 'textarea[name="emoji_whitelist"]' ) ;
	var $remoji_wl_preview = $( '[data-remoji-whitelist-preview]' ) ;
	var $remoji_custom = $( 'textarea[name="emoji_custom"]' ) ;

	function remoji_whitelist_preview() {
		var base = remoji_admin.catalog || {} ;
		var custom = remoji_custom_map( $remoji_custom.val(), base ) ;
		var seen = {} ;
		var items = [] ;

		String( $remoji_wl.val() || '' ).split( /\r\n|\r|\n/ ).forEach( function( line ) {
			var name = line.trim() ;
			if ( ! name ) return ;

			var ch = remoji_has( custom, name ) ? custom[ name ] : ( remoji_has( base, name ) ? base[ name ] : '' ) ;
			var $item = $( '<span class="remoji-whitelist-item" />' ) ;
			if ( ch ) {
				$item.append( $( '<span class="remoji_emoji" role="img" />' ).attr( 'aria-label', name.replace( /_/g, ' ' ) ).text( ch ) ) ;
			} else {
				$item.addClass( 'remoji-danger' ).attr( 'title', remoji_admin.unknown ).append( $( '<span />' ).text( '\u26A0\uFE0F' ) ) ;
			}
			$item.append( ' ' ).append( $( '<code />' ).text( name ) ) ;

			if ( remoji_has( seen, name ) ) {
				$item.css( 'opacity', 0.5 ).attr( 'title', remoji_admin.duplicate ) ;
			}
			seen[ name ] = 1 ;
			items.push( $item ) ;
		} ) ;

		$remoji_wl_preview.empty() ;
		if ( ! items.length ) {
			$remoji_wl_preview.hide() ;
			return ;
		}
		$remoji_wl_preview.append( document.createTextNode( remoji_admin.preview + ': ' ) ).append( items ).show() ;
	}

	if ( $remoji_wl.length && $remoji_wl_preview.length && window.remoji_admin ) {
		$remoji_wl.on( 'input', remoji_whitelist_preview ) ;
		$remoji_custom.on( 'input', remoji_whitelist_preview ) ;
		remoji_whitelist_preview() ;
	}

} ); } );