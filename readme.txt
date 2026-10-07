=== WAW Nav Accordion ===
Contributors: wearewp, thierrypigot
Tags: navigation, mobile menu, accordion, submenu, accessibility
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Collapsible submenus in the mobile overlay of the core Navigation block. Uses the core interactivity store: no custom JavaScript.

== Description ==

In the mobile overlay of the core Navigation block, WordPress expands every submenu and the submenu toggles do nothing. With deep menus, visitors have to scroll through a very long list. See Gutenberg issue #44346.

This plugin makes the overlay submenus collapsible, while letting WordPress do the work:

* every submenu is collapsed when the overlay opens;
* the chevron opens and closes its submenu, and `aria-expanded` reflects the real state;
* only one branch is open at a time: opening a submenu closes the others (core focus-out behavior);
* Escape closes the open submenu;
* a parent item without a link (empty URL or `#`) opens its submenu when tapped;
* desktop behavior is unchanged.

= How it works =

Core binds the overlay toggles to `state.isSubmenuOpen`, which is true for every submenu while the overlay is open. The plugin rebinds them, at render time, to `state.isMenuOpen`, which already holds the real state of each submenu. A small stylesheet, loaded only with the Navigation block, collapses the submenus whose toggle is not expanded.

If a future WordPress version changes the core directive, the plugin leaves the markup untouched and the default behavior comes back. Nothing breaks.

= Filter =

`wawp_nav_accordion_enabled` (bool, default true): disable the accordion for a given Navigation block.

`add_filter( 'wawp_nav_accordion_enabled', function ( $enabled, $block ) { return 'footer' !== ( $block['attrs']['className'] ?? '' ); }, 10, 2 );`

= Theme styling =

The stylesheet only handles behavior (collapse, chevron visibility, 44px touch target). Every rule is scoped under `.wawp-nav-accordion .is-menu-open`. Add one class to your selectors (e.g. `.site-header`) to override it.

== Changelog ==

= 0.1.0 =
* Add: overlay submenu toggles bound to the real submenu state (`state.isMenuOpen`).
* Add: submenus collapsed in the overlay unless their toggle is expanded; chevron shown, 44px touch target.
* Add: parent items without a link (empty URL or `#`) toggle their submenu.
* Add: `wawp_nav_accordion_enabled` filter.
