# Dimensionnement Serveur — Trouve-Laravel

> Document technique pour le déploiement du conteneur Docker (Laravel Sail) en production.
> **Contexte** : Application pour **3 utilisateurs maximum**, inventaire partagé, synchronisation hors-ligne.
> Rédigé le 2026-06-15.

---

## 1. Spécifications Fonctionnelles Impactant le Dimensionnement

| Caractéristique | Valeur | Impact sur les ressources |
|----------------|--------|---------------------------|
| Nombre d'utilisateurs | **3 max** | Faible charge concurrente |
| Base de données | SQLite | Pas de serveur SQL dédié |
| Stockage | Images (max 1024px) + fichiers | Espace disque principal |
| Trafic réseau | Synchro occasionnelle (push/pull) | Bande passante minime |
| Framework | Laravel 12 + Livewire | Mémoire PHP modérée |
| Conteneurisation | Laravel Sail (Docker) | Overhead conteneur léger |

---

## 2. Analyse des Composants du Conteneur

### 2.1 Stack Technique dans le Conteneur (Laravel Sail)
D'après `compose.yaml` et `Dockerfile` (implicite via Sail) :

```yaml
# Conteneurs Sail par défaut (si non customisé)
services:
  laravel.test:
    build:
      context: ./docker/8.3  # PHP 8.3
      dockerfile: Dockerfile
    image: sail-8.3/app
```

**Services actifs dans le conteneur unique** :
- **PHP-FPM** (8.3) + **Nginx** (reverse proxy intégré)
- **Queue Worker** (Laravel `queue:listen` pour les jobs)
- **Base SQLite** (fichier `database/database.sqlite`)

> ✅ **Pas de MySQL/Redis/PostgreSQL** → SQLite suffit pour 3 utilisateurs (cf. README).

---

### 2.2 Ressources par Composant

| Composant | CPU | RAM | Stockage | Notes |
|-----------|-----|-----|---------|-------|
| **PHP-FPM (Laravel)** | 0.1-0.5 vCPU | 128-256 Mo | 50-100 Mo | Inclut vendor/, bootstrap/ |
| **Nginx** | 0.1 vCPU | 32-64 Mo | 10 Mo | Configuration légère |
| **Queue Worker** | 0.1 vCPU | 64-128 Mo | 5 Mo | Jobs de sync (traitement rapide) |
| **SQLite** | 0 vCPU | 32-64 Mo | **Variable** | Fichier `.sqlite` + WAL/SHM |
| **Overhead Docker** | - | 20-50 Mo | - | Conteneur unique |
| **Buffer/OS** | - | 100-200 Mo | - | Pour les ops systèmes |
| **TOTAL** | **0.5-1 vCPU** | **512 Mo - 1 Go** | **1 Go + données** | |

---

## 3. Dimensionnement par Type d'Environnement

### 3.1 Environnement de Développement (Local)
**Utilisation typique** :
- 1 développeur
- Base SQLite locale
- Hot reload (Vite)
- Queue en écoute

| Ressource | Minimum | Recommandé | Notes |
|----------|---------|------------|-------|
| **vCPU** | 1 | 2 | Pour le build des assets (Vite) |
| **RAM** | 1 Go | **2 Go** | Vite + PHP + Docker overhead |
| **Stockage** | 500 Mo | **2 Go** | Inclut vendor/ (~200 Mo) |
| **Bande passante** | - | 10 Mbps | Local, non critique |

> ⚠️ **Sur macOS/Windows** : Docker Desktop consomme +500 Mo RAM pour le daemon.

---

### 3.2 Environnement de Production (Serveur dédié/VPS)
**Hypothèses** :
- 3 utilisateurs **simultanés max**
- Inventaire : **~500 items**, **~50 tags**, **~20 maisons**
- Images : **~100 fichiers** × 200 Ko (JPEG 1024px) = **20 Mo**
- SQLite : **~5 Mo** (base) + **~10 Mo** (WAL/SHM)
- Logs : **~50 Mo** (rotation mensuelle)

#### Scénario 1 : **Micro-Instance (Recommandé pour 3 users)**
| Ressource | Valeur | Justification |
|----------|--------|---------------|
| **vCPU** | **1** | Suffisant pour PHP + Nginx + Queue |
| **RAM** | **1 Go** | Laravel (512 Mo) + SQLite (128 Mo) + OS (256 Mo) + buffer |
| **Stockage SSD** | **5 Go** | OS (1 Go) + app (1 Go) + données (1 Go) + logs (500 Mo) + marge |
| **Bande passante** | 100 Mbps | Large marge (sync = quelques Ko/minute) |
| **IOPS** | 1000 | SQLite nécessite des E/S rapides |

#### Scénario 2 : **Nano-Instance (Budget serré)**
| Ressource | Valeur | Risque |
|----------|--------|--------|
| **vCPU** | 0.5 | Latence possible sous charge |
| **RAM** | 512 Mo | Risque d'OOM si cache PHP activé |
| **Stockage** | 2 Go | Limite si croissance données |
| **Bande passante** | 10 Mbps | OK pour la sync |

> ❌ **À éviter** : Instances avec **< 512 Mo RAM** → Crash probable.

#### Scénario 3 : **Instance Standard (Confort)**
| Ressource | Valeur | Pour |
|----------|--------|------|
| **vCPU** | 2 | Déploiement CI/CD + marge |
| **RAM** | 2 Go | Cache PHP (OPcache) + tests |
| **Stockage** | 10 Go | Données futures + backups |
| **Bande passante** | 1 Gbps | Sans impact |

---

### 3.3 Hébergement Cloud (Exemples)

| Fournisseur | Type | vCPU | RAM | Stockage | Coût mensuel (2026) | Adapté ? |
|------------|------|------|-----|---------|---------------------|----------|
| **Hetzner** | CX11 | 1 | 2 Go | 20 Go SSD | ~3.49 € | ✅ **Recommandé** |
| **OVH** | VPS Starter | 1 | 2 Go | 20 Go SSD | ~3.99 € | ✅ |
| **DigitalOcean** | Basic | 1 | 1 Go | 25 Go SSD | ~4 $ | ✅ |
| **AWS Lightsail** | nano | 0.5 | 512 Mo | 20 Go | ~3.5 $ | ⚠️ RAM limite |
| **AWS Lightsail** | micro | 1 | 1 Go | 40 Go | ~5 $ | ✅ |
| **Scaleway** | DEV1-S | 1 | 2 Go | 20 Go | ~4.99 € | ✅ |
| **Google Cloud** | e2-micro | 0.25 | 1 Go | 30 Go | ~8 $ | ⚠️ vCPU faible |
| **Azure** | B1s | 1 | 1 Go | 20 Go | ~10 $ | ✅ |

---

## 4. Stockage : Estimation Détaillée

### 4.1 Base de Données (SQLite)
| Élément | Taille estimée | Calcul |
|---------|----------------|--------|
| **Structure** | ~1 Mo | Tables + index |
| **Lignes `items`** | ~500 × 500 o = **250 Ko** | 500 items × ~500 octets/ligne |
| **Lignes `houses`** | ~50 × 200 o = **10 Ko** | |
| **Lignes `tags`** | ~50 × 100 o = **5 Ko** | |
| **Lignes `item_tag`** | ~1 000 × 100 o = **100 Ko** | 500 items × 2 tags avg |
| **Lignes `sync_operations`** | ~1 000 × 200 o = **200 Ko** | 1 an d'opérations |
| **WAL/SHM** | **~10 Mo** | Journal SQLite |
| **TOTAL** | **~12 Mo** | |

### 4.2 Fichiers Application
| Élément | Taille | Notes |
|---------|--------|-------|
| Code source | ~10 Mo | `app/`, `config/`, etc. |
| `vendor/` | **~200 Mo** | Dépendances Composer |
| `node_modules/` | **~100 Mo** | Si présent (build front) |
| Logs | ~50 Mo | Rotation mensuelle |
| **TOTAL** | **~360 Mo** | |

### 4.3 Données Utilisateur (Images)
| Élément | Taille/unité | Quantité | Total |
|---------|--------------|----------|-------|
| Image (JPEG 1024px) | **~200 Ko** | 100 | **20 Mo** |
| Miniatures | - | - | **0 Mo** | Pas générées |
| **TOTAL** | - | - | **20 Mo** |

### 4.4 **Total Stockage Recommandé**
| Catégorie | Taille | Marge (20%) | Total |
|----------|--------|-------------|-------|
| **Système** (OS conteneur) | 500 Mo | - | 500 Mo |
| **Application** | 360 Mo | - | 360 Mo |
| **Données** | 32 Mo | - | 32 Mo |
| **Images** | 20 Mo | - | 20 Mo |
| **Marge croissance** | - | 500 Mo | 500 Mo |
| **TOTAL** | - | - | **~1.4 Go** |

> 💡 **Recommandation** : **5 Go SSD** pour une marge confortable (backups, mise à jour, logs).

---

## 5. Bande Passante

### 5.1 Trafic par Opération de Synchronisation
| Type d'opération | Taille requête | Taille réponse | Fréquence |
|------------------|---------------|---------------|-----------|
| **Push (1 op)** | ~500 o | ~300 o | Occasionnelle |
| **Pull (full)** | - | **~500 Ko** | À la reconnexion |
| **Image upload** | ~200 Ko | ~100 o | Rare |

> **Full pull** = toutes les `houses` (2 × 200 o) + tous les `items` (500 × 300 o) + `tags` (50 × 100 o) ≈ **200 Ko**.

### 5.2 Trafic Mensuel Estimé
| Activité | Quantité | Taille totale |
|----------|----------|---------------|
| Pulls | 30 (1/jour/user) | 30 × 500 Ko = **15 Mo** |
| Pushs | 60 (2/jour/user) | 60 × 1 Ko = **60 Ko** |
| Images | 5 uploads | 5 × 200 Ko = **1 Mo** |
| **TOTAL** | - | **~16 Mo/mois** |

> ✅ **100 Mbps suffisent largement** (marge ×1000).

---

## 6. Optimisations pour Réduire les Ressources

### 6.1 Configuration PHP
```ini
# php.ini (dans le conteneur)
memory_limit = 256M       # Suffisant pour Laravel + SQLite
opcache.enable = 1        # Réduit la RAM utilisée par PHP
opcache.memory_consumption = 64  # 64 Mo pour OPcache
```

### 6.2 Configuration Laravel
```env
# .env
CACHE_DRIVER=file       # Évite Redis (RAM)
QUEUE_CONNECTION=sync   # Désactive les workers si pas besoin de jobs async
SESSION_DRIVER=file     # Évite la base pour les sessions
```
> ⚠️ **Si `QUEUE_CONNECTION=database`** : SQLite gère les jobs, mais **1 worker suffisant**.

### 6.3 SQLite
- **WAL Mode** : Activé par défaut → Meilleure concurrence.
- **Synchronous=NORMAL** : Équilibre sécurité/performance.
- **Cache size** : `-cache_size 2000` (2 Mo) suffisant.

```bash
# Dans le conteneur, après création de la base :
echo "PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL; PRAGMA cache_size=-2000;" \
  | sqlite3 database/database.sqlite
```

### 6.4 Docker
- **Limiter les ressources** du conteneur :
  ```yaml
  # docker-compose.override.yml (pour la prod)
  services:
    laravel.test:
      deploy:
        resources:
          limits:
            cpus: '0.5'
            memory: 512M
  ```

---

## 7. Recommandations Finales

### 7.1 Pour un Déploiement Production
| Critère | Valeur | Justification |
|---------|--------|---------------|
| **CPU** | 1 vCPU | Suffisant pour 3 users |
| **RAM** | **1 Go** | Laravel (512 Mo) + SQLite (128 Mo) + OS |
| **Stockage** | **5 Go SSD** | App (1 Go) + données (1 Go) + marge |
| **Bande passante** | 100 Mbps | Trafic minimal |
| **OS** | Ubuntu 22.04 LTS | Compatible Docker |
| **Type** | VPS | Contrôle total |

### 7.2 Fournisseurs Recommandés
1. **Hetzner CX11** (3.49 €/mois) → **Meilleur rapport qualité-prix**
2. **OVH VPS Starter** (3.99 €/mois) → Support francophone
3. **DigitalOcean Basic** (4 $/mois) → Simplicité

### 7.3 Commandes de Déploiement
```bash
# Sur le serveur (Ubuntu 22.04)
# 1. Installer Docker
sudo apt update && sudo apt install -y docker.io docker-compose
sudo systemctl enable --now docker
sudo usermod -aG docker $USER

# 2. Cloner le projet
git clone https://github.com/edrin17/trouve-laravel.git /opt/trouve
cd /opt/trouve

# 3. Configurer
cp .env.example .env
./vendor/bin/sail artisan key:generate

# 4. Démarrer avec limites ressources
./vendor/bin/sail up -d --cpus 0.5 --memory 512m

# 5. Préparer la base
./vendor/bin/sail artisan migrate --seed

# 6. (Optionnel) Configurer un reverse proxy (Nginx/Apache) vers le port 80
```

---

## 8. Monitoring & Alertes

### 8.1 Métriques à Surveiller
| Métrique | Seuil critique | Commande |
|----------|----------------|----------|
| **RAM utilisée** | > 800 Mo | `docker stats` |
| **Stockage** | > 4 Go | `df -h /var/lib/docker` |
| **CPU** | > 90% 5 min | `htop` |
| **Taille SQLite** | > 50 Mo | `ls -lh database/database.sqlite` |
| **Logs** | > 100 Mo | `du -sh storage/logs` |

### 8.2 Script de Nettoyage (Cron)
```bash
# /etc/cron.daily/trouve-cleanup
#!/bin/bash
cd /opt/trouve

# Nettoyer les logs (> 30 jours)
find storage/logs -name "*.log" -mtime +30 -delete

# Optimiser SQLite
docker exec trouve-laravel-1 sqlite3 database/database.sqlite "VACUUM;"

# Redémarrer le conteneur si OOM
if ! docker inspect trouve-laravel-1 --format '{{.State.Running}}' | grep -q true; then
  docker restart trouve-laravel-1
fi
```

---

## 9. Plan de Croissance (Si Besoin)

| Scénario | Action | Impact |
|----------|--------|--------|
| **+ de 3 users** | Migrer vers MySQL/PostgreSQL | +200 Mo RAM |
| **> 1 000 items** | Augmenter stockage à 10 Go | - |
| **> 10 users** | Ajouter 1 vCPU + 512 Mo RAM | ~5 €/mois |
| **Disponibilité** | Ajouter un Load Balancer | Complexité ↑ |

> ⚠️ **Pour l'instant, 3 users max → pas de besoin de scaling.**

---

## 10. Résumé des Coûts

| Poste | Coût mensuel | Fournisseur |
|-------|--------------|-------------|
| **Serveur (VPS)** | 3.49 € - 5 € | Hetzner/OVH |
| **Nom de domaine** | ~1 € | OVH/Gandi |
| **Backup** | 0 € | Inclus (snapshot VPS) |
| **TOTAL** | **~4-6 €/mois** | |

---

## Annexe A : Benchmark Local (Données Réelles)

> À exécuter sur une machine de test pour valider :

```bash
# 1. Lancer le conteneur
./vendor/bin/sail up -d

# 2. Mesurer la RAM
./vendor/bin/sail ps --format "table {{.Names}}\t{{.Status}}\t{{.Size}}"

# 3. Tester la charge (avec Apache Bench)
ab -n 100 -c 3 http://localhost/inventory

# 4. Vérifier les logs
./vendor/bin/sail logs --tail 50
```

**Résultats attendus** (sur M1 Mac 16 Go) :
- **RAM conteneur** : ~300-400 Mo
- **Temps réponse** : < 200 ms (moyenne)
- **CPU** : < 5%

---

## Annexe B : Configuration Docker Optimisée

```yaml
# docker-compose.override.yml (pour la production)
version: '3.8'
services:
  laravel.test:
    restart: unless-stopped
    deploy:
      resources:
        limits:
          cpus: '0.75'
          memory: 768M
    environment:
      - PHP_MEMORY_LIMIT=256M
      - OPcache_memory_consumption=64
```

---

*Document généré le 2026-06-15. À réviser en cas de changement d'échelle (ex: > 3 utilisateurs).*
