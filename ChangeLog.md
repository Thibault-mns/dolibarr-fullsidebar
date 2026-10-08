# ChangeLog — FullSidebar

## 1.6.0 — octobre 2026

- **Option « Clic sur le libellé d'une branche »** (`FULLSIDEBAR_CLICK_MODE`), suite à un
  retour de la communauté Dolibarr : aller chercher la petite flèche pour déplier un
  sous-menu n'est pas optimal. Trois modes : `arrow` (défaut, comportement inchangé),
  `title` (le libellé déplie, la petite flèche ouvre la page de l'entrée, avec un chevron
  placé après le libellé pour l'état) et `dblclick` (clic = déplier, double-clic = ouvrir
  la page). Ctrl/Cmd/Maj/Alt + clic ouvrent toujours le lien normalement ; une entrée sans
  sous-menu ouvre toujours sa page. Le bouton de ce mode porte l'icône chaîne (`fa-link`).
- **Tiroir mobile pleine largeur sous 768 px** (eldy et bootstrap5) : il faisait 280 px
  (eldy) ou 200 px (bootstrap5) et laissait une bande vide à droite d'un écran de 425 px.
  Avec bootstrap5, 333 px (90 % de l'écran au maximum) entre 768 et 900 px, où le thème
  impose 200 px.
- **Tiroir mobile : `#id-left` et `.vmenu` à 100 %** (eldy et bootstrap5). Ils prenaient la
  largeur de leur contenu : étroits à l'ouverture, ils s'élargissaient dès qu'une branche
  aux libellés plus longs était dépliée.
- **Tiroir mobile eldy : les icônes de la barre du haut ne recouvrent plus le menu.** Le bloc
  (recherche, création rapide, marque-pages, import, aide, utilisateur) était une boîte de
  245 px de haut fixe dont les trois groupes s'empilaient et débordaient sur les premières
  entrées. Il occupe maintenant toute la largeur sur une ligne qui passe à la suivante au
  besoin. Le tiroir passe aussi en `box-sizing: border-box` : à 100 % plus ses marges
  internes, il dépassait de l'écran.
- **Tiroir mobile : le bas de l'arbre est de nouveau atteignable.** La hauteur de l'arbre
  était bornée à `100dvh - 70px`, alors que le haut de l'arbre est en réalité plus bas
  (barre du haut + bloc d'outils, ~130 px avec eldy) : les dernières entrées restaient
  sous l'écran sans rien à faire défiler. La hauteur est maintenant mesurée par le JS
  (écran moins position réelle de l'arbre) et recalculée au redimensionnement, à la
  rotation et à l'ouverture ou la fermeture du tiroir.
- **Tablette (768 px et plus) : le menu n'est plus affiché en permanence alors que le thème est
  encore en mode tiroir.** Le passage au tiroir dépend du thème (eldy : nombre d'entrées du
  menu du haut x 47 px + 130 px), pas d'un seuil fixe à 768 px. Les règles « ordinateur » et
  « tiroir » ne reposent plus sur une media query mais sur la classe `fsb-drawer` posée par le
  JS : thème en mode hamburger, ou écran de 767 px ou moins. L'arbre est limité à 95 % de la
  largeur dans le tiroir, sinon il dépassait.
- **Une seule largeur, portée par `.side-nav`.** `#id-left`, `div.vmenu` et l'arbre portaient chacun
  leur règle de largeur et se désynchronisaient (cellule de tableau plus étroite que la
  colonne, arbre qui s'élargissait). Ils suivent maintenant `.side-nav` (100 %, bloc,
  `border-box`) et, comme `.side-nav` lui-même, en `!important` pour ne pas dépendre de
  l'ordre de chargement des CSS du thème et des autres modules. Thèmes eldy et bootstrap5.
- **Recherche en pilule : largeur adaptée à l'écran (180 à 300 px) au lieu des 370 px du thème.**
  `#topmenu-global-search-dropdown .dropdown-menu` impose `width: 370px` ; avec les icônes, le bloc
  d'outils dépassait 650 px et ne laissait que quelques centaines de pixels au fil d'Ariane.
- **Barre du haut : la marge de droite réservée par le thème est supprimée** (menu du haut masqué,
  hors tiroir). Elle valait 180 à 385 px quel que soit le bloc d'outils. Le fil d'Ariane est limité
  à la place libre mesurée à l'écran entre son bord gauche et le bloc d'outils (qu'il soit posé par-dessus la
  barre comme avec eldy, ou à côté dans une ligne flex comme avec bootstrap5).
- **Colonne de largeur fixe avec le thème bootstrap5 sur ordinateur** (300 px) : la
  colonne prenait la largeur de son contenu et s'élargissait à chaque ouverture d'une
  branche aux libellés plus longs. Les libellés passent à la ligne.

## 1.5.1 — septembre 2026 — préparation Dolistore

- **Le CSS modifiait le menu eldy natif quand l'arbre n'était pas rendu.** La feuille de
  style est chargée sur toutes les pages dès que le module est actif : avec le
  gestionnaire désactivé dans le setup, pour les utilisateurs externes ou en mode
  `MAIN_MENU_INVERT`, la colonne `.side-nav` devenait quand même sticky, `#id-container`
  passait en tableau et le tiroir mobile était forcé à 280 px. Ces règles sont désormais
  limitées à `html.fsb-on`, classe posée par le JS dès que l'arbre apparaît dans le DOM.
- **Le MutationObserver ne s'arrêtait jamais sur une page sans arbre** (popup, menu
  gauche masqué, utilisateur externe, gestionnaire désactivé) et observait toutes les
  mutations du DOM pendant toute la vie de la page. Il est déconnecté au
  `DOMContentLoaded` dans tous les cas.
- **Mémorisation des branches ouvertes par utilisateur et par instance.** La clé
  localStorage unique était partagée entre les utilisateurs d'un même poste et entre deux
  instances Dolibarr servies sur le même domaine. Elle inclut désormais la racine d'URL et
  l'id utilisateur ; l'ancienne clé est supprimée au premier chargement.
- Recherche en pilule de la barre du haut : couleurs reprises des variables du thème
  (`--inputbackgroundcolor`, `--inputbordercolor`, `--colortext`, `--colorbackbody`) au
  lieu de valeurs en dur, pour suivre le mode sombre.
- **Barre du haut à hauteur fixe de 55 px** quand ses entrées sont masquées
  (`FULLSIDEBAR_HIDE_TOPMENU`) : sans elles, la barre se réduisait à la hauteur du burger
  et de la recherche, et ne correspondait plus au décalage de 50 px de la colonne sticky.
- **Liste de la recherche globale alignée sur la création rapide** : la règle qui forçait
  la couleur du texte sur tous ses descendants (deux id, donc plus forte que le thème)
  effaçait la couleur des icônes et le survol natif de `.dropdown-item`. Elle est
  supprimée ; la flèche avant chaque élément est retirée et l'espacement repris de la
  liste de création rapide.
- **Le fil d'Ariane commence toujours par Accueil** (libellé et lien de l'entrée Accueil
  de l'arbre), sauf quand la page affichée est déjà dans le module Accueil.
- **Clic sur un élément de la recherche globale inopérant sous Firefox macOS et Safari** :
  la liste n'était affichée que sur `:focus-within`, or ces navigateurs ne donnent pas le
  focus à un `<button>` cliqué — la liste disparaissait à l'appui de la souris, avant le
  clic. Elle reste désormais affichée tant qu'elle est survolée. Survol et focus clavier
  explicites, aux couleurs du survol natif des listes déroulantes.
- **Barre latérale vide avec le thème md.** Les règles de colonne (sticky, tiroir
  mobile élargi) étaient écrites pour eldy. Dans md, `.side-nav` est en position fixe et
  `#id-container` est un tableau en `table-layout: fixed` : rendue sticky, la colonne
  revenait dans le flux et le tableau la réduisait à 0 px. Ces règles sont désormais
  limitées à eldy (`html.fsb-theme-eldy`, posé par le JS depuis `data-fsb-theme`) ; md
  garde sa propre colonne fixe, et le contenu (`#id-right`) ne passe plus par-dessus.
  La hauteur fixe de la barre du haut est elle aussi réservée à eldy.
- **HTML de l'arbre allégé d'environ un tiers** (banc d'essai sur 580 entrées :
  308 Ko → 206 Ko, rendu identique). L'arbre contient toutes les entrées de tous les
  modules et part avec chaque page. Balisage compact (ni indentation ni sauts de ligne),
  plus d'attribut `title` répétant le libellé, bouton d'expansion nommé par
  `aria-labelledby` au lieu d'une copie traduite du libellé (clé `FullSidebarToggle`
  supprimée), point et flèche dessinés en CSS au lieu d'un `<span>` par ligne,
  `style=""` vides retirés des icônes. `data-fsb-key` porte un crc32 en base 36 de la
  clé complète (qui répétait le chemin de tous les parents) ; la racine porte
  `data-fsb-mm` pour le repérage du module Accueil. Clé localStorage passée en `v2`,
  l'ancienne est supprimée : les branches ouvertes sont oubliées une fois.
- **Diagnostic de coût pour les administrateurs** : un commentaire HTML après l'arbre
  donne le nombre d'entrées, le temps de construction, le temps d'affichage et la taille.
- Tests : unicité et stabilité des clés courtes, boutons reliés à un libellé existant.
- Page de configuration : chargement de `main.inc.php` par la séquence du modèle
  ModuleBuilder (racine web, puis chemins relatifs `../../` et `../../../`), exigée par
  le validateur Dolistore à la place de la boucle sur les dossiers parents.
- `need_dolibarr_version` porté à 23.0, seule version réellement testée.
- Packaging : fichier `COPYING` (GPL v3) ajouté, README anglais (`README.md`) et
  français (`README.fr.md`), dossier `sql/` supprimé (script MySQL uniquement, préfixe
  en dur, inutile puisque l'activation pose la constante), références à la documentation
  interne et liens vers des lignes du cœur retirés, indentation en tabulations.

## 1.5.0 — septembre 2026 — la colonne suit le défilement

- **Le menu ne suivait pas le défilement sur une longue page.** Le `position: sticky`
  était posé sur `.fsb`, dont le parent `div.vmenu` ne fait que l'envelopper — même
  hauteur, donc **zéro marge pour glisser** : un sticky sans marge ne fait rien. Il est
  déplacé sur `.side-nav`, l'élément que les deux thèmes rendent sticky sous leur propre
  option « menu gauche fixe » (`THEME_STICKY_TOPMENU = scrollleftmenu_after_mainpage`) :
  les règles émises sont celles de cette option, reprises telles quelles (`top: 50px` =
  hauteur d'en-tête du thème). Le comportement ne dépend donc plus de ce réglage.
- **Les `min-height: 100vh` sur `.side-nav` et `#id-container` sont supprimés** : un
  élément sticky aussi haut que l'écran n'a aucune raison de bouger — ils sabotaient aussi
  l'option du thème quand elle était activée. La colonne est à la place **bornée à
  l'écran et défile en interne** (recherche, favoris et arbre ensemble) ; sinon un arbre
  plus haut que l'écran repoussait la colonne au-delà et ses dernières entrées devenaient
  inatteignables.
- **Mobile : le bas du tiroir était masqué** par les barres du navigateur — `100vh` ne les
  compte pas, `100dvh` oui (déclaré après le repli `vh`). Marge basse élargie et zone
  sûre des téléphones sans bouton (`env(safe-area-inset-bottom)`), `scroll-padding-bottom`
  pour que l'entrée courante amenée à l'écran ne s'y cale pas.
- **Défilement automatique vers la page courante** : il faisait `nav.scrollTop` — or sur
  bureau ce n'est plus l'arbre qui défile mais toute la colonne, donc il ne bougeait
  rien. Il cherche désormais l'ancêtre qui déborde réellement (`scrollParentOf`), et
  fonctionne donc sur bureau comme dans le tiroir mobile.

## 1.4.2 — septembre 2026

- **Les sous-menus d'un module disparaissaient quand l'utilisateur n'avait pas la
  permission du menu HAUT.** `isVisibleToUserType()` renvoie `2` — « visible mais grisé » —
  quand la condition `enabled` d'une entrée passe mais que sa condition `perms` échoue.
  `buildTree()` testait `=== 1` pour décider de construire la branche : le module restait
  dans la barre, **tous ses liens disparaissaient**. Le cœur fait l'inverse (eldy teste la
  valeur pour sa véracité, donc `2` conserve son sous-arbre). Corrigé en `!== 0`.
  Construire les enfants d'un parent grisé est sans risque : chacun porte son propre
  `enabled` et reste filtré individuellement par `nestFlatList()` — seuls remontent ceux
  que l'utilisateur a le droit de voir. L'expander est déjà rendu dès qu'il y a des
  enfants, ils sont donc atteignables même sous un parent non cliquable.
  Cas réel : un module dont le menu haut exige `read` alors que l'opérateur n'a que
  `emballage` — il ne voyait plus aucune de ses pages.
- Test de non-régression sur la sémantique `enabled = 2`.

## 1.4.1 — septembre 2026 — relecture du 1.4.0

- **Le fil d'Ariane ne s'affichait jamais** : il ciblait `header#id-top div.tmenu`, qui
  n'existe pas — le cœur imprime `<div class="tmenudiv"><ul class="tmenu">`. Le conteneur
  est maintenant `.tmenudiv` (`div.tmenu` gardé en repli), et la règle flex du haut de
  page vise le même élément.
- **Détection du module depuis l'URL** : `location.pathname.split('/')[1]` renvoyait
  `htdocs` sur une install servie sous `/htdocs` → tous les liens de module
  contenaient `/htdocs/` et la détection retournait toujours le premier module (Accueil).
  Le serveur expose `DOL_URL_ROOT` (`data-fsb-urlroot`), le JS le retire du chemin, et
  les modules externes sous `/custom/<module>/` sont reconnus. Même correction pour la
  détection de la page d'accueil, qui prenait tout `index.php` de module pour l'accueil.
- **Démarrage anticipé sur arbre incomplet** : l'anti-flash démarrait dès le **premier**
  `.fsb-item` parsé → restauration et mesures faites sur un arbre à moitié construit.
  Une sentinelle `#fsb-end` est imprimée après `</nav>` ; le JS ne démarre qu'à sa
  présence.
- Condition « menu du haut masqué » lue depuis le serveur (`data-fsb-hidetop`) au lieu
  d'un test de style calculé sur la première entrée, faux pour un utilisateur sans
  aucune entrée de menu haut.
- Libellé ARIA du fil d'Ariane traduit (`FullSidebarBreadcrumb`) au lieu d'un français
  en dur ; couleurs du fil d'Ariane via `--colortextbackhmenu` (lisible quel que soit le
  fond de header du thème, mode sombre compris).
- Test du repli `leftmenu` aligné sur la règle 1.4.0 (limité au module courant) + contre-
  test depuis un autre module. 18 vérifications.

## 1.4.0 — septembre 2026

- Fil d'Ariane dans la barre du haut libérée par `FULLSIDEBAR_HIDE_TOPMENU`, construit
  côté client depuis le chemin actif de l'arbre ; sur une fiche atteinte sans
  `mainmenu` (recherche globale), le module est déduit de l'URL et le titre de la page
  ajouté en dernier maillon.
- Recherche globale promue en champ visible (pilule) dans la barre du haut, résultats
  en liste déroulante au focus.
- Repli `leftmenu` de session limité au module courant (un code comme `setup` existe
  dans plusieurs modules).
- Tiroir mobile élargi à 280 px.

## 1.3.0 — septembre 2026 — revue complète

- **Robustesse : un module dont le menu gauche plante ne casse plus la page.** Un
  gestionnaire de menu tourne sur *toutes* les pages ; `buildBranch()` est désormais
  isolé en `try/catch(\Throwable)` par module (hook cassé, requête de dictionnaire en
  échec, fichier de langue absent…) → le module apparaît sans sous-menu et l'erreur part
  dans le syslog, au lieu d'un écran blanc général.
- **Notices PHP évitées** sur les entrées ajoutées par le hook `menuLeftMenuItems` qui
  omettent `url`/`enabled` (clés que `Menu::add()` pose toujours mais qu'un hook peut
  oublier), et sur un paramètre de requête tableau (`search_options[]`) dans la
  détection de la page active (`is_scalar`).
- **Clé de mémorisation basée sur l'URL, plus sur le libellé.** Les libellés changent
  avec la langue de l'interface et portent des accents ; l'URL non. Les branches
  ouvertes survivent donc à un changement de langue. Unicité garantie structurellement
  (suffixe `~2`, `~3` en cas d'URL dupliquée sous un même parent), déterministe d'une
  page à l'autre.
- **Mode sombre** : la couleur d'accent retombe sur `--colortextlink` (adaptée par le
  thème dans les deux modes) et non plus sur `--colorbackhmenu1`, fond du header quasi
  noir en sombre qui faisait disparaître l'entrée active et les icônes de modules.
- **Mobile** : le tiroir est borné à la hauteur de l'écran et défile seul (une liste de
  200 entrées ne l'allonge plus au-delà du viewport) ; lignes à 10 px de padding
  vertical et bouton d'expansion à 40 px pour le doigt.
- `dolPrintHTMLForAttribute()` remplacé par `dol_escape_htmltag(dol_string_nohtmltag())`
  pour les attributs `title` (disponible sur toutes les versions supportées, et une
  infobulle sans balise est de toute façon le comportement voulu) ; `getNonce()` gardé
  par `function_exists`. `need_dolibarr_version` porté à 18.0 (`GETPOSTINT`).
- Suite de tests autonome livrée dans `test/tree_test.php` (CLI uniquement, aucune base
  requise) : 17 vérifications sur l'imbrication, les URLs, la règle d'ouverture et les
  clés.

## 1.2.1 — septembre 2026

- **Chargement des menus deux fois moins coûteux sur chaque page.** `loadMenu()` faisait
  deux passes `Menubase::menuLoad()` (contexte courant + `leftmenu='all'`), chacune
  évaluant `verifCond()` sur toutes les lignes de `llx_menu`. Or `loadMenu()` est aussi
  appelée par `theme/*/style.css.php` pour calculer `$nbtopmenuentries`, et cette
  feuille de style est *render-blocking* : la seconde passe y retardait le CSS de toute
  la page pour un résultat jamais utilisé.
  La passe large est désormais **paresseuse** (`loadMenuAll()`), déclenchée uniquement
  au moment où l'arbre est construit. Les globales `$mainmenu`/`$leftmenu` que
  `menuLoad()` laisse derrière elle sont sauvegardées et restaurées, puisque l'appel
  a maintenant lieu après l'exécution du code de la page.

## 1.2.0 — septembre 2026

- Section « Barre du haut » dans le setup, reprenant trois constantes **du cœur** qui
  remplissent la barre libérée par `FULLSIDEBAR_HIDE_TOPMENU` :
  `MAIN_USE_TOP_MENU_SEARCH_DROPDOWN` (recommandée : elle retire aussi le formulaire de
  recherche du haut de la barre latérale), `MAIN_USE_TOP_MENU_QUICKADD_DROPDOWN` et
  `MAIN_USE_TOP_MENU_IMPORT_FILE` — cette dernière n'ayant aucun écran de configuration
  dans Dolibarr.
- Ces trois constantes sont **volontairement absentes** de `$this->const` : elles
  appartiennent à Dolibarr (Configuration → Affichage). `insert_const()` ne peut donc
  jamais y réimposer une valeur du module lors d'un cycle désactiver/réactiver, et la
  désactivation du module ne les touche pas.
- `MAIN_USE_TOP_MENU_IMPORT_FILE` peut contenir une URL d'upload personnalisée à la
  place de `1` (`main.inc.php` teste `is_numeric`) : le setup la conserve et l'affiche
  au lieu de l'écraser par `1`.

## 1.1.0 — septembre 2026

- **Correction : liens de filtre inopérants.** `eldy.lib.php` écrit ses URLs de menu
  tantôt avec `&`, tantôt avec `&amp;`. L'échappement à l'affichage produisait alors
  `&amp;amp;`, et le navigateur lisait un paramètre nommé `amp;search_status` :
  cliquer « Brouillon », « Impayée », « Payée »… n'appliquait aucun filtre. Les
  séparateurs sont désormais normalisés avant construction du lien.
- **Correction : trop de branches dépliées.** Toute la branche du module courant
  s'ouvrait jusqu'aux feuilles. Seuls le module courant et le **chemin menant à la page
  affichée** sont dépliés ; sur une fiche (où aucune URL de menu ne correspond), le
  `leftmenu` de la session sert de repli pour ouvrir le bon groupe.
- Nouvelle option `FULLSIDEBAR_HIDE_TOPMENU` : masque les entrées du menu du haut,
  redondantes avec la barre latérale. La barre elle-même reste (logo, recherche, bloc
  utilisateur) et surtout le **hamburger**, seul moyen d'ouvrir la barre latérale sur
  mobile.
- JS : passe de cohérence garantissant qu'une branche ouverte a toujours ses ancêtres
  ouverts (un sous-arbre déplié ne peut plus rester invisible dans un parent replié).

## 1.0.0 — septembre 2026

Version initiale.

- Gestionnaire de menu `fullsidebar_menu.php` livré **dans le module**
  (`module_parts['menus']`) : aucun fichier du cœur de Dolibarr n'est modifié, et
  `main.inc.php` retombe seul sur `eldy_menu.php` si le module est désactivé.
- Menu gauche rendu en arbre pliable listant **tous les modules** et l'intégralité de
  leurs sous-menus, construit en réutilisant `print_eldy_menu()` /
  `print_left_eldy_menu()` (chargement `leftmenu='all'`), avec un rendu HTML moderne
  — et non le rendu jQuery Mobile d'`auguria_menu.php`, invisible hors thème Auguria.
- Hook `menuLeftMenuItems` et tri `positionfull` rejoués, que le cœur jette quand on
  l'appelle en `$noout = 1`.
- `mainmenu` du module racine forcé dans les liens qui n'en portent pas, pour que la
  navigation inter-modules bascule bien le contexte.
- Branche du module courant dépliée côté serveur, mémorisation des branches ouvertes
  par utilisateur (localStorage), surlignage de la page affichée.
- Menu du haut, `topnb` (dimensionnement du header par le thème) et `jmobile`
  inchangés côté comportement.
- Page de configuration (activation, tout déplier, mémorisation, entrées non
  autorisées) + SQL de pose manuelle de `MAIN_MENU_STANDARD`.
