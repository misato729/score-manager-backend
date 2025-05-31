# PHPとComposerが使えるベースのイメージ
FROM php:8.2-fpm

# 必要なパッケージをインストール（Laravelが動くために必要）
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Composerを使えるようにする（composerイメージからコピー）
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 作業ディレクトリを設定（ここにLaravelを配置）
WORKDIR /var/www

# プロジェクトの中身を全部コピー
COPY . .

# Laravelの依存パッケージをインストール（本番用）
RUN composer install --no-dev --optimize-autoloader

# 公開するポート（Renderで使う）
EXPOSE 10000

# Laravelを起動（HTTPサーバーを起動）
CMD php artisan serve --host=0.0.0.0 --port=10000

# Laravelの依存インストール
RUN composer install --no-dev --optimize-autoloader

# ✅ この2行を追加（パーミッション付与）
RUN chmod -R 775 storage bootstrap/cache
