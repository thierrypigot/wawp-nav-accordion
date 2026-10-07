# WAW Nav Accordion

Sous-menus repliables dans l'overlay mobile du bloc Navigation de WordPress, en s'appuyant sur le store Interactivity du cœur (`core/navigation`). Aucun JavaScript maison.

## Le problème

Dans l'overlay mobile, le cœur lie `aria-expanded` des chevrons à `state.isSubmenuOpen`, qui renvoie vrai pour tous les sous-menus dès que l'overlay est ouvert. Tout est déplié et les chevrons ne font rien. Ticket Gutenberg : [#44346](https://github.com/WordPress/gutenberg/issues/44346).

## La solution

| Brique | Rôle |
|---|---|
| Filtre `render_block_core/navigation` | Relie chaque chevron à `state.isMenuOpen` (état réel du sous-menu). Retire le `href` des parents sans lien (vide ou `#`) et leur donne l'action `toggleMenuOnClick`. Pose la classe `wawp-nav-accordion` sur le `<nav>`. |
| `assets/css/overlay.css` | Replie les sous-menus dans `.is-menu-open` sauf si le chevron est `aria-expanded="true"`, affiche le chevron, cible tactile de 44 px. Chargée via `wp_enqueue_block_style()`, seulement avec le bloc. |

Tout le reste vient du cœur : un seul ensemble ouvert à la fois (fermeture quand le focus quitte le sous-menu), Échap, tout replié à l'ouverture, ordinateur inchangé.

**Mode non exclusif (plusieurs sous-menus ouverts)** : non proposé. Il faudrait retirer `handleMenuFocusout`, partagé avec l'ordinateur ; on casserait la fermeture au clavier et un sous-menu ouvert sur mobile resterait affiché sur ordinateur.

## Dégradation

La directive n'est remplacée que si elle vaut exactement `state.isSubmenuOpen`. Si le cœur change, le plugin ne modifie rien, la classe n'est pas posée, la CSS ne s'applique pas : retour au comportement par défaut.

## Points de vigilance

- Repose sur deux détails internes du cœur : le nom `state.isSubmenuOpen` et le fait que `menuOpenedBy` désigne `submenuOpenedBy` dans un sous-menu. À retester à chaque version majeure de WordPress.
- Piège à focus de l'overlay : le cœur calcule le dernier élément focusable à l'ouverture. Si le dernier item du menu est un parent, Tab sur son chevron renvoie en haut au lieu d'entrer dans le sous-menu déplié.
- Souris dans une fenêtre étroite : le survol ouvre les sous-menus dans l'overlay (mode « survol »).

## Filtre

```php
// Désactiver l'accordéon pour un bloc Navigation donné.
add_filter( 'wawp_nav_accordion_enabled', function ( $enabled, $block ) {
	return 'menu-pied' !== ( $block['attrs']['className'] ?? '' );
}, 10, 2 );
```

## Styles du thème

La CSS du plugin ne gère que le comportement. Toutes ses règles sont sous `.wawp-nav-accordion .is-menu-open` ; un thème les surcharge en ajoutant une classe (ex. `.site-header`).

## Releases

Mises à jour automatiques sur les sites via [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) v5, qui lit les releases GitHub de ce dépôt.

1. Bumper la version dans 3 endroits : en-tête `Version:` et constante `WAWP_NAV_ACCORDION_VERSION` (`wawp-nav-accordion.php`), `Stable tag` (`readme.txt`). Ajouter l'entrée du changelog dans `readme.txt`.
2. Commit, puis tag et push :

```powershell
git tag v0.1.1
git push origin main --tags
```

3. La GitHub Action `.github/workflows/release.yml` vérifie que le tag et les 3 versions concordent, construit le zip (`git archive`, exclusions dans `.gitattributes`) et publie la release. Les sites voient la mise à jour dans Extensions.

Zip local, pour une installation manuelle : `python build-zip.py` (`dist/wawp-nav-accordion-{version}.zip`).

**Dépôt privé** : plugin-update-checker ne peut pas lire les releases sans jeton. Soit le dépôt est public (comme `waw-plan-du-site`), soit chaque site déclare un jeton GitHub en lecture seule.
