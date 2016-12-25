#!/bin/bash

docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/generic-extractor quay.io/keboola/generic-extractor:$TRAVIS_TAG
docker tag keboola/generic-extractor quay.io/keboola/generic-extractor:latest
docker images
docker push quay.io/keboola/generic-extractor:$TRAVIS_TAG
docker push quay.io/keboola/generic-extractor:latest
