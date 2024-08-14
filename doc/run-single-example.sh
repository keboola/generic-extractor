#!/bin/bash
set -e

run_example() {
    if [ -z "$1" ] ; then
        printf "No example name provided."
        exit 1
    else
        printf "\nRunning example $1\n"
    fi
    EXAMPLE_NAME=$1
    rm -rf examples/$1/out/*
    mkdir -p examples/$1/out/tables/
    docker compose run -e "KBC_EXAMPLE_NAME=$EXAMPLE_NAME" extractor
    if diff --brief --recursive examples/${EXAMPLE_NAME}/out/tables/ examples/${EXAMPLE_NAME}/_sample_out/ ; then
        printf "Example $EXAMPLE_NAME successful.\n"
    else
        printf "Example $EXAMPLE_NAME failed.\n"
        diff --recursive examples/${EXAMPLE_NAME}/out/tables/ examples/${EXAMPLE_NAME}/_sample_out/
    fi
}

# Start mock server
docker compose build --force-rm --pull

# Run example
run_example $1

# Stop mock server
printf "\nAll examples successfull.\n"
docker stop mock-server
