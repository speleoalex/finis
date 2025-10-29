#!/bin/bash

find . -iname \*~ | xargs rm -f 
for i in $(find . -type f); do 
	if echo "$i" | egrep -qe "\.(php|sh)$"; then 
		chmod a+x $i
	else
		chmod a-x $i 
	fi; 
done
pwd=$(basename $PWD) 

echo \<?#$pwd-$(date +"%F")?\> > VERSION

cd ..
if [ -d "$pwd" ]; then
	name=$pwd-$(date +"%F").tar.gz
	declare n=0
	while [ -e $name ]; do
		let n++
		name=$pwd-$(date +"%F").$n.tar.gz
	done
#	tar cvzf $name $pwd
fi

if [ -d "$pwd" ]; then
	name=$pwd-$(date +"%F")-with-sample-data.zip
	namenotable=$pwd-$(date +"%F")-no-sample-data.zip
	namenodb=$pwd-$(date +"%F")-no-db.zip
	declare n=0
	while [ -e $name ]; do
		let n++
		name=$pwd-$(date +"%F").$n-with-sample-data.zip
		namenotable=$pwd-$(date +"%F").$n-no-sample-data.zip
		namenodb=$pwd-$(date +"%F").$n-no-db.zip
	done
	zip -r $name $pwd
	
	zip -r $namenotable -x@fncommerce/exlude.lst $pwd
	zip -r $namenodb -x@fncommerce/exludedb.lst $pwd
	
fi



