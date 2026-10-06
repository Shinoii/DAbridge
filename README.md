# DA Bridge: деплой на сервер (прод)

Подходит для VPS с Ubuntu 24.04. Для десятков стримеров достаточно 1 vCPU и 1-2 ГБ RAM. Нужен **домен** (виджетам требуется `wss://`, а значит TLS-сертификат).

Примеры ниже используют домен `example.com` и путь `/var/www/da-bridge`. Замените на свои.

## 1. Подготовка сервера

```bash
apt update && apt upgrade -y
apt install -y nginx mysql-server supervisor certbot python3-certbot-nginx git unzip ufw fail2ban \
  php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
```

Swap на случай пиков памяти (для 2 ГБ RAM):

```bash
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

DNS: создайте A-запись `example.com` → IP сервера.

## 2. База данных

```bash
mysql
```
```sql
CREATE DATABASE da_bridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'da_bridge'@'localhost' IDENTIFIED BY 'Rfgbnjirf_5891';
GRANT ALL PRIVILEGES ON da_bridge.* TO 'da_bridge'@'localhost';
FLUSH PRIVILEGES;
```

MySQL должен слушать только `127.0.0.1` (по умолчанию в Ubuntu так и есть: `bind-address = 127.0.0.1`).

## 3. Код и зависимости

```bash
mkdir -p /var/www/da-bridge && cd /var/www/da-bridge
# загрузите проект (git clone / scp / rsync)
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

> **Сохраните `APP_KEY` в надёжном месте.** Токены стримеров шифруются этим ключом. Потеряете или смените ключ, и всех придётся подключать заново.

## 4. `.env` для прода

```env
APP_NAME="DA Bridge"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=da_bridge
DB_USERNAME=da_bridge
DB_PASSWORD=СЛОЖНЫЙ_ПАРОЛЬ

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database

LOG_CHANNEL=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=warning

DONATIONALERTS_CLIENT_ID=...
DONATIONALERTS_CLIENT_SECRET=...        # сгенерируйте НОВЫЙ секрет для прода
DONATIONALERTS_REDIRECT_URI=https://example.com/da/callback

ADMIN_LOGIN=придумайте_не_admin
ADMIN_PASSWORD_HASH='$2y$12$...'

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=сгенерируйте
REVERB_APP_KEY=сгенерируйте
REVERB_APP_SECRET=сгенерируйте
REVERB_HOST=example.com                 # адрес, который видят клиенты
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1            # слушаем только локально, наружу идёт через nginx
REVERB_SERVER_PORT=8080

SITE_NAME="DA Bridge"
SITE_OPERATOR="ФИО / ИП / самозанятый"
SITE_CONTACT_EMAIL=you@example.com
```

Генерация значений:

```bash
php -r "echo bin2hex(random_bytes(10)),PHP_EOL;"                 # APP_ID, APP_KEY
php -r "echo bin2hex(random_bytes(20)),PHP_EOL;"                 # APP_SECRET
php artisan tinker --execute="echo Hash::make('надёжный_пароль');"
```

## 5. Миграции и кэш

```bash
php artisan migrate --force
php artisan optimize          # config, route, view, event cache
```

После любого изменения `.env` выполняйте `php artisan optimize:clear && php artisan optimize`.

## 6. nginx

`/etc/nginx/sites-available/da-bridge`:

```nginx
map $http_upgrade $connection_upgrade { default upgrade; '' close; }

server {
    listen 80;
    server_name example.com;
    root /var/www/da-bridge/public;
    index index.php;
    client_max_body_size 2m;
    server_tokens off;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # WebSocket (виджеты) и HTTP API Reverb (бэкенд публикует события)
    location ~ ^/(app|apps)(/|$) {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_read_timeout 3600s;
        proxy_pass http://127.0.0.1:8080;
    }

    # Админка только с вашего IP (замените)
    location ^~ /admin {
        allow ВАШ.IP.АДРЕС;
        deny all;
        try_files $uri /index.php?$query_string;
    }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) { deny all; }

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;
    location ~* \.(css|js|svg|png|jpg|woff2)$ { expires 30d; access_log off; }
}
```

```bash
ln -s /etc/nginx/sites-available/da-bridge /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
certbot --nginx -d example.com --redirect
```

`certbot` сам добавит HTTPS-сервер, редирект с HTTP и автопродление. Проверка продления: `certbot renew --dry-run`.

> Если динамический IP, не ограничивайте `/admin` по IP, а защитите его basic-auth поверх пароля (`auth_basic`) или VPN.

## 7. Supervisor (процессы-демоны)

`/etc/supervisor/conf.d/da-bridge.conf`:

```ini
[program:da-reverb]
command=php /var/www/da-bridge/artisan reverb:start
directory=/var/www/da-bridge
user=www-data
autostart=true
autorestart=true
stopwaitsecs=10
redirect_stderr=true
stdout_logfile=/var/www/da-bridge/storage/logs/reverb.log

[program:da-listen]
command=php /var/www/da-bridge/artisan da:listen
directory=/var/www/da-bridge
user=www-data
autostart=true
autorestart=true
stopwaitsecs=10
redirect_stderr=true
stdout_logfile=/var/www/da-bridge/storage/logs/da-listen.log
```

```bash
supervisorctl reread && supervisorctl update
supervisorctl status
```

Оба процесса должны быть в состоянии `RUNNING`. Если соединений с виджетами станет больше ~1000, поднимите лимит открытых файлов (`minfds=10000` в секции `[supervisord]` файла `/etc/supervisor/supervisord.conf`).

## 8. DonationAlerts и виджет

1. В настройках приложения DA замените Redirect URI на `https://example.com/da/callback`.
2. Сгенерируйте новый Client Secret для прода и впишите его в `.env`.
3. В коде виджета StreamElements:

```js
const pusher = new Pusher('ВАШ_REVERB_APP_KEY', {
  cluster: 'mt1',
  wsHost: 'example.com',
  wssPort: 443,
  forceTLS: true,
  enabledTransports: ['ws', 'wss'],
});
```

## 9. Проверка после запуска

- `https://example.com` открывается, замочек в браузере зелёный.
- `https://example.com/up` отвечает 200 (встроенная проверка Laravel).
- Вход через DA работает, в `/admin` статус «online».
- `test.html` с прод-настройками (хост `example.com`, порт 443, шифрование включено) получает тестовый донат.
- `supervisorctl status` показывает оба демона `RUNNING`.

## 10. Обновление версии

```bash
cd /var/www/da-bridge
php artisan down
git pull                                   # или загрузите новые файлы
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear && php artisan optimize
supervisorctl restart da-reverb da-listen
systemctl reload php8.3-fpm
php artisan up
```

## 11. Оптимизация

**PHP-FPM** (`/etc/php/8.3/fpm/pool.d/www.conf`, для 2 ГБ RAM):

```ini
pm = dynamic
pm.max_children = 6
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

**OPcache** (`/etc/php/8.3/fpm/conf.d/99-app.ini`):

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0     ; после деплоя обязательно: systemctl reload php8.3-fpm
expose_php=Off
```

**MySQL** (`/etc/mysql/mysql.conf.d/zz-app.cnf`, для 2 ГБ RAM):

```ini
[mysqld]
innodb_buffer_pool_size=256M
performance_schema=OFF
```

**Прочее**

- Логи: `LOG_CHANNEL=daily` и `LOG_LEVEL=warning` не дадут одному файлу вырасти.
- Память: следите по `free -h`, `supervisorctl status`, `systemctl status php8.3-fpm`.
- Много стримеров (сотни): запускайте несколько воркеров `da:listen` с шардированием по `id % N`, подключите Redis для Reverb (`REVERB_SCALING_ENABLED=true`), увеличьте CPU.
- Сырые данные донатов хранятся в `donations.raw`. Если база разрастается, добавьте команду по расписанию, которая очищает это поле у старых записей.
- Статика (`site.css`) подключается с версией файла, поэтому кэшируется надолго и обновляется сама.

## 12. Безопасность

**Сервер**

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow OpenSSH && ufw allow 80 && ufw allow 443
ufw enable
```

- Порт 8080 наружу **не открывайте**: Reverb слушает `127.0.0.1`, доступ идёт только через nginx.
- SSH: только по ключам. В `/etc/ssh/sshd_config`: `PasswordAuthentication no`, `PermitRootLogin no`. Работайте под отдельным пользователем с `sudo`.
- `fail2ban` уже установлен: он блокирует перебор паролей SSH из коробки.
- Включите автообновления безопасности: `apt install unattended-upgrades && dpkg-reconfigure -plow unattended-upgrades`.

**Приложение**

- `APP_DEBUG=false` и `APP_ENV=production`: иначе ошибки покажут пути и секреты.
- Корень сайта должен указывать на `public/`, а `.env` не должен быть доступен из браузера (проверьте: `https://example.com/.env` отдаёт 404).
- Права: код принадлежит деплой-пользователю, запись нужна только в `storage/` и `bootstrap/cache/`.
- Админка: нестандартный логин, длинный пароль, ограничение по IP в nginx, лимит попыток входа (`throttle:5,1` уже в роутах).
- Секреты (Client Secret DA, `REVERB_APP_SECRET`, `APP_KEY`, пароли БД) не должны попадать в git и в чаты. Если секрет где-то засветился, перевыпустите его.
- Ограничение источников для Reverb: в `config/reverb.php` замените `'allowed_origins' => ['*']` на список ваших доменов, например `['overlays.streamelements.com', 'example.com']`. Проверьте на реальном виджете и в OBS: если виджет не подключается, посмотрите в консоли браузера, с какого Origin идёт запрос, и добавьте его.
- HSTS: после того как убедитесь, что HTTPS работает везде, добавьте в блок HTTPS `add_header Strict-Transport-Security "max-age=31536000" always;`.
- Токены виджета можно перевыпустить в кабинете или админке. Если подозреваете утечку, сделайте это сразу.

## 13. Бэкапы и мониторинг

Ежедневный бэкап БД (`crontab -e` от root):

```cron
0 3 * * * mysqldump --single-transaction da_bridge | gzip > /root/backups/da_bridge_$(date +\%F).sql.gz && find /root/backups -mtime +14 -delete
```

Раз в неделю копируйте бэкапы и **`APP_KEY`** на другой сервер или в облако (шифруйте архив: в бэкапе есть профили и данные донатов). Без `APP_KEY` бэкап токенов бесполезен.

Мониторинг: бесплатный UptimeRobot или аналог на `https://example.com/up`, плюс уведомления о падении. Периодически смотрите `storage/logs/` на `DA connect failed` и `DA error`.

## 14. Чек-лист перед запуском

- [ ] Домен, A-запись, сертификат, редирект HTTP → HTTPS
- [ ] `APP_DEBUG=false`, новые `REVERB_*`, новый Client Secret DA
- [ ] Redirect URI в DA совпадает с `.env`
- [ ] `SITE_OPERATOR` и `SITE_CONTACT_EMAIL` заполнены настоящими данными
- [ ] Политика и условия проверены, соответствуют вашей работе и законам вашей страны
- [ ] Порт 8080 закрыт, `ufw` включён, SSH только по ключам
- [ ] `/admin` ограничена IP, пароль сложный
- [ ] Supervisor: оба демона `RUNNING`, после перезагрузки сервера поднимаются сами
- [ ] Бэкап БД настроен, `APP_KEY` сохранён отдельно
- [ ] Проверен настоящий донат до виджета
