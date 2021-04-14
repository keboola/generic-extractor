FROM perl:5

# Install deps
RUN curl -L http://cpanmin.us | perl - App::cpanminus && \
    cpanm Mojolicious && \
    cpanm Bytes::Random::Secure

COPY ./JsonTests.pl /src/JsonTests.pl
WORKDIR /src

EXPOSE 80

CMD ["perl", "JsonTests.pl", "prefork"]
