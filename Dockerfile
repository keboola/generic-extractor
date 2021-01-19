FROM php:7.4-cli

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        locales \
        unzip \
        ssh \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

ENV LANGUAGE=en_US.UTF-8
ENV LANG=en_US.UTF-8
ENV LC_ALL=en_US.UTF-8

## Add additional certificates
## Certificates downloaded from: https://www.digicert.com/digicert-root-certificates.htm
##
## From "man update-ca-certificates":
## > Furthermore all certificates with a .crt  extension found below
## > /usr/local/share/ca-certificates are also included as implicitly trusted.
RUN curl https://cacerts.digicert.com/GeoTrustRSACA2018.crt.pem --output /usr/local/share/ca-certificates/GeoTrustRSACA2018.crt \
    && curl https://cacerts.digicert.com/DigiCertGlobalRootCA.crt.pem --output /usr/local/share/ca-certificates/DigiCertGlobalRootCA.crt \
    && update-ca-certificates


## Composer - deps always cached unless changed
# First copy only composer files
COPY composer.* /code/

# Download dependencies, but don't run scripts or init autoloaders as the app is missing
RUN composer install $COMPOSER_FLAGS --no-scripts --no-autoloader

# Copy rest of the app
COPY . /code/

# Run normal composer - all deps are cached already
RUN composer install $COMPOSER_FLAGS

CMD ["php", "/code/src/run.php"]
