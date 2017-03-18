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
	docker-compose run -e "KBC_EXAMPLE_NAME=$EXAMPLE_NAME" extractor
	if diff --brief --recursive examples/$EXAMPLE_NAME/out/tables/ examples/$EXAMPLE_NAME/_sample_out/ ; then
		printf "Example $EXAMPLE_NAME successfull.\n"
	else
		printf "Example $EXAMPLE_NAME failed.\n"
		diff --recursive examples/$EXAMPLE_NAME/out/tables/ examples/$EXAMPLE_NAME/_sample_out/
	fi
}

# Start mock server
docker-compose build --force-rm --pull

# Run examples
# run_example "001-simple-job"
# run_example "002-array-in-object"
# run_example "003-multiple-arrays-in-object"
# run_example "004-array-in-nested-object"
# run_example "005-two-arrays-in-nested-object"
# run_example "006-simple-object"
# run_example "007-nested-object"
# run_example "008-single-object-in-array"
# run_example "009-nested-array"
# run_example "010-object-with-nested-array"
# run_example "011-object-with-nested-object"
# run_example "012-deeply-nested-object"
# run_example "013-skip-flatten"
# run_example "014-skip-flatten-nested"
# run_example "015-skip-boolean"
# run_example "016-inconsistent-object"
# run_example "017-upgrading-array"
# run_example "018-multiple-filters"
# run_example "019-different-delimiter"
# run_example "020-setting-delimiter-complex"
# run_example "021-basic-child-job"
# run_example "022-basic-child-job-datatype"
# run_example "023-child-job-nested-id"
# run_example "024-child-job-deeply-nested-id"
# run_example "025-naming-conflict"
# run_example "026-basic-deeper-nesting"
# run_example "027-basic-deeper-nesting-alternative"
# run_example "028-advanced-deep-nesting"
# run_example "029-simple-filter"
# run_example "030-not-like-filter"
# run_example "031-combined-filter"
run_example "032-multiple-combined-filter"

# Stop mock server
printf "\nAll examples successfull.\n"
docker stop mock-server
