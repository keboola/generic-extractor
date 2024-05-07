FROM php:7.4-cli

ARG COMPOSER_FLAGS="--prefer-dist --no-interaction"
ARG DEBIAN_FRONTEND=noninteractive
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_PROCESS_TIMEOUT 3600

WORKDIR /code/

COPY docker/php.ini /usr/local/etc/php/php.ini
COPY docker/composer-install.sh /tmp/composer-install.sh

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        git \
        locales \
        unzip \
        ssh \
        netcat \
        wget \
        build-essential \
        libbluetooth-dev \
        libssl-dev \
        zlib1g-dev \
        libncurses5-dev \
        libncursesw5-dev \
        libreadline-dev \
        libsqlite3-dev \
        libffi-dev \
        uuid-dev \
        tk-dev \
        liblzma-dev \
        gnupg \
	&& rm -r /var/lib/apt/lists/* \
	&& sed -i 's/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen \
	&& locale-gen \
	&& chmod +x /tmp/composer-install.sh \
	&& /tmp/composer-install.sh

# Set environment variables for Python installation
ENV GPG_KEY 7169605F62C751356D054A26A821E680E5FA6305
ENV PYTHON_VERSION 3.12.3

# Download, verify, and install Python from source
RUN set -eux; \
    wget -O python.tar.xz "https://www.python.org/ftp/python/${PYTHON_VERSION%%[a-z]*}/Python-$PYTHON_VERSION.tar.xz"; \
    wget -O python.tar.xz.asc "https://www.python.org/ftp/python/${PYTHON_VERSION%%[a-z]*}/Python-$PYTHON_VERSION.tar.xz.asc"; \
    GNUPGHOME="$(mktemp -d)"; export GNUPGHOME; \
    gpg --batch --keyserver hkps://keys.openpgp.org --recv-keys "$GPG_KEY"; \
    gpg --batch --verify python.tar.xz.asc python.tar.xz; \
    gpgconf --kill all; \
    rm -rf "$GNUPGHOME" python.tar.xz.asc; \
    mkdir -p /usr/src/python; \
    tar -xvf python.tar.xz --strip-components=1 -C /usr/src/python; \
    rm python.tar.xz; \
    \
    cd /usr/src/python; \
    ./configure \
        --enable-optimizations \
        --enable-option-checking=fatal \
        --with-system-expat \
        --with-lto \
        --enable-loadable-sqlite-extensions; \
    make -j "$(nproc)"; \
    make install; \
    \
    cd /; \
    rm -rf /usr/src/python; \
    ldconfig

# Create useful symlinks for Python tools
RUN set -eux; \
    for src in idle3 pydoc3 python3 python3-config; do \
        dst="$(echo "$src" | tr -d 3)"; \
        [ -s "/usr/local/bin/$src" ]; \
        [ ! -e "/usr/local/bin/$dst" ]; \
        ln -svT "$src" "/usr/local/bin/$dst"; \
    done

# Install Node.js and set up symlinks
RUN set -eux; \
    NODE_VERSION="$(curl -fsSL https://nodejs.org/dist/latest/SHASUMS256.txt | head -n1 | awk '{ print $2 }' | awk -F - '{ print $2 }')" \
    ARCH= && dpkgArch="$(dpkg --print-architecture)"; \
    case "${dpkgArch##*-}" in \
        amd64) ARCH='x64';; \
        arm64) ARCH='arm64';; \
        *) echo "unsupported architecture"; exit 1 ;; \
    esac; \
    for key in $(curl -sL https://raw.githubusercontent.com/nodejs/docker-node/HEAD/keys/node.keys); do \
        gpg --batch --keyserver hkps://keys.openpgp.org --recv-keys "$key" || \
        gpg --batch --keyserver keyserver.ubuntu.com --recv-keys "$key"; \
    done; \
    curl -fsSLO --compressed "https://nodejs.org/dist/$NODE_VERSION/node-$NODE_VERSION-linux-$ARCH.tar.xz"; \
    curl -fsSLO --compressed "https://nodejs.org/dist/$NODE_VERSION/SHASUMS256.txt.asc"; \
    gpg --batch --decrypt --output SHASUMS256.txt SHASUMS256.txt.asc; \
    grep " node-$NODE_VERSION-linux-$ARCH.tar.xz\$" SHASUMS256.txt | sha256sum -c -; \
    tar -xJf "node-$NODE_VERSION-linux-$ARCH.tar.xz" -C /usr/local --strip-components=1 --no-same-owner; \
    rm "node-$NODE_VERSION-linux-$ARCH.tar.xz" SHASUMS256.txt.asc SHASUMS256.txt; \
    ln -s /usr/local/bin/node /usr/local/bin/nodejs

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
