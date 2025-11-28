# 1) PHP-FPM 8.2
FROM php:8.2-fpm

# 2) 필요한 패키지 & PHP 확장
RUN apt-get update && apt-get install -y --no-install-recommends \
      git unzip libzip-dev libicu-dev \
  && docker-php-ext-install pdo_mysql mysqli intl zip opcache \
  && rm -rf /var/lib/apt/lists/*

# 3) Composer 바이너리 (공식 composer 이미지에서 복사)
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# 4) 워킹 디렉터리
WORKDIR /var/www/html

# 5) 권장: www-data에 권한 맞추기 (볼륨 마운트 시 퍼미션 문제 예방)
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data
