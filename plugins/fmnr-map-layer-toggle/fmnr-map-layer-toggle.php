<?php
/**
 * Plugin Name: FMNR Map Layer Toggle
 * Description: Adds a "Regions / Country pins" toggle for an Interactive Geo Maps map. Shows
 *              regions by default; switching to Country pins reveals the per-country markers
 *              and disables region hovering, so the two tooltips never compete for the cursor.
 *              Usage: [fmnr-map-toggle id="1438"] placed above the [display-map] shortcode.
 * Version:     1.0.0
 * Author:      FMNR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'fmnr-map-toggle',
	function ( $atts ) {
		$atts   = shortcode_atts( array( 'id' => '' ), $atts, 'fmnr-map-toggle' );
		$map_id = (int) $atts['id'];
		if ( $map_id < 1 ) {
			return '';
		}

		ob_start();
		?>
<div class="fmnr-map-toggle" data-map="<?php echo esc_attr( $map_id ); ?>" role="group" aria-label="Map layer" style="opacity:0;">
	<button type="button" class="is-active" data-mode="regions">Regions</button>
	<button type="button" data-mode="pins">Countries</button>
</div>
<style>
body.page-template-page-blank{background-color:#d0ffef;}
#map_wrapper_<?php echo (int) $map_id; ?>{background-color:#d0ffef;}
.fmnr-map-toggle{display:inline-flex;gap:0;margin:0 0 14px;border:1px solid #00552f;border-radius:5px;overflow:hidden;font-family:'Lato',sans-serif;background:#fff;transition:opacity .2s;}
.fmnr-map-toggle button{appearance:none;border:0;background:#fff;color:#00552f;font-family:'Lato',sans-serif;font-size:14px;font-weight:700;padding:8px 18px;cursor:pointer;line-height:1.2;transition:background .15s,color .15s;}
.fmnr-map-toggle button + button{border-left:1px solid #00552f;}
.fmnr-map-toggle button.is-active{background:#00552f;color:#fff;}
.fmnr-map-toggle button:focus-visible{outline:2px solid #2da57f;outline-offset:2px;}
/* Overlaid inside the map, top-left, like the zoom/fullscreen controls */
.fmnr-map-toggle.is-overlay{position:absolute;top:12px;left:12px;z-index:50;margin:0;box-shadow:0 1px 4px rgba(0,0,0,.2);}
</style>
<script>
(function () {
	var MAP_ID = <?php echo (int) $map_id; ?>;
	var CONTAINER = 'map_' + MAP_ID;

	var currentMode = 'regions'; // default: regions only, pins hidden
	var boundSeries = [];

	function getLayers(inst) {
		var regions = [], markers = [];
		inst.map.series.each(function (s) {
			// This map has no background/image-marker series, so every MapImageSeries is the pin layer.
			if (s.className === 'MapPolygonSeries') {
				regions.push(s);
			} else if (s.className === 'MapImageSeries') {
				markers.push(s);
			}
		});
		return { regions: regions, markers: markers };
	}

	function setRegionInteract(series, on) {
		series.mapPolygons.template.interactionsEnabled = on;
		series.mapPolygons.each(function (p) { p.interactionsEnabled = on; });
	}

	function applyMode(inst) {
		var l = getLayers(inst);
		l.markers.forEach(function (m) {
			if (currentMode === 'pins') { m.show(); } else { m.hide(); }
			// Pins may be drawn after we first run; re-enforce the mode when the series validates.
			if (boundSeries.indexOf(m) === -1) {
				boundSeries.push(m);
				m.events.on('datavalidated', function () {
					if (currentMode === 'pins') { m.show(); } else { m.hide(); }
				});
			}
		});
		l.regions.forEach(function (r) { setRegionInteract(r, currentMode !== 'pins'); });
	}

	function placeInsideMap(wrap) {
		// Move the toggle into the map's container so it overlays the map (top-left),
		// alongside the built-in zoom / fullscreen controls.
		var host = document.querySelector('#map_wrapper_' + MAP_ID + ' .map_container');
		if (host && wrap.parentNode !== host) {
			host.appendChild(wrap);
			wrap.classList.add('is-overlay');
		}
		wrap.style.opacity = '1';
	}

	function wire(inst) {
		var wrap = document.querySelector('.fmnr-map-toggle[data-map="' + MAP_ID + '"]');
		if (!wrap || wrap.dataset.wired) { return; }
		wrap.dataset.wired = '1';
		placeInsideMap(wrap);
		var btns = Array.prototype.slice.call(wrap.querySelectorAll('button'));
		btns.forEach(function (b) {
			b.addEventListener('click', function () {
				btns.forEach(function (x) { x.classList.remove('is-active'); });
				b.classList.add('is-active');
				currentMode = b.getAttribute('data-mode');
				applyMode(inst);
			});
		});
		applyMode(inst); // default view: regions only
		// Re-enforce after the map's appear animation, in case pins draw late.
		var el = document.getElementById(CONTAINER);
		if (el) { el.addEventListener('mapappeared', function () { applyMode(inst); }); }
	}

	function tryReady() {
		var im = window.iMapsManager;
		if (im && im.maps && im.maps[MAP_ID] && im.maps[MAP_ID].map) {
			wire(im.maps[MAP_ID]);
			return true;
		}
		return false;
	}

	function start() {
		if (tryReady()) { return; }
		var el = document.getElementById(CONTAINER);
		if (el) { el.addEventListener('mapready', tryReady); }
		var n = 0, t = setInterval(function () {
			if (tryReady() || ++n > 80) {
				clearInterval(t);
				// Safety: never leave the control invisible if the map never reports ready.
				var w = document.querySelector('.fmnr-map-toggle[data-map="' + MAP_ID + '"]');
				if (w) { w.style.opacity = '1'; }
			}
		}, 250);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
</script>
		<?php
		return ob_get_clean();
	}
);
