<?php

/**
 * @package flatnux_controlcenter_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
include ("modules/fncommerce/functions/fncommerce.php");
FN_LoadMessagesFolder("fncommerce/");

// FN_XmltableEditor($tablename, $dbname = "fndatabase", $path = "", $functioninsert = "", $restr = false,$table=false,$fields=false)
//FN_XmltableEditor("fnc_products","fndatabase","","insertproducts",false,false,"name|model|photo1|status|quantity|price",array("price"=>"fnc_format_price"));

FNCC_XMETATableEditor("fnc_products", array("price" => "fnc_format_price",
    "fields" => "unirecid|name|model|photo1|status|quantity|price",
    "functioninsert" => "insertproducts",
    "textviewlist" => "<img style=\"vertical-align:middle;border:0px;\" alt=\"\"  src=\"" . FN_FromTheme("images/left.png") . "\" />&nbsp;" . FN_Translate("go to products list"),
    "textnew" => "<img style=\"vertical-align:middle;border:0px;\" alt=\"\"  src=\"" . FN_FromTheme("images/add.png") . "\" />" . FN_Translate("add new product") . "",
    "list_onsave" => false
));

function insertproducts($newvalues)
{
    $opmod = FN_GetParam("op___xdb_fnc_products", $_GET, "html");
    
    $pid = isset($newvalues['unirecid']) ? $newvalues['unirecid'] : "";
    if ($pid == "")
        $pid = FN_GetParam("pk___xdb_fnc_products", $_GET, "html");
    switch ($opmod)
    {
        case "insnew" :
            $categories = fnc_getcategories(false, false);
            $p_categories = fnc_getcategoriesbyproduct($pid);
            $categories_in_product = array();
            foreach ($p_categories as $c)
            {
                $categories_in_product[$c['unirecid']] = $c;
            }
            if (isset($_POST["save___xdb_fnc_products"]))
            {
                $catselect = false;
                foreach ($categories as $category)
                {
                    $enablecategory = FN_GetParam("category_" . $category['unirecid'], $_POST, "html");
                    if ($enablecategory == 1)
                    {
                        $catselect = true;
                        fnc_add_product_in_category($newvalues['unirecid'], $category['unirecid']);
                    }
                    else
                        fnc_delete_product_from_category($newvalues['unirecid'], $category['unirecid']);
                }
                if (!$catselect)
                    FN_Alert(FN_Translate("this product is not yet associated with any category"));
                //dprint_r($_POST);
                $categories = fnc_getcategories(false, false);
                $p_categories = fnc_getcategoriesbyproduct($pid);
                $categories_in_product = array();
                foreach ($p_categories as $c)
                {
                    $categories_in_product[$c['unirecid']] = $c;
                }
            }
            echo "<b>" . FN_Translate("categories") . "</b>";
            foreach ($categories as $category)
            {
                $ck = "";
                if (isset($categories_in_product[$category['unirecid']]))
                    $ck = "checked=\"checked\"";
                echo "<br />{$category['name']}<input value=\"1\" name=\"category_{$category['unirecid']}\" $ck type=\"checkbox\" value=\"{$category['unirecid']}\" />";
            }
            
            break;
        default :
            //echo "ciao ciao";
            break;
    }
}

/**
 * elimina un prodotto da una categoria
 * 
 */
function fnc_delete_product_from_category($pid, $cat)
{
    global $_FN;
    if ($pid != "" && $cat != "")
    {
        $table = FN_XMDBTable("fnc_products_to_categories") ;
        $all = $table->GetRecords(array("product" => $pid, "category" => $cat));
        foreach ($all as $row)
        {
            $table->DelRecord($row['unirecid']);
        }
    }
}

/**
 * aggiunge un prodotto in una categoria
 * 
 * 
 */
function fnc_add_product_in_category($pid, $cat)
{
    global $_FN;
    if ($pid != "" && $cat != "")
    {
        $table =  FN_XMDBTable("fnc_products_to_categories") ;
        $all = $table->GetRecords(array("product" => $pid, "category" => $cat));
        //----se non esiste gia' lo aggiunge
        if (!isset($all[0]['unirecid']))
        {
            $all = $table->InsertRecord(array("product" => $pid, "category" => $cat));
        }
    }
}

?>