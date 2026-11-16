# Learnova LMS Web Development

Moodle 5.2 development environment using Docker Compose.

## Architecture

```
Nginx (port 8080) → Apache/PHP (Moodle 5.2) → MariaDB
                                           → Redis (cache)
                                           → Mailpit (email dev)
```

## Quick Start

```bash
docker compose up -d --build
# Access: http://localhost:8080 (admin / LearnovaLMS2026!)
```

## Plugin Development

All custom plugins are developed inside this repository under `local/`, `blocks/`, `mod/`, and `theme/` directories.

### Directory Structure

```
learnova-lms-web/
├── local/learnovareports/        # local_learnovareports plugin
├── blocks/learnovadashboard/     # block_learnovadashboard plugin
├── mod/learnovaquiz/             # mod_learnovaquiz plugin
├── theme/learnovatheme/          # theme_learnovatheme plugin
└── scripts/
    └── create-plugin.sh          # Plugin scaffold generator
```

### Current Plugins

| Plugin | Path |
|--------|------|
| local_learnovareports | local/learnovareports |
| block_learnovadashboard | blocks/learnovadashboard |
| mod_learnovaquiz | mod/learnovaquiz |
| theme_learnovatheme | theme/learnovatheme |

### Create New Plugin

```bash
./scripts/create-plugin.sh <type> <name> "<Display Name>"

# Examples:
./scripts/create-plugin.sh local myreports "My Reports"
./scripts/create-plugin.sh block dashboard "Dashboard"
./scripts/create-plugin.sh mod quiz "Quiz"
./scripts/create-plugin.sh theme mytheme "My Theme"
```

This creates:
- Plugin directory (e.g., `local/myreports/`)
- `version.php` with correct component name
- `README.md` skeleton

**Important:** After creating a new plugin, add its volume mount to `docker-compose.yml`:

```yaml
# Example: add under moodle -> volumes (alongside existing mounts)
- ./local/myreports:/var/www/html/local/myreports:delegated
```

Then restart containers and visit `/admin/index.php` to install the plugin in Moodle.

### Develop

1. Edit plugin files directly in `local/`, `blocks/`, `mod/`, or `theme/`
2. Changes are reflected immediately via volume mounts (no restart needed)
3. For Moodle to register the plugin, run: `docker compose restart moodle`

### Test

```bash
docker compose exec moodle bash -c "cd /var/www/html && vendor/bin/phpunit <path>"
```

### Deploy

1. Commit and push to GitHub
2. On VM: pull latest, then run deploy script

## Useful Commands

```bash
# View logs
docker compose logs -f moodle

# Restart
docker compose restart moodle

# Rebuild
docker compose up -d --build

# PHPUnit tests
docker compose exec moodle bash -c "cd /var/www/html && vendor/bin/phpunit <path>"

# Debug mode
# Edit moodle/config.php: $CFG->debug = DEBUG_DEVELOPER;
```

## Mobile App

Mobile app repo: `/Users/mekari/Documents/lab/learnova-lms-mobile/`
Plugin mobile support: add `db/mobile.php` + `classes/output/mobile.php` to each plugin.

## Deployment

### Local Development

```bash
docker compose up -d --build
```

### Deploy to VM

```bash
./scripts/deploy.sh <vm-ip> [vm-user]
```

Example:
```bash
./scripts/deploy.sh 192.168.1.100 root
```

The deploy script will:
1. SSH to VM
2. Pull latest code from `main` branch
3. Rebuild moodle container
4. Restart all services
5. Verify with health check

See [README-DEPLOY.md](README-DEPLOY.md) for detailed deployment guide.
