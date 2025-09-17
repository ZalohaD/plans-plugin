# Plans Plugin

## Description
This plugin adds a Custom Post Type (CPT) "Plan" for creating pricing plans and a shortcode [plans] to display them on pages as tabs: Monthly | Annual with plan cards. The plugin is independent of other plugins and libraries like jQuery, using vanilla CSS and JavaScript.

## Requirements
- PHP 7.4 or higher
- WordPress 6.8.2 or higher

## Installation
1. Upload the `plans` folder to `wp-content/plugins/`.
2. Activate the plugin in the WordPress admin panel (Plugins > Installed Plugins).
3. Create new "Plan" CPT entries in the "Plans" menu.
4. Add the shortcode [plans] to any page or post.

## Shortcode Parameters
The [plans] shortcode has no additional parameters. It automatically displays all active plans (is_enabled = true), filtering by is_annual in the tabs.

## Key Features
- CPT "Plan" without public pages (single/archive disabled).
- Fields: title, price (number), custom_price_label (string), is_annual (bool), button_text (string), button_link (url), features (array of strings via textarea), is_starred (bool), is_enabled (bool).
- Shortcode with Monthly/Annual tabs, adaptive cards (3-4 columns via CSS grid).
- Meta Boxes in admin with validation/sanitization.
- Quick edit for is_starred and is_enabled in the plans table.
- Shortcode output caching (transient), cleared on plan save.

## Architecture
- OOP-oriented code separated into classes: CPT registration, meta boxes, shortcode, admin features.
- Uses WP best practices: hooks, sanitization, transients for caching.
- Frontend: vanilla JS for tab switching, CSS for styles and responsiveness.
- Easily extensible: add new fields via Metaboxes or Shortcode classes.

## Notes
- Features implemented as textarea (each line is a separate feature), stored as an array.
- If custom_price_label is not empty, it replaces the price.
- Annual tab shows plans with is_annual = true, Monthly - false.
- Cache: transient 'plans_shortcode_output' for the entire shortcode HTML.