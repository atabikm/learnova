# Deployment Guide — Learnova LMS

Panduan deploy lengkap untuk staging server Learnova LMS.

---

## 📋 Ringkasan

| Item | Detail |
|------|--------|
| **Repo** | `github.com/lookmedia-tech/learnova-lms-web` |
| **Branch** | `main` |
| **Server** | Staging VM (Ubuntu) |
| **Deploy dir** | `/home/ubuntu/learnova-lms-web` |
| **Domain** | `https://learnova.lookmedia.co.id` |
| **Container** | `learnova-lms-web-moodle-1` |

---

## 🔀 1. Merge PR ke `main`

### Via GitHub UI
Merge PR di GitHub (bisa squash / merge commit / rebase).

### Via CLI (recommended, gunakan ref PR langsung)
```bash
# Fetch branch PR
git fetch origin pull/<NOMOR_PR>/head

# Test merge tanpa commit (cek konflik)
git merge --no-commit --no-ff FETCH_HEAD
# Jika ada konflik, resolve lalu:
git commit -m "Merge pull request #N — deskripsi"

# Jika tidak ada konflik, langsung commit:
git commit -m "Merge pull request #N — deskripsi"

# Push
git push origin main
```

> 💡 `pull/<N>/head` selalu tersedia di GitHub meski source branch sudah dihapus atau dari fork. Lebih robust daripada fetch branch by name.

---

## 🚀 2. Deploy ke Server

### Cara 1: Script otomatis
```bash
./scripts/deploy.sh <vm-ip> ubuntu
```

### Cara 2: Manual (SSH + password)
```bash
sshpass -p '<PASSWORD>' ssh ubuntu@<VM_IP> '
cd /home/ubuntu/learnova-lms-web && \
git pull origin main && \
docker compose exec -T moodle php admin/cli/upgrade.php --non-interactive && \
docker compose exec -T moodle php admin/cli/purge_caches.php && \
docker compose restart moodle'
```

### Cara 3: Manual + cache nuke lengkap (SAAT ADA PERUBAHAN SCSS)
```bash
sshpass -p '<PASSWORD>' ssh ubuntu@<VM_IP> '
cd /home/ubuntu/learnova-lms-web && \
sudo chown -R ubuntu:ubuntu /home/ubuntu/learnova-lms-web && \
git pull origin main && \
docker compose exec -T moodle php admin/cli/upgrade.php --non-interactive && \
docker compose exec -T moodle rm -rf /var/www/moodledata/cache/* /var/www/moodledata/localcache/* && \
docker compose exec -T moodle php admin/cli/purge_caches.php && \
docker compose exec -T moodle php -r "define(\"CLI_SCRIPT\", true); require \"/var/www/html/config.php\"; set_config(\"themerev\", time()); set_config(\"cssrev\", time()); theme_reset_all_caches();" && \
docker compose restart moodle'
```

---

## 📝 3. Langkah Kunci (Wajib Tahu)

### 3.1. Moodle DB Upgrade
Jalankan **setiap kali** ada perubahan di `version.php` atau `db/` (install.xml, upgrade.php):
```bash
docker compose exec -T moodle php admin/cli/upgrade.php --non-interactive
```
> Jika skip ini, Moodle bisa error karena schema DB tidak match dengan versi plugin.

### 3.2. Purge Caches
```bash
docker compose exec -T moodle php admin/cli/purge_caches.php
```

### 3.3. Theme Revision Bump (WAJIB saat SCSS berubah)
SCSS di-cache oleh Moodle. Tanpa bump revision, perubahan style tidak akan muncul:
```bash
docker compose exec -T moodle php -r 'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; set_config("themerev", time()); set_config("cssrev", time()); theme_reset_all_caches();'
```

### 3.4. Restart
```bash
docker compose restart moodle
```

---

## ⏰ 4. Cron (Setup Sekali)

Moodle butuh cron untuk background tasks (regrade nilai, email, completion, dll.). Tanpa cron, data tidak sinkron.

Cek apakah cron sudah ada:
```bash
crontab -l
```

Jika belum, tambahkan:
```bash
crontab -e
# Tambahkan baris ini:
* * * * * docker exec -u www-data learnova-lms-web-moodle-1 php /var/www/html/public/admin/cli/cron.php >/dev/null 2>&1
```

Trigger manual (untuk test):
```bash
docker exec -u www-data learnova-lms-web-moodle-1 php /var/www/html/public/admin/cli/cron.php
```

---

## 🔧 5. Troubleshooting Deploy

### 5.1. `git pull` gagal: "Permission denied" saat unlink
Penyebab: file dibuat/dimodifikasi oleh user lain (root/container). Fix:
```bash
sudo chown -R ubuntu:ubuntu /home/ubuntu/learnova-lms-web
git reset --hard HEAD
git pull origin main
```

### 5.2. Tampilan hancur setelah deploy (CSS tidak ter-load)
Ini isu **paling umum**. Penyebab utama:
- **Brace imbalance di SCSS** — satu `}` hilang memutus kompilasi seluruh CSS.
- **Cache stale** — theme revision tidak di-bump.

Cek brace balance:
```bash
python3 -c "print(open('theme/learnovatheme/scss/post.scss').read().count('{') - open('theme/learnovatheme/scss/post.scss').read().count('}'))"
# Harus output 0
```

Fix lengkap (nuke + bump):
```bash
docker compose exec -T moodle rm -rf /var/www/moodledata/cache/* /var/www/moodledata/localcache/*
docker compose exec -T moodle php admin/cli/purge_caches.php
docker compose exec -T moodle php -r 'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; set_config("themerev", time()); set_config("cssrev", time()); theme_reset_all_caches();'
docker compose restart moodle
```

Verifikasi CSS sudah ter-compile:
```bash
# Ambil rev dari halaman, lalu cek apakah selector custom ada
curl -s 'https://learnova.lookmedia.co.id/' | grep -oP 'styles\.php[^"]+'
curl -s 'https://learnova.lookmedia.co.id/theme/styles.php/learnovatheme/<REV>/all' | grep -c 'learnova-cl__hero'
```

### 5.3. Permission plugin install "Write access check"
```bash
docker compose exec -T moodle chown -R www-data:www-data /var/www/html/public/local /var/www/html/public/theme /var/www/html/public/blocks /var/www/html/public/mod
docker compose exec -T moodle chmod -R 775 /var/www/html/public/local /var/www/html/public/theme /var/www/html/public/blocks /var/www/html/public/mod
```

### 5.4. Grades tidak sinkron / perlu regrade
```bash
# Cek course yang perlu regrade
docker exec -u www-data learnova-lms-web-moodle-1 php -r 'define("CLI_SCRIPT",true); require "/var/www/html/config.php"; $ids = $DB->get_fieldset_select("grade_items", "DISTINCT courseid", "itemtype = ? AND needsupdate = 1", ["course"]); echo count($ids) . ": " . implode(", ", $ids) . "\n";'

# Regrade course tertentu
docker exec -u www-data learnova-lms-web-moodle-1 php -r 'define("CLI_SCRIPT",true); require "/var/www/html/config.php"; require_once $CFG->libdir."/gradelib.php"; $r = grade_regrade_final_grades(<COURSE_ID>); echo ($r === true ? "OK" : "GAGAL"), "\n";'
```

---

## ✅ 6. Checklist Deploy

- [ ] PR di-merge ke `main` (via UI atau CLI)
- [ ] `git pull origin main` di server
- [ ] Moodle upgrade dijalankan (jika ada perubahan db/version)
- [ ] Purge caches
- [ ] Theme revision bump (jika ada perubahan SCSS)
- [ ] Restart moodle
- [ ] Verifikasi CSS / halaman di browser

---

## 📞 Referensi

- [README.md](README.md) — overview proyek
- [STAGING_SERVER_SETUP.md](STAGING_SERVER_SETUP.md) — referensi setup staging
