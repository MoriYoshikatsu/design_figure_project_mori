#!/bin/sh

mkdir -p storage bootstrap/cache
chmod -R ug+rw storage bootstrap/cache

if [ ! -f "vendor/autoload.php" ]; then
    composer install --no-progress --no-interaction
fi

if [ ! -f ".env" ]; then
    cp .env.example .env
    # Docker環境用にPostgreSQL設定を適用
    sed -i 's/^DB_CONNECTION=.*/DB_CONNECTION=pgsql/' .env
    sed -i 's/^# DB_HOST=.*/DB_HOST=db/' .env
    sed -i 's/^DB_HOST=.*/DB_HOST=db/' .env
    sed -i 's/^# DB_PORT=.*/DB_PORT=5432/' .env
    sed -i 's/^DB_PORT=.*/DB_PORT=5432/' .env
    sed -i 's/^# DB_DATABASE=.*/DB_DATABASE=mfd/' .env
    sed -i 's/^DB_DATABASE=.*/DB_DATABASE=mfd/' .env
    sed -i 's/^# DB_USERNAME=.*/DB_USERNAME=mfd/' .env
    sed -i 's/^DB_USERNAME=.*/DB_USERNAME=mfd/' .env
    sed -i 's/^# DB_PASSWORD=.*/DB_PASSWORD=5011/' .env
    sed -i 's/^DB_PASSWORD=.*/DB_PASSWORD=5011/' .env
fi

exec "$@"
