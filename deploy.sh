#!/bin/bash
set -e

docker images
docker pull quay.io/keboola/developer-portal-cli-v2:latest
export REPOSITORY=`docker run --rm  \
    -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest ecr:get-repository ${KBC_DEVELOPERPORTAL_VENDOR} ${KBC_DEVELOPERPORTAL_APP}`
docker tag ${KBC_APP_REPOSITORY}:latest ${REPOSITORY}:${TRAVIS_TAG}
docker tag ${KBC_APP_REPOSITORY}:latest ${REPOSITORY}:latest
eval $(docker run --rm \
    -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest ecr:get-login ${KBC_DEVELOPERPORTAL_VENDOR} ${KBC_DEVELOPERPORTAL_APP})
docker push ${REPOSITORY}:${TRAVIS_TAG}
docker push ${REPOSITORY}:latest

# Deploy to KBC -> update the tag in Keboola Developer Portal (needs $KBC_DEVELOPERPORTAL_VENDOR & $KBC_DEVELOPERPORTAL_APP)
docker run --rm -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
    quay.io/keboola/developer-portal-cli-v2:latest update-app-repository ${KBC_DEVELOPERPORTAL_VENDOR} ${KBC_DEVELOPERPORTAL_APP} ${TRAVIS_TAG} \
    ecr ${REPOSITORY}

# Update templated components using generic image
declare -a components=(
    "ex-adform"
    "ex-impactradius"
    "keboola.ex-babelforce"
    "keboola.ex-flexibee"
    "keboola.ex-gcalendar"
    "keboola.ex-hubspot"
    "keboola.ex-intercom-v2"
    "keboola.ex-mailchimp"
    "keboola.ex-mediamath"
    "keboola.ex-pingdom"
    "keboola.ex-pipedrive"
    "keboola.ex-portadi"
    "keboola.ex-github"
    "keboola.ex-slack"
    "keboola.ex-stripe"
    "keboola.ex-telfa"
    "keboola.ex-zendesk"
)

for component in "${components[@]}"
do
   docker run --rm -e KBC_DEVELOPERPORTAL_USERNAME -e KBC_DEVELOPERPORTAL_PASSWORD -e KBC_DEVELOPERPORTAL_URL \
       quay.io/keboola/developer-portal-cli-v2:latest update-app-repository --configuration-format=json \
       ${KBC_DEVELOPERPORTAL_VENDOR} ${component} ${TRAVIS_TAG} \
       ecr ${REPOSITORY}
done

# deploy to Quay public repository
docker login -u="$QUAY_USERNAME" -p="$QUAY_PASSWORD" quay.io
docker tag keboola/generic-extractor quay.io/keboola/generic-extractor:${TRAVIS_TAG}
docker tag keboola/generic-extractor quay.io/keboola/generic-extractor:latest
docker images
docker push quay.io/keboola/generic-extractor:${TRAVIS_TAG}
docker push quay.io/keboola/generic-extractor:latest
