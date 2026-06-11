<?php
/**
 * Convert the "Countries FMNR is implemented" list in each region tooltip from <br>-separated
 * to comma-separated, to slightly reduce tooltip height. Only touches the countries span;
 * partners (and everything else) are left unchanged.
 *
 * Run: ./wp eval-file countries-to-commas.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$map_id = 1438;
$meta   = get_post_meta( $map_id, 'map_info', true );
if ( empty( $meta['regions'] ) ) {
	WP_CLI::error( 'No regions found.' );
}

$changed = 0;
foreach ( $meta['regions'] as &$region ) {
	$html = isset( $region['tooltipContent'] ) ? (string) $region['tooltipContent'] : '';
	if ( '' === trim( $html ) || '<' !== trim( $html )[0] ) {
		continue; // not raw HTML
	}

	$new = preg_replace_callback(
		'/(Countries FMNR is implemented<\/span><span[^>]*>)(.*?)(<\/span>)/s',
		function ( $m ) {
			$countries = preg_replace( '/\s*<br\s*\/?>\s*/i', ', ', $m[2] );
			return $m[1] . $countries . $m[3];
		},
		$html,
		1
	);

	if ( null !== $new && $new !== $html ) {
		$region['tooltipContent'] = $new;
		++$changed;
		preg_match( '/Countries FMNR is implemented<\/span><span[^>]*>(.*?)<\/span>/s', $new, $mm );
		WP_CLI::log( '✓ ' . ( $region['name'] ?? '?' ) . ': ' . ( $mm[1] ?? '' ) );
	}
}
unset( $region );

if ( $changed ) {
	update_post_meta( $map_id, 'map_info', $meta );
}
WP_CLI::success( "Updated $changed region tooltip(s)." );
