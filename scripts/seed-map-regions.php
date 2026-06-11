<?php
/**
 * One-time: create a Map Region (ACF) post for every region on map 1438 that doesn't
 * already have one, populated from the current plain-text tooltip data.
 *
 * Run: ./wp eval-file seed-map-regions.php
 * Idempotent: skips regions whose code already has a Map Region post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! function_exists( 'fmnr_parse_tooltip_fields' ) ) {
	WP_CLI::error( 'FMNR Map Tooltips plugin (parser) must be active.' );
}

$map_id       = 1438;
$uploads_base = trailingslashit( wp_get_upload_dir()['baseurl'] );

/** Resolve a stored image path/URL to an attachment ID (strip resize suffix to hit the original). */
function fmnr_seed_image_to_id( $ref, $uploads_base ) {
	$url  = preg_match( '#^https?://#i', $ref ) ? $ref : $uploads_base . ltrim( $ref, '/' );
	$full = preg_replace( '#-\d+x\d+(\.[a-z0-9]+)$#i', '$1', $url ); // drop -768x512 etc.
	$id   = attachment_url_to_postid( $full );
	if ( ! $id ) {
		$id = attachment_url_to_postid( $url );
	}
	return (int) $id;
}

/** Existing Map Region posts keyed by normalized code (to stay idempotent). */
$existing = array();
foreach ( get_posts( array( 'post_type' => 'map_region', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) ) as $pid ) {
	$existing[ fmnr_acf_normalize_code( (string) get_field( 'region_code', $pid ) ) ] = $pid;
}

$meta = get_post_meta( $map_id, 'map_info', true );
if ( empty( $meta['regions'] ) ) {
	WP_CLI::error( 'No regions on map.' );
}

foreach ( $meta['regions'] as $region ) {
	$name = isset( $region['name'] ) ? $region['name'] : '';
	$code = isset( $region['id'] ) ? $region['id'] : '';
	if ( '' === $code ) {
		continue;
	}
	if ( isset( $existing[ fmnr_acf_normalize_code( $code ) ] ) ) {
		WP_CLI::log( "— $name ($code): already has a Map Region post, skipped." );
		continue;
	}

	$raw = isset( $region['tooltipContent'] ) ? trim( (string) $region['tooltipContent'] ) : '';
	if ( '' === $raw || '<' === $raw[0] ) {
		WP_CLI::log( "— $name ($code): tooltip is empty or raw HTML, skipped (convert it first)." );
		continue;
	}
	$f = fmnr_parse_tooltip_fields( $raw );

	$pid = wp_insert_post(
		array(
			'post_type'   => 'map_region',
			'post_status' => 'publish',
			'post_title'  => $name,
		)
	);
	if ( is_wp_error( $pid ) || ! $pid ) {
		WP_CLI::warning( "Failed to create post for $name." );
		continue;
	}

	update_field( 'region_code', $code, $pid );

	$img_id = fmnr_seed_image_to_id( $f['image'], $uploads_base );
	if ( $img_id ) {
		update_field( 'image', $img_id, $pid );
	} elseif ( '' !== $f['image'] ) {
		WP_CLI::warning( "  $name: could not match image '{$f['image']}' to an attachment." );
	}

	update_field( 'countries', array_map( function ( $c ) { return array( 'name' => $c ); }, $f['countries'] ), $pid );
	update_field( 'hectares', $f['hectares'], $pid );
	update_field( 'partners', array_map( function ( $p ) { return array( 'name' => $p ); }, $f['partners'] ), $pid );

	$projects = array();
	foreach ( $f['projects'] as $proj ) {
		$page_id = url_to_postid( preg_match( '#^https?://#i', $proj['url'] ) ? $proj['url'] : home_url( $proj['url'] ) );
		if ( ! $page_id ) {
			WP_CLI::warning( "  $name: could not match project link '{$proj['url']}' to a page; left blank." );
			continue;
		}
		$projects[] = array( 'page' => $page_id, 'label' => $proj['title'] );
	}
	update_field( 'projects', $projects, $pid );

	WP_CLI::log( "✓ $name ($code): created Map Region post $pid (image " . ( $img_id ? "#$img_id" : 'none' ) . ', ' . count( $projects ) . ' projects).' );
}

WP_CLI::success( 'Done seeding Map Region posts.' );
