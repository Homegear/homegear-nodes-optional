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

rm -Rf node-blue-node-miele*
mkdir node-blue-node-miele-$version
cp -R locales *.php *.hni debian node-blue-node-miele-$version
date=`LANG=en_US.UTF-8 date +"%a, %d %b %Y %T %z"`
echo "node-blue-node-miele ($version-$revision) $1; urgency=low

  * See https://forum.homegear.eu

 -- Dr. Sathya Laufer <s.laufer@homegear.email>  $date" > node-blue-node-miele-$version/debian/changelog
tar -zcpf node-blue-node-miele_$version.orig.tar.gz node-blue-node-miele-$version
cd node-blue-node-miele-$version
debuild -us -uc
cd ..
rm -Rf node-blue-node-miele-$version
if [ ! -d ../output ]; then
	mkdir ../output
fi
mv node-blue-node-miele* ../output/
