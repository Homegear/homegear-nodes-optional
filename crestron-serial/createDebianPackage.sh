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

rm -Rf node-blue-node-crestron-serial*
mkdir node-blue-node-crestron-serial-$version
cp -R crestron-serial-in crestron-serial-out crestron-serial-port configure cfg m4 aclocal.m4 autom4te.cache config.h.in Makefile.in *.png debian *.md revision.txt version.txt *.sh LICENSE bootstrap configure.ac Makefile.am node-blue-node-crestron-serial-$version
date=`LANG=en_US.UTF-8 date +"%a, %d %b %Y %T %z"`
echo "node-blue-node-crestron-serial ($version-$revision) $1; urgency=low

  * See https://forum.homegear.eu

 -- Homegear GmbH <contact@homegear.email>  $date" > node-blue-node-crestron-serial-$version/debian/changelog
tar -zcpf node-blue-node-crestron-serial_$version.orig.tar.gz node-blue-node-crestron-serial-$version
cd node-blue-node-crestron-serial-$version
debuild --no-lintian -us -uc
cd ..
rm -Rf node-blue-node-crestron-serial-$version
if [ ! -d ../output ]; then
	mkdir ../output
fi
mv node-blue-node-crestron-serial* ../output/
