FROM php:8.1.11-fpm-alpine
WORKDIR /var/www/html

COPY ./boot.sh /sbin/boot.sh
COPY service /etc/service

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories \
 && apk update \
 # setup runit
 && chmod 700 /sbin/boot.sh \
 && chmod 700 -R /etc/service/ \
 && apk add autoconf nginx curl tzdata dcron runit \
        php8-ctype \
        php8-curl \
        php8-dom \
        php8-exif \
        php8-fileinfo \
        php8-fpm \
        php8-iconv \
        php8-intl \
        php8-mbstring \
        php8-opcache \
        php8-openssl \
        php8-pecl-imagick \
        php8-phar \
        php8-session \
        php8-simplexml \
        php8-soap \
        php8-xml \
        php8-xmlreader \
        php8-zip \
        php8-zlib \
        php8-pdo \
        php8-xmlwriter \
        php8-tokenizer \
        php8-pdo_sqlite \
 # gd
 && apk add --no-cache --virtual .gd-deps \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        freetype-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) gd \
 && docker-php-ext-install pdo pdo_mysql \
 # redis
 && apk add --no-cache --virtual .build-deps \
		gcc \
		libc-dev \
		make \
		openssl-dev \
		pcre-dev \
		zlib-dev \
		linux-headers \
		gnupg \
		libxslt-dev \
		gd-dev \
		geoip-dev \
		perl-dev \
 && pecl install -o -f redis \
 && rm -rf /tmp/pear \
 && docker-php-ext-enable redis \
 #zip
 && apk add --no-cache zip libzip-dev \
 && docker-php-ext-configure zip \
 && docker-php-ext-install zip \
 # clean
 && apk del .build-deps \
 && apk del .gd-deps \
 # nginx
 && ln -sf /dev/stdout /var/log/nginx/access.log \
 && ln -sf /dev/stderr /var/log/nginx/error.log


# 配置參數
ARG IMAGE_ENV=development

COPY php.conf/php.ini-${IMAGE_ENV} $PHP_INI_DIR/php.ini

COPY --chown=nginx:nginx nginx/nginx.conf /etc/nginx/nginx.conf
COPY --chown=nginx:nginx nginx/app.conf /etc/nginx/http.d/default.conf

COPY --chown=www-data src/ ./
EXPOSE 8080

CMD ["/sbin/boot.sh"]