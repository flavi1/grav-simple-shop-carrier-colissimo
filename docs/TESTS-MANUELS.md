# MANUAL_TESTS — À tester à la main (non testable depuis l'environnement de développement)

> Seuls `php -l`, `node --check` et des scripts en ligne de commande ont été exécutés. Rien
> de ce qui suit n'a été essayé dans un vrai Grav, un vrai Admin2 ni chez Stripe.
> Notez tout écart dans `TODO.md`. **Ne figure ici que ce qui reste à tester** : un point validé est retiré
> et consigné dans `DONE.md` (passes des 13/10/2026 dans Admin2 réel via Claude in Chrome).

> Extrait du site d'origine : seules les sections utiles à ce plugin sont gardées (numérotation d'origine).

## 11. Transporteurs : administration et Colissimo (lot du 2026-10-13)

1. **Clavier et lecteur d'écran** (onglets au clavier validés) : Tab dans les formulaires des modes ; annonces d'état lues par un lecteur d'écran (Orca, NVDA…).
2. **Erreurs** : provoquez une erreur serveur (ex. YAML invalide dans le mode avancé) : le message est une alerte (`role=alert`).
3. **Colissimo, identifiants réels** (le refus sans identifiants est validé) : Plugins > Simple Shop Colissimo, saisissez les identifiants, activez `pickup_enabled`,
   puis « Tester la connexion » (Transporteurs). Attendu : « Réussi ».
4. **Tunnel sans JavaScript** : choisissez « Colissimo point retrait » ; une liste de points apparaît, « autre ville » fonctionne, le point choisi figure aux étapes 3 et 4 et dans les e-mails.

## 12. Transporteurs simplifiés : forfait, tarif du plugin, grille repliée, CSV, contraintes globales (lot du 2026-10-14)

1. **Contraintes globales** : la section « Contraintes globales » montre, dans cet ordre :
   - « Loi Darcos (frais de livraison des livres) » : type de produits (blueprint) `book`, « Retrait à l'atelier » coché dans « Modes non concernés »,
     deux règles avec leur commentaire ;
   - « Livraison offerte à partir de 50 € » (exemple d'offre) : aucun type coché, une règle « Total TTC de 50 », « Prix imposé » 0,01.
   Plus d'encart « Plancher légal ».
   - Simulateur (340 g, 12,95 €, type `book` coché) : un forfait à 0 € affiche 3,00 € et, dans « Contraintes et remarques », « « Loi Darcos… » : 0,00 € → 3,00 € — commentaire ».
     Décochez `book` : la contrainte ne s'applique plus. À 60 € : 0,01 € pour tous les modes sauf le retrait.
   - « Monter » / « Descendre » changent l'ordre (enregistrez puis remettez l'ordre d'origine).
   - Une règle sans valeur, ou « Total TTC de » supérieur à « jusqu'à », est refusée avec un message.
2. **Forfait** : sur un mode, choisissez « Forfait », saisissez 5, enregistrez : la marge disparaît du formulaire ; simulateur 5,28 € TTC (5 × 1,055) pour tout poids.
3. **Tarif du plugin** (Colissimo domicile) : résumé « Grille du plugin : 33 lignes, de 6,72 € à 36,39 € HT… », grille visible en lecture seule sous « Voir la grille du plugin ».
4. **Personnaliser cette grille** : bascule en « Grille personnalisée » avec la grille du plugin pré-remplie et repliable ; colonnes panier / épaisseur masquées
   sauf si cochées ou utilisées. Enregistrer, puis « Rétablir les valeurs du plugin » revient au tarif du plugin.
5. **CSV** : « Exporter en CSV » s'ouvre correctement dans LibreOffice / Excel (accents, colonnes, virgule décimale). Modifiez un prix,
   « Importer un CSV… » ce fichier : « N lignes importées », enregistrez. Un fichier avec une zone ou un prix invalide est refusé avec le numéro de ligne.
6. **Tunnel** : avec un forfait à 0 € comme seul mode actif, l'étape livraison propose 3,00 € (livres < 35 €) ou 0,01 € (≥ 35 €), par la contrainte « Loi Darcos ».
