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
	 * Breites Dashboard-Panel direkt unter die Überschrift „Dashboard“
	 * setzen. Läuft sofort (nicht erst bei „ready“), also bevor der Editor
	 * startet – sonst ginge der Inhalt des Editors beim Verschieben verloren.
	 */
	( function () {
		var panel = document.getElementById( 'b83-alert-wide' );
		if ( ! panel ) {
			return;
		}
		var anchor = document.querySelector( '#wpbody-content .wrap > h1' );
		if ( anchor && anchor.parentNode ) {
			anchor.parentNode.insertBefore( panel, anchor.nextSibling );
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
