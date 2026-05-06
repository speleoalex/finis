<?php exit(0);?>
<tables>
	<field>
		<name>unirecid</name>
		<primarykey>1</primarykey>
		<extra>autoincrement</extra>
		<frm_show>0</frm_show>
	</field>
	<field>
		<name>username</name>
		<frm_it>Nome utente</frm_it>
	</field>
	<field>
		<name>payments</name>
		<frm_it>Tipo di pagamento</frm_it>
	</field>
	<field>
		<name>shippingmethods</name>
		<frm_it>Metodo spedizione</frm_it>
	</field>
	<field>
		<name>total</name>
		<frm_it>Totale</frm_it>
	</field>
	<field>
		<name>time</name>
		<frm_it>Data ordine</frm_it>		
		<frm_type>datetime</frm_type>
	</field>	
	<field>
		<name>address</name>
		<frm_it>Indirizzo</frm_it>
		<frm_type>text</frm_type>
		<frm_cols>80</frm_cols>
	</field>	
	<field>
		<name>shippingaddress</name>
		<frm_it>Indirizzo Spedizione</frm_it>
		<frm_it>Shipping Address</frm_it>
		<frm_type>text</frm_type>
		<frm_cols>80</frm_cols>
	</field>	
	<field>
		<name>orderstatus</name>
		<frm_it>Stato ordine</frm_it>
		<frm_en>Order status</frm_en>
		<foreignkey>fnc_orderstatus</foreignkey>
		<fk_link_field>unirecid</fk_link_field>
		<fk_show_field>name</fk_show_field>
		<frm_required>1</frm_required>
	</field>
	
	<field>
		<name>notes</name>
		<type>text</type>
	</field>
	<field>
		<name>trackingnumber</name>
		<type>text</type>
	</field>
</tables>
