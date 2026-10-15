# Historique — ce qui a été délibérément fait (simple-shop-carrier-colissimo)

> Repris le 15/10/2026 de `private/prompts/DONE.md` du site d'origine (senonevero.flavieng.com), en ne gardant que ce qui concerne
> ce plugin. « Preuve » = fichier, test ou procédure qui le montre. Les numéros (A1, T4, O6, Q9…) sont ceux des anciens fichiers de
> suivi du site ; ils ne renvoient à rien dans ce dépôt mais sont gardés pour pouvoir retrouver une discussion.

| Point | État | Preuve |
|---|---|---|
| Tarifs 2026 | Oui (à confirmer) | lettre suivie (grille lettre verte + 0,50 €) et Colissimo domicile ramené en HT ; Chronopost retiré (tarifs d'exemple) ; retrait atelier désactivé ; démo « livraison symbolique dès 35 € » activée |
| Recherche tarifs La Poste (2026-10-13) | Oui | lettre verte disponible pour les pros (mêmes prix nets) ; suivi inclus dans Colissimo, +0,50 € pour la lettre verte ; grille HT Facilité 2025 mise dans `carriers.yaml` (Colissimo domicile) ; détails dans la conception §5.2 |
| Contrat fournisseur + événement `onSimpleShopRegisterCarriers` | Fait | `Delivery/CarrierProviderInterface.php`, `CarrierCatalog`, `ShippingManager` |
| Plugin `simple-shop-carrier-colissimo` (grille Facilité HT 2025, points de retrait) | Fait, testé sur fixtures | `tests/run.php` ; service réel non testé (pas d'identifiants) |
| Point de retrait dans le tunnel (sans JavaScript), e-mails et récapitulatifs | Fait | `CartController::step2Delivery`, templates `step2/3/4`, `pickup_point` |
| Administration des transporteurs (Admin2) : modes, marge, offert dès, grille, plancher, simulateur, test de connexion, YAML avancé | Fait | `admin-next/pages/simple-shop.js`, API `PUT/DELETE /carriers/{key}`, `POST /carriers/simulate` |
| Test de connexion Colissimo sans identifiants | Validé | message « Identifiants incomplets… » (§11.5) ; reste le test avec de vrais identifiants |
| Configuration Colissimo : encadré « contrat Colissimo Facilité ou Entreprise Privilège obligatoire » en tête du formulaire | Fait | `simple-shop-carrier-colissimo/blueprints.yaml` (champ `display`) |
