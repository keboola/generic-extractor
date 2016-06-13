# VERSION 1.6.8
FROM php:7.0
RUN apt-get update -q && apt-get install unzip git -y
RUN cd \
  && curl -sS https://getcomposer.org/installer | php \
  && ln -s /root/composer.phar /usr/local/bin/composer

MAINTAINER Ondrej Vana <ondrej.vana@keboola.com>

WORKDIR /home

# Initialize
COPY . /home/

COPY php.ini /usr/local/etc/php/
RUN composer install --no-interaction --no-dev

ENTRYPOINT php ./run.php --data=/data