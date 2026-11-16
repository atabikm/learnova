# 📋 Setup Staging Server Moodle 5.2 - Context Reference

> Dokumen ini berisi semua informasi untuk setup staging server Moodle 5.2 dengan Docker Compose dan CI/CD.

---

## 🎯 Goal & Constraints

**Goal**: Setup staging server Moodle 5.2 dengan Docker Compose, CI/CD untuk plugin development.

**Constraints**:
- Custom code lama di-archive (learnova-lms-infrastructure-archive)
- Moodle 5.2 clean, tanpa custom code bawaan
- Docker Compose sebagai environment dev standar
- Satu server untuk semua (staging)
- Satu repo per plugin/theme (tidak satu VM/plugin)
- CI/CD auto-deploy ke staging server

---

## 📦 Repositories di LookMedia Tech

| Repo | Visibility | Purpose | Link |
|---|---|---|---|
| learnova-lms-web | PUBLIC | Moodle core + Docker setup | [github.com/lookmedia-tech/learnova-lms-web](https://github.com/lookmedia-tech/learnova-lms-web) |
| learnova-lms-local-reports | PUBLIC | Local reports plugin | [github.com/lookmedia-tech/learnova-lms-local-reports](https://github.com/lookmedia-tech/learnova-lms-local-reports) |
| learnova-lms-block-dashboard | PUBLIC | Dashboard block plugin | [github.com/lookmedia-tech/learnova-lms-block-dashboard](https://github.com/lookmedia-tech/learnova-lms-block-dashboard) |
| learnova-lms-mod-quiz | PUBLIC | Quiz module plugin | [github.com/lookmedia-tech/learnova-lms-mod-quiz](https://github.com/lookmedia-tech/learnova-lms-mod-quiz) |
| learnova-lms-infrastructure-archive | PRIVATE | Archive old infra | - |
| learnova-lms-data | PRIVATE | Data migration scripts | - |
| learnova-lms-app | PRIVATE | Mobile app | - |

---

## 📁 Directory Structure (Local)

```
/Users/mekari/Documents/lab/
├── learnova-lms-web/              ← Main repo (Moodle core + Docker)
│   ├── docker-compose.yml
│   ├── moodle/                ← Moodle 5.2 core
│   ├── moodledata/            ← Data volume
│   ├── mariadb-data/          ← DB volume
│   └── docker/
│       ├── moodle/Dockerfile
│       ├── nginx/default.conf
│       └── mariadb/custom.cnf
│
├── learnova-lms-plugins/          ← Plugin repos folder
│   ├── learnova-lms-local-reports/
│   ├── learnova-lms-block-dashboard/
│   └── learnova-lms-mod-quiz/
│
└── learnova-lms-archive/          ← Old code archive
```

---

## 🐳 Docker Compose Stack

### Services
| Service | Image | Purpose |
|---|---|---|
| `moodle` | Custom build | Moodle 5.2 with Apache/PHP |
| `mariadb` | mariadb:11.4 | Database |
| `redis` | redis:alpine | Caching |
| `nginx` | nginx:latest | Reverse proxy (port 80) |
| `mailpit` | axllent/mailpit | Email testing (port 8025) |

### Volumes
| Volume | Mount Point | Purpose |
|---|---|---|
| `./moodle` | `/var/www/html` | Moodle core |
| `./moodledata` | `/var/www/moodledata` | Moodle data |
| `./mariadb-data` | `/var/lib/mysql` | Database data |
| Plugin repos | `/var/www/html/{type}/{name}` | Plugin source |

---

## 🔄 CI/CD Workflow

### Trigger
Push ke `main` branch di setiap repo

### Actions
1. GitHub Actions sync files ke server via SSH/RSYNC
2. Restart Docker container
3. Plugin tersedia di Moodle

### Secrets Required (per repo)
| Secret | Description | Example |
|--------|-------------|---------|
| `SSH_PRIVATE_KEY` | Private key untuk SSH ke server | `-----BEGIN OPENSSH PRIVATE KEY-----` |
| `SERVER_HOST` | IP/hostname server staging | `192.168.1.100` |
| `SERVER_USER` | Username SSH server | `ubuntu` |

---

## 📝 File Penting per Plugin

### version.php (WAJIB)
```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_learnovareports'; // Nama plugin
$plugin->version = 2025012500; // YYYYMMDDXX
$plugin->requires = 2024100700; // Moodle 5.2
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.1.0';
```

### Struktur Folder Plugin
```
plugin-name/
├── version.php
├── lang/en/
│   └── plugin_name.php
├── index.php (entry point)
├── classes/ (PHP classes)
├── amd/src/ (JavaScript)
├── templates/ (Mustache)
├── .github/workflows/deploy.yml
└── README.md
```

---

## 🚀 Setup Staging Server

### Langkah 1: Clone Repositori
```bash
# Clone learnova-lms-web
git clone https://github.com/lookmedia-tech/learnova-lms-web.git
cd learnova-lms-web

# Clone Moodle core
git clone -b MOODLE_502_STABLE https://git.moodle.org/moodle.git moodle

# Copy environment
cp .env.example .env
```

### Langkah 2: Setup Docker
```bash
# Start containers
docker compose up -d --build

# Cek status
docker compose ps
```

### Langkah 3: Install Moodle
- Buka: http://localhost
- Admin: `admin` / `LearnovaLMS2026!`

### Langkah 4: Setup Plugin Volumes
Edit `docker-compose.yml`:
```yaml
services:
  moodle:
    volumes:
      - ./moodle:/var/www/html:delegated
      - ./moodledata:/var/www/moodledata:delegated
      # Plugin mounts
      - /path/to/learnova-lms-local-reports:/var/www/html/local/learnovareports:delegated
      - /path/to/learnova-lms-block-dashboard:/var/www/html/blocks/learnovadashboard:delegated
      - /path/to/learnova-lms-mod-quiz:/var/www/html/mod/learnovaquiz:delegated
```

### Langkah 5: Setup CI/CD
1. Buka repo di GitHub
2. Settings → Secrets and variables → Actions
3. Tambahkan secrets:
   - `SSH_PRIVATE_KEY`
   - `SERVER_HOST`
   - `SERVER_USER`

---

## 🔧 Commands Penting

### Docker
```bash
# Start
docker compose up -d

# Stop
docker compose down

# Restart
docker compose restart moodle

# Logs
docker compose logs -f moodle

# Rebuild
docker compose up -d --build
```

### Git
```bash
# Clone repo
git clone https://github.com/lookmedia-tech/learnova-lms-web.git

# Push changes
git add .
git commit -m "feat: add new feature"
git push origin main
```

### Server
```bash
# Sync plugin ke server
rsync -avz ./ user@server:/opt/learnova-lms-web/plugins/plugin-name/

# Restart Docker di server
ssh user@server "cd /opt/learnova-lms-web && docker compose restart moodle"
```

---

## 🔗 URLs Penting

| Service | URL | Credentials |
|---|---|---|
| Moodle | http://localhost | admin / LearnovaLMS2026! |
| Mailpit | http://localhost:8025 | - |

---

## 📋 Checklist Setup Staging Server

- [ ] Clone learnova-lms-web repo
- [ ] Clone Moodle core (MOODLE_502_STABLE)
- [ ] Setup Docker Compose
- [ ] Start containers
- [ ] Install Moodle via web UI
- [ ] Clone plugin repos
- [ ] Setup plugin volumes di docker-compose.yml
- [ ] Restart Docker
- [ ] Setup CI/CD secrets di GitHub
- [ ] Test CI/CD dengan push dummy commit
- [ ] Verifikasi plugins di Moodle

---

## 🎯 Next Steps

1. **Setup server** - Deploy ke staging server
2. **Setup CI/CD** - Configure GitHub Actions secrets
3. **Test deployment** - Push dummy commit & verify
4. **Develop plugins** - Mulai development plugin baru

---

## 📞 Contact & Resources

- **GitHub Org**: https://github.com/lookmedia-tech
- **Moodle Docs**: https://docs.moodle.org
- **Docker Compose**: https://docs.docker.com/compose/

---

## 📄 Plugin Repositories

### 1. learnova-lms-local-reports (Local Reports Plugin)
**Link**: https://github.com/lookmedia-tech/learnova-lms-local-reports

**Struktur**:
```
learnova-lms-local-reports/
├── version.php
├── lang/en/local_learnovareports.php
├── index.php
├── .github/workflows/deploy.yml
└── README.md
```

**CI/CD**:
- Trigger: Push ke `main`
- Dest: `/opt/learnova-lms-web/plugins/local_learnovareports/`

---

### 2. learnova-lms-block-dashboard (Dashboard Block Plugin)
**Link**: https://github.com/lookmedia-tech/learnova-lms-block-dashboard

**Struktur**:
```
learnova-lms-block-dashboard/
├── version.php
├── lang/en/block_learnovadashboard.php
├── block_learnovadashboard.php
├── .github/workflows/deploy.yml
└── README.md
```

**CI/CD**:
- Trigger: Push ke `main`
- Dest: `/opt/learnova-lms-web/plugins/block_learnovadashboard/`

---

### 3. learnova-lms-mod-quiz (Quiz Module Plugin)
**Link**: https://github.com/lookmedia-tech/learnova-lms-mod-quiz

**Struktur**:
```
learnova-lms-mod-quiz/
├── version.php
├── lang/en/mod_learnovaquiz.php
├── lib.php
├── .github/workflows/deploy.yml
└── README.md
```

**CI/CD**:
- Trigger: Push ke `main`
- Dest: `/opt/learnova-lms-web/plugins/mod_learnovaquiz/`

---

## 🔄 Workflow Lengkap

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│  Repo A     │    │  Repo B     │    │  Repo C     │
│ (local)     │    │ (block)     │    │ (mod)       │
└──────┬──────┘    └──────┬──────┘    └──────┬──────┘
       │                  │                  │
       ▼                  ▼                  ▼
┌──────────────────────────────────────────────────────┐
│                  GitHub Actions                       │
│  • Trigger: push to main                             │
│  • Sync files to server                              │
└──────────────────┬───────────────────────────────────┘
                   │
                   ▼ SSH/RSYNC
┌──────────────────────────────────────────────────────┐
│                     SERVER                           │
│  ┌──────────────────────────────────────────────┐   │
│  │  learnova-lms-web (Docker Compose)               │   │
│  │  ├── moodle (core)                           │   │
│  │  ├── plugins/                                │   │
│  │  │   ├── local_learnovareports (sync dari Repo A)│   │
│  │  │   ├── block_learnovadashboard (Repo B)        │   │
│  │  │   └── mod_learnovaquiz (Repo C)               │   │
│  │  └── moodledata, mariadb-data                │   │
│  └──────────────────────────────────────────────┘   │
└──────────────────────────────────────────────────────┘
```

---

**Dokumen ini siap digunakan untuk setup staging server!** 🚀
