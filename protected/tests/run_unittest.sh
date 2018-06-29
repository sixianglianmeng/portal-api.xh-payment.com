#!/bin/bash
OUTPUT_DIR=$1
echo "begin run test case"

source $PWD/test_jenkins.sh $OUTPUT_DIR

if [ $STATUS -ne 0 ]
then
    echo "run test case failed"
else
    echo "run test case OK"
fi
echo "end run test case"
