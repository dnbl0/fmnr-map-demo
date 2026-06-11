# FMNR Global Map

Custom code behind the **FMNR Global Map** ( https://fmnrhub.com.au/fmnr-global-map/ ), built on top of the
[Interactive Geo Maps](https://wordpress.org/plugins/interactive-geo-maps/) (Premium) plugin.

The map shows world regions where Farmer Managed Natural Regeneration (FMNR) is implemented. Each **region**
has a styled tooltip card (image, list of countries, hectares restored, key partners, key project links), and
each **country** is marked with a round pin.

This repo contains the custom plugins, theme assets, helper scripts and a snapshot of the map data — everything
that is *not* part of a stock WordPress + Interactive Geo Maps install.

## Contents

| Path | What it is |
|------|------------|
| `plugins/fmnr-map-tooltips/` | Turns plain-text `Label: value` tooltip content into the styled region card HTML at render time (hooks `igm_add_meta`). Holds the shared card builder `fmnr_build_tooltip_card()`. |
| `plugins/fmnr-map-regions-acf/` | Adds a **Map Region** ACF post type so editors fill in image / countries / hectares / partners / project links in a clean admin screen instead of plain text. Renders matching regions via the shared card builder (priority 20, overrides the text plugin where ACF data exists). |
| `plugins/fmnr-map-layer-toggle/` | Front-end toggle to switch between the region layer and the country-pin layer. |
| `theme-assets/igm-tooltip-flush.js` | Removes the amCharts tooltip padding so the card image sits flush with the top of the tooltip. |
| `theme-assets/functions-enqueue-snippet.php` | The `functions.php` snippet that enqueues the script above. |
| `scripts/` | One-off WP-CLI maintenance scripts (see below). |
| `data/map_info.json` | Snapshot of the `map_info` post meta for the map (post ID 1438) — regions, country pins, coordinates and tooltip content. |

## Requirements

- WordPress
- Interactive Geo Maps **Premium**
- Advanced Custom Fields (ACF) — for `fmnr-map-regions-acf`

## Install

1. Copy each folder in `plugins/` into `wp-content/plugins/` and activate all three.
2. Copy `theme-assets/igm-tooltip-flush.js` to `wp-content/themes/<your-theme>/assets/js/` and add the snippet
   from `theme-assets/functions-enqueue-snippet.php` to your theme's `functions.php`.
3. Create an Interactive Geo Maps map. The current map is post ID **1438**; several scripts assume that ID —
   change `$map_id` / `FMNR_ACF_MAP_ID` if yours differs.

## Authoring tooltips

**Regions** can be authored two ways:

- **ACF (preferred):** WP Admin → *Map Regions* → add/edit a region, pick its code, fill in image, countries,
  hectares, partners and project links.
- **Plain text fallback:** in the Interactive Geo Maps tooltip field, use lines like:
  ```
  Image: my-region.jpg
  Countries: Kenya, Tanzania, Ethiopia
  Hectares: 1,200,000
  Partners: World Vision, ICRAF
  Project: Re-greening Kenya | /kenya-project
  ```

## Scripts (`wp eval-file scripts/<name>.php`)

- `migrate-tooltips.php` — back up and convert existing hand-written region tooltip HTML to the plain-text format.
- `revert-and-add-pins.php` — restore region tooltips from the pre-migration backup and add a round pin for every country.
- `seed-map-regions.php` — create a **Map Region** ACF post for each region from current tooltip data (idempotent).
- `countries-to-commas.php` — display helper used while switching the country list from line breaks to commas.

> These scripts target map post ID **1438** and are specific to this site's data. Read before running.
