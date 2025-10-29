<?php exit(0);?>
<tables>
	<field>
		<name>unirecid</name>
		<primarykey>1</primarykey>
		<extra>autoincrement</extra>
		<frm_show>0</frm_show>
	</field>
	<field>
		<name>code</name>
		<frm_i18n>code</frm_i18n>
	</field>
	<field>
		<name>max_uses</name>
		<frm_i18n>maximum number of uses</frm_i18n>
		<frm_default>1</frm_default>
	</field>
	<field>
		<name>startdate</name>
		<frm_en>Publication start date</frm_en>
		<frm_i18n>validity start</frm_i18n>
		<frm_type>datetime</frm_type>
		<frm_dateformat>y-mm-dd 00:00:00</frm_dateformat>
	</field>
	<field>
		<name>enddate</name>
		<frm_i18n>validity end</frm_i18n>
		<frm_type>datetime</frm_type>
		<frm_dateformat>y-mm-dd 00:00:00</frm_dateformat>
	</field>
	<field>
		<name>discount</name>
		<frm_i18n>discount</frm_i18n>
		<frm_default>0</frm_default>	
	</field>
	<field>
		<name>minprice</name>
		<frm_i18n>minimum price</frm_i18n>
		<frm_default>0</frm_default>	
	</field>
	
	<field>
		<name>enabled</name>
		<frm_type>radio</frm_type>
		<frm_options>1,0</frm_options>
		<frm_options_i18n>yes,no</frm_options_i18n>
		<frm_default>1</frm_default>		
	</field>
</tables>