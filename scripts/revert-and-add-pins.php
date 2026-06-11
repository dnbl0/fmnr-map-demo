<?php
/**
 * 1. Revert: restore map_info from the pre-migration backup, so region tooltips show the
 *    original hand-written HTML again.
 * 2. Add a pin (round marker) for every country in every region, each with placeholder
 *    tooltip content for the client to fill in later.
 *
 * Run: ./wp eval-file revert-and-add-pins.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$map_id = 1438;

// Country => [ region label, latitude, longitude ]. Centroid-ish coordinates.
$countries = array(
	// Southern Africa
	'Malawi'          => array( 'Southern Africa', '-13.25', '34.30' ),
	'Zambia'          => array( 'Southern Africa', '-13.13', '27.85' ),
	'Zimbabwe'        => array( 'Southern Africa', '-19.02', '29.15' ),
	'Mozambique'      => array( 'Southern Africa', '-18.67', '35.53' ),
	// East Africa
	'Ethiopia'        => array( 'East Africa', '9.15', '40.49' ),
	'Kenya'           => array( 'East Africa', '-0.02', '37.91' ),
	'Uganda'          => array( 'East Africa', '1.37', '32.29' ),
	'Tanzania'        => array( 'East Africa', '-6.37', '34.89' ),
	'Rwanda'          => array( 'East Africa', '-1.94', '29.87' ),
	'South Sudan'     => array( 'East Africa', '6.88', '31.31' ),
	'Somalia'         => array( 'East Africa', '5.15', '46.20' ),
	// West Africa
	'Niger'           => array( 'West Africa', '17.61', '8.08' ),
	'Mali'            => array( 'West Africa', '17.57', '-4.00' ),
	'Burkina Faso'    => array( 'West Africa', '12.24', '-1.56' ),
	'Ghana'           => array( 'West Africa', '7.95', '-1.02' ),
	'Senegal'         => array( 'West Africa', '14.50', '-14.45' ),
	'Nigeria'         => array( 'West Africa', '9.08', '8.68' ),
	// Latin America
	'Colombia'        => array( 'Latin America', '4.57', '-74.30' ),
	'Haiti'           => array( 'Latin America', '18.97', '-72.29' ),
	// Pacific
	'Timor-Leste'     => array( 'Pacific', '-8.87', '125.73' ),
	'Solomon Islands' => array( 'Pacific', '-9.65', '160.16' ),
	// Asia
	'India'           => array( 'Asia', '20.59', '78.96' ),
	'Indonesia'       => array( 'Asia', '-2.55', '118.01' ),
	'Myanmar'         => array( 'Asia', '21.91', '95.96' ),
	// Middle East
	'Yemen'           => array( 'Middle East', '15.55', '48.52' ),
);

/** Compact placeholder tooltip card for a country pin (raw HTML, single line). */
function fmnr_pin_placeholder_html( $country, $region ) {
	$lbl = 'display:block;font-size:.7em;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2da57f;margin-bottom:1px;';
	$val = 'display:block;font-size:.9em;line-height:1.45;margin-bottom:.6em;';
	return '<div style="max-width:240px;font-family:\'Lato\',sans-serif;color:#1a1a1a;padding:14px;">'
		. '<strong style="font-family:\'FatFrank\',sans-serif;font-weight:400;font-size:1.4em;line-height:1;display:block;color:#00552f;margin-bottom:.5em;">' . esc_html( $country ) . '</strong>'
		. '<span style="' . $lbl . '">Region</span>'
		. '<span style="' . $val . '">' . esc_html( $region ) . '</span>'
		. '<span style="' . $lbl . '">Placeholder information</span>'
		. '<span style="' . $val . '">Add country-level details here — hectares restored, local partners and project highlights.</span>'
		. '</div>';
}

// --- 1. Revert -------------------------------------------------------------

$backup = get_post_meta( $map_id, 'map_info_pre_tooltip_migration', true );
if ( empty( $backup['regions'] ) ) {
	WP_CLI::error( 'Backup map_info_pre_tooltip_migration not found; cannot revert.' );
}
$meta = $backup;
WP_CLI::log( 'Restored map_info from pre-migration backup (region tooltips = original HTML).' );

// --- 2. Add country pins ---------------------------------------------------

$markers = array();
foreach ( $countries as $country => $info ) {
	list( $region, $lat, $lng ) = $info;
	$markers[] = array(
		'id'             => $country,
		'coordinates'    => array(
			'name'      => $country,
			'latitude'  => $lat,
			'longitude' => $lng,
		),
		'tooltipContent' => fmnr_pin_placeholder_html( $country, $region ),
		'content'        => '',
		'action'         => 'none',
	);
}
$meta['roundMarkers'] = $markers;

update_post_meta( $map_id, 'map_info', $meta );
WP_CLI::success( 'Reverted tooltips and added ' . count( $markers ) . ' country pins with placeholder info.' );
