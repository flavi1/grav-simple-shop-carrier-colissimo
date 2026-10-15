# Simple Shop Colissimo

Transporteur Colissimo pour le plugin Grav [Simple Shop](https://github.com/flavi1/grav-simple-shop) :

- modes **Colissimo domicile** et **Colissimo point de retrait** (`colissimo.domicile`, `colissimo.retrait`) ;
- prix tirés de la grille du contrat **Colissimo Facilité** (HT, `data/grid-facilite-2025.yaml`), modifiables dans
  Admin2 > Boutique > Transporteurs (forfait, tarif du plugin ou grille personnalisée ; marge, offre et contraintes calculées par
  Simple Shop) ;
- **points de retrait** par le service web La Poste `findRDVPointRetraitAcheminement`, proposés dans le tunnel **sans JavaScript** ;
- bouton « Tester la connexion » dans Boutique > Transporteurs.

## Installation

1. Dossier `user/plugins/simple-shop-carrier-colissimo/` (hébergement mutualisé : `/shared/grav-2/user/plugins/…`, déployé par
   GitHub Actions, voir `AGENTS.md`). Requiert `simple-shop`.
2. Activer le plugin dans la configuration du site (**désactivé par défaut**).
3. Points de retrait : un contrat professionnel **Colissimo Facilité** ou **Entreprise Privilège** est obligatoire. Saisir dans
   Plugins > Simple Shop Colissimo le numéro de compte et le mot de passe (ou la clé d'application) créés dans l'espace Colissimo Box,
   puis activer `pickup_enabled`.

## Configuration (`user/config/plugins/simple-shop-carrier-colissimo.yaml`)

| Clé | Défaut | Rôle |
|---|---|---|
| `enabled` | `false` | active le transporteur |
| `pickup_enabled` | `false` | propose les points de retrait |
| `account_number`, `password` | `''` | identifiants du service web (6 chiffres + mot de passe) |
| `api_key` | `''` | clé d'application (32 caractères), prioritaire sur le couple ci-dessus |
| `timeout` | `4` | délai maximal d'un appel (secondes) |
| `max_points` | `10` | nombre de points proposés au client |

Les identifiants ne doivent jamais être versionnés.

## Tests

`SS=../simple-shop YAML=<autoload symfony/yaml> php -d zend.assertions=1 -d assert.exception=1 tests/run.php` (réponses du service
enregistrées dans `tests/fixtures/`).
