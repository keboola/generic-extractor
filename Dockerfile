FROM php:7.1

ARG COMPOSER_FLAGS="--no-interaction"
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    ssh \
    unzip \
    && rm -r /var/lib/apt/lists/* \
    && cd /root/ \
    && curl -sS https://getcomposer.org/installer | php \
    && ln -s /root/composer.phar /usr/local/bin/composer

## Add additional certificates
## Certificates downloaded from: https://www.digicert.com/digicert-root-certificates.htm
##
## From "man update-ca-certificates":
## > Furthermore all certificates with a .crt  extension found below
## > /usr/local/share/ca-certificates are also included as implicitly trusted.
RUN curl https://cacerts.digicert.com/GeoTrustRSACA2018.crt.pem --output /usr/local/share/ca-certificates/GeoTrustRSACA2018.crt \
  && curl https://cacerts.digicert.com/DigiCertGlobalRootCA.crt.pem --output /usr/local/share/ca-certificates/DigiCertGlobalRootCA.crt \
  && update-ca-certificates

WORKDIR /code

# Initialize
COPY php.ini /usr/local/etc/php/

## Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* /code/
# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader
# copy rest of the app
COPY . /code/
# run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD php /code/run.php --data=/data
