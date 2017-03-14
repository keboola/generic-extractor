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
	rm -f ../mock-server/samples/$1/out/tables/*
	docker-compose -f docker-compose-test.yml run -e "KBC_SAMPLES_DIR=$EXAMPLE_NAME" extractor
	if diff --brief --recursive ../mock-server/samples/$EXAMPLE_NAME/out/tables/ ../mock-server/samples/$EXAMPLE_NAME/_sample_out/ ; then
		printf "Example $EXAMPLE_NAME successfull.\n"
	else
		printf "Example $EXAMPLE_NAME failed.\n"
		diff --recursive ../mock-server/samples/$EXAMPLE_NAME/out/tables/ ../mock-server/samples/$EXAMPLE_NAME/_sample_out/
	fi
}

# Start mock server
# docker-compose -f docker-compose-mock.yml up -d

# Run example
run_example "1-simple-job"
run_example "2-array-in-object"
run_example "3-multiple-arrays-in-object"
run_example "4-array-in-nested-object"
run_example "5-two-arrays-in-nested-object"
run_example "6-simple-object"
run_example "7-nested-object"
run_example "8-single-object-in-array"
run_example "9-nested-array"
run_example "10-object-with-nested-array"
run_example "11-object-with-nested-object"
run_example "12-deeply-nested-object"
run_example "13-skip-flatten"
run_example "14-skip-flatten-nested"
run_example "15-skip-boolean"
run_example "16-inconsistent-object"
run_example "17-upgrading-array"
run_example "18-multiple-filters"
run_example "19-different-delimiter"
run_example "20-setting-delimiter-complex"

# Stop mock server
printf "\nAll examples successfull."
# docker stop mock-server
