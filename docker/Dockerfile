ARG PHP_VERSION=8.4

FROM composer:latest as composer
FROM php:${PHP_VERSION}-cli

ARG PHALCON_VERSION=5.9.3

RUN apt update -y && \
    apt install -y \
    apt-utils \
    gettext \
    git \
    libpq-dev \
    libzip-dev \
    nano \
    sudo \
    wget \
    zip

# Installing PHP Extensions
RUN pecl install phalcon-${PHALCON_VERSION} \
    xdebug

# Enabling PHP Extensions
RUN docker-php-ext-enable \
    opcache \
    phalcon \
    xdebug

# Installing Mailpit
RUN /bin/bash -c "curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh | bash" && \
    ln -s /usr/local/bin/mailpit /usr/sbin/sendmail

# Cleanup
RUN apt-get autoremove -y \
    && apt-get autoclean -y \
    && apt-get clean -y

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

CMD ["php"]