<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0); ?>
<tables>
    <field>
        <name>id</name>
        <primarykey>1</primarykey>
        <extra>autoincrement</extra>
        <frm_show>0</frm_show>
    </field>
    <field>
        <name>name</name>
    </field>
    <field>
        <name>avatar</name>
        <type>image</type>
        <frm_i18n>upload provider image</frm_i18n>
        <frm_maximagesize>1200</frm_maximagesize>
        <thumbsize>192</thumbsize>
    </field>
    <field>
        <name>client_id</name>
    </field>
    <field>
        <name>client_secret</name>
    </field>
    <field>
        <name>auth_url</name>
    </field>
    <field>
        <name>token_url</name>
    </field>
    <field>
        <name>userinfo_url</name>
    </field>
    <field>
        <name>scope</name>
    </field>
    <field>
        <name>enabled</name>
        <frm_i18n>enabled</frm_i18n>
        <type>check</type>
        <frm_type>check</frm_type>
        <frm_setonlyadmin>1</frm_setonlyadmin>
        <frm_show>1</frm_show>
    </field>



</tables>

