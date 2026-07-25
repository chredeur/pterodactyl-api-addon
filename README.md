# Pterodactyl Application API addon

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
| `GET` | `/servers/{server}/mounts` | Liste les montages attachés |
| `POST` | `/servers/{server}/mounts` | Attache plusieurs montages (corps `{"mounts": [1,2]}`) |
| `POST` | `/servers/{server}/mounts/{mount}` | Attache un montage |
| `DELETE` | `/servers/{server}/mounts/{mount}` | Détache un montage |

`{server}` et `{mount}` sont les identifiants numériques (`id`), comme dans les routes
admin du panel.

### Exemples

```bash
PANEL=https://panel.example.com
KEY=ptla_xxxxxxxxxxxxxxxxxxxx

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
