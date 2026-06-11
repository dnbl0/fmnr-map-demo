<?php
/**
 * Add this to your active theme's functions.php to load igm-tooltip-flush.js,
 * which removes the amCharts tooltip padding so the region/country image sits
 * flush with the top of the tooltip card.
 *
 * Place igm-tooltip-flush.js at: <theme>/assets/js/igm-tooltip-flush.js
 */
add_action( 'wp_enqueue_scripts', 'fmnr_enqueue_igm_tooltip_flush' );

function fmnr_enqueue_igm_tooltip_flush() {
	wp_enqueue_script(
		'fmnr-igm-tooltip-flush',
		get_stylesheet_directory_uri() . '/assets/js/igm-tooltip-flush.js',
		array(),
		'1.0.0',
		true
	);
}
