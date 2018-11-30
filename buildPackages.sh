#!/bin/bash

if test -z $1; then
    echo "Please specify a distribution (i. e. wheezy)."
    exit 0;
fi

for directory in */ ; do
	if [ -f "${directory}createDebianPackage.sh" ]; then
		echo "Building $directory..."
		cd $directory
		isBinary=$(find . -name *.cpp | wc -l)
		if [ $isBinary -gt 0 ]; then
			./createDebianPackage.sh $1 amd64
			./createDebianPackage.sh $1 i386
			./createDebianPackage.sh $1 armhf
			./createDebianPackage.sh $1 arm64
		else
			./createDebianPackage.sh $1
		fi
		cd ..
	fi
done
