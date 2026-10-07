/**
 * WAW Nav Accordion : garde le focus en place à l'appui, dans l'overlay.
 *
 * Le store `core/navigation` referme un sous-menu quand le focus le quitte
 * (`handleMenuFocusout`). Or le navigateur déplace le focus dès l'appui
 * (`mousedown`), avant le clic : le sous-menu ouvert se replie, la liste
 * remonte, et le relâchement tombe sur un autre élément que le chevron
 * visé. Le clic est perdu et rien ne s'ouvre.
 *
 * On annule donc le déplacement de focus de l'appui, sur les chevrons et
 * les intitulés de parent de l'overlay. `toggleMenuOnClick` donne lui-même
 * le focus à l'élément au moment du clic : la fermeture du sous-menu
 * précédent et l'ouverture du nouveau ont lieu ensemble.
 *
 * Sur ordinateur (overlay fermé), rien n'est annulé.
 *
 * @package WawpNavAccordion
 * @since   0.1.1
 */

import { store, withSyncEvent } from '@wordpress/interactivity';

store( 'wawp/nav-accordion', {
	actions: {
		keepFocus: withSyncEvent( ( event ) => {
			if (
				0 === event.button &&
				event.target.closest(
					'.wp-block-navigation__responsive-container.is-menu-open'
				)
			) {
				event.preventDefault();
			}
		} ),
	},
} );
