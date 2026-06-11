/**
 * Interactive Geo Maps: remove the amCharts tooltip box padding so the tooltip
 * content (e.g. the full-bleed region image) sits flush with the tooltip edges.
 *
 * The tooltip background is drawn in SVG, so its internal padding can't be
 * reached with CSS. Each map's series tooltips have their own padding default;
 * we zero it once the map is ready. The tooltip content templates already pad
 * their own text, so the visual stays correct.
 */
( function () {
	function flushTooltips() {
		if ( typeof iMapsManager === 'undefined' || ! iMapsManager.maps ) {
			return;
		}

		Object.keys( iMapsManager.maps ).forEach( function ( id ) {
			var map = iMapsManager.maps[ id ];
			var series = ( map && map.allBaseSeries ) || [];

			series.forEach( function ( s ) {
				if ( s && s.tooltip && s.tooltip.label ) {
					s.tooltip.label.padding( 0, 0, 0, 0 );
				}
			} );
		} );
	}

	// 'mapready' is dispatched (non-bubbling) on each map container; catch it in
	// the capture phase so a single document listener covers every map.
	document.addEventListener( 'mapready', flushTooltips, true );
}() );
