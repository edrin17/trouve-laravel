# Trouve — Backend Laravel

Backend de l'application **Trouve** (localisation d'objets dans la maison),
migration de la v1 Python/GTK4 ([dépôt trouve](https://github.com/edrin17/trouve))
vers une architecture web multi-plateforme.

- **Stack :** Laravel 12 · PHP 8.5 · SQLite
- **Environnement :** 100 % conteneurisé via [Laravel Sail](https://laravel.com/docs/sail) (Docker)
- **Aucune dépendance sur l'OS hôte** hormis Docker.

---

## Prérequis

Une seule chose à installer sur la machine : **Docker** (Engine + plugin Compose).

> Sur Ubuntu / Linux Mint, suivre la procédure officielle :
> <https://docs.docker.com/engine/install/ubuntu/>
> puis `sudo usermod -aG docker $USER` et redémarrer la session.

---

## Installation (machine vierge)

```bash
# 1. Cloner le dépôt
git clone https://github.com/edrin17/trouve-laravel.git
cd trouve-laravel

# 2. Installer les dépendances PHP (conteneur jetable, rien sur l'OS)
docker run --rm -v "$(pwd)":/app -w /app composer:2 install

# 3. Créer le fichier d'environnement
cp .env.example .env
docker run --rm -v "$(pwd)":/app -w /app composer:2 php artisan key:generate

# 4. Démarrer l'environnement (l'image se construit au premier lancement)
./vendor/bin/sail up -d

# 5. Préparer la base SQLite
touch database/database.sqlite
./vendor/bin/sail artisan migrate
```

L'application est disponible sur <http://localhost>.

---

## Commandes au quotidien

```bash
./vendor/bin/sail up -d        # démarrer (détaché)
./vendor/bin/sail down         # arrêter (libère la RAM)
./vendor/bin/sail artisan …    # commandes Artisan
./vendor/bin/sail composer …   # Composer
./vendor/bin/sail php …        # PHP
./vendor/bin/sail test         # tests
```

> Astuce : alias `sail` dans `~/.bashrc`
> ```bash
> alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'
> ```

---

## Notes

- **`.env`** et **`database/database.sqlite`** ne sont pas versionnés (config et
  données locales). Ils sont recréés par les étapes d'installation ci-dessus.
- La base SQLite repart **vide** sur une nouvelle machine ; copier le fichier
  `database/database.sqlite` manuellement pour transférer des données.
- Le `compose.yaml` est volontairement minimal (pas de MySQL / Redis) :
  SQLite suffit pour les 3 utilisateurs visés.
