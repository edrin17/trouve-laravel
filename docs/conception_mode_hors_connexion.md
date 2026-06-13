# Conception — Mode hors connexion et synchronisation

> Document de conception validé avant implémentation. Définit la stratégie de
> synchronisation hors-ligne et de résolution de conflits pour Trouve.
> Rédigé le 2026-06-13.

## 1. Contexte et contraintes

- **3 utilisateurs maximum**, inventaire **partagé** (pas de cloisonnement par
  utilisateur — cf. décision d'auth).
- Données : `houses`, `items` (hiérarchiques, auto-référençants via `parent_id`),
  `tags` (vocabulaire global), pivot `item_tag`.
- Conflits **rares** : il est peu probable que deux personnes modifient le même
  objet pendant la même fenêtre de déconnexion.
- Profil développeur : PHP/Laravel, JS non maîtrisé → privilégier des concepts
  proches du verrouillage optimiste ORM classique, éviter CRDT/OT.

## 2. Stratégie retenue (vue d'ensemble)

Combinaison de trois briques, validées :

1. **Journal d'opérations côté client** — les modifications faites hors-ligne
   sont enfilées localement et rejouées à la reconnexion.
2. **Versioning optimiste côté serveur** — chaque objet porte un numéro de
   version ; le serveur détecte qu'un objet a changé entretemps et **rejette**
   l'opération au lieu d'écraser.
3. **Politique « garder les deux »** — en cas de vrai conflit, aucune perte :
   la version locale rejetée est **dupliquée** dans l'arbre, marquée
   « (conflit) », et l'utilisateur tranche.

### Décisions structurantes (validées)

| Décision | Choix retenu |
|----------|--------------|
| Granularité des conflits | **Au niveau objet** (une version par item/maison) |
| Matérialisation « garder les deux » | **Duplication** de l'objet en conflit dans l'arbre |
| Couverture du journal | **Tout le CRUD métier** (create/update/delete, déplacement, tags, images) |

## 3. Modèle de données

### 3.1 Versioning optimiste (côté serveur)

Ajout sur les tables versionnées (`items`, `houses`) :

```
version        INTEGER NOT NULL DEFAULT 1   -- incrémenté à chaque update serveur
uuid           CHAR(36) UNIQUE              -- identité stable indépendante de l'auto-increment
updated_by     ... (optionnel, traçabilité)
```

**Pourquoi un `uuid` en plus de l'`id` auto-increment ?**
Hors-ligne, un client crée des objets sans connaître l'`id` que le serveur
attribuera. L'`uuid` est généré côté client à la création, sert de clé stable
pour référencer l'objet dans le journal et lier parent/enfant **avant** la
première synchro. L'`id` reste la clé primaire SQL ; l'`uuid` est la clé
d'échange.

> Les `tags` (vocabulaire global, identifiés par leur `name` unique) ne sont pas
> versionnés objet par objet : ce sont des valeurs partagées. Une opération sur
> un tag d'un item est traitée comme une modification **de l'item** (qui, lui,
> est versionné). Renommer/supprimer un tag du vocabulaire reste une opération
> en ligne (rare, faite via l'écran de gestion).

### 3.2 Journal d'opérations (côté client)

Stocké localement dans le navigateur (IndexedDB — voir §7 sur la techno). Chaque
entrée :

```jsonc
{
  "op_id": "uuid",            // identifiant unique de l'opération (idempotence)
  "type": "create|update|delete|move|tag|image",
  "entite": "item|house",
  "uuid": "uuid-objet",       // cible (uuid stable, pas l'id SQL)
  "base_version": 7,          // version lue au moment de la modif (null si create)
  "payload": { ... },         // champs modifiés / nouvelles valeurs
  "horodatage": "2026-06-13T20:00:00Z",
  "etat": "en_attente|envoye|applique|conflit"
}
```

**Propriétés importantes :**
- `op_id` rend chaque opération **idempotente** : si la réponse réseau est
  perdue et l'op rejouée, le serveur reconnaît l'`op_id` déjà traité et ne
  l'applique pas deux fois.
- `base_version` est le cœur de la détection optimiste : c'est la version que le
  client croyait à jour quand il a fait la modif.
- Les opérations sont **ordonnées** (FIFO) et rejouées dans l'ordre.

### 3.3 Marquage des conflits

Pas de table dédiée (décision : duplication dans l'arbre). On ajoute sur
`items`/`houses` :

```
en_conflit     BOOLEAN NOT NULL DEFAULT 0   -- objet issu d'une résolution « garder les deux »
conflit_de     CHAR(36) NULL                -- uuid de l'objet dont il est la copie en conflit
```

Un objet dupliqué pour conflit est un objet normal (visible, éditable), juste
marqué. Cela évite une UI séparée et garde le principe « rien n'est caché ».

## 4. Algorithme de synchronisation

### 4.1 À la reconnexion (push du journal)

Pour chaque opération en attente, dans l'ordre :

```
1. Le client envoie { op_id, type, entite, uuid, base_version, payload }.
2. Le serveur vérifie l'idempotence : op_id déjà traité ? → renvoie le résultat
   mémorisé, passe à la suivante.
3. Selon le type :

   CREATE :
     - L'uuid n'existe pas encore côté serveur → créer, version = 1.
     - L'uuid existe déjà (rejeu / autre client) → traiter comme idempotent.

   UPDATE / MOVE / TAG / IMAGE :
     - Charger l'objet par uuid.
     - SI objet.version == base_version → appliquer, version++. ✅ OK
     - SI objet.version  > base_version → CONFLIT (qqn a modifié entretemps).
       → voir §5.
     - SI objet absent → l'objet a été supprimé entretemps → CONFLIT de type
       update-vs-delete (voir §5.3).

   DELETE :
     - SI objet.version == base_version → supprimer (CASCADE habituel).
     - SI objet.version  > base_version → CONFLIT delete-vs-update (voir §5.3).
     - SI objet absent → déjà supprimé → idempotent, OK.

4. Le serveur répond par op : { op_id, statut: applique|conflit, version,
   id_serveur, donnees_serveur? }.
```

### 4.2 Au pull (récupérer les changements des autres)

Le client garde un **curseur de synchro** (timestamp ou numéro de séquence du
dernier pull réussi). Au pull, il demande tout ce qui a changé depuis. Approche
simple à cette échelle : `GET /sync?depuis=<curseur>` renvoie les objets
(houses/items/pivot) modifiés après le curseur, plus le nouveau curseur.

> Pour 3 utilisateurs et un petit inventaire, un **full pull** périodique
> (renvoyer tout l'état) est même acceptable en première version. L'incrémental
> est une optimisation ultérieure.

### 4.3 Ordre push puis pull

À la reconnexion : **push d'abord** (envoyer mes modifs locales, récolter les
conflits), **pull ensuite** (intégrer les modifs des autres + les objets de
conflit créés). Cela garantit que l'état final côté client reflète la résolution.

## 5. Résolution de conflits — « garder les deux »

### 5.1 Principe

Quand le serveur détecte `objet.version > base_version` (vrai conflit
concurrent), il **ne tranche pas tout seul** et ne perd rien :

1. La **version du serveur reste la version « officielle »** de l'objet
   (elle a déjà été acceptée, possiblement vue par d'autres).
2. La **version locale rejetée** du client est **recréée comme un nouvel objet**,
   marqué `en_conflit = true`, `conflit_de = <uuid original>`, nom suffixé
   « (conflit — <utilisateur>) », rattaché au **même parent** que l'original.
3. L'utilisateur voit les deux objets côte à côte dans l'arbre et décide :
   fusionner manuellement, supprimer le doublon, ou garder les deux.

### 5.2 Qui crée le doublon ?

C'est le **serveur** qui matérialise le doublon lors du push (il a les deux
versions : l'officielle en base + le payload rejeté). Il renvoie au client l'op
avec `statut: conflit` et l'`uuid`/`id` du doublon créé, que le client intègre
au pull. Ainsi tous les clients convergent vers le même état (original + doublon).

### 5.3 Cas particuliers

| Situation | Traitement |
|-----------|------------|
| **update vs update** | Doublon de la version locale (cas nominal §5.1). |
| **update vs delete** (j'édite un objet qu'un autre a supprimé) | On **recrée** l'objet à partir du payload local, marqué conflit, rattaché à la racine de sa maison si le parent a disparu. On ne ressuscite pas l'original ; on matérialise l'intention locale. |
| **delete vs update** (je supprime un objet qu'un autre a modifié) | La modif de l'autre **gagne** (l'objet reste), ma suppression est **annulée** et signalée. Justification : ne jamais détruire une modif concurrente non vue. |
| **move créant un cycle** (mon déplacement local rendrait A enfant de son propre descendant après les déplacements d'un autre) | Rejet du move, l'objet reste à sa place serveur, opération signalée (garde-fou anti-cycle déjà présent dans `ItemService::estDescendant`). |

### 5.4 Tags (granularité objet)

Comme la granularité est l'objet : si deux personnes modifient les tags du même
item hors-ligne, c'est un conflit d'`update` sur l'item → doublon. À l'échelle de
3 utilisateurs c'est acceptable. (Une fusion ensembliste des tags serait possible
plus tard, mais on s'en tient à la règle simple et uniforme.)

## 6. Interface utilisateur de résolution

- Les objets `en_conflit` sont **mis en évidence** dans l'arbre (badge
  « ⚠ conflit » + couleur). Réutilise le composant `item-node` existant.
- Un **bandeau** en haut de l'inventaire annonce « N conflit(s) à résoudre » avec
  un filtre pour ne voir que ceux-ci.
- Pour chaque objet en conflit : actions **« Garder cette version »** (supprime
  l'autre), **« Garder les deux »** (lève juste le marquage), **« Fusionner »**
  (ouvre l'édition pour recopier à la main puis supprimer le doublon).
- Tant qu'un conflit n'est pas résolu, l'objet reste utilisable — on ne bloque
  jamais l'app.

## 7. Choix techniques (à trancher à l'implémentation)

- **Stockage local du journal + cache données** : IndexedDB (seul stockage
  navigateur adapté au volume et aux requêtes). ⚠️ C'est du JS — point d'attention
  vu le profil. Pistes pour limiter le JS manuel : une lib légère type **Dexie.js**
  (wrapper IndexedDB), ou s'appuyer sur ce qu'Alpine permet. À évaluer au moment
  de coder ; **hors périmètre de ce document de conception**.
- **Détection en ligne/hors-ligne** : `navigator.onLine` + heartbeat vers une
  route `/ping`.
- **Endpoints serveur** : `POST /sync/push` (liste d'opérations),
  `GET /sync/pull?depuis=` (delta). Versionnés, idempotents.
- **Migrations** : ajout `version`, `uuid`, `en_conflit`, `conflit_de` sur
  `items` et `houses` ; migration idempotente pour backfill des `uuid`/`version`
  des données existantes (règle projet CLAUDE.md #4).

## 8. Ce qui est explicitement hors périmètre (v1 du offline)

- Fusion automatique fine (CRDT/OT) — non, on reste sur « garder les deux ».
- Résolution automatique des conflits — non, l'humain tranche.
- Synchro temps réel / websockets — non, push/pull à la reconnexion suffit.
- Conflits sur le **vocabulaire de tags** global — géré en ligne uniquement.
- Pull incrémental optimisé — full pull acceptable en première version.

## 9. Ordre d'implémentation proposé

1. **Schéma** : migrations `uuid`/`version` sur items/houses (+ backfill), modèles.
2. **Versioning serveur** : incrément de version sur update, endpoints
   `POST /sync/push` + `GET /sync/pull` avec détection optimiste et tests.
3. **Matérialisation des conflits** : duplication serveur + champs `en_conflit`,
   tests des cas du §5.3.
4. **UI de résolution** : marquage dans `item-node`, bandeau, actions.
5. **Client offline** (le plus JS, en dernier) : journal IndexedDB, file FIFO,
   détection en ligne, rejeu à la reconnexion.

> Étapes 1–4 sont du Laravel pur (testables sans navigateur). L'étape 5, la plus
> exposée au JS, est isolée en fin de parcours pour livrer de la valeur
> testable au plus tôt.
