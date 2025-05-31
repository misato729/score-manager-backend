# PHPとComposerが使えるベースのイメージ
FROM php:8.2-fpm

# 必要なパッケージをインストール
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_pgsql zip

# Composerを使えるようにする
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリを設定
WORKDIR /var/www

# Laravelのプロジェクトファイルをコピー
COPY . .

# Laravelの依存パッケージをインストール（本番用）
RUN composer install --no-dev --optimize-autoloader

# パーミッション調整
RUN chmod -R 775 storage bootstrap/cache

# 公開ポート
EXPOSE 10000

# 設定キャッシュクリア
RUN php artisan config:clear && php artisan route:clear && php artisan view:clear

# マイグレーションしてからサーバー起動
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=10000
