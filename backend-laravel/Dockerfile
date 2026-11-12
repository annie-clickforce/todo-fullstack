FROM php:8.1-cli-alpine

RUN apk add --no-cache \
    oniguruma-dev \
    libxml2-dev \
    curl-dev \
    && docker-php-ext-install pdo_mysql mbstring xml bcmath fileinfo curl

WORKDIR /app

COPY . .

EXPOSE 3011

CMD ["php", "-S", "0.0.0.0:3011", "-t", "public", "public/index.php"]
