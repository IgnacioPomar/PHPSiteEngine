FROM php:8.3-apache

#
# Herramientas de desarrollo
#
RUN apt-get update \
    && apt-get install -y \
        git \
        curl \
        unzip \
        less \
        vim \
        sudo \
        libicu-dev \
    && rm -rf /var/lib/apt/lists/*

#
# Extensiones PHP
#
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mysqli \
    intl

#
# Apache
#
RUN a2enmod rewrite

#
# Composer
#
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

#
# Xdebug
#
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

COPY .devcontainer/xdebug.ini \
    /usr/local/etc/php/conf.d/99-xdebug.ini

# El paquete Debian libphp-phpmailer instala su autoloader en /usr/share/php.
# El motor lo carga como libphp-phpmailer/autoload.php.
COPY .devcontainer/php-paths.ini \
    /usr/local/etc/php/conf.d/20-php-paths.ini

#
# Usuario de desarrollo
#
RUN useradd -m -s /bin/bash vscode \
    && echo "vscode ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/vscode \
    && chmod 0440 /etc/sudoers.d/vscode \
    && mkdir -p /home/vscode/.ssh \
    && chown -R vscode:vscode /home/vscode

WORKDIR /PHPSiteEngine
