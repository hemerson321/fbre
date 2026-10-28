#!/bin/bash
set -e
cd /var/www

# gera APP_KEY se não veio no ambiente
if [ -z "$APP_KEY" ]; then
  echo "APP_KEY ausente — gerando uma temporária. Defina APP_KEY nas variáveis do EasyPanel!"
  export APP_KEY=$(php artisan key:generate --show)
fi

# espera o banco
for i in $(seq 1 30); do
  php -r "try { new PDO('mysql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT')?:3306), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); exit(0);} catch (Exception \$e) { exit(1);} " && break
  echo "aguardando banco ($i)..."; sleep 2
done

php artisan storage:link >/dev/null 2>&1 || true
php artisan migrate --force
if [ "$SEED_ON_BOOT" = "1" ]; then php artisan db:seed --force || echo "AVISO: seed falhou (ignorado)"; fi
php artisan config:cache && php artisan route:cache && php artisan view:cache

# trava de seguranca: se alguma view compilada virar PHP invalido
# (ex.: diretiva @if colada -> "unexpected endif"), aborta o deploy
# em vez de servir 500 pra todo mundo. Pega a classe de erro do v23.
for f in storage/framework/views/*.php; do
  [ -e "$f" ] || continue
  if ! php -l "$f" >/dev/null 2>&1; then
    echo "=================================================="
    echo "DEPLOY ABORTADO: view compilada ficou invalida:"
    php -l "$f"
    echo "=================================================="
    exit 1
  fi
done
echo "OK: todas as views compiladas sao PHP valido."

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
