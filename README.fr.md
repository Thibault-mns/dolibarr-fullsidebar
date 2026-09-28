# FullSidebar — barre latérale complète pour Dolibarr

Remplace le menu de gauche **contextuel** de Dolibarr par un **arbre pliable listant
tous les modules et l'intégralité de leurs sous-menus**, accessible depuis n'importe
quelle page.

Le menu du haut, le drawer mobile (`menuhider`), le thème et les hooks existants ne sont
pas touchés : seul le rendu du menu gauche est remplacé.

*English version: [README.md](README.md)*

| | |
|---|---|
| **Dolibarr** | 23.0 et suivantes (seule version testée) |
| **PHP** | 7.3 et suivantes |
| **Thème** | eldy |
| **Licence** | GPL v3 ou ultérieure ([COPYING](COPYING)) |

---

## Installation

1. Copier le dossier dans `htdocs/custom/fullsidebar/` (permissions `644` pour les
   fichiers, `755` pour les dossiers). Le dossier doit s'appeler **`fullsidebar`** :
   renommer celui que produit le zip de GitHub (`dolibarr-fullsidebar-main`), ou cloner
   directement au bon nom :

   ```
   git clone https://github.com/Thibault-mns/dolibarr-fullsidebar.git htdocs/custom/fullsidebar
   ```
2. **Accueil → Configuration → Modules/Applications → onglet Interfaces** : activer
   « Barre latérale complète ».
   L'activation pose elle-même `MAIN_MENU_STANDARD = 'fullsidebar_menu.php'` et mémorise
   le gestionnaire précédent dans `FULLSIDEBAR_PREVIOUS_MENU`.
3. Recharger une page : la sidebar complète apparaît.

La désactivation du module restaure automatiquement le gestionnaire précédent.

La barre latérale complète s'applique aux **utilisateurs internes**. Les utilisateurs
externes (portail) utilisent le gestionnaire défini par `MAIN_MENUFRONT_STANDARD`, que le
module ne modifie pas : ils gardent le menu eldy.

Pour revenir au menu natif sans désactiver le module, passer « Activer la barre latérale
complète » à *Non* dans la page de configuration.

---

## Comment ça marche

`main.inc.php` charge le gestionnaire nommé par `MAIN_MENU_STANDARD` en cherchant dans :

```php
$dirmenus = array_merge(array("/core/menus/"), (array) $conf->modules_parts['menus']);
foreach ($dirmenus as $dirmenu) {
    $menufound = dol_include_once($dirmenu."standard/".$file_menu);
```

Le descripteur déclare `module_parts['menus'] = 1`, ce qui ajoute
`/fullsidebar/core/menus/` à cette liste. **Aucun fichier du cœur n'est modifié** et le
fichier survit aux mises à jour de Dolibarr.

Si le gestionnaire est introuvable (module désactivé), `main.inc.php` retombe tout seul
sur `eldy_menu.php` en écrivant un avertissement dans le syslog — jamais d'écran blanc.

### Construction de l'arbre

Le menu gauche « tout modules » est construit en réutilisant le cœur, pas en le
réécrivant :

| Étape | Appel |
|-------|-------|
| Entrées de premier niveau (les modules) | `print_eldy_menu(..., $noout = 1, 'jmobile')` — le mode `jmobile` évite l'entrée « hamburger » |
| Sous-menu complet d'un module | `print_left_eldy_menu(..., $noout = 1, $mainmenu, 'all')` |
| Entrées venant de `llx_menu` | `Menubase::menuLoad($mainmenu, 'all', ...)` |

Deux points sont essentiels :

- `menuLoad()` avec `leftmenu = 'all'` réécrit toute condition `$leftmenu == 'x'` en
  `1==1`. C'est ce qui débloque les sous-menus des modules **que l'on ne consulte pas**.
- `print_left_eldy_menu()` avec `$forceleftmenu` non vide met son `$leftmenu` interne à
  `''`. Or chaque branche codée en dur d'`eldy.lib.php` est gardée par
  `$usemenuhider || empty($leftmenu) || $leftmenu == "x"` : toutes sont donc ajoutées.

Le rendu, lui, est entièrement réécrit (`<ul>`/`<li>` + boutons d'expansion). C'est le
piège d'`auguria_menu.php` : il charge bien `leftmenu='all'`, mais son rendu produit du
jQuery Mobile qui **ne s'affiche pas** hors thème Auguria — sidebar vide, sans erreur.

### Deux détails que le cœur perd avec `$noout = 1`

`print_left_eldy_menu()` exécute le hook `menuLeftMenuItems` et le tri `positionfull` sur
sa **copie locale**, qu'elle jette quand `$noout = 1`. Les deux sont donc rejoués ici
(`buildBranch()`), sinon les entrées ajoutées par des modules tiers et l'ordre déclaré
seraient perdus. Conséquence assumée : le hook est appelé deux fois par menu principal —
il est censé être une transformation pure de tableau, comme dans le cœur.

### Navigation inter-modules, et le piège du `&amp;`

Les URLs de sous-menu d'`eldy` ne portent souvent que `leftmenu=...`. Cliquer
« Tiers → Liste » depuis l'accueil laisserait alors `$_SESSION['mainmenu']` sur `home`.
`buildHref()` force donc le `mainmenu` du module racine quand l'URL n'en porte pas —
c'est tout l'intérêt d'une sidebar complète.

`eldy.lib.php` mélange par ailleurs les séparateurs `&` et `&amp;` dans ses URLs
(par exemple les entrées de filtre des factures). Le rendu natif
imprime le `href` **sans échapper**, donc le `&amp;` reste valide. Ici l'attribut est
échappé (c'est plus sûr), ce qui transformait `&amp;` en `&amp;amp;` : le navigateur
lisait alors un paramètre nommé `amp;search_status` et **le filtre ne s'appliquait pas**.
`buildHref()` normalise donc les séparateurs en `&` avant de construire le lien, et
l'échappement unique de l'affichage produit un `href` correct.

### Ce qui est déplié au chargement

Uniquement le **module courant** et le **chemin menant à la page affichée**
(`markPaths()`). Déplier toute la branche du module courant — ce que fait le menu plat
d'eldy — noie l'arbre : tous les groupes frères finissent ouverts.

Sur une page fiche (`/compta/facture/card.php?id=12`) aucune URL de menu ne correspond ;
le `leftmenu` de la session (`customers_bills`) sert alors de repli pour ouvrir le bon
groupe.

---

## Configuration

**Accueil → Configuration → Modules → Barre latérale complète → roue crantée**

| Constante | Défaut | Rôle |
|-----------|--------|------|
| `MAIN_MENU_STANDARD` | `fullsidebar_menu.php` | Gestionnaire actif (posé à l'activation) |
| `FULLSIDEBAR_HIDE_TOPMENU` | `0` | Masque les entrées du menu du haut (barre, logo et hamburger conservés) |
| `FULLSIDEBAR_EXPAND_ALL` | `0` | Tout déplier au chargement au lieu du seul chemin courant |
| `FULLSIDEBAR_REMEMBER_OPENED` | `1` | Mémorise les branches ouvertes (localStorage, par navigateur, par utilisateur et par instance) |
| `FULLSIDEBAR_SHOW_UNAUTHORIZED` | `0` | Affiche en grisé les entrées non autorisées au lieu de les masquer |
| `FULLSIDEBAR_PREVIOUS_MENU` | — | Gestionnaire à restaurer à la désactivation |

### Barre du haut

Masquer les entrées du menu du haut vide la barre. Le setup reprend donc trois options
**natives de Dolibarr** qui la remplissent (lues par `top_menu()` dans `main.inc.php`) :

| Constante | Effet |
|-----------|-------|
| `MAIN_USE_TOP_MENU_SEARCH_DROPDOWN` | Recherche globale dans la barre du haut |
| `MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN` | Bouton « + » de création rapide |
| `MAIN_USE_TOP_MENU_IMPORT_FILE` | Lien d'envoi de fichier (aucun écran natif) |

La première est recommandée : `left_menu()` ne construit le formulaire de recherche
**que si elle est absente**. L'activer
libère donc le haut de la barre latérale — où le formulaire concurrence l'arbre — tout en
occupant la barre du haut. Les favoris (`top_menu_bookmark()`) y sont déjà par défaut.

Ces trois constantes appartiennent au cœur (Configuration → Affichage) et sont
**délibérément absentes de `$this->const`** : `insert_const()` ne peut donc jamais y
réimposer une valeur du module à la réactivation, et `remove()` ne les touche pas.
`MAIN_USE_TOP_MENU_IMPORT_FILE` acceptant une URL d'upload personnalisée à la place de
`1`, le setup la conserve au lieu de l'écraser.

> `FULLSIDEBAR_SHOW_UNAUTHORIZED` est à `0` par défaut, contrairement au comportement du
> cœur (`MAIN_MENU_HIDE_UNAUTHORIZED` absent ⇒ tout est affiché grisé). Avec un arbre de
> **tous** les modules, ce défaut produirait une sidebar illisible.

---

## Pièges / limites

- **Les `module_parts` sont lues à l'ACTIVATION.** Toute modification de
  `module_parts` (menus, css, js) dans le descripteur exige un cycle
  **désactiver / réactiver**. Le setup affiche un avertissement si
  `$conf->modules_parts['menus']['fullsidebar']` est absent.
- **`MAIN_MENU_INVERT`** (menus inversés horizontal/vertical) n'est pas supporté : un
  arbre de tous les modules ne se pose pas horizontalement. Dans ce cas le module rend le
  menu `eldy` d'origine.
- **Thème** : testé contre `eldy`. Les couleurs viennent des jetons du design system
  (`--accent`, `--ink`, `--muted`, `--line`, `--surface`, `--radius-element`,
  `--font-family`) quand le thème les expose, sinon des variables CSS de Dolibarr
  (qui basculent déjà en mode sombre), sinon d'une valeur en dur.
- **Coût** : le chargement des menus fait 2 requêtes sur `llx_menu` au lieu d'une (une
  passe « contexte courant », une passe `all`). La seconde est **paresseuse** : elle
  n'est faite qu'au moment de construire l'arbre. C'est important, car `loadMenu()` est
  aussi appelée par `theme/*/style.css.php` (pour `$nbtopmenuentries`) et cette feuille
  de style est *render-blocking* — y ajouter une passe inutilisée retarde le CSS de
  toute la page. Si le module Comptabilité est actif,
  `get_left_menu_accountancy()` ajoute ses 2 requêtes de dictionnaire sur chaque page au
  lieu des seules pages compta.
- **Mesurer le coût réel** : pour un administrateur, un commentaire HTML placé juste
  après l'arbre (code source de la page) donne le nombre d'entrées, le temps de
  construction et d'affichage, et la taille du HTML de l'arbre.
- **Hook `menuLeftMenuItems`** : il est appelé deux fois par module et par page (une fois
  par le cœur, une fois rejoué par le module). Un module tiers qui fait des requêtes dans
  ce hook en paie donc le double.
- **Largeur** : `.vmenu` fait 240 px dans `eldy`. L'arbre s'y adapte (`width:100%`,
  césure des libellés longs) mais ne l'élargit pas — élargir la colonne demanderait de
  toucher au thème.

---

## Fichiers

```
fullsidebar/
├── core/modules/modFullSidebar.class.php        descripteur (module_parts, const, init/remove)
├── core/menus/standard/fullsidebar_menu.php     classe MenuManager (chargement + rendu)
├── admin/setup.php                              page de configuration
├── css/fullsidebar.css                          styles de l'arbre
├── js/fullsidebar.js                            pliage/dépliage + mémorisation
├── langs/{fr_FR,en_US}/fullsidebar.lang         libellés
└── test/tree_test.php                           tests autonomes (CLI, sans base)
```

## Tests

```
php htdocs/custom/fullsidebar/test/tree_test.php
```

Les helpers Dolibarr sont bouchonnés : la suite tourne sans base ni `conf.php` et couvre
l'imbrication (niveaux → arbre, sauts de niveau, parents interdits), la construction des
liens (`mainmenu` forcé, normalisation des `&amp;`), la règle d'ouverture (chemin actif
seul, repli sur le `leftmenu` de session) et l'unicité des clés. Elle ne teste **pas**
l'API menu de Dolibarr elle-même — seule une installation réelle le fait.

## Pièges rencontrés en développement

- **Ne jamais laisser une exception sortir d'un gestionnaire de menu** : il tourne sur
  toutes les pages, une erreur = écran blanc partout. `buildBranch()` est isolé par
  module.
- **`&amp;` dans les URLs d'eldy** : voir plus haut, le double échappement rend les
  filtres inopérants sans aucune erreur visible.
- **`loadMenu()` est appelée par la feuille de style du thème** (`style.css.php`,
  render-blocking) : tout coût ajouté là retarde le CSS de toute la page.
- **Les libellés ne sont pas des identifiants** : ils changent avec la langue. Toute
  clé persistante doit se baser sur l'URL.

Licence GPL v3 ou ultérieure, voir [COPYING](COPYING).
