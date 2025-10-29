<?php exit(0); ?>
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
        <frm_show>onlyadmin</frm_show>
        <frm_setonlyadmin>1</frm_setonlyadmin>
    </field>
    <field>
        <name>type</name>
        <frm_it>Tipo</frm_it>
        <frm_en>Type</frm_en>
        <frm_required>1</frm_required>
        <frm_type>radio</frm_type>
        <frm_options>private,company</frm_options>
        <frm_options_it>Privato,Azienda</frm_options_it>
        <frm_options_en>Private,Company</frm_options_en>
        <frm_options_enable>lastname|firstname|fiscalcode,vat|companyname</frm_options_enable>
        <frm_options_disable>vat|companyname,lastname|firstname|fiscalcode</frm_options_disable>                
        <frm_group>shipping address</frm_group>
        <frm_group_it>Dati anagrafici</frm_group_it>
        <frm_group_en>Personal data for shipping</frm_group_en>
        <frm_default>private</frm_default>
    </field>
    <field>
        <name>firstname</name>
        <frm_it>Nome</frm_it>
        <frm_en>Name</frm_en>
        <frm_es>Nombre</frm_es>
        <frm_de>Name</frm_de>
        <frm_required_condition>type = "private"</frm_required_condition>
    </field>
    <field>
        <name>lastname</name>
        <frm_it>Cognome</frm_it>
        <frm_en>Last name</frm_en>
        <frm_es>Last name</frm_es>
        <frm_de>Last name</frm_de>
        <frm_required_condition>type = "private"</frm_required_condition>
    </field>
    <field>
        <name>companyname</name>
        <frm_it>Societ&agrave;</frm_it>
        <frm_en>Company name</frm_en>
    </field>
    <field>
        <name>fiscalcode</name>
        <frm_it>Codice fiscale *</frm_it>
        <frm_en>Fiscal Code *</frm_en>
        <frm_required_condition>type = "private"</frm_required_condition>
        <frm_verify>fnc_validate_fiscal_code</frm_verify>
        <frm_help_it>Inserisci un codice fiscale valido (5-20 caratteri alfanumerici)</frm_help_it>
        <frm_help_en>Enter a valid fiscal code (5-20 alphanumeric characters)</frm_help_en>
    </field>
    <field>
        <name>vat</name>
        <frm_it>Partita iva *</frm_it>
        <frm_en>VAT *</frm_en>
        <frm_endgroup></frm_endgroup>
        <frm_required_condition>type = "company"</frm_required_condition>
        <frm_verify>fnc_validate_fiscal_code</frm_verify>
        <frm_help_it>Inserisci una partita IVA valida (5-20 caratteri alfanumerici)</frm_help_it>
        <frm_help_en>Enter a valid VAT number (5-20 alphanumeric characters)</frm_help_en>
    </field>	

    <field>
        <name>address</name>
        <frm_it>Indirizzo</frm_it>
        <frm_en>Address</frm_en>
        <frm_required>1</frm_required>
        <frm_group>shipping address</frm_group>
        <frm_group_it>Indirizzo acquirente</frm_group_it>
        <frm_group_en>shipping address</frm_group_en>
    </field>
    <field>
        <name>city</name>
        <frm_it>Città</frm_it>
        <frm_en>City</frm_en>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>country</name>
        <frm_it>Stato</frm_it>
        <frm_en>State</frm_en>
        <foreignkey>fnc_countries</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>zone</name>
        <frm_it>Provincia</frm_it>
        <frm_en>State</frm_en>
        <foreignkey>fnc_zones</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <fk_filter_field>country=country</fk_filter_field>
        <frm_required>0</frm_required>
    </field>

    <field>
        <name>zip</name>
        <frm_it>CAP</frm_it>
        <frm_en>ZIP</frm_en>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>telephone</name>
        <frm_it>Telefono</frm_it>
        <frm_en>Telephone</frm_en>		
        <frm_required>0</frm_required>
        <frm_endgroup></frm_endgroup>
    </field>

    <field>
        <name>doshippingaddress</name>
        <frm_it>Consegna</frm_it>
        <frm_en>Consegna</frm_en>
        <frm_required>1</frm_required>
        <frm_type>radio</frm_type>
        <frm_options>no,yes</frm_options>
        <frm_options_enable>,shippingzone|shippingtelephone|shippingzip|shippingname|shippingaddress|shippingcity|shippingtelephone|shippingzip</frm_options_enable>
        <frm_options_disable>shippingzone|shippingtelephone|shippingzip|shippingname|shippingaddress|shippingcity|shippingtelephone|shippingzip,</frm_options_disable>
        <frm_options_it>Spedisci e fattura allo stesso indirizzo,Spedisci ad un altro indirizzo </frm_options_it>
        <frm_options_en>This,Other</frm_options_en>
        <frm_default>no</frm_default>
    </field>
    <field>
        <name>shippingname</name>
        <frm_it>Destinatario</frm_it>
        <frm_en>Name</frm_en>
        <frm_es>Nombre</frm_es>
        <frm_de>Name</frm_de>
        <frm_group>shipping address</frm_group>
        <frm_group_it>Spedisci a questo indirizzo</frm_group_it>
        <frm_group_en>shipping address</frm_group_en>
    </field>
    <field>
        <name>shippingaddress</name>
        <frm_it>Indirizzo</frm_it>
        <frm_en>Address</frm_en>
    </field>
    <field>
        <name>shippingcity</name>
        <frm_it>Città</frm_it>
        <frm_en>City</frm_en>
        <frm_required_condition>doshippingaddress = "yes"</frm_required_condition>
    </field>
    <field>
        <name>shippingcountry</name>
        <frm_it>Stato</frm_it>
        <frm_en>State</frm_en>
        <foreignkey>fnc_countries</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <frm_required_condition>doshippingaddress = "yes"</frm_required_condition>
    </field>
    <field>
        <name>shippingzone</name>
        <frm_it>Provincia</frm_it>
        <frm_en>State</frm_en>
        <foreignkey>fnc_zones</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <fk_filter_field>country=shippingcountry</fk_filter_field>
    </field>
    <field>
        <name>shippingzip</name>
        <frm_it>CAP</frm_it>
        <frm_en>ZIP</frm_en>
        <frm_required_condition>doshippingaddress = "yes"</frm_required_condition>
    </field>
    <field>
        <name>shippingtelephone</name>
        <frm_it>Telefono</frm_it>
        <frm_en>Telephone</frm_en>		
        <frm_required>0</frm_required>
        <frm_endgroup></frm_endgroup>
    </field>

</tables>