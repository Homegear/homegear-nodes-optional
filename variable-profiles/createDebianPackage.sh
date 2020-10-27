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

rm -Rf node-blue-node-variable-profiles*
mkdir node-blue-node-variable-profiles-$version
cp -R locales *.php *.hni debian node-blue-node-variable-profiles-$version
date=`LANG=en_US.UTF-8 date +"%a, %d %b %Y %T %z"`
echo "node-blue-node-variable-profiles ($version-$revision) $1; urgency=low

  * See https://forum.homegear.eu

 -- Sathya Laufer <sathya@laufers.net>  $date" > node-blue-node-variable-profiles-$version/debian/changelog
tar -zcpf node-blue-node-variable-profiles_$version.orig.tar.gz node-blue-node-variable-profiles-$version
cd node-blue-node-variable-profiles-$version
debuild -us -uc
cd ..
rm -Rf node-blue-node-variable-profiles-$version
if [ ! -d ../output ]; then
	mkdir ../output
fi
mv node-blue-node-variable-profiles* ../output/