# PHP + Apache image able to run ImpressCMS 2.0.4 and the ccenter module.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

# pdo_mysql / mysqli build against mysqlnd and need no extra system packages.
# (ImpressCMS only uses GD for optional image features, which are not exercised by the smoke tests.)
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql mysqli

# Surface every notice/deprecation so regressions are visible in the log.
RUN { echo 'error_reporting=E_ALL'; echo 'display_errors=Off'; echo 'log_errors=On'; \
      echo 'error_log=/var/log/php_errors.log'; echo 'memory_limit=256M'; \
      echo 'date.timezone=UTC'; } > /usr/local/etc/php/conf.d/zz-ccenter-test.ini \
 && touch /var/log/php_errors.log && chmod 666 /var/log/php_errors.log

RUN a2enmod rewrite
