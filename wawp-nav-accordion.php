<?php
/**
 * Plugin Name:       WAW Nav Accordion
 * Plugin URI:        https://github.com/thierrypigot/wawp-nav-accordion
 * Description:       Collapsible submenus in the mobile overlay of the core Navigation block, driven by the core interactivity store, plus a one-line focus fix.
 * Version:           0.1.1
 * Author:            WeAre[WP]
 * Author URI:        https://www.wearewp.pro
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wawp-nav-accordion
 * Requires at least: 6.9
 * Requires PHP:      8.0
 *
 * @package WawpNavAccordion
 * @since   0.1.0
 */

/*
 * FONCTIONNEMENT
 * ==============
 * Dans l'overlay mobile, le store `core/navigation` lie `aria-expanded` des
 * chevrons à `state.isSubmenuOpen`, qui renvoie vrai pour TOUS les sous-menus
 * dès que l'overlay est ouvert : rien ne se replie.
 *
 * Dans le contexte d'un sous-menu, `state.isMenuOpen` reflète déjà l'état réel
 * de ce sous-menu (`menuOpenedBy` y désigne `submenuOpenedBy`). On relie donc
 * les chevrons à ce getter, au rendu, et une feuille de style replie les
 * sous-menus dont le chevron n'est pas `aria-expanded="true"`.
 *
 * Tout le reste est natif :
 *   - un seul ensemble ouvert à la fois : en ouvrir un donne le focus à son
 *     chevron, et le sous-menu précédent se ferme quand le focus le quitte
 *     (`actions.handleMenuFocusout`) ;
 *   - Échap referme le sous-menu ouvert (`actions.handleMenuKeydown`) ;
 *   - tout est replié à chaque ouverture de l'overlay ;
 *   - sur ordinateur, les deux getters renvoient la même valeur : rien ne
 *     change.
 *
 * Les parents sans lien (URL vide ou « # ») perdent leur `href` et reçoivent
 * la même action que le chevron : toucher l'intitulé ouvre le sous-menu.
 *
 * Seul ajout JavaScript : `assets/js/view.js` (store `wawp/nav-accordion`)
 * empêche l'appui de déplacer le focus dans l'overlay. Sans lui, le
 * sous-menu ouvert se replie dès l'appui, la liste remonte et le clic tombe
 * à côté de sa cible.
 *
 * DÉGRADATION
 * ===========
 * La directive n'est remplacée que si elle vaut exactement la valeur connue.
 * Si le cœur la modifie, le plugin ne touche à rien, la classe
 * `wawp-nav-accordion` n'est pas posée et la CSS ne s'applique pas : on
 * retrouve le comportement par défaut de WordPress.
 */

defined( 'ABSPATH' ) || exit;

define( 'WAWP_NAV_ACCORDION_VERSION', '0.1.1' );

// Mises à jour depuis les releases GitHub (zip publié par
// .github/workflows/release.yml à chaque tag vX.Y.Z).
require_once plugin_dir_path( __FILE__ ) . 'plugin-update-checker/plugin-update-checker.php';

$wawp_nav_accordion_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/thierrypigot/wawp-nav-accordion/',
	__FILE__,
	'wawp-nav-accordion'
);
$wawp_nav_accordion_update_checker->getVcsApi()->enableReleaseAssets();

/**
 * Valeur de la directive posée par le cœur sur les chevrons de sous-menu.
 *
 * @see block_core_navigation_add_directives_to_submenu()
 */
const WAWP_NAV_ACCORDION_CORE_BINDING = 'state.isSubmenuOpen';

/**
 * Valeur de remplacement : état réel du sous-menu.
 */
const WAWP_NAV_ACCORDION_BINDING = 'state.isMenuOpen';

/**
 * Classe posée sur le `<nav>` traité, qui active la feuille de style.
 */
const WAWP_NAV_ACCORDION_CLASS = 'wawp-nav-accordion';

/**
 * Directive qui garde le focus en place à l'appui (cf. assets/js/view.js).
 */
const WAWP_NAV_ACCORDION_KEEP_FOCUS = 'wawp/nav-accordion::actions.keepFocus';

add_action( 'init', 'wawp_nav_accordion_register_style' );
add_action( 'init', 'wawp_nav_accordion_register_script_module' );
add_filter( 'render_block_core/navigation', 'wawp_nav_accordion_render_navigation', 10, 2 );

/**
 * Enregistre la feuille de style, chargée seulement avec le bloc Navigation.
 *
 * @since 0.1.0
 */
function wawp_nav_accordion_register_style() {
	wp_enqueue_block_style(
		'core/navigation',
		array(
			'handle' => 'wawp-nav-accordion',
			'src'    => plugins_url( 'assets/css/overlay.css', __FILE__ ),
			'path'   => plugin_dir_path( __FILE__ ) . 'assets/css/overlay.css',
			'ver'    => WAWP_NAV_ACCORDION_VERSION,
		)
	);
}

/**
 * Enregistre le module JS, chargé seulement quand un menu est traité.
 *
 * @since 0.1.1
 */
function wawp_nav_accordion_register_script_module() {
	wp_register_script_module(
		'wawp-nav-accordion-view',
		plugins_url( 'assets/js/view.js', __FILE__ ),
		array( '@wordpress/interactivity' ),
		WAWP_NAV_ACCORDION_VERSION
	);
}

/**
 * Relie les chevrons de l'overlay à l'état réel de leur sous-menu.
 *
 * @since 0.1.0
 *
 * @param string $block_content Rendu du bloc core/navigation.
 * @param array  $block         Bloc analysé (nom, attributs).
 * @return string Rendu modifié.
 */
function wawp_nav_accordion_render_navigation( $block_content, $block ) {
	$overlay_menu = $block['attrs']['overlayMenu'] ?? 'mobile';

	if ( '' === $block_content || 'never' === $overlay_menu ) {
		return $block_content;
	}

	/**
	 * Active ou non l'accordéon pour un bloc Navigation donné.
	 *
	 * @since 0.1.0
	 *
	 * @param bool  $enabled Vrai par défaut.
	 * @param array $block   Bloc analysé (nom, attributs).
	 */
	if ( ! apply_filters( 'wawp_nav_accordion_enabled', true, $block ) ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $tags->next_tag( array( 'class_name' => 'wp-block-navigation' ) ) ) {
		return $block_content;
	}
	$tags->set_bookmark( 'nav' );

	$rebound        = 0;
	$awaiting_label = false;

	while ( $tags->next_tag() ) {
		$tag = $tags->get_tag();

		// Chaque LI ouvre un nouvel item : seul un parent attend un intitulé.
		if ( 'LI' === $tag ) {
			$awaiting_label = $tags->has_class( 'has-child' ) && ! $tags->has_class( 'open-always' );
			continue;
		}

		// Chevron (mode survol) ou intitulé-bouton (mode clic).
		if ( 'BUTTON' === $tag && $tags->has_class( 'wp-block-navigation-submenu__toggle' ) ) {
			$awaiting_label = false;

			if ( WAWP_NAV_ACCORDION_CORE_BINDING === $tags->get_attribute( 'data-wp-bind--aria-expanded' ) ) {
				$tags->set_attribute( 'data-wp-bind--aria-expanded', WAWP_NAV_ACCORDION_BINDING );
				$tags->set_attribute( 'data-wp-on--mousedown', WAWP_NAV_ACCORDION_KEEP_FOCUS );
				++$rebound;
			}
			continue;
		}

		// Intitulé d'un parent en mode survol : il précède son chevron.
		if ( 'A' === $tag && $awaiting_label && $tags->has_class( 'wp-block-navigation-item__content' ) ) {
			$awaiting_label = false;

			if ( wawp_nav_accordion_is_empty_link( $tags->get_attribute( 'href' ) ) ) {
				// `tabindex="-1"` : l'intitulé peut recevoir le focus que lui
				// donne `toggleMenuOnClick`, sans entrer dans l'ordre de
				// tabulation (le clavier passe par le chevron).
				$tags->remove_attribute( 'href' );
				$tags->set_attribute( 'tabindex', '-1' );
				$tags->set_attribute( 'data-wp-on--click', 'actions.toggleMenuOnClick' );
				$tags->set_attribute( 'data-wp-on--mousedown', WAWP_NAV_ACCORDION_KEEP_FOCUS );
			}
		}
	}

	if ( 0 === $rebound ) {
		return $block_content;
	}

	wp_enqueue_script_module( 'wawp-nav-accordion-view' );

	$tags->seek( 'nav' );
	$tags->add_class( WAWP_NAV_ACCORDION_CLASS );
	$tags->release_bookmark( 'nav' );

	return $tags->get_updated_html();
}

/**
 * Indique si un `href` ne mène nulle part (absent, vide ou « # »).
 *
 * @since 0.1.0
 *
 * @param string|true|null $href Valeur renvoyée par get_attribute().
 * @return bool
 */
function wawp_nav_accordion_is_empty_link( $href ) {
	if ( null === $href || true === $href ) {
		return true;
	}

	return in_array( trim( $href ), array( '', '#' ), true );
}
