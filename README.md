# Learnova LMS

<!-- badges -->
[![Moodle](https://img.shields.io/badge/Moodle-5.2-blue?style=flat-square)](https://moodle.org)
[![PHP](https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square&logo=php)](https://php.net)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square&logo=docker)](https://docker.com)
[![License](https://img.shields.io/badge/License-GPL--3.0-green?style=flat-square)](LICENSE)

> Learnova Learning Management System — Moodle 5.2 dengan custom theme, course landing, focus mode, dan course management. Dikembangkan dengan Docker Compose.

---

## ✨ Fitur Utama

### Custom Theme (`theme_learnovatheme`)
- **Course Landing** — halaman course/view.php custom dengan hero, tab (Course Content / About / Discussion / Progress), progress sidebar, dan edit mode inline.
- **Focus Mode** — tampilan activity bebas distraksi (topbar, table of contents, prev/next navigation) untuk Page, Book, Assignment, Forum, Quiz, SCORM, File, dan PDF.
- **PDF Viewer** — renderer PDF client-side via PDF.js (v4.10.38, Apache-2.0).
- **Site Admin UI** — halaman administrasi custom dengan collapsible category cards & bookmark bar.
- **Management UI** — component library `lm-*` untuk panel-panel manajemen.
- **Course Catalogue** — halaman course/index.php custom dengan filter kategori, durasi, status, dan pencarian.
- **Front Page** — hero, announcement band (3-tier), featured programs, kategori, latest courses, FAQ, CTA.

### Course Management (`local/learnova_course`)
- **Grader Report** — gradebook overview per peserta, kolom per aktivitas + total, export CSV, "Overall average".
- **Participants** — daftar peserta, edit peran, status keaktifan, tambah peserta.
- **Question Banks** — manajemen bank soal.
- **Recycle Bin** — manajemen item yang dihapus.
- **Reports** — overview report dan laporan kursus.
- **Competencies** — manajemen kompetensi.
- **Badges** — manajemen badge.
- **Activities** — daftar aktivitas kursus.
- **Course Reuse / Reset Completion / Unenrol Me** — utilitas kursus.
- **Discussion Engine** — diskusi custom (course-level & activity-scoped via `cmid`), post/reply/delete via AJAX.
- **Announcement Band** — pengumuman 3-tier (urgent/warning/info) dengan scope & rentang waktu.

---

## 🏗️ Arsitektur

```
┌──────────────────────────────────────────────────────────────┐
│                      Docker Compose                           │
│                                                              │
│  ┌──────────┐    ┌──────────┐    ┌──────────────────────┐   │
│  │  Nginx   │───▶│  Moodle  │───▶│     MariaDB          │   │
│  │  (8080)  │    │ (Apache) │    │   (persistent)       │   │
│  └──────────┘    └──────────┘    └──────────────────────┘   │
│                        │                                      │
│                        ▼                                      │
│                 ┌──────────┐    ┌──────────────────────┐   │
│                 │  Redis   │    │      Mailpit          │   │
│                 │ (cache)  │    │  (email dev :8026)   │   │
│                 └──────────┘    └──────────────────────┘   │
└──────────────────────────────────────────────────────────────┘
```

Moodle core di-clone dari branch `MOODLE_502_STABLE`, custom plugin/theme di-mount sebagai volume.

---

## 📋 Prerequisites

- [Docker](https://docs.docker.com/) + Docker Compose plugin
- Git
- `sshpass` (untuk deploy via password) atau SSH key terdaftar di VM
- 4GB+ RAM (recommended 8GB)

---

## 🚀 Quick Start (Local)

```bash
git clone https://github.com/lookmedia-tech/learnova-lms-web.git
cd learnova-lms-web
cp .env.example .env
docker compose up -d --build
```

> First run clones Moodle core + non-interactive install. Butuh 2–5 menit.

| Service | URL | Credentials |
|---------|-----|-------------|
| **Moodle** | http://localhost:8080 | `admin` / `LearnovaLMS2026!` |
| **Mailpit** | http://localhost:8026 | — (webmail dev) |

---

## 📂 Struktur Proyek

```
learnova-lms-web/
├── docker/
│   ├── moodle/
│   │   ├── Dockerfile            # Moodle PHP-Apache image
│   │   ├── php.ini               # PHP config (opcache, limits)
│   │   └── 20-auto-install.sh    # Auto-provision entrypoint
│   ├── nginx/
│   │   └── default.conf          # Nginx reverse proxy
│   └── mariadb/
│       └── custom.cnf            # MariaDB tuning
├── local/
│   └── learnova_course/          # local_learnova_course — Course Management
│       ├── classes/              # activities, badges, grades, participants, dll.
│       ├── classes/external/     # Web service endpoints (post_message, dll.)
│       ├── amd/src/              # JS modules (management, participants, qbank, dll.)
│       ├── db/                   # install.xml, upgrade.php, services.php, access.php
│       ├── templates/            # Mustache templates
│       └── management.php        # Entry point Course Management panel
├── blocks/
│   └── learnovadashboard/        # block_learnovadashboard
├── mod/
│   └── learnovaquiz/             # mod_learnovaquiz
├── theme/
│   └── learnovatheme/            # theme_learnovatheme — custom theme
│       ├── classes/              # course_landing, focus_mode, catalogue, site_admin, dll.
│       ├── amd/src/              # JS modules (focus_mode, course_landing, site_admin, dll.)
│       ├── js/pdfjs/             # PDF.js + standard fonts (third-party)
│       ├── layout/               # drawers.php, focusmode.php
│       ├── scss/                 # pre.scss, post.scss, _management_ui.scss, _site_admin.scss
│       ├── templates/            # Mustache templates (theme_boost overrides + theme_learnovatheme)
│       └── pix/                  # icons, logos
├── scripts/
│   ├── create-plugin.sh          # Scaffold plugin baru
│   ├── deploy.sh                 # Deploy ke VM via SSH
│   ├── vm-setup.sh               # Initial VM setup
│   └── db-client.sh              # Quick DB query helper
├── docker-compose.yml
├── .env.example
├── README.md
├── README-DEPLOY.md              # Panduan deploy detail
└── STAGING_SERVER_SETUP.md       # Referensi setup staging
```

---

## 🔧 Environment Variables

```bash
cp .env.example .env
```

| Variable | Default | Description |
|----------|---------|-------------|
| `MOODLE_DATABASE_HOST` | `mariadb` | Database host |
| `MOODLE_DATABASE_USER` | `moodle` | Database username |
| `MOODLE_DATABASE_PASSWORD` | `moodle` | Database password |
| `MOODLE_DATABASE_NAME` | `moodle` | Database name |
| `MOODLE_REPO` | *(empty)* | Git URL Moodle core. Kosong = upstream `moodle/moodle.git` |
| `MOODLE_BRANCH` | `MOODLE_502_STABLE` | Branch Moodle |
| `ADMINUSER` | `admin` | Username admin awal |
| `ADMINPASS` | `LearnovaLMS2026!` | Password admin awal (dev only) |
| `ADMINEMAIL` | `dev@lookmedia.id` | Email admin |

---

## 🧩 Plugin & Theme

### Custom Plugins Aktif

| Plugin | Path | Deskripsi |
|--------|------|-----------|
| `theme_learnovatheme` | `theme/learnovatheme/` | Custom theme (course landing, focus mode, site admin, management UI) |
| `local_learnova_course` | `local/learnova_course/` | Course Management + discussion engine + announcement |
| `block_learnovadashboard` | `blocks/learnovadashboard/` | Dashboard block |
| `local_learnovareports` | `local/learnovareports/` | Reports plugin |
| `mod_learnovaquiz` | `mod/learnovaquiz/` | Quiz activity module |

### Struktur SCSS Theme

SCSS dipisah jadi beberapa file partial yang di-concat di `config.php` (tanpa `@import`):

| File | Isi |
|------|-----|
| `scss/pre.scss` | Design tokens + variabel Bootstrap override (di-compile SEBELUM Bootstrap) |
| `scss/post.scss` | Style per-halaman/fitur (course landing, hero, focus mode, dll.) |
| `scss/_management_ui.scss` | Component library `lm-*` (reusable) |
| `scss/_site_admin.scss` | UI Site Administration custom |
| `scss/editor.scss` | Style untuk editor |

> ⚠️ **PENTING**: Karena file SCSS di-concat menjadi satu input besar, pastikan **brace balance selalu 0** (`{` == `}`) di setiap file partial. Satu `}` yang hilang akan memutus kompilasi seluruh CSS.

### Third-party Library

| Library | Lokasi | Versi | License |
|---------|--------|-------|---------|
| PDF.js | `theme/learnovatheme/js/pdfjs/` | 4.10.38 | Apache-2.0 |

Dideklarasikan di `theme/learnovatheme/thirdpartylibs.xml`.

---

## 🚢 Deployment

Deploy workflow lengkap & detail ada di [README-DEPLOY.md](README-DEPLOY.md). Ringkasnya:

```bash
# 1. Merge PR (via GitHub UI atau CLI)
git fetch origin pull/<N>/head
git merge FETCH_HEAD --no-ff -m "Merge pull request #N — deskripsi"
git push

# 2. Deploy ke staging (lihat README-DEPLOY.md untuk langkah detail)
./scripts/deploy.sh <vm-ip> ubuntu
```

**Langkah kunci setelah pull di server** (jangan skip):

1. **DB Upgrade** — wajib jika ada perubahan `version.php`/`db/`:
   ```bash
   docker compose exec -T moodle php admin/cli/upgrade.php --non-interactive
   ```
2. **Purge caches**:
   ```bash
   docker compose exec -T moodle php admin/cli/purge_caches.php
   ```
3. **Bump theme revision** — wajib jika ada perubahan SCSS agar CSS di-recompile:
   ```bash
   docker compose exec -T moodle php -r 'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; set_config("themerev", time()); set_config("cssrev", time()); theme_reset_all_caches();'
   ```
4. **Restart**:
   ```bash
   docker compose restart moodle
   ```

### Cron (WAJIB di server)

Moodle butuh cron berjalan otomatis untuk background tasks (regrade nilai, kirim email, dll). Tambahkan ke crontab host VM:

```bash
crontab -e
# Tambahkan baris:
* * * * * docker exec -u www-data learnova-lms-web-moodle-1 php /var/www/html/public/admin/cli/cron.php >/dev/null 2>&1
```

---

## 🛠️ Troubleshooting

### CSS/SCSS tidak ter-update (tampilan hancur / style lama)

Ini isu paling umum. Penyebab & solusi:

```bash
# 1. Cek brace balance SCSS (harus 0)
python3 -c "print(sum(1 for c in open('theme/learnovatheme/scss/post.scss').read() if c=='{') - sum(1 for c in open('theme/learnovatheme/scss/post.scss').read() if c=='}'))"

# 2. Nuke semua cache + bump revision
docker compose exec -T moodle rm -rf /var/www/moodledata/cache/* /var/www/moodledata/localcache/*
docker compose exec -T moodle php admin/cli/purge_caches.php
docker compose exec -T moodle php -r 'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; set_config("themerev", time()); set_config("cssrev", time()); theme_reset_all_caches();'
docker compose restart moodle
```

### Permission denied saat install plugin / git pull di server

```bash
# Fix ownership di dalam container
docker compose exec -T moodle chown -R www-data:www-data /var/www/html/public/local /var/www/html/public/theme /var/www/html/public/blocks /var/www/html/public/mod
docker compose exec -T moodle chmod -R 775 /var/www/html/public/local /var/www/html/public/theme /var/www/html/public/blocks /var/www/html/public/mod

# Fix ownership git di host (jika git reset --hard gagal)
sudo chown -R ubuntu:ubuntu /home/ubuntu/learnova-lms-web
```

### Grades tidak sinkron / perlu regrade

```bash
# Cek course yang perlu regrade
docker exec -u www-data learnova-lms-web-moodle-1 php -r 'define("CLI_SCRIPT",true); require "/var/www/html/config.php"; $ids = $DB->get_fieldset_select("grade_items", "DISTINCT courseid", "itemtype = ? AND needsupdate = 1", ["course"]); echo implode(", ", $ids) . "\n";'

# Regrade semua
# (gunakan cron agar otomatis)
```

### Moodle blank page

```bash
docker compose exec moodle php admin/cli/purge_caches.php
docker compose restart moodle
```

### Reset admin password

```bash
docker compose exec moodle su -s /bin/sh -c "php admin/cli/reset_password.php --username=admin --password='LearnovaLMS2026!' --ignore-password-policy" www-data
```

### Rebuild dari nol (hapus data)

```bash
docker compose down -v      # ⚠️ hapus database
docker compose up -d --build
```

---

## 🔒 Security Notes

- Password admin default (`LearnovaLMS2026!`) **hanya untuk development**. Ganti setelah login pertama.
- Jangan expose stack ini ke internet publik tanpa HTTPS.
- Service `dbclient` dengan root credentials hanya untuk development.
- Jangan commit kredensial server (SSH password, dll.) ke repository.

---

## 📖 Resources

- [Moodle Docs](https://moodle.org/docs/)
- [Moodle Development](https://moodledev.io/)
- [Docker Compose](https://docs.docker.com/compose/)
- [Moodle Plugin Types](https://moodledev.io/docs/guides/components)
- [PDF.js](https://github.com/mozilla/pdf.js)

---

## 📄 License

GPL v3 — lihat [LICENSE](LICENSE) dan [Moodle License](https://github.com/moodle/moodle/blob/master/COPYING.txt).

PDF.js dilisensikan Apache-2.0 (lihat `theme/learnovatheme/js/pdfjs/LICENSE`).

---

Dibuat oleh [Lookmedia](https://lookmedia.id)
