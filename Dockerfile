FROM php:7.0

ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apt-get update && apt-get install -y --no-install-recommends \
	git \
	unzip \
	&& rm -r /var/lib/apt/lists/* \
	&& cd /root/ \
	&& curl -sS https://getcomposer.org/installer | php \
  	&& ln -s /root/composer.phar /usr/local/bin/composer

WORKDIR /home

# Initialize
COPY . /home/
COPY php.ini /usr/local/etc/php/

RUN composer install --no-interaction

ENTRYPOINT php ./run.php --data=/data
