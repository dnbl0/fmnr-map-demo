<?php
/**
 * Plugin Name: FMNR Map Tooltips
 * Description: Lets editors fill in plain "Label: value" lines in the Interactive Geo Maps
 *              Tooltip Content field and generates the styled region card automatically,
 *              so no HTML is required. Hooks the plugin's igm_add_meta filter at render time;
 *              the stored field stays human-readable.
 * Version:     1.0.0
 * Author:      FMNR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fixed section labels shown on every card. Edit copy here to change all regions at once.
 */
const FMNR_TT_LABEL_COUNTRIES = 'Countries FMNR is implemented';
const FMNR_TT_LABEL_HECTARES  = 'Hectares being restored';
const FMNR_TT_LABEL_PARTNERS  = 'Key partners';
const FMNR_TT_LABEL_PROJECTS  = 'Key projects';

/**
 * Inline styles, kept verbatim from the original hand-written cards so output is identical.
 */
const FMNR_TT_STYLE_WRAP    = "max-width:340px;font-family:'Lato',sans-serif;color:#1a1a1a;overflow:hidden;border-radius:8px;";
const FMNR_TT_STYLE_IMG     = 'display:block;width:100%;height:120px;object-fit:cover;border-radius:8px 8px 0 0;';
const FMNR_TT_STYLE_BODY    = 'padding:16px;';
const FMNR_TT_STYLE_HEADING = "font-family:'FatFrank',sans-serif;font-weight:400;font-size:1.7em;line-height:1;display:block;color:#00552f;margin-bottom:.5em;";
const FMNR_TT_STYLE_LABEL   = 'display:block;font-size:.7em;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2da57f;margin-bottom:1px;';
const FMNR_TT_STYLE_VALUE   = 'display:block;font-size:.95em;line-height:1.45;margin-bottom:.6em;';
const FMNR_TT_STYLE_LINKBOX = 'margin-top:.1em;';
const FMNR_TT_STYLE_LINK    = 'color:#00552f;text-decoration:underline;font-weight:600;font-size:.8em;line-height:1.3;display:block;margin-bottom:12px;';

/**
 * Replace plain-text tooltip input with generated card HTML at render time.
 * Runs on every map render, before the plugin's nl2br pass.
 */
add_filter(
	'igm_add_meta',
	function ( $meta ) {
		if ( empty( $meta['regions'] ) || ! is_array( $meta['regions'] ) ) {
			return $meta;
		}
		foreach ( $meta['regions'] as &$region ) {
			$raw = isset( $region['tooltipContent'] ) ? trim( (string) $region['tooltipContent'] ) : '';
			// Empty, or already raw HTML -> leave untouched (backward compatible).
			if ( '' === $raw || '<' === $raw[0] ) {
				continue;
			}
			$name                     = isset( $region['name'] ) ? $region['name'] : '';
			$region['tooltipContent'] = fmnr_build_tooltip_card( $name, fmnr_parse_tooltip_fields( $raw ) );
		}
		unset( $region );
		return $meta;
	},
	10,
	1
);

/**
 * Parse "Label: value" lines into a structured array.
 *
 * @param string $raw Raw textarea content.
 * @return array{image:string,countries:array,hectares:string,partners:array,projects:array}
 */
function fmnr_parse_tooltip_fields( $raw ) {
	$fields = array(
		'image'     => '',
		'countries' => array(),
		'hectares'  => '',
		'partners'  => array(),
		'projects'  => array(),
	);

	$lines = preg_split( '/\r\n|\r|\n/', $raw );
	foreach ( $lines as $line ) {
		if ( ! preg_match( '/^\s*([A-Za-z]+)\s*:\s*(.*)$/', $line, $m ) ) {
			continue;
		}
		$label = strtolower( $m[1] );
		$value = trim( $m[2] );
		if ( '' === $value ) {
			continue;
		}

		switch ( $label ) {
			case 'image':
				$fields['image'] = $value;
				break;
			case 'countries':
				$fields['countries'] = fmnr_split_list( $value );
				break;
			case 'hectares':
				$fields['hectares'] = $value;
				break;
			case 'partners':
				$fields['partners'] = fmnr_split_list( $value );
				break;
			case 'project':
				$parts                = array_map( 'trim', explode( '|', $value, 2 ) );
				$title                = $parts[0];
				$url                  = isset( $parts[1] ) ? $parts[1] : '';
				if ( '' !== $title && '' !== $url ) {
					$fields['projects'][] = array(
						'title' => $title,
						'url'   => $url,
					);
				}
				break;
		}
	}

	return $fields;
}

/**
 * Split a comma-separated list into trimmed, non-empty items.
 */
function fmnr_split_list( $value ) {
	$items = array_map( 'trim', explode( ',', $value ) );
	return array_values( array_filter( $items, 'strlen' ) );
}

/**
 * Resolve an image reference (full URL or uploads-relative path) to an absolute URL.
 */
function fmnr_resolve_image_url( $ref ) {
	if ( preg_match( '#^https?://#i', $ref ) ) {
		return $ref;
	}
	$uploads = wp_get_upload_dir();
	return trailingslashit( $uploads['baseurl'] ) . ltrim( $ref, '/' );
}

/**
 * Resolve a link reference (full URL, site-relative path, or bare slug) to an absolute URL.
 */
function fmnr_resolve_link_url( $ref ) {
	if ( preg_match( '#^https?://#i', $ref ) ) {
		return $ref;
	}
	return home_url( '/' . ltrim( $ref, '/' ) );
}

/**
 * Build the styled card as a single-line HTML string. Sections with no data are omitted.
 *
 * @param string $name   Region name (card heading).
 * @param array  $fields Parsed fields from fmnr_parse_tooltip_fields().
 */
function fmnr_build_tooltip_card( $name, $fields ) {
	$html = '<div style="' . FMNR_TT_STYLE_WRAP . '">';

	if ( '' !== $fields['image'] ) {
		$html .= '<img src="' . esc_url( fmnr_resolve_image_url( $fields['image'] ) ) . '"'
			. ' alt="' . esc_attr( $name ) . '"'
			. ' style="' . FMNR_TT_STYLE_IMG . '" />';
	}

	$html .= '<div style="' . FMNR_TT_STYLE_BODY . '">';

	if ( '' !== $name ) {
		$html .= '<strong style="' . FMNR_TT_STYLE_HEADING . '">' . esc_html( $name ) . '</strong>';
	}

	if ( ! empty( $fields['countries'] ) ) {
		$html .= fmnr_tt_section( FMNR_TT_LABEL_COUNTRIES, fmnr_tt_comma_list( $fields['countries'] ) );
	}

	if ( '' !== $fields['hectares'] ) {
		$html .= fmnr_tt_section( FMNR_TT_LABEL_HECTARES, esc_html( $fields['hectares'] ) );
	}

	if ( ! empty( $fields['partners'] ) ) {
		$html .= fmnr_tt_section( FMNR_TT_LABEL_PARTNERS, fmnr_tt_br_list( $fields['partners'] ) );
	}

	if ( ! empty( $fields['projects'] ) ) {
		$html .= '<span style="' . FMNR_TT_STYLE_LABEL . '">' . esc_html( FMNR_TT_LABEL_PROJECTS ) . '</span>';
		$html .= '<div style="' . FMNR_TT_STYLE_LINKBOX . '">';
		foreach ( $fields['projects'] as $project ) {
			$html .= '<a href="' . esc_url( fmnr_resolve_link_url( $project['url'] ) ) . '"'
				. ' style="' . FMNR_TT_STYLE_LINK . '">' . esc_html( $project['title'] ) . '</a>';
		}
		$html .= '</div>';
	}

	$html .= '</div></div>';

	return $html;
}

/**
 * One label + value block.
 *
 * @param string $label       Section label (plain text).
 * @param string $value_html  Already-escaped value HTML.
 */
function fmnr_tt_section( $label, $value_html ) {
	return '<span style="' . FMNR_TT_STYLE_LABEL . '">' . esc_html( $label ) . '</span>'
		. '<span style="' . FMNR_TT_STYLE_VALUE . '">' . $value_html . '</span>';
}

/**
 * Escape a list of items and join them with <br>.
 */
function fmnr_tt_br_list( $items ) {
	return implode( '<br>', array_map( 'esc_html', $items ) );
}

/**
 * Escape a list of items and join them with commas.
 */
function fmnr_tt_comma_list( $items ) {
	return implode( ', ', array_map( 'esc_html', $items ) );
}
