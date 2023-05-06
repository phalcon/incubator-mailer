FROM composer:latest as composer
FROM php:7.4-fpm

ENV PHALCON_VERSION='5.2.1' \
    PHP_VERSION='7.4'

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
RUN pecl install phalcon-${PHALCON_VERSION}

# Enabling PHP Extensions
RUN docker-php-ext-enable \
    opcache \
    phalcon

# Installing Mailpit
RUN /bin/bash -c "curl -sL https://raw.githubusercontent.com/axllent/mailpit/develop/install.sh | bash" && \
    ln -s /usr/local/bin/mailpit /usr/sbin/sendmail

# Cleanup
RUN apt autoremove -y \
    && apt autoclean -y \
    && apt clean -y

# Composer
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

CMD ["php-fpm"]