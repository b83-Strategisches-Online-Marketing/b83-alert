/**
 * b83 Alert – Frontend
 *
 * - Zeitfenster (data-start / data-end) im Browser prüfen, damit auch
 *   gecachte Seiten pünktlich ein- und ausblenden
 * - Pop-up öffnen (Verzögerung, Häufigkeit, Fokus, Escape)
 * - Laufschrift endlos und gleichmäßig laufen lassen
 */
( function () {
	'use strict';

	var MAX_TIMEOUT = 2147483000;

	function now() {
		return Date.now() / 1000;
	}

	function num( el, name ) {
		return parseInt( el.getAttribute( name ) || '0', 10 ) || 0;
	}

	/**
	 * Zeitfenster prüfen und Wechsel einplanen.
	 * onShow/onHide werden bei Beginn bzw. Ende aufgerufen.
	 */
	function watchWindow( el, onShow, onHide ) {
		if ( el.hasAttribute( 'data-force' ) ) {
			onShow();
			return;
		}
		var start = num( el, 'data-start' );
		var end = num( el, 'data-end' );
		var t = now();

		if ( end > 0 && t >= end ) {
			onHide();
			return;
		}
		if ( start > 0 && t < start ) {
			onHide();
			var wait = ( start - t ) * 1000;
			if ( wait < MAX_TIMEOUT ) {
				window.setTimeout( function () {
					watchWindow( el, onShow, onHide );
				}, wait + 500 );
			}
			return;
		}
		onShow();
		if ( end > 0 ) {
			var left = ( end - t ) * 1000;
			if ( left < MAX_TIMEOUT ) {
				window.setTimeout( onHide, left + 500 );
			}
		}
	}

	function storage( type ) {
		try {
			var s = window[ type ];
			var probe = '__b83_alert__';
			s.setItem( probe, '1' );
			s.removeItem( probe );
			return s;
		} catch ( e ) {
			return null;
		}
	}

	/* -----------------------------------------------------------------
	 * Laufschrift
	 * --------------------------------------------------------------- */

	function Ticker( el ) {
		this.el = el;
		this.viewport = el.querySelector( '.b83-alert-ticker__viewport' );
		this.track = el.querySelector( '.b83-alert-ticker__track' );
		this.base = this.track ? Array.prototype.slice.call( this.track.children ).map( function ( n ) {
			return n.cloneNode( true );
		} ) : [];
		this.speed = Math.max( 20, num( el, 'data-speed' ) || 60 );
		this.width = 0;
	}

	Ticker.prototype.appendSet = function () {
		var track = this.track;
		this.base.forEach( function ( n ) {
			track.appendChild( n.cloneNode( true ) );
		} );
	};

	Ticker.prototype.build = function () {
		if ( ! this.track || ! this.viewport || ! this.base.length ) {
			return;
		}
		var available = this.viewport.clientWidth;
		if ( ! available ) {
			return;
		}
		this.width = available;

		this.track.innerHTML = '';
		this.appendSet();
		var setWidth = this.track.scrollWidth || 1;

		// Eine Hälfte muss mindestens so breit sein wie der sichtbare Bereich.
		var sets = Math.max( 1, Math.ceil( available / setWidth ) );
		for ( var i = 1; i < sets; i++ ) {
			this.appendSet();
		}
		var half = this.track.scrollWidth;
		// Zweite, identische Hälfte für den nahtlosen Übergang bei -50 %.
		for ( var j = 0; j < sets; j++ ) {
			this.appendSet();
		}

		this.el.style.setProperty( '--b83a-duration', ( half / this.speed ).toFixed( 2 ) + 's' );
		this.el.classList.add( 'is-running' );
	};

	Ticker.prototype.refresh = function () {
		if ( this.viewport && this.viewport.clientWidth !== this.width ) {
			this.el.classList.remove( 'is-running' );
			this.build();
		}
	};

	// Für die Live-Vorschau in den Einstellungen.
	window.B83AlertTicker = Ticker;

	var tickers = [];

	function initTickers() {
		var list = document.querySelectorAll( '.b83-alert-ticker:not(.is-preview)' );
		Array.prototype.forEach.call( list, function ( el ) {
			// Theme ohne wp_body_open: Leiste nach oben holen.
			if ( el.classList.contains( 'is-moved' ) && document.body.firstChild !== el ) {
				document.body.insertBefore( el, document.body.firstChild );
			}
			var ticker = new Ticker( el );
			tickers.push( ticker );
			watchWindow(
				el,
				function () {
					el.hidden = false;
					ticker.build();
				},
				function () {
					el.hidden = true;
					document.body.classList.remove( 'b83-alert-has-ticker-bottom', 'b83-alert-has-ticker-top' );
				}
			);
		} );

		var timer = null;
		window.addEventListener( 'resize', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				tickers.forEach( function ( t ) {
					if ( ! t.el.hidden ) {
						t.refresh();
					}
				} );
			}, 150 );
		} );

		// Nach dem Laden der Webfonts neu vermessen.
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( function () {
				tickers.forEach( function ( t ) {
					if ( ! t.el.hidden ) {
						t.el.classList.remove( 'is-running' );
						t.build();
					}
				} );
			} );
		}
	}

	/* -----------------------------------------------------------------
	 * Pop-up
	 * --------------------------------------------------------------- */

	function initPopup() {
		var el = document.getElementById( 'b83-alert-popup' );
		if ( ! el ) {
			return;
		}
		var dialog = el.querySelector( '.b83-alert-popup__dialog' );
		var force = el.hasAttribute( 'data-force' );
		var frequency = el.getAttribute( 'data-frequency' ) || 'session';
		var key = 'b83_alert_popup_' + ( el.getAttribute( 'data-version' ) || '' );
		var store = 'once' === frequency ? storage( 'localStorage' ) : ( 'session' === frequency ? storage( 'sessionStorage' ) : null );
		var lastFocus = null;
		var opened = false;

		function focusables() {
			return Array.prototype.filter.call(
				dialog.querySelectorAll( 'a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])' ),
				function ( n ) {
					return ! n.disabled && n.offsetParent !== null;
				}
			);
		}

		function onKey( e ) {
			if ( 'Escape' === e.key ) {
				e.preventDefault();
				close();
				return;
			}
			if ( 'Tab' !== e.key ) {
				return;
			}
			var items = focusables();
			if ( ! items.length ) {
				e.preventDefault();
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) {
				e.preventDefault();
				last.focus();
			} else if ( ! e.shiftKey && document.activeElement === last ) {
				e.preventDefault();
				first.focus();
			}
		}

		function open() {
			if ( opened ) {
				return;
			}
			opened = true;
			lastFocus = document.activeElement;
			el.hidden = false;
			void el.offsetWidth; // Übergang auslösen.
			el.classList.add( 'is-open' );
			document.documentElement.classList.add( 'b83-alert-lock' );
			document.addEventListener( 'keydown', onKey );
			// Fokus auf das Fenster: Screenreader lesen es vor, Tab führt zu den Buttons.
			dialog.focus( { preventScroll: true } );
		}

		function close() {
			if ( ! opened ) {
				return;
			}
			opened = false;
			el.classList.remove( 'is-open' );
			document.documentElement.classList.remove( 'b83-alert-lock' );
			document.removeEventListener( 'keydown', onKey );
			if ( store && ! force ) {
				try {
					store.setItem( key, '1' );
				} catch ( e ) {}
			}
			window.setTimeout( function () {
				el.hidden = true;
			}, 260 );
			if ( lastFocus && lastFocus.focus ) {
				lastFocus.focus( { preventScroll: true } );
			}
		}

		Array.prototype.forEach.call( el.querySelectorAll( '[data-b83-alert-close]' ), function ( n ) {
			n.addEventListener( 'click', close );
		} );

		if ( ! force && store ) {
			try {
				if ( store.getItem( key ) ) {
					return; // Bereits gesehen.
				}
			} catch ( e ) {}
		}

		var delay = force ? 0 : Math.max( 0, num( el, 'data-delay' ) ) * 1000;
		watchWindow(
			el,
			function () {
				window.setTimeout( open, delay );
			},
			function () {
				if ( opened ) {
					close();
				}
			}
		);
	}

	/* -----------------------------------------------------------------
	 * Hinweisbox
	 * --------------------------------------------------------------- */

	function initBoxes() {
		Array.prototype.forEach.call( document.querySelectorAll( '.b83-alert-box' ), function ( el ) {
			watchWindow(
				el,
				function () {
					el.hidden = false;
				},
				function () {
					el.hidden = true;
				}
			);
		} );
	}

	function init() {
		initTickers();
		initBoxes();
		initPopup();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
