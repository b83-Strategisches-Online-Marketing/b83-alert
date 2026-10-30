/**
 * b83 Alert – Einstellungsseite: Farbwähler, Seitenauswahl, Live-Vorschau.
 */
( function ( $ ) {
	'use strict';

	var SHADOWS = {
		none: 'none',
		soft: '0 12px 32px rgba(0,0,0,.18)',
		strong: '0 24px 64px rgba(0,0,0,.38)'
	};

	var $form = $( '#b83-alert-settings-form' );
	var popup = document.getElementById( 'b83-alert-popup-preview' );
	var tickerEl = document.querySelector( '#b83-alert-preview .b83-alert-ticker' );
	var ticker = tickerEl && window.B83AlertTicker ? new window.B83AlertTicker( tickerEl ) : null;

	function levelOf( el ) {
		var m = el ? el.className.match( /b83-alert--(info|warning|alarm)/ ) : null;
		return m ? m[ 1 ] : 'warning';
	}

	function val( key ) {
		var $f = $form.find( '[name="b83_alert[' + key + ']"]' );
		if ( $f.is( ':radio' ) ) {
			return $f.filter( ':checked' ).val() || '';
		}
		return $f.val() || '';
	}

	function rebuildTicker() {
		if ( ! ticker ) {
			return;
		}
		ticker.speed = Math.max( 20, parseInt( val( 'ticker_speed' ), 10 ) || 60 );
		var sep = val( 'ticker_separator' );
		ticker.base.forEach( function ( n ) {
			if ( n.classList.contains( 'b83-alert-ticker__sep' ) ) {
				n.textContent = sep;
				n.classList.toggle( 'is-empty', '' === sep );
			}
		} );
		tickerEl.classList.remove( 'is-running' );
		ticker.width = 0;
		ticker.build();
	}

	/** Alle Vorschau-Variablen aus dem Formular übernehmen. */
	function update() {
		if ( popup ) {
			var accent = val( 'color_' + levelOf( popup ) );
			popup.style.setProperty( '--b83a-accent', accent );
			popup.style.setProperty( '--b83a-bg', val( 'popup_bg' ) || '#ffffff' );
			popup.style.setProperty( '--b83a-text', val( 'popup_text' ) || '#1d2327' );
			popup.style.setProperty( '--b83a-radius', ( parseInt( val( 'popup_radius' ), 10 ) || 0 ) + 'px' );
			popup.style.setProperty( '--b83a-width', ( parseInt( val( 'popup_width' ), 10 ) || 560 ) + 'px' );
			popup.style.setProperty( '--b83a-overlay', ( ( parseInt( val( 'popup_overlay' ), 10 ) || 0 ) / 100 ).toFixed( 2 ) );
			popup.style.setProperty( '--b83a-shadow', SHADOWS[ val( 'popup_shadow' ) ] || SHADOWS.soft );
			var label = val( 'popup_button_label' );
			$( popup ).find( '.b83-alert-popup__btn' ).text( label || $( '#b83-alert-popup-button-label' ).attr( 'placeholder' ) );
		}
		if ( tickerEl ) {
			tickerEl.style.setProperty( '--b83a-ticker-bg', val( 'ticker_bg' ) || val( 'color_' + levelOf( tickerEl ) ) );
			tickerEl.style.setProperty( '--b83a-ticker-color', val( 'ticker_color' ) || '#ffffff' );
			tickerEl.style.setProperty( '--b83a-ticker-size', ( parseInt( val( 'ticker_font_size' ), 10 ) || 16 ) + 'px' );
		}
	}

	// Farbwähler.
	$( '.b83-alert-color' ).wpColorPicker( {
		change: function ( event, ui ) {
			$( event.target ).val( ui.color.toString() );
			update();
		},
		clear: function () {
			window.setTimeout( update, 0 );
		}
	} );

	// Schieberegler mit Zahlenanzeige.
	$form.on( 'input change', 'input[type="range"]', function () {
		$( this ).siblings( 'output' ).text( this.value );
		update();
		var key = $( this ).data( 'key' );
		if ( 'ticker_speed' === key || 'ticker_font_size' === key ) {
			rebuildTicker();
		}
	} );

	$form.on( 'change input', 'input[type="radio"], input[type="text"]:not(.b83-alert-color)', function () {
		update();
		if ( 'b83_alert[ticker_separator]' === this.name ) {
			rebuildTicker();
		}
	} );

	// Seitenliste nur bei „Ausgewählten Seiten“.
	function togglePages() {
		$( '.b83-alert-pages-row' ).toggle( 'pages' === val( 'popup_where' ) );
	}
	$form.on( 'change', 'input[name="b83_alert[popup_where]"]', togglePages );
	togglePages();

	// Seiten filtern.
	$( '.b83-alert-pages__filter' ).on( 'input', function () {
		var term = this.value.toLowerCase().trim();
		$( '.b83-alert-pages label' ).each( function () {
			$( this ).toggle( ! term || $( this ).text().toLowerCase().indexOf( term ) !== -1 );
		} );
	} );

	update();
	if ( ticker ) {
		tickerEl.hidden = false;
		ticker.build();
	}
}( jQuery ) );
