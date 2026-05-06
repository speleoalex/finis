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
        <frm_i18n>username</frm_i18n>
        <frm_show>onlyadmin</frm_show>
        <frm_setonlyadmin>1</frm_setonlyadmin>
    </field>
    <field>
        <name>type</name>
        <frm_i18n>buyer type</frm_i18n>
        <frm_required>1</frm_required>
        <frm_type>radio</frm_type>
        <frm_options>private,company</frm_options>
        <frm_options_i18n>private person,company</frm_options_i18n>
        <frm_options_enable>lastname|firstname|fiscalcode,vat|companyname</frm_options_enable>
        <frm_options_disable>vat|companyname,lastname|firstname|fiscalcode</frm_options_disable>
        <frm_group>shipping address</frm_group>
        <frm_group_i18n>personal data</frm_group_i18n>
        <frm_default>private</frm_default>
    </field>
    <field>
        <name>firstname</name>
        <frm_i18n>first name</frm_i18n>
        <frm_required_condition>type = "private"</frm_required_condition>
    </field>
    <field>
        <name>lastname</name>
        <frm_i18n>last name</frm_i18n>
        <frm_required_condition>type = "private"</frm_required_condition>
    </field>
    <field>
        <name>companyname</name>
        <frm_i18n>company name</frm_i18n>
    </field>
    <field>
        <name>fiscalcode</name>
        <frm_i18n>fiscal code</frm_i18n>
        <frm_required_condition>type = "private"</frm_required_condition>
        <frm_verify>fnc_validate_fiscal_code</frm_verify>
        <frm_help_i18n>enter a valid fiscal code</frm_help_i18n>
    </field>
    <field>
        <name>vat</name>
        <frm_i18n>VAT number</frm_i18n>
        <frm_endgroup></frm_endgroup>
        <frm_required_condition>type = "company"</frm_required_condition>
        <frm_verify>fnc_validate_fiscal_code</frm_verify>
        <frm_help_i18n>enter a valid VAT number</frm_help_i18n>
    </field>

    <field>
        <name>address</name>
        <frm_i18n>address</frm_i18n>
        <frm_required>1</frm_required>
        <frm_group>shipping address</frm_group>
        <frm_group_i18n>shipping and billing address</frm_group_i18n>
    </field>
    <field>
        <name>city</name>
        <frm_i18n>city</frm_i18n>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>country</name>
        <frm_i18n>country</frm_i18n>
        <foreignkey>fnc_countries</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>zone</name>
        <frm_i18n>province</frm_i18n>
        <foreignkey>fnc_zones</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <fk_filter_field>country=country</fk_filter_field>
        <frm_required>0</frm_required>
    </field>

    <field>
        <name>zip</name>
        <frm_i18n>ZIP code</frm_i18n>
        <frm_required>1</frm_required>
    </field>
    <field>
        <name>telephone</name>
        <frm_i18n>telephone</frm_i18n>
        <frm_required>0</frm_required>
        <frm_endgroup></frm_endgroup>
    </field>

    <field>
        <name>doshippingaddress</name>
        <frm_i18n>delivery</frm_i18n>
        <frm_required>1</frm_required>
        <frm_type>radio</frm_type>
        <frm_options>no,yes</frm_options>
        <frm_options_enable>,shippingzone|shippingtelephone|shippingzip|shippingname|shippingaddress|shippingcity|shippingtelephone|shippingzip</frm_options_enable>
        <frm_options_disable>shippingzone|shippingtelephone|shippingzip|shippingname|shippingaddress|shippingcity|shippingtelephone|shippingzip,</frm_options_disable>
        <frm_options_i18n>ship and bill to the same address,ship to a different address</frm_options_i18n>
        <frm_default>no</frm_default>
    </field>
    <field>
        <name>shippingname</name>
        <frm_i18n>recipient</frm_i18n>
        <frm_group>shipping address</frm_group>
        <frm_group_i18n>alternative shipping address</frm_group_i18n>
    </field>
    <field>
        <name>shippingaddress</name>
        <frm_i18n>address</frm_i18n>
    </field>
    <field>
        <name>shippingcity</name>
        <frm_i18n>city</frm_i18n>
        <frm_required_condition>doshippingaddress = "yes"</frm_required_condition>
    </field>
    <field>
        <name>shippingcountry</name>
        <frm_i18n>country</frm_i18n>
        <foreignkey>fnc_countries</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <frm_required_condition>doshippingaddress = "yes"</frm_required_condition>
    </field>
    <field>
        <name>shippingzone</name>
        <frm_i18n>province</frm_i18n>
        <foreignkey>fnc_zones</foreignkey>
        <fk_link_field>unirecid</fk_link_field>
        <fk_show_field>name</fk_show_field>
        <fk_filter_field>country=shippingcountry</fk_filter_field>
    </field>
    <field>
        <name>shippingzip</name>
        <frm_i18n>ZIP code</frm_i18n>
        <frm_required_condition>doshippingaddress = "yes"</frm_required_condition>
    </field>
    <field>
        <name>shippingtelephone</name>
        <frm_i18n>telephone</frm_i18n>
        <frm_required>0</frm_required>
        <frm_endgroup></frm_endgroup>
    </field>

</tables>
