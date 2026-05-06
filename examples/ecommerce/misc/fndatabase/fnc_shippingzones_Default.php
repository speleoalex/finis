<?php exit(0);?>
<tables>
	<field>
		<name>unirecid</name>
		<extra>autoincrement</extra>
		<frm_show>0</frm_show>
		<primarykey>1</primarykey>
	</field>
	<field>
		<name>country</name>
		<frm_it>Nazione</frm_it>
		<frm_en>Country</frm_en>
		<frm_es>Country</frm_es>
		<frm_de>Country</frm_de>
		<foreignkey>fnc_countries</foreignkey>
		<fk_link_field>unirecid</fk_link_field>
		<fk_show_field>name</fk_show_field>
	</field>
	<field>
		<name>zone</name>
		<frm_it>Zona</frm_it>
		<frm_en>Max height</frm_en>
		<frm_es>Max height</frm_es>
		<frm_de>Max height</frm_de>
		<foreignkey>fnc_zones</foreignkey>
		<fk_link_field>unirecid</fk_link_field>
		<fk_show_field>name</fk_show_field>
	</field>
	<field>
		<name>tablename</name>
		<frm_it>Tabella</frm_it>
		<frm_en>Tablename</frm_en>
		<frm_es>Tablename</frm_es>
		<frm_de>Tablename</frm_de>
		<frm_type>select</frm_type>
	</field>
<filename>shippingcostszones</filename>
</tables>