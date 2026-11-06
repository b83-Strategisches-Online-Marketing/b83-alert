/**
 * b83 Alert – Eingabeformular (Dashboard-Widget und Seite „Alert“).
 *
 * TinyMCE verliert seinen Inhalt, wenn das Dashboard-Widget per Drag & Drop
 * verschoben wird (das iframe wird neu geladen). Deshalb den Editor vor dem
 * Verschieben abbauen und danach wieder aufbauen.
 */
( function ( $ ) {
	'use strict';

	var EDITOR = 'b83alertwidgeteditor';
	var STATE_KEY = 'b83_alert_wide_open';

	/*
	 * Dashboard-Zeile direkt unter die Überschrift „Dashboard“ setzen und das
	 * gewählte Widget (Standard: Kundeninformation aus b83 White Label) in die
	 * linke Spalte holen. Läuft sofort (nicht erst bei „ready“), also bevor
	 * der Editor startet – sonst ginge der Inhalt des Editors beim
	 * Verschieben verloren.
	 */
	( function () {
		var row = document.getElementById( 'b83-alert-row' );
		var panel = document.getElementById( 'b83-alert-wide' );
		if ( ! row || ! panel ) {
			return;
		}
		var anchor = document.querySelector( '#wpbody-content .wrap > h1' );
		if ( anchor && anchor.parentNode ) {
			anchor.parentNode.insertBefore( row, anchor.nextSibling );
		}

		var slot = row.querySelector( '.b83-alert-row__side' );
		var sideId = row.getAttribute( 'data-side' ) || '';
		var side = sideId ? document.getElementById( sideId ) : null;

		function isVisible( el ) {
			return el && ! el.classList.contains( 'hide-if-js' ) && 'none' !== el.style.display;
		}

		function layout() {
			row.classList.toggle( 'has-side', isVisible( side ) && side.parentNode === slot );
		}

		if ( side && slot && side.classList.contains( 'postbox' ) ) {
			slot.appendChild( side );
			side.classList.add( 'b83-alert-row__widget' );
			layout();
			// „Ansicht anpassen“: Widget ein- oder ausblenden.
			var toggle = document.getElementById( sideId + '-hide' );
			if ( toggle ) {
				toggle.addEventListener( 'change', function () {
					window.setTimeout( layout, 0 );
				} );
			}
		}
		var details = panel.querySelector( 'details' );
		if ( ! details ) {
			return;
		}
		try {
			if ( '0' === window.localStorage.getItem( STATE_KEY ) ) {
				details.removeAttribute( 'open' );
			}
		} catch ( e ) {}
		details.addEventListener( 'toggle', function () {
			try {
				window.localStorage.setItem( STATE_KEY, details.open ? '1' : '0' );
			} catch ( e ) {}
		} );
	}() );

	$( document ).on( 'sortstart', '.meta-box-sortables', function ( event, ui ) {
		if ( window.tinymce && ui.item && ui.item.find( '#' + EDITOR ).length ) {
			var ed = window.tinymce.get( EDITOR );
			if ( ed ) {
				ed.save();
				window.tinymce.execCommand( 'mceRemoveEditor', false, EDITOR );
			}
		}
	} );

	$( document ).on( 'sortstop', '.meta-box-sortables', function ( event, ui ) {
		if ( window.tinymce && ui.item && ui.item.find( '#' + EDITOR ).length ) {
			window.tinymce.execCommand( 'mceAddEditor', false, EDITOR );
		}
	} );

	// Zeichen-Zähler für die Laufschrift.
	$( '.b83-alert-form input[name="b83_alert[ticker_text]"]' ).each( function () {
		var $input = $( this );
		var max = parseInt( $input.attr( 'maxlength' ), 10 ) || 300;
		var $count = $( '<span class="b83-alert-count" aria-live="polite"></span>' ).insertAfter( $input );
		function update() {
			$count.text( $input.val().length + ' / ' + max );
		}
		$input.on( 'input', update );
		update();
	} );
}( jQuery ) );
