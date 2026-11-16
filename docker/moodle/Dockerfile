FROM moodlehq/moodle-php-apache:8.3

# Install git and netcat so the container can fetch Moodle at startup and
# wait for the database to be ready.
RUN apt-get update \
    && apt-get install -y --no-install-recommends git netcat-openbsd rsync \
    && rm -rf /var/lib/apt/lists/*

# FROM moodlehq/moodle-php-apache:8.3

# Salin source core Moodle ke default web root dihapus karena menggunakan Volume di development
# Jika untuk Production, context build harus di root project dan gunakan: COPY ./moodle /var/www/html

# Pastikan folder data exist dan permission
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata \
    && chown -R www-data:www-data /var/www/html

# Apache doc root ke /var/www/html/public (sesuai Moodle 5.x)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

WORKDIR /var/www/html

# Copy an entrypoint helper that will clone Moodle (if needed), create a
# minimal config.php from environment variables and run the CLI installer on
# first container start. This ensures a fresh clone of learnova-lms-web + a simple
# `docker compose up` will bring the site up without requiring the user to
# clone Moodle core separately.
COPY ./php.ini /usr/local/etc/php/conf.d/zz-learnova.ini

COPY ./20-auto-install.sh /docker-entrypoint.d/20-auto-install.sh
COPY ./20-auto-install.sh /final-docker-entrypoint.d/20-auto-install.sh
RUN chmod +x /docker-entrypoint.d/20-auto-install.sh /final-docker-entrypoint.d/20-auto-install.sh || true
