# Guide du dépôt — plugin Grav `simple-shop-carrier-colissimo`

> Ce fichier est écrit pour l'agent (Claude) et pour le propriétaire. Il doit permettre de travailler sur ce plugin
> **sans accès immédiat aux autres dépôts** : contexte, décisions, historique et travail restant y sont recopiés.
> Mettre à jour les sections « À faire » et « Historique » à chaque lot de travail.

## Communication
- S'adresser au propriétaire en **français** ; code, commentaires et messages du plugin en français.
- Ne jamais reproduire de secret (clés Stripe, identifiants Colissimo, mots de passe SMTP, sel Grav). Ce dépôt est **public** :
  aucune clé, même de test, ne doit y entrer (configuration réelle : `user/config/plugins/simple-shop-carrier-colissimo.yaml` du site, jamais versionnée).
- Ne pas supprimer de fichiers à la place du propriétaire : les lister dans « À supprimer par le propriétaire » ci-dessous.

## Rôle du plugin

Transporteur Colissimo pour `simple-shop` (fournisseur déclaré par l'événement `onSimpleShopRegisterCarriers`, contrat
`Delivery\CarrierProviderInterface` de simple-shop) :

- modes `colissimo.domicile` et `colissimo.retrait` ; prix tirés de la **grille du contrat Colissimo Facilité HT 2025**
  (`data/grid-facilite-2025.yaml`), surchargeable dans Admin2 > Boutique > Transporteurs (forfait, tarif du plugin ou grille
  personnalisée, marge, offre, contraintes : tout est calculé par simple-shop) ;
- **points de retrait** : service web La Poste `findRDVPointRetraitAcheminement` (`classes/PickupPointClient.php`, appel GET,
  identifiants `account_number` + `password` ou `api_key`), sous-étape **sans JavaScript** dans le tunnel ; points fermés ou trop
  légers écartés, tri par distance, horaires lisibles ; erreurs → `CarrierUnavailable` (le mode n'est alors pas proposé) ;
- test de connexion depuis Admin2 (Boutique > Transporteurs).

**Contrat requis** : le service des points de retrait ne fonctionne qu'avec un contrat professionnel **Colissimo Facilité** ou
**Entreprise Privilège** (identifiants créés dans l'espace Colissimo Box). Identifiants : configuration du site seulement
(`user/config/plugins/simple-shop-carrier-colissimo.yaml`), **jamais dans ce dépôt public**.

Décisions du propriétaire : compte professionnel Colissimo « Facilité » ; pas d'agrégateur (Boxtal, Sendcloud…) ; points de retrait
activables/désactivables ; pas d'options (signature, assurance) : tout passe par la marge de simple-shop ; aucune API de tarifs n'existe,
la mise à jour annuelle des grilles est l'affaire du propriétaire (nouvelle grille livrée dans ce plugin, ou CSV dans Admin2) ;
La Poste est inaccessible par moments depuis l'environnement de développement : appliquer les valeurs de départ et noter la question.

## Famille de plugins

Extraits le 15/10/2026 du dépôt du site `senonevero.flavieng.com` (`private/grav/plugins/<nom>`), historique Git conservé
(commits ne touchant que ce dossier ; clés Stripe de test présentes dans d'anciennes versions remplacées par `CLE-STRIPE-RETIREE`).

| Dossier du plugin | Dépôt | Rôle |
|---|---|---|
| `mvc` | [flavi1/grav-mvc](https://github.com/flavi1/grav-mvc) | routes → contrôleurs (`Request`, `Response`, `AbstractController`) ; requis par simple-shop |
| `simple-shop` | [flavi1/grav-simple-shop](https://github.com/flavi1/grav-simple-shop) | boutique : panier, tunnel sans JavaScript, OTP, livraison, taxes, commandes, factures Factur-X, API Admin2 |
| `simple-shop-carrier-colissimo` **(ce dépôt)** | [flavi1/grav-simple-shop-carrier-colissimo](https://github.com/flavi1/grav-simple-shop-carrier-colissimo) | transporteur Colissimo (grille Facilité, points de retrait) |
| `simple-shop-payment-stripe` | [flavi1/grav-simple-shop-payment-stripe](https://github.com/flavi1/grav-simple-shop-payment-stripe) | paiement Stripe (formulaire intégré ou page hébergée), webhook |
| `simple-shop-payment-test` | [flavi1/grav-simple-shop-payment-test](https://github.com/flavi1/grav-simple-shop-payment-test) | passerelle de paiement simulée (sites de test) |
| — | [flavi1/senonevero.flavieng.com](https://github.com/flavi1/senonevero.flavieng.com) (privé) | site d'origine : thème `bookstore` (type de produit `book`), configuration, pages |

## Installation sur le serveur (hébergement mutualisé multi-domaines)

- Grav 2.0 est installé **une fois** dans `/shared/grav-2/` et sert plusieurs domaines (`/domains/<domaine>/private/grav` = dossier
  `user://` de chaque site ; routeurs hors de ces dépôts).
- Ce plugin est déployé dans **`/shared/grav-2/user/plugins/simple-shop-carrier-colissimo/`** (chemin absolu chez OVH : `/home/<identifiant>/shared/…`), partagé par tous les domaines.
- Grav cherche un plugin d'abord dans le dossier du site (`user://plugins`, soit `/domains/<domaine>/private/grav/plugins/simple-shop-carrier-colissimo`),
  **puis** dans le dossier partagé. Une copie restée dans un site masque donc la version partagée : la supprimer de ce site
  pour qu'il utilise la version partagée.
- **Désactivé par défaut**, comme tous les plugins `mvc` et `simple-shop-*` (`enabled: false` dans `simple-shop-carrier-colissimo.yaml`), puisque le dossier est commun à tous les domaines : chaque site
  l'active dans sa propre configuration (`user/config/plugins/simple-shop-carrier-colissimo.yaml` : `enabled: true`, ou Admin2 > Plugins).
- Fichiers publics : le routeur de domaine ne sert depuis `/user/plugins/…` que les ressources statiques (CSS, JS, images, polices) ;
  `.php`, `.yaml`, `.md`, `.json`, `.lock`… répondent 403.

## Déploiement (GitHub Actions → rsync sur SSH)

Chaque push sur `main` lance `.github/workflows/deploy.yml` (même principe que « Deploy to OVH via rsync/ssh » du site) :
1. **Contrôle** : `php -l` sur tous les fichiers PHP (hors bibliothèques tierces).
2. **Envoi** : `rsync --delete` sur SSH (mot de passe via `sshpass`) vers `/home/<identifiant>/shared/grav-2/user/plugins/simple-shop-carrier-colissimo/`.
   Le dossier du plugin devient l'image exacte du dépôt (un fichier supprimé du dépôt disparaît du serveur), sauf `.git*`,
   `.github/`, `tests/`, `docs/`, `AGENTS.md`, jamais envoyés. Rien n'est touché en dehors de `…/plugins/simple-shop-carrier-colissimo/`.
3. Relance à la main : onglet *Actions* → *Déploiement (rsync/ssh)* → *Run workflow* (ou *Re-run all jobs* d'une exécution).

**Mise en place (une fois par dépôt)** — GitHub → dépôt `flavi1/grav-simple-shop-carrier-colissimo` → *Settings* → *Secrets and variables* → *Actions*.
Les noms gardent le préfixe `FTP_` (premier essai en FTP) mais ce sont les identifiants de l'hébergement, valables aussi en SSH :
- *Secrets* : `FTP_SERVER` (hôte, ex. `ftp.clusterNNN.hosting.ovh.net` ; le workflow essaie aussi `ssh.clusterNNN.hosting.ovh.net`),
  `FTP_USERNAME` (identifiant de l'hébergement), `FTP_PASSWORD`.
- *Variables* (facultatives) :
  - `FTP_PLUGINS_DIR` : chemin **absolu** du dossier des plugins partagés, avec ou sans `/` final ; par défaut
    `/home/<FTP_USERNAME>/shared/grav-2/user/plugins` (chemin d'un hébergement OVH) ;
  - `SSH_HOST` : hôte SSH s'il diffère des deux ci-dessus ; `SSH_PORT` (`22`).
- SSH doit être ouvert sur l'hébergement (offre OVH Pro ou supérieure : c'est déjà le cas pour le déploiement du site).

Vérification : le journal de l'étape « Envoi » liste les fichiers transmis ; `simple-shop-carrier-colissimo.php` doit être présent dans le dossier.

## Environnement de développement

Les tests se lancent dans une copie du site d'origine, qui fournit le thème `bookstore` (type de produit `book`, utilisé par les
tests du tunnel) et les plugins voisins :

```
git clone https://github.com/flavi1/senonevero.flavieng.com site          # dépôt privé du site
cd site/private/grav/plugins
git clone https://github.com/flavi1/grav-mvc mvc
git clone https://github.com/flavi1/grav-simple-shop simple-shop
git clone https://github.com/flavi1/grav-simple-shop-carrier-colissimo simple-shop-carrier-colissimo
git clone https://github.com/flavi1/grav-simple-shop-payment-test simple-shop-payment-test
git clone https://github.com/flavi1/grav-simple-shop-payment-stripe simple-shop-payment-stripe
```

(ces dossiers sont ignorés par le dépôt du site). `symfony/yaml` est nécessaire : `composer require symfony/yaml` dans un dossier
temporaire, ou, sans accès à Packagist, `git clone -b 7.3 https://github.com/symfony/yaml` + un petit autoloader PSR-4
(`Symfony\Component\Yaml\` → dossier cloné) ; la variable `YAML` pointe vers cet autoload.

## Tests en ligne de commande
Disposition de développement ci-dessus (simple-shop voisin), puis depuis ce dossier :

```
SS=../simple-shop YAML=<autoload symfony/yaml> php -d zend.assertions=1 -d assert.exception=1 tests/run.php
```

Affiche `OK`. Le service réel n'est pas appelé : réponses enregistrées dans `tests/fixtures/` (point trouvé, aucun point, erreur
d'authentification), y compris une tentative XXE.

## Conventions (reprises du site d'origine)
- Fonctionnement **sans JavaScript** côté client public (hors option Stripe « formulaire intégré ») ; JavaScript admis côté Admin2.
- Un contrôleur retourne toujours une `Response` ; pas de `exit`, pas d'accès direct à `$_POST`/`$_GET`/`$_SERVER` (passer par `Request`).
- Écriture de fichiers partagés : verrou + fichier temporaire + `rename`.
- Pas de `innerHTML` avec des données dans le JavaScript.
- Tester en ligne de commande ce qui peut l'être (`php -l`, scripts de `tests/`) ; signaler explicitement ce qui n'est testable
  qu'après déploiement (Admin2, Stripe réel, service Colissimo réel).
- Commits distincts par étape, poussés directement sur `main` (le déploiement suit).

## À faire
- [ ] **Brancher le service réel** (en dernier, sur le site) : saisir `account_number` + `password` (ou `api_key`) dans Plugins >
  Simple Shop Colissimo, activer `pickup_enabled`, « Tester la connexion » dans Boutique > Transporteurs (attendu : « Réussi »), puis
  parcourir le tunnel sans JavaScript avec « Colissimo point retrait » (liste de points, « autre ville », point repris aux étapes 3 et 4
  et dans les e-mails) — `docs/TESTS-MANUELS.md` §11.
- [ ] **Grille 2026** : remplacer la grille Facilité 2025 par celle de l'espace Colissimo Entreprise (question Q10/Q11 du site :
  services web inclus dans le contrat Facilité ? grilles HT ou TTC ?). Ajouter `data/grid-facilite-2026.yaml` plutôt que modifier l'ancienne.
- [ ] **Tarifs 2027** : nouvelles grilles La Poste au 1er janvier 2027.

## Historique
- `git log` : commits d'origine (messages en français, un par étape).
- `docs/HISTORIQUE.md` : ce qui a été **délibérément** fait pour ce plugin (repris de `DONE.md` du site), avec les preuves.
- `docs/CONCEPTION-TRANSPORTEURS.md`, `docs/AUDIT-TRANSPORTEURS.md` : conception validée (contrat du cœur, plugin Colissimo, administration), recherche des tarifs La Poste.
- `docs/TESTS-MANUELS.md` : §11 et §12 du site (transporteurs).
