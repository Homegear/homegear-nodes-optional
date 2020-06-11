#!/bin/bash
if test -z version.txt; then
    echo "Please create a version file."
    exit 0;
fi
if test -z revision.txt; then
    echo "Please create a revision file."
    exit 0;
fi
if test -z $1; then
    echo "Please specify a distribution (i. e. wheezy)."
    exit 0;
fi
version=$(cat version.txt)
revision=$(cat revision.txt)

rm -Rf node-blue-node-dummy*
mkdir node-blue-node-dummy-$version
cp -R locales *.txt debian node-blue-node-dummy-$version
date=`LANG=en_US.UTF-8 date +"%a, %d %b %Y %T %z"`
echo "node-blue-node-dummy ($version-$revision) $1; urgency=low

  * See https://forum.homegear.eu

 -- Sathya Laufer <sathya@laufers.net>  $date" > node-blue-node-dummy-$version/debian/changelog
tar -zcpf node-blue-node-dummy_$version.orig.tar.gz node-blue-node-dummy-$version
cd node-blue-node-dummy-$version
debuild -us -uc
cd ..
rm -Rf node-blue-node-dummy-$version
if [ ! -d ../output ]; then
	mkdir ../output
fi
mv node-blue-node-dummy* ../output/
