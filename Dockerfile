FROM php:8.4-cli

# Установка расширений
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Установка PHP расширений
RUN docker-php-ext-enable pdo_mysql

# Рабочая директория
WORKDIR /var/www

# Копирование файлов
COPY . .

# Установка зависимостей
RUN composer install --no-dev --optimize-autoloader

# Кэш и права
RUN chmod -R 777 var/cache var/log public/uploads

# Frontend build
RUN npm install && npm run build

EXPOSE 8000

CMD ["php", "bin/console", "server:run", "0.0.0.0:8000"]
