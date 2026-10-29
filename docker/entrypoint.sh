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

# checagem: alguma view compilada virou PHP invalido? (ex.: diretiva @if colada -> "unexpected endif")
# Por padrao so AVISA (nao derruba o site). Para abortar o deploy, defina DEPLOY_STRICT=1
# no .env -- recomendado SO depois de configurar um healthcheck no EasyPanel, senao
# o container quebrado substitui o bom e o site fica fora do ar.
view_quebrada=0
for f in storage/framework/views/*.php; do
  [ -e "$f" ] || continue
  if ! php -l "$f" >/dev/null 2>&1; then
    view_quebrada=1
    echo "=================================================="
    echo "VIEW COMPILADA INVALIDA: $f"
    php -l "$f" || true
    echo "=================================================="
  fi
done
if [ "$view_quebrada" = "1" ]; then
  if [ "$DEPLOY_STRICT" = "1" ]; then
    echo "DEPLOY_STRICT=1 -> abortando o deploy."
    exit 1
  fi
  echo "AVISO: ha view(s) com PHP invalido, mas seguindo o boot (DEPLOY_STRICT!=1)."
else
  echo "OK: todas as views compiladas sao PHP valido."
fi

chown -R www-data:www-data storage bootstrap/cache

exec "$@"
