<?php
/**
 * One-time migration: convert existing hand-written HTML tooltip cards on the
 * "FMNR Global Regions" map (igmap post 1438) into plain "Label: value" lines.
 *
 * Run:  ./wp eval-file migrate-tooltips.php
 *       ./wp eval-file migrate-tooltips.php --dry   (preview only, no write)
 *
 * Backs up map_info to meta key map_info_pre_tooltip_migration before writing.
 * Self-checks by regenerating each card with the live plugin and comparing to the
 * original; aborts the write for any region that does not round-trip exactly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$map_id = 1438;
$dry    = in_array( 'dry', (array) ( $args ?? array() ), true );

$labels = array(
	FMNR_TT_LABEL_COUNTRIES => 'countries',
	FMNR_TT_LABEL_HECTARES  => 'hectares',
	FMNR_TT_LABEL_PARTNERS  => 'partners',
	FMNR_TT_LABEL_PROJECTS  => 'projects',
);

$uploads_base = trailingslashit( wp_get_upload_dir()['baseurl'] );
$home         = trailingslashit( home_url( '/' ) );

/**
 * Parse one original card's HTML back into labelled lines.
 */
function fmnr_html_to_lines( $html, $labels, $uploads_base, $home ) {
	$lines = array();

	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8"?><div id="__root">' . $html . '</div>' );
	libxml_clear_errors();
	$xpath = new DOMXPath( $dom );

	// Image.
	$img = $xpath->query( '//img' )->item( 0 );
	if ( $img ) {
		$src = $img->getAttribute( 'src' );
		if ( 0 === strpos( $src, $uploads_base ) ) {
			$src = substr( $src, strlen( $uploads_base ) );
		}
		$lines[] = 'Image: ' . $src;
	}

	// Walk the body div children in order, pairing label spans with value spans.
	$body = $xpath->query( '//div[contains(@style,"padding:16px")]' )->item( 0 );
	if ( $body ) {
		$current = null;
		foreach ( $body->childNodes as $node ) {
			if ( XML_ELEMENT_NODE !== $node->nodeType ) {
				continue;
			}
			$tag  = strtolower( $node->nodeName );
			$text = trim( $node->textContent );

			if ( 'span' === $tag && isset( $labels[ $text ] ) ) {
				$current = $labels[ $text ];
				continue;
			}

			if ( 'span' === $tag && 'countries' === $current ) {
				$lines[] = 'Countries: ' . fmnr_br_to_csv( $node );
				$current = null;
			} elseif ( 'span' === $tag && 'hectares' === $current ) {
				$lines[] = 'Hectares: ' . $text;
				$current = null;
			} elseif ( 'span' === $tag && 'partners' === $current ) {
				$lines[] = 'Partners: ' . fmnr_br_to_csv( $node );
				$current = null;
			} elseif ( 'div' === $tag && 'projects' === $current ) {
				foreach ( $node->getElementsByTagName( 'a' ) as $a ) {
					$href  = $a->getAttribute( 'href' );
					$title = trim( $a->textContent );
					if ( 0 === strpos( $href, $home ) ) {
						$href = '/' . substr( $href, strlen( $home ) );
					}
					$lines[] = 'Project: ' . $title . ' | ' . $href;
				}
				$current = null;
			}
		}
	}

	return implode( "\n", $lines );
}

/**
 * Turn a span containing <br>-separated values into a comma-separated string.
 */
function fmnr_br_to_csv( $node ) {
	$html  = '';
	$inner = $node->ownerDocument;
	foreach ( $node->childNodes as $child ) {
		$html .= $inner->saveHTML( $child );
	}
	$parts = preg_split( '#<br\s*/?>#i', $html );
	$parts = array_map(
		function ( $p ) {
			return trim( html_entity_decode( wp_strip_all_tags( $p ), ENT_QUOTES ) );
		},
		$parts
	);
	$parts = array_filter( $parts, 'strlen' );
	return implode( ', ', $parts );
}

// --- Run -------------------------------------------------------------------

$meta = get_post_meta( $map_id, 'map_info', true );
if ( empty( $meta['regions'] ) || ! is_array( $meta['regions'] ) ) {
	WP_CLI::error( "No regions found on map $map_id." );
}

// Back up once.
if ( ! $dry && '' === (string) get_post_meta( $map_id, 'map_info_pre_tooltip_migration', true ) ) {
	update_post_meta( $map_id, 'map_info_pre_tooltip_migration', $meta );
	WP_CLI::log( "Backed up map_info -> map_info_pre_tooltip_migration\n" );
}

$all_ok = true;
foreach ( $meta['regions'] as $i => &$region ) {
	$name = isset( $region['name'] ) ? $region['name'] : "(region $i)";
	$orig = isset( $region['tooltipContent'] ) ? trim( (string) $region['tooltipContent'] ) : '';

	if ( '' === $orig || '<' !== $orig[0] ) {
		WP_CLI::log( "— $name: skipped (already plain text or empty)" );
		continue;
	}

	$lines = fmnr_html_to_lines( $orig, $labels, $uploads_base, $home );

	// Self-check: regenerate the card from the parsed lines and compare to the original.
	$regen = fmnr_build_tooltip_card( $name, fmnr_parse_tooltip_fields( $lines ) );
	$match = ( $regen === $orig );

	WP_CLI::log( "— $name: " . ( $match ? 'OK (round-trips exactly)' : 'MISMATCH — review below' ) );
	WP_CLI::log( "  parsed:\n" . preg_replace( '/^/m', '    ', $lines ) );
	if ( ! $match ) {
		$all_ok = false;
		WP_CLI::log( "  original: $orig" );
		WP_CLI::log( "  regen:    $regen" );
	}

	$region['tooltipContent'] = $lines;
}
unset( $region );

if ( $dry ) {
	WP_CLI::success( 'Dry run complete — nothing written.' );
	return;
}

if ( ! $all_ok ) {
	WP_CLI::error( 'One or more regions did not round-trip; map_info NOT updated. Backup is intact.' );
}

update_post_meta( $map_id, 'map_info', $meta );
WP_CLI::success( 'All regions converted to plain text and written to map_info.' );
