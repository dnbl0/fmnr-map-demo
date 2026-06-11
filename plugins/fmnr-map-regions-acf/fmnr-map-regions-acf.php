<?php
/**
 * Plugin Name: FMNR Map Regions (ACF spike)
 * Description: SPIKE — an alternative editing experience for map tooltips. Each region is a
 *              "Map Region" post with proper ACF fields (image picker, country/partner
 *              repeaters, project page-links) instead of a text field. At render it builds the
 *              same styled card (reusing fmnr_build_tooltip_card from the FMNR Map Tooltips
 *              plugin). Regions that have a matching Map Region post are driven by ACF; all
 *              others fall back to the text-based plugin untouched.
 * Version:     0.1.0
 * Author:      FMNR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** The Interactive Geo Maps post that these regions belong to. */
const FMNR_ACF_MAP_ID = 1438;

/* -------------------------------------------------------------------------
 * 1. "Map Region" custom post type
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	function () {
		register_post_type(
			'map_region',
			array(
				'labels'       => array(
					'name'          => 'Map Regions',
					'singular_name' => 'Map Region',
					'add_new_item'  => 'Add New Map Region',
					'edit_item'     => 'Edit Map Region',
					'menu_name'     => 'Map Regions',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => true,
				'menu_icon'    => 'dashicons-location-alt',
				'supports'     => array( 'title' ),
				'has_archive'  => false,
				'rewrite'      => false,
			)
		);
	}
);

/* -------------------------------------------------------------------------
 * 2. ACF field group (defined in code so it's portable, no DB-only config)
 * ---------------------------------------------------------------------- */

add_action(
	'acf/init',
	function () {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_fmnr_map_region',
				'title'    => 'Region tooltip',
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'map_region',
						),
					),
				),
				'fields'   => array(
					array(
						'key'          => 'field_fmnr_region_code',
						'label'        => 'Which map region?',
						'name'         => 'region_code',
						'type'         => 'select',
						'instructions' => 'Pick the region on the FMNR map this card belongs to.',
						'required'     => 1,
						'ui'           => 1,
						'choices'      => array(), // Filled live from the map (see acf/load_field below).
					),
					array(
						'key'           => 'field_fmnr_image',
						'label'         => 'Image',
						'name'          => 'image',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'all',
					),
					array(
						'key'          => 'field_fmnr_countries',
						'label'        => 'Countries',
						'name'         => 'countries',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add country',
						'sub_fields'   => array(
							array(
								'key'   => 'field_fmnr_country_name',
								'label' => 'Country',
								'name'  => 'name',
								'type'  => 'text',
							),
						),
					),
					array(
						'key'   => 'field_fmnr_hectares',
						'label' => 'Hectares being restored',
						'name'  => 'hectares',
						'type'  => 'text',
					),
					array(
						'key'          => 'field_fmnr_partners',
						'label'        => 'Key partners',
						'name'         => 'partners',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add partner',
						'sub_fields'   => array(
							array(
								'key'   => 'field_fmnr_partner_name',
								'label' => 'Partner',
								'name'  => 'name',
								'type'  => 'text',
							),
						),
					),
					array(
						'key'          => 'field_fmnr_projects',
						'label'        => 'Key projects',
						'name'         => 'projects',
						'type'         => 'repeater',
						'layout'       => 'block',
						'button_label' => 'Add project',
						'sub_fields'   => array(
							array(
								'key'           => 'field_fmnr_project_page',
								'label'         => 'Linked page',
								'name'          => 'page',
								'type'          => 'post_object',
								'post_type'     => array( 'post', 'page' ),
								'return_format' => 'id',
								'ui'            => 1,
								'instructions'  => 'Pick the blog post / page to link to.',
							),
							array(
								'key'          => 'field_fmnr_project_label',
								'label'        => 'Link text (optional)',
								'name'         => 'label',
								'type'         => 'text',
								'instructions' => 'Leave blank to use the page title.',
							),
						),
					),
				),
			)
		);
	}
);

/**
 * Populate the "Which map region?" dropdown live from the map's own regions,
 * so editors choose "Pacific (TL,SB)" instead of typing a code.
 */
add_filter(
	'acf/load_field/key=field_fmnr_region_code',
	function ( $field ) {
		$field['choices'] = fmnr_acf_map_region_choices();
		return $field;
	}
);

/**
 * code => "Name (code)" for every region defined on the map.
 */
function fmnr_acf_map_region_choices() {
	$choices = array();
	$meta    = get_post_meta( FMNR_ACF_MAP_ID, 'map_info', true );
	if ( empty( $meta['regions'] ) || ! is_array( $meta['regions'] ) ) {
		return $choices;
	}
	foreach ( $meta['regions'] as $region ) {
		if ( empty( $region['id'] ) ) {
			continue;
		}
		$code             = $region['id'];
		$name             = ! empty( $region['name'] ) ? $region['name'] : $code;
		$choices[ $code ] = $name . ' (' . $code . ')';
	}
	return $choices;
}

/* -------------------------------------------------------------------------
 * 3. Render: drive matching map regions from ACF, reusing the shared card builder
 * ---------------------------------------------------------------------- */

add_filter(
	'igm_add_meta',
	function ( $meta ) {
		if ( empty( $meta['regions'] ) || ! is_array( $meta['regions'] ) ) {
			return $meta;
		}
		if ( ! function_exists( 'fmnr_build_tooltip_card' ) ) {
			return $meta; // Text plugin (card builder) not active.
		}

		$by_code = fmnr_acf_regions_by_code();
		if ( empty( $by_code ) ) {
			return $meta;
		}

		foreach ( $meta['regions'] as &$region ) {
			$code = isset( $region['id'] ) ? fmnr_acf_normalize_code( $region['id'] ) : '';
			if ( '' === $code || ! isset( $by_code[ $code ] ) ) {
				continue; // No ACF entry -> leave for the text-based plugin.
			}
			$name                     = isset( $region['name'] ) ? $region['name'] : '';
			$region['tooltipContent'] = fmnr_build_tooltip_card( $name, $by_code[ $code ] );
		}
		unset( $region );

		return $meta;
	},
	20, // After the text plugin (priority 10) so ACF wins where it has data.
	1
);

/**
 * Load all published Map Region posts into a map of normalized code => $fields array
 * (the same shape fmnr_build_tooltip_card expects).
 */
function fmnr_acf_regions_by_code() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	$cache = array();

	$posts = get_posts(
		array(
			'post_type'      => 'map_region',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $pid ) {
		$code = fmnr_acf_normalize_code( (string) get_field( 'region_code', $pid ) );
		if ( '' === $code ) {
			continue;
		}

		$countries = array();
		foreach ( (array) get_field( 'countries', $pid ) as $row ) {
			if ( ! empty( $row['name'] ) ) {
				$countries[] = $row['name'];
			}
		}
		$partners = array();
		foreach ( (array) get_field( 'partners', $pid ) as $row ) {
			if ( ! empty( $row['name'] ) ) {
				$partners[] = $row['name'];
			}
		}
		$projects = array();
		foreach ( (array) get_field( 'projects', $pid ) as $row ) {
			$page_id = isset( $row['page'] ) ? (int) $row['page'] : 0;
			if ( $page_id < 1 ) {
				continue;
			}
			$title = ! empty( $row['label'] ) ? $row['label'] : get_the_title( $page_id );
			$projects[] = array(
				'title' => $title,
				'url'   => get_permalink( $page_id ),
			);
		}

		$image_id  = (int) get_field( 'image', $pid );
		$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium_large' ) : '';

		$cache[ $code ] = array(
			'image'     => (string) $image_url,
			'countries' => $countries,
			'hectares'  => (string) get_field( 'hectares', $pid ),
			'partners'  => $partners,
			'projects'  => $projects,
		);
	}

	return $cache;
}

/**
 * Normalize a region code string for matching (trim, strip spaces, uppercase).
 */
function fmnr_acf_normalize_code( $code ) {
	return strtoupper( preg_replace( '/\s+/', '', (string) $code ) );
}

/* -------------------------------------------------------------------------
 * 4. Admin list: at-a-glance columns so editors can verify everything is wired up
 * ---------------------------------------------------------------------- */

add_filter(
	'manage_map_region_posts_columns',
	function ( $cols ) {
		$new = array( 'cb' => $cols['cb'] );
		$new['fmnr_thumb']    = 'Image';
		$new['title']         = $cols['title'];
		$new['fmnr_linked']   = 'On the map?';
		$new['fmnr_counts']   = 'Content';
		$new['date']          = $cols['date'];
		return $new;
	}
);

add_action(
	'manage_map_region_posts_custom_column',
	function ( $col, $post_id ) {
		switch ( $col ) {
			case 'fmnr_thumb':
				$img = (int) get_field( 'image', $post_id );
				echo $img
					? wp_get_attachment_image( $img, array( 56, 40 ), false, array( 'style' => 'border-radius:4px;object-fit:cover;width:56px;height:40px;' ) )
					: '<span style="color:#b32d2e;">— none —</span>';
				break;

			case 'fmnr_linked':
				$code    = fmnr_acf_normalize_code( (string) get_field( 'region_code', $post_id ) );
				$choices = fmnr_acf_map_region_choices();
				$match   = '';
				foreach ( $choices as $c => $label ) {
					if ( fmnr_acf_normalize_code( $c ) === $code ) {
						$match = $label;
						break;
					}
				}
				echo $match
					? '<span style="color:#1a7f37;">✓ ' . esc_html( $match ) . '</span>'
					: '<span style="color:#b32d2e;">✗ not matched to a map region</span>';
				break;

			case 'fmnr_counts':
				$countries = count( (array) get_field( 'countries', $post_id ) );
				$partners  = count( (array) get_field( 'partners', $post_id ) );
				$projects  = count( (array) get_field( 'projects', $post_id ) );
				$hectares  = get_field( 'hectares', $post_id );
				echo esc_html( sprintf( '%d countries · %d partners · %d projects', $countries, $partners, $projects ) );
				echo $hectares ? ' · ' . esc_html( $hectares ) : '';
				break;
		}
	},
	10,
	2
);
