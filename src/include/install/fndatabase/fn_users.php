<?xml version="1.0" encoding="UTF-8"?>
<?php exit(0); ?>
<tables>
    <field>
        <name>username</name>
        <frm_required>1</frm_required>
        <frm_i18n>username</frm_i18n>
        <frm_validator>is_alphanumeric</frm_validator>
        <frm_allowupdate>0</frm_allowupdate>
        <primarykey>1</primarykey>
        <size>128</size>
        <frm_validchars>01234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-@.</frm_validchars>
    </field>
    <field>
        <name>email</name>
        <frm_i18n>email</frm_i18n>
        <unique>1</unique>
        <frm_validator>FN_CheckMail</frm_validator>
        <showinprofile>1</showinprofile>
        <frm_required>1</frm_required>
        <frm_allowupdate>onlyadmin</frm_allowupdate>
    </field>
    <field>
        <name>passwd</name>
        <frm_i18n>password</frm_i18n>
        <frm_type>password</frm_type>
        <frm_retype>1</frm_retype>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>name</name>
        <frm_i18n>name</frm_i18n>
        <frm_help_i18n>Insert your name here</frm_help_i18n>
        <frm_i18n>name</frm_i18n>
        <showinprofile>1</showinprofile>
    </field>
    <field>
        <name>surname</name>
        <frm_i18n>surname</frm_i18n>
        <frm_help_i18n>Insert your surname here</frm_help_i18n>
        <showinprofile>1</showinprofile>
    </field>    
    <field>
        <name>link</name>
        <frm_i18n>link</frm_i18n>
        <frm_default>http://</frm_default>
        <showinprofile>1</showinprofile>
    </field>
    <field>
        <name>avatarimage</name>
        <type>varchar</type>
        <frm_type>select</frm_type>
        <frm_i18n>avatar</frm_i18n>
        <foreignkey>fn_avatars</foreignkey>
        <fk_link_field>filename</fk_link_field>
        <fk_show_field>filename</fk_show_field>
        <frm_show_image>filename</frm_show_image>
    </field>
    <field>
        <name>avatar</name>
        <type>image</type>
        <frm_i18n>upload custom avatar</frm_i18n>
        <frm_maximagesize>400</frm_maximagesize>
        <thumbsize>24</thumbsize>
    </field>
    <field>
        <name>level</name>
        <defaultvalue>0</defaultvalue>
        <frm_type>select</frm_type>
        <frm_options>0,1,2,3,4,5,6,7,8,9,10</frm_options>
        <frm_options_i18n>0,1,2,3,4,5,6,7,8,9,admin</frm_options_i18n>
        <frm_show>0</frm_show>
        <frm_setonlyadmin>1</frm_setonlyadmin>
    </field>
    <field>
        <name>group</name>
        <foreignkey>fn_groups</foreignkey>
        <frm_i18n>groups</frm_i18n>
        <fk_link_field>groupname</fk_link_field>
        <fk_show_field>groupname</fk_show_field>
        <frm_type>multicheck</frm_type>
        <frm_show>0</frm_show>
        <frm_setonlyadmin>1</frm_setonlyadmin>
    </field>
    <field>
        <name>ip</name>
        <frm_show>0</frm_show>
    </field>
    <field>
        <name>rnd</name>
        <frm_show>0</frm_show>
    </field>
    <field>
        <name>active</name>
        <frm_i18n>user enabled</frm_i18n>
        <type>check</type>
        <frm_type>check</frm_type>
        <frm_setonlyadmin>1</frm_setonlyadmin>
        <frm_show>1</frm_show>
    </field>
    <field>
        <name>registrationdate</name>
        <frm_show>0</frm_show>
        <frm_type>datetime</frm_type>
        <showinprofile>1</showinprofile>
        <frm_allowupdate>onlyadmin</frm_allowupdate>
    </field>
</tables>

