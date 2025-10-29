#!/bin/bash
echo "copio fncommerce"
rm /home/speleoalex/public_html/flatnux/flatnux/fncommerce
rm /home/speleoalex/public_html/flatnux/flatnux/misc/fndatabase/fnc_*
rm /home/speleoalex/public_html/flatnux/flatnux/sections/fncommerce
rm /home/speleoalex/public_html/flatnux/flatnux/controlcenter/sections/fnEcommerce


ln -s /home/speleoalex/public_html/flatnux/fncommerce/fncommerce /home/speleoalex/public_html/flatnux/flatnux/fncommerce
ln -s /home/speleoalex/public_html/flatnux/fncommerce/misc/fndatabase/* /home/speleoalex/public_html/flatnux/flatnux/misc/fndatabase/
ln -s /home/speleoalex/public_html/flatnux/fncommerce/sections/* /home/speleoalex/public_html/flatnux/flatnux/sections/
ln -s /home/speleoalex/public_html/flatnux/fncommerce/modules/* /home/speleoalex/public_html/flatnux/flatnux/modules/
ln -s /home/speleoalex/public_html/flatnux/fncommerce/controlcenter/sections/* /home/speleoalex/public_html/flatnux/flatnux/controlcenter/sections/


chown speleoalex -R /home/speleoalex/public_html/flatnux/
chmod 777 -R /home/speleoalex/public_html/flatnux/

