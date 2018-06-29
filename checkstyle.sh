#!/bin/bash

app=`pwd`
checkstyle --app=$app $@
