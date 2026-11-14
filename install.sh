#!/usr/bin/env bash
# ==============================================================================
# JAWIRBLAST — AUTO INSTALLER UNTUK UBUNTU VPS (22.04 / 24.04 / 26)
# Menginstall Nginx, PHP, MySQL, Composer, Node.js, Baileys, dan Panel CLI
# ==============================================================================
set -euo pipefail

SCRIPT_PATH="$(readlink -f "${BASH_SOURCE[0]}")"
CURRENT_DIR="$(cd "$(dirname "${SCRIPT_PATH}")" && pwd)"
INSTALL_DIR="/var/www/jawirblast"
REPO_URL="https://github.com/dalifajr/jawirblast.git"

# Pewarnaan Teks
GREEN='\033[1;32m'
BLUE='\033[1;34m'
CYAN='\033[1;36m'
YELLOW='\033[1;33m'
RED='\033[1;31m'
NC='\033[0m'

log_step() {
    echo -e "\n${CYAN}====================================================================${NC}"
    echo -e "${GREEN}[+] $1${NC}"
    echo -e "${CYAN}====================================================================${NC}"
}

check_root() {
    if [[ "$(id -u)" -ne 0 ]]; then
        echo -e "${RED}[!] Skrip instalasi ini harus dijalankan sebagai root (gunakan sudo).${NC}" >&2
        exit 1
    fi
}

setup_swap() {
    local swap_count
    swap_count=$(swapon --show | wc -l)
    if [[ "${swap_count}" -eq 0 ]]; then
        log_step "Membuat file Swap 2GB untuk stabilitas memori proses compile..."
        fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048 status=none
        chmod 600 /swapfile
        mkswap /swapfile >/dev/null 2>&1
        swapon /swapfile
        if ! grep -q "/swapfile" /etc/fstab; then
            echo '/swapfile none swap sw 0 0' >> /etc/fstab
        fi
        echo -e "${GREEN}[✓] Swap 2GB aktif.${NC}"
    else
        echo -e "${GREEN}[✓] Swap memori sudah tersedia.${NC}"
    fi
}

install_packages() {
    log_step "Memperbarui repositori dan menginstall paket sistem..."
    export DEBIAN_FRONTEND=noninteractive
    export NEEDRESTART_MODE=a
    export NEEDRESTART_SUSPEND=1

    # Bersihkan file PPA broken jika ada dari percobaan sebelumnya
    rm -f /etc/apt/sources.list.d/ondrej-*.list /etc/apt/sources.list.d/ondrej-*.sources /etc/apt/sources.list.d/*php*
    apt-get clean
    apt-get update -qq || true

    # Install tools dasar
    apt-get install -y -qq software-properties-common curl git unzip zip jq ufw cron build-essential

    # Deteksi codename Ubuntu
    local codename=""
    if [[ -f /etc/os-release ]]; then
        codename="$(. /etc/os-release && echo "${VERSION_CODENAME:-}")"
    fi

    # Ubuntu 24.04 (noble) dan 26.04 (resolute) sudah membawa PHP 8.3/8.4 secara native di repo resmi Ubuntu.
    # Hanya pasang Ondrej PPA jika OS versi lama (misal: jammy 22.04) dan belum memiliki php modern.
    if [[ "${codename}" != "resolute" && "${codename}" != "noble" ]]; then
        if ! apt-cache show php8.3-fpm >/dev/null 2>&1 && ! apt-cache show php8.4-fpm >/dev/null 2>&1; then
            log_step "Menambahkan repositori PHP untuk Ubuntu ${codename}..."
            LC_ALL=C.UTF-8 add-apt-repository -y ppa:ondrej/php || true
            if ! apt-get update -qq; then
                # Jika PPA gagal atau 404, hapus segera agar tidak memblokir apt
                rm -f /etc/apt/sources.list.d/ondrej-*.list /etc/apt/sources.list.d/ondrej-*.sources
                apt-get update -qq || true
            fi
        fi
    fi

    # Memasang Node.js & NPM
    if ! command -v node >/dev/null 2>&1 || ! command -v npm >/dev/null 2>&1; then
        log_step "Memasang Node.js & NPM..."
        if [[ "${codename}" != "resolute" ]]; then
            curl -fsSL https://deb.nodesource.com/setup_22.x | bash - || true
        fi
        # Catatan: Paket nodejs dari nodesource sudah memuat npm. Jangan install 'npm' terpisah agar tidak bentrok package manager.
        apt-get install -y nodejs
    fi

    # Deteksi prefix paket PHP yang tersedia
    local php_pkg_prefix="php"
    if apt-cache show php8.4-fpm >/dev/null 2>&1; then
        php_pkg_prefix="php8.4"
    elif apt-cache show php8.3-fpm >/dev/null 2>&1; then
        php_pkg_prefix="php8.3"
    fi

    dpkg --configure -a || true
    apt-get --fix-broken install -y || true

    log_step "Menginstall Nginx, ${php_pkg_prefix}, MySQL Server, dan Certbot..."
    if ! apt-get install -y \
        nginx certbot python3-certbot-nginx \
        "${php_pkg_prefix}-fpm" "${php_pkg_prefix}-cli" "${php_pkg_prefix}-curl" "${php_pkg_prefix}-mbstring" \
        "${php_pkg_prefix}-xml" "${php_pkg_prefix}-zip" "${php_pkg_prefix}-mysql" "${php_pkg_prefix}-gd" \
        "${php_pkg_prefix}-bcmath" "${php_pkg_prefix}-intl" "${php_pkg_prefix}-sqlite3" \
        mysql-server; then

        echo -e "${YELLOW}[!] Gagal menginstall paket dengan prefix ${php_pkg_prefix}, mencoba fallback ke php default...${NC}"
        apt-get --fix-broken install -y || true
        apt-get install -y \
            nginx certbot python3-certbot-nginx \
            php-fpm php-cli php-curl php-mbstring php-xml php-zip php-mysql php-gd php-bcmath php-intl php-sqlite3 \
            mysql-server
    fi

    # Pastikan service PHP-FPM aktif
    systemctl enable "${php_pkg_prefix}-fpm" 2>/dev/null || systemctl enable php-fpm 2>/dev/null || true
    systemctl start "${php_pkg_prefix}-fpm" 2>/dev/null || systemctl start php-fpm 2>/dev/null || true

    # Install Composer secara global
    if ! command -v composer >/dev/null 2>&1; then
        log_step "Mengunduh dan memasang Composer global..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --quiet
    fi

    echo -e "${GREEN}[✓] Paket sistem berhasil terpasang:${NC}"
    echo -e "  - PHP Version      : $(php -r 'echo PHP_VERSION;')"
    echo -e "  - Node.js Version  : $(node -v)"
    echo -e "  - NPM Version      : $(npm -v)"
    echo -e "  - Composer Version : $(composer --version | head -n1)"
}

setup_mysql() {
    log_step "Menyiapkan basis data MySQL..."
    systemctl start mysql
    systemctl enable mysql

    local db_name="blast"
    local db_user="blast_user"
    local db_pass
    db_pass="$(openssl rand -hex 16)"

    # Buat DB dan user dengan hak akses penuh untuk localhost dan 127.0.0.1
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${db_name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';"
    mysql -e "CREATE USER IF NOT EXISTS '${db_user}'@'127.0.0.1' IDENTIFIED BY '${db_pass}';"
    mysql -e "ALTER USER '${db_user}'@'localhost' IDENTIFIED BY '${db_pass}';"
    mysql -e "ALTER USER '${db_user}'@'127.0.0.1' IDENTIFIED BY '${db_pass}';"
    mysql -e "GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'localhost';"
    mysql -e "GRANT ALL PRIVILEGES ON \`${db_name}\`.* TO '${db_user}'@'127.0.0.1';"
    mysql -e "FLUSH PRIVILEGES;"

    echo "${db_name}:${db_user}:${db_pass}" > /tmp/jawirblast_db_creds.txt
    echo -e "${GREEN}[✓] Basis data MySQL [${db_name}] berhasil disiapkan.${NC}"
}

deploy_app() {
    log_step "Menyiapkan direktori aplikasi JAWIRBLAST di ${INSTALL_DIR}..."

    mkdir -p "${INSTALL_DIR}"
    git config --global --add safe.directory "${INSTALL_DIR}" || true
    export COMPOSER_ALLOW_SUPERUSER=1

    # Jika skrip dijalankan dari dalam direktori source code
    if [[ -f "${CURRENT_DIR}/artisan" ]] && [[ "${CURRENT_DIR}" != "${INSTALL_DIR}" ]]; then
        echo -e "${BLUE}[*] Menyalin berkas dari ${CURRENT_DIR} ke ${INSTALL_DIR}...${NC}"
        rsync -a --exclude='.git' --exclude='node_modules' --exclude='vendor' "${CURRENT_DIR}/" "${INSTALL_DIR}/"
    elif [[ ! -f "${INSTALL_DIR}/artisan" ]]; then
        echo -e "${BLUE}[*] Mengkloning source code dari repositori GitHub...${NC}"
        git clone "${REPO_URL}" "${INSTALL_DIR}"
    fi

    cd "${INSTALL_DIR}"

    # Setup .env
    if [[ ! -f "${INSTALL_DIR}/.env" ]]; then
        if [[ -f "${INSTALL_DIR}/.env.example" ]]; then
            cp "${INSTALL_DIR}/.env.example" "${INSTALL_DIR}/.env"
        else
            touch "${INSTALL_DIR}/.env"
        fi
    fi

    # Fungsi pembantu update atau append variabel .env (termasuk yang diawali #)
    set_env_param() {
        local file="$1" key="$2" val="$3"
        if grep -qE "^#?[[:space:]]*${key}=" "${file}"; then
            sed -i "s|^#\?[[:space:]]*${key}=.*|${key}=\"${val}\"|" "${file}"
        else
            echo "${key}=\"${val}\"" >> "${file}"
        fi
    }

    # Terapkan konfigurasi database ke .env
    if [[ -f "/tmp/jawirblast_db_creds.txt" ]]; then
        IFS=":" read -r db_name db_user db_pass < /tmp/jawirblast_db_creds.txt
        set_env_param "${INSTALL_DIR}/.env" "DB_CONNECTION" "mysql"
        set_env_param "${INSTALL_DIR}/.env" "DB_HOST" "127.0.0.1"
        set_env_param "${INSTALL_DIR}/.env" "DB_PORT" "3306"
        set_env_param "${INSTALL_DIR}/.env" "DB_DATABASE" "${db_name}"
        set_env_param "${INSTALL_DIR}/.env" "DB_USERNAME" "${db_user}"
        set_env_param "${INSTALL_DIR}/.env" "DB_PASSWORD" "${db_pass}"
        rm -f /tmp/jawirblast_db_creds.txt
    fi

    # Instalasi dependensi Composer
    log_step "Menginstall dependensi Composer Laravel..."
    composer install --no-dev --optimize-autoloader --no-interaction

    # Instalasi dependensi Node.js Baileys WhatsApp
    if [[ -d "${INSTALL_DIR}/wa-service" ]]; then
        log_step "Menginstall dependensi Baileys WhatsApp Node.js..."
        if [[ ! -f "${INSTALL_DIR}/wa-service/.env" ]] && [[ -f "${INSTALL_DIR}/wa-service/.env.example" ]]; then
            cp "${INSTALL_DIR}/wa-service/.env.example" "${INSTALL_DIR}/wa-service/.env"
        fi
        (cd "${INSTALL_DIR}/wa-service" && npm install --production)
    fi

    # Bersihkan cache sebelum migrasi untuk memastikan kredensial baru terbaca
    php artisan config:clear || true

    # Generate key & migrasi
    log_step "Menjalankan migrasi basis data..."
    php artisan key:generate --force
    php artisan migrate --force
    php artisan storage:link || true
    php artisan optimize:clear
    php artisan optimize

    # Atur kepemilikan dan izin folder
    chown -R www-data:www-data "${INSTALL_DIR}"
    chmod -R 775 "${INSTALL_DIR}/storage" "${INSTALL_DIR}/bootstrap/cache" "${INSTALL_DIR}/wa-service"

    echo -e "${GREEN}[✓] Deploy aplikasi Laravel dan service Baileys selesai.${NC}"
}

setup_systemd() {
    log_step "Memasang service daemon systemd (WhatsApp & Queue Worker)..."

    # 1. Service Baileys WhatsApp Node.js
    cat <<EOF > /etc/systemd/system/jawirblast-wa.service
[Unit]
Description=JAWIRBLAST WhatsApp Baileys Microservice
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=${INSTALL_DIR}/wa-service
ExecStart=/usr/bin/node index.js
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal
Environment=NODE_ENV=production
Environment=PORT=3000

[Install]
WantedBy=multi-user.target
EOF

    # 2. Service Queue Worker Laravel
    cat <<EOF > /etc/systemd/system/jawirblast-worker.service
[Unit]
Description=JAWIRBLAST WhatsApp Queue Broadcast Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=${INSTALL_DIR}
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=60 --sleep=3
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable --now jawirblast-wa
    systemctl enable --now jawirblast-worker

    echo -e "${GREEN}[✓] Service daemon jawirblast-wa dan jawirblast-worker aktif.${NC}"
}

setup_nginx() {
    log_step "Mengonfigurasi Web Server Nginx..."

    local php_sock
    php_sock="$(find /run/php/ -name 'php*-fpm.sock' 2>/dev/null | head -n 1 || echo '/run/php/php8.3-fpm.sock')"

    cat <<EOF > /etc/nginx/sites-available/jawirblast
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    root ${INSTALL_DIR}/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:${php_sock};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    # Reverse proxy ke Baileys API internal jika diperlukan
    location /wa-api/ {
        proxy_pass http://127.0.0.1:3000/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 86400s;
        proxy_buffering off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    ln -sf /etc/nginx/sites-available/jawirblast /etc/nginx/sites-enabled/jawirblast
    rm -f /etc/nginx/sites-enabled/default

    nginx -t
    systemctl restart nginx

    echo -e "${GREEN}[✓] Nginx berhasil dikonfigurasi dan di-reload.${NC}"
}

setup_cli_panel() {
    log_step "Memasang Shortcut Panel Kontrol Terminal /usr/local/bin/jawirblast..."

    chmod +x "${INSTALL_DIR}/bin/jawirblast"
    ln -sf "${INSTALL_DIR}/bin/jawirblast" /usr/local/bin/jawirblast
    ln -sf "${INSTALL_DIR}/bin/jawirblast" /usr/bin/jawirblast

    echo -e "${GREEN}[✓] Shortcut terminal 'jawirblast' berhasil dipasang.${NC}"
}

create_initial_admin() {
    log_step "Konfigurasi Akun Administrator..."
    local admin_email admin_pass
    echo -e "Silakan tentukan akun admin untuk login pertama kali:"
    read -rp "Email Admin (default: admin@jawirblast.com): " admin_email
    admin_email="${admin_email:-admin@jawirblast.com}"

    read -rp "Password Admin (default: admin123456): " admin_pass
    admin_pass="${admin_pass:-admin123456}"

    cd "${INSTALL_DIR}"
    php artisan jawirblast:reset-admin "${admin_email}" "${admin_pass}"

    echo -e "${GREEN}[✓] Akun Administrator [${admin_email}] berhasil dibuat.${NC}"
}

setup_domain_ssl() {
    log_step "Konfigurasi Nama Domain & SSL (Opsional)..."

    # Pastikan port 80 dan 443 dibuka jika UFW aktif untuk mencegah Error 522
    if command -v ufw >/dev/null 2>&1; then
        if ufw status 2>/dev/null | grep -qw "active"; then
            echo -e "${BLUE}[*] Membuka port 80 dan 443 pada UFW Firewall...${NC}"
            ufw allow 80/tcp >/dev/null 2>&1 || true
            ufw allow 443/tcp >/dev/null 2>&1 || true
        fi
    fi

    read -rp "Apakah Anda ingin memasang nama domain sekarang? (y/n, default: n): " setup_dom
    if [[ "${setup_dom}" =~ ^[Yy]$ ]]; then
        read -rp "Masukkan nama domain (contoh: blast.domainanda.com): " domain_name
        domain_name="$(echo "${domain_name}" | tr -d ' ')"
        if [[ -z "${domain_name}" ]]; then
            echo -e "${YELLOW}[!] Nama domain kosong, melewati setup domain.${NC}"
            return
        fi

        echo -e "\nPilih metode SSL untuk domain ${CYAN}${domain_name}${NC}:"
        echo -e "  ${GREEN}1)${NC} Cloudflare Origin Certificate (DNS Proxied / Awan Oranye - Masa aktif 15 Tahun) [Direkomendasikan]"
        echo -e "  ${GREEN}2)${NC} Otomatis Let's Encrypt via Certbot (DNS Non-Proxied / Awan Abu-abu)"
        echo -e "  ${GREEN}3)${NC} HTTP Standar (Setup SSL nanti melalui perintah 'jawirblast')"
        read -rp "Pilihan metode SSL [1/2/3, default: 1]: " ssl_choice
        ssl_choice="${ssl_choice:-1}"

        if [[ "${ssl_choice}" == "1" ]]; then
            local ssl_dir="/etc/ssl/jawirblast"
            mkdir -p "${ssl_dir}"

            echo -e "\n${CYAN}--- Langkah 1: Masukkan Cloudflare Origin Certificate ---${NC}"
            echo -e "${YELLOW}(Salin dari Dashboard Cloudflare. Tekan Enter setelah baris -----END CERTIFICATE----- atau ketik EOF)${NC}"
            echo -e "Paste Origin Certificate di sini:"
            local cert_data=""
            while IFS= read -r line; do
                [[ "${line}" == "EOF" ]] && break
                cert_data+="${line}"$'\n'
                [[ "${line}" =~ "-----END CERTIFICATE-----" ]] && break
            done

            echo -e "\n${CYAN}--- Langkah 2: Masukkan Cloudflare Private Key ---${NC}"
            echo -e "${YELLOW}(Salin dari Dashboard Cloudflare. Tekan Enter setelah baris -----END PRIVATE KEY----- atau ketik EOF)${NC}"
            echo -e "Paste Private Key di sini:"
            local key_data=""
            while IFS= read -r line; do
                [[ "${line}" == "EOF" ]] && break
                key_data+="${line}"$'\n'
                [[ "${line}" =~ "-----END "(RSA |EC )?"PRIVATE KEY-----" ]] && break
            done

            if [[ "${cert_data}" =~ "-----BEGIN CERTIFICATE-----" ]] && [[ "${key_data}" =~ "-----BEGIN "(RSA |EC )?"PRIVATE KEY-----" ]]; then
                printf '%s' "${cert_data}" > "${ssl_dir}/cert.pem"
                printf '%s' "${key_data}" > "${ssl_dir}/key.pem"
                chmod 644 "${ssl_dir}/cert.pem"
                chmod 600 "${ssl_dir}/key.pem"

                local php_sock
                php_sock="$(find /run/php/ -name 'php*-fpm.sock' 2>/dev/null | head -n 1 || echo '/run/php/php8.3-fpm.sock')"

                local ssl_listen_1="listen 443 ssl http2;"
                local ssl_listen_2="listen [::]:443 ssl http2;"
                local http2_directive=""
                local ngx_v
                ngx_v="$(nginx -v 2>&1 || true)"
                if echo "${ngx_v}" | grep -qE "nginx/([2-9]\.|1\.(2[5-9]|[3-9][0-9]))"; then
                    ssl_listen_1="listen 443 ssl;"
                    ssl_listen_2="listen [::]:443 ssl;"
                    http2_directive="http2 on;"
                fi

                cat <<EOF > /etc/nginx/sites-available/jawirblast
server {
    listen 80;
    listen [::]:80;
    server_name ${domain_name};
    return 301 https://\$host\$request_uri;
}

server {
    ${ssl_listen_1}
    ${ssl_listen_2}
    ${http2_directive}
    server_name ${domain_name};
    root ${INSTALL_DIR}/public;

    ssl_certificate ${ssl_dir}/cert.pem;
    ssl_certificate_key ${ssl_dir}/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php index.html;
    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:${php_sock};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location /wa-api/ {
        proxy_pass http://127.0.0.1:3000/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 86400s;
        proxy_buffering off;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
                sed -i "s|^APP_URL=.*|APP_URL=https://${domain_name}|" "${INSTALL_DIR}/.env"
                if nginx -t; then
                    systemctl reload nginx
                    echo -e "${GREEN}[✓] SSL Cloudflare Origin Certificate berhasil dikonfigurasi pada Nginx!${NC}"
                else
                    echo -e "${RED}[!] Konfigurasi Nginx SSL gagal diverifikasi.${NC}"
                fi
            else
                echo -e "${YELLOW}[!] Format sertifikat atau private key tidak lengkap. Menggunakan konfigurasi HTTP standar.${NC}"
                sed -i "s/server_name .*/server_name ${domain_name};/" /etc/nginx/sites-available/jawirblast
                sed -i "s|^APP_URL=.*|APP_URL=http://${domain_name}|" "${INSTALL_DIR}/.env"
                nginx -t && systemctl reload nginx || true
            fi
        elif [[ "${ssl_choice}" == "2" ]]; then
            sed -i "s/server_name .*/server_name ${domain_name};/" /etc/nginx/sites-available/jawirblast
            sed -i "s|^APP_URL=.*|APP_URL=https://${domain_name}|" "${INSTALL_DIR}/.env"
            nginx -t && systemctl reload nginx || true

            echo -e "${BLUE}[*] Menjalankan Certbot SSL untuk domain ${domain_name}...${NC}"
            certbot --nginx -d "${domain_name}" --non-interactive --agree-tos --register-unsafely-without-email --redirect || {
                echo -e "${YELLOW}[!] Gagal menerbitkan SSL Certbot. Anda dapat menjalankannya kembali melalui perintah 'jawirblast'.${NC}"
            }
        else
            sed -i "s/server_name .*/server_name ${domain_name};/" /etc/nginx/sites-available/jawirblast
            sed -i "s|^APP_URL=.*|APP_URL=http://${domain_name}|" "${INSTALL_DIR}/.env"
            nginx -t && systemctl reload nginx || true
            echo -e "${GREEN}[✓] Domain diset ke HTTP: http://${domain_name}${NC}"
        fi

        cd "${INSTALL_DIR}" && php artisan config:clear || true
    fi
}

main() {
    check_root
    echo -e "${CYAN}"
    echo "  ╔══════════════════════════════════════════════════════════════════════╗"
    echo "  ║                   JAWIRBLAST AUTO INSTALLER VPS                      ║"
    echo "  ║        WhatsApp High-Performance Broadcast & Telegram Platform       ║"
    echo "  ╚══════════════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"

    setup_swap
    install_packages
    setup_mysql
    deploy_app
    setup_systemd
    setup_nginx
    setup_cli_panel
    create_initial_admin
    setup_domain_ssl

    local server_ip
    server_ip="$(curl -s -4 ifconfig.me || hostname -I | awk '{print $1}')"

    echo -e "\n${GREEN}====================================================================${NC}"
    echo -e "${GREEN}      INSTALASI JAWIRBLAST PADA VPS BERHASIL SEPENUHNYA!             ${NC}"
    echo -e "${GREEN}====================================================================${NC}"
    echo -e "Akses Website     : ${CYAN}http://${server_ip}${NC}"
    echo -e "Direktori Aplikasi: ${YELLOW}${INSTALL_DIR}${NC}"
    echo -e "Kontrol Terminal  : Ketik ${GREEN}jawirblast${NC} pada terminal VPS kapan saja."
    echo -e "                    untuk Start/Stop/Restart, Ganti Domain, Reset Admin,"
    echo -e "                    Ubah Key Turnstile, Ubah Token Bot Telegram, atau Update."
    echo -e "${GREEN}====================================================================${NC}\n"
}

main "$@"
