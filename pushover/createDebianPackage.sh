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

rm -Rf node-blue-node-pushover*
mkdir node-blue-node-pushover-$version
cp -R locales vendor *.php *.hni debian node-blue-node-pushover-$version
date=`LANG=en_US.UTF-8 date +"%a, %d %b %Y %T %z"`
echo "node-blue-node-pushover ($version-$revision) $1; urgency=low

  * See https://forum.homegear.eu

 -- Sathya Laufer <sathya@laufers.net>  $date" > node-blue-node-pushover-$version/debian/changelog
tar -zcpf node-blue-node-pushover_$version.orig.tar.gz node-blue-node-pushover-$version
cd node-blue-node-pushover-$version
debuild -us -uc
cd ..
rm -Rf node-blue-node-pushover-$version
if [ ! -d ../output ]; then
	mkdir ../output
fi
mv node-blue-node-pushover* ../output/