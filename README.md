# Pterodactyl Application API addon
[![Latest Version on Packagist](https://img.shields.io/packagist/v/chredeur/pterodactyl-api-addon.svg?style=flat-square)](https://packagist.org/packages/chredeur/pterodactyl-api-addon)
[![Total Downloads](https://img.shields.io/packagist/dt/chredeur/pterodactyl-api-addon.svg?style=flat-square)](https://packagist.org/packages/chredeur/pterodactyl-api-addon)

Ajoute à l'API applicative de Pterodactyl des endpoints que le panel n'expose pas :
le transfert de serveur entre nodes, et la gestion des montages d'un serveur.
L'implémentation réutilise les modèles, services et repositories du panel.

Version du panel ciblée : **1.14.1**.

## Installation

```bash
composer require chredeur/pterodactyl-api-addon
php artisan route:clear && php artisan config:clear
```

## Authentification

Les routes sont montées sous `/api/application` avec la pile de middleware standard du
panel (`api` + `application-api` + `throttle:api.application`). Elles exigent donc une
clé applicative appartenant à un compte administrateur, présentée en
`Authorization: Bearer ptla_...`, avec la permission adéquate sur la ressource `servers`
(lecture pour les `GET`, écriture pour les `POST` et `DELETE`).

## Endpoints

| Méthode | Route | Effet |
| --- | --- | --- |
| `POST` | `/servers/transfer` | Démarre un transfert de serveur vers un autre node |
| `GET` | `/mounts` | Liste les montages du panel |
| `GET` | `/mounts/{mount}` | Détaille un montage |
| `GET` | `/servers/{server}/mounts` | Liste les montages attachés à un serveur |
| `POST` | `/servers/{server}/mounts` | Attache plusieurs montages (corps `{"mounts": [1,2]}`) |
| `POST` | `/servers/{server}/mounts/{mount}` | Attache un montage |
| `DELETE` | `/servers/{server}/mounts/{mount}` | Détache un montage |
| `POST` | `/users/{user}/sso` | Génère un lien de connexion automatique |

`{server}` et `{mount}` sont les identifiants numériques (`id`), comme dans les routes
admin du panel.

`GET /mounts` accepte deux filtres facultatifs, `egg_id` et `node_id`. Fournis ensemble,
ils retournent les montages réellement attachables à un serveur bâti sur cet egg et ce
node — la même règle que celle appliquée à l'attache.

### Exemples

```bash
PANEL=https://panel.example.com
KEY=ptla_xxxxxxxxxxxxxxxxxxxx

# Lister les montages attachables sur un egg et un node donnés
curl "$PANEL/api/application/mounts?egg_id=5&node_id=2" \
  -H "Authorization: Bearer $KEY" -H "Accept: application/json"

# Attacher un montage
curl -X POST "$PANEL/api/application/servers/12/mounts/3" \
  -H "Authorization: Bearer $KEY" -H "Accept: application/json"

# Attacher plusieurs montages en un appel
curl -X POST "$PANEL/api/application/servers/12/mounts" \
  -H "Authorization: Bearer $KEY" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"mounts": [3, 7]}'

# Lister
curl "$PANEL/api/application/servers/12/mounts" \
  -H "Authorization: Bearer $KEY" -H "Accept: application/json"

# Détacher
curl -X DELETE "$PANEL/api/application/servers/12/mounts/3" \
  -H "Authorization: Bearer $KEY" -H "Accept: application/json"
```

Réponses : `200` avec la ressource `mount` transformée pour les attaches et la liste,
`204` pour le détachement.

### Comportement

- **Idempotence.** Ré-attacher un montage déjà présent ne crée pas de doublon et
  renvoie `200`. Détacher un montage absent renvoie `204`.
- **Attache en masse : tout ou rien.** Si un seul des montages demandés est refusé,
  aucun n'est attaché.
- **Éligibilité.** Un montage doit être associé au node et à l'egg du serveur, sinon
  `409` avec la liste des identifiants refusés. C'est le même filtre que celui qui
  alimente la liste déroulante de l'interface admin
  (`MountRepository::getMountListForServer`).
- **Erreurs.** `404` si le serveur ou le montage n'existe pas, `422` si le corps de
  l'attache en masse est invalide.

## Connexion automatique (SSO)

Permet à un système de facturation de déposer un client directement dans son serveur,
sans mot de passe. Deux routes : l'une délivre un jeton, l'autre le consomme.

```bash
curl -X POST "$PANEL/api/application/users/42/sso" \
  -H "Authorization: Bearer $KEY" -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"redirect": "/server/1a7ce997"}'
```

```json
{
  "object": "sso",
  "attributes": {
    "url": "https://panel.example.com/auth/sso/3f2a...",
    "expires_at": "2026-07-26T12:00:00+00:00"
  }
}
```

Il suffit ensuite de rediriger le navigateur du client vers cette URL. La session est
ouverte et il atterrit sur la page demandée.

Permission requise : écriture sur la ressource `users`. L'appel ne modifie pas le compte,
mais il délivre la capacité d'ouvrir une session dessus — ce n'est pas une lecture.

### Garde-fous

Le jeton fait 32 octets aléatoires, n'est stocké que haché, vaut **60 secondes** et ne
sert qu'une fois. Génère-le au moment du clic, pas au rendu de la page.

**Aucun jeton n'est délivré pour un compte avec double authentification.** Une session
ouverte par `loginUsingId()` ne traverse jamais le challenge TOTP, qui vit dans le flux de
connexion par mot de passe : délivrer un lien contournerait silencieusement une protection
que le titulaire a activée volontairement. Le compte passe par la page de connexion.

**Aucun jeton n'est délivré pour un compte administrateur.** Une clé applicative permet
déjà d'administrer le panel, mais pas d'ouvrir une session interactive d'administrateur.
Ce refus limite les dégâts d'une fuite de clé.

Les deux conditions sont revérifiées à la consommation du jeton, pas seulement à
l'émission : le compte peut avoir changé entre-temps.

**Toute session déjà ouverte sur le panel dans ce navigateur est fermée.** La route
appelle `logout()` puis invalide la session avant d'ouvrir la nouvelle. C'est délibéré :
un lien SSO doit déposer le visiteur sur le compte du lien, pas sur celui qui traînait
dans le navigateur. Conséquence pratique en phase de test — si tu es connecté en
administrateur sur le panel et que tu cliques sur un lien SSO client, tu perds ta session
d'administrateur. Utilise une fenêtre privée pour tester.

La connexion est inscrite au journal d'activité du compte sous l'évènement `auth:sso`,
visible par le titulaire dans son onglet Activity. La clé n'ayant pas de traduction dans
le panel, elle s'affiche telle quelle.

Le paramètre `redirect` est restreint à un chemin de ce panel. Un `//` en tête est refusé :
un navigateur y verrait une URL relative au protocole, ce qui ferait de la route une
redirection ouverte vers n'importe quel hôte.

En cas de refus — jeton expiré, déjà utilisé, compte inéligible — le visiteur est redirigé
vers `/auth/login`. La raison va dans les logs et non à l'écran : l'expliquer n'aiderait
qu'une personne en train de sonder la route.

## Prise en compte par Wings

Après chaque modification, l'addon appelle `DaemonServerRepository::sync()`
(`POST /api/servers/{uuid}/sync`) pour que Wings recharge la configuration du serveur,
qui contient la clé `mounts`. C'est le mécanisme utilisé par le panel lui-même dans
`BuildModificationService`.

Deux limites à connaître :

1. Sans ce sync, la ligne existe en base mais Wings conserve l'ancienne configuration
   en mémoire.
2. Même avec le sync, Docker n'applique un bind mount qu'à la création du conteneur.
   Le montage n'apparaît dans `/home/container` qu'après recréation : arrêt puis
   démarrage du serveur, ou réinstallation.

Un échec de synchronisation n'est pas fatal ; il est journalisé en `warning` et l'appel
aboutit, Wings récupérant de toute façon une configuration fraîche au démarrage.

**Point à revérifier après une mise à jour du panel.** C'est la partie la plus exposée
aux changements internes. Si le nom ou la signature de `DaemonServerRepository::sync()`,
ou la forme de la clé `mounts` dans `ServerConfigurationStructureService`, évoluent,
c'est `ServerMountApplicationController::syncWithDaemon()` qu'il faut adapter.

## Prérequis côté montage

Le montage doit exister dans `/admin/mounts` et y être associé au node et à l'egg du
serveur visé. Côté Wings, son chemin source doit figurer dans `allowed_mounts` du
`config.yml`, suivi d'un redémarrage de Wings.

## Classes du panel réutilisées

| Classe | Usage |
| --- | --- |
| `Api\Application\ApplicationApiController` | Contrôleur de base (Fractal, `returnNoContent()`) |
| `Api\Application\Servers\GetServerRequest`, `ServerWriteRequest` | ACL sur la ressource `servers` |
| `Models\Server`, `Models\Mount`, `Models\MountServer` | Modèles et table pivot `mount_server` |
| `Repositories\Eloquent\MountRepository` | Référence pour la validation d'éligibilité |
| `Repositories\Wings\DaemonServerRepository` | Synchronisation vers Wings |
| `Exceptions\DisplayException` | Rendu d'erreur au format JSONAPI du panel |
| `Transformers\Api\Application\BaseTransformer` | Base du `MountTransformer`, absent du panel |
| `Enum\JwtScope` | Scope `ServerTransfer` requis depuis le panel 1.12.3 |

## License

The MIT License (MIT). Voir [LICENSE.md](LICENSE.md).
