<?php

/**
 * @package flatnux_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;
global $_FNC;
if (!isset($_FNC['products_to_categories'])) {
    $_FNC['products_to_categories'] = array();
}
if (!isset($_FNC['categories'])) {
    $_FNC['categories'] = array();
}

$_FNC['errors'] = false;
include(__DIR__ . "/fncommerce.php");
FN_LoadMessagesFolder("modules/fncommerce/");

if (!isset($_FNC['allproducts'])) {
    $tableproduct = FN_XMDBForm("fnc_products"); //new FieldFrm("fndatabase","fnc_products",$_FN['datadir'],$_FN['lang'],$_FN['languages']);
    //$_FNC['sort_product']="name";
    //if (!isset($_FNC['sort_product']) || $_FNC['sort_product']=="")
    //	$_FNC['sort_product']="sort_order";
    $_allproducts = $tableproduct->xmltable->GetRecords(false, false, false, false);

    //$_allproducts =  $tableproduct->xmltable->GetRecords(false,false,false,$_FNC['sort_product']);
    //dprint_r($_allproducts);
    $cat = FN_GetParam("cat", $_GET, "int");
    if ($_allproducts)
        foreach ($_allproducts as $item) {
            if ($_FN['lang'] != $_FN['listlanguages'][0])
                $_FNC['allproducts'][$item['unirecid']] = $tableproduct->GetRecordTranslated($item);
            else
                $_FNC['allproducts'][$item['unirecid']] = $item;
            $_FNC['allproducts'][$item['unirecid']]['manufacture_image'] = "";
            $_FNC['allproducts'][$item['unirecid']]['url_img'] = $tableproduct->xmltable->getFilePath($item, 'photo1');
            $_FNC['allproducts'][$item['unirecid']]['url'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=view&id={$item['unirecid']}&cat=$cat");
            // Supporto prezzi scaglionati: se contiene ":", mostra il prezzo più basso
            if (strpos($item['price'], ':') !== false) {
                $_FNC['allproducts'][$item['unirecid']]['txt_price'] = FN_i18n("from") . " " . fnc_format_price(fnc_get_price_by_quantity($item['price'], 1));
                $_FNC['allproducts'][$item['unirecid']]['price_tiers'] = fnc_format_price_tiers($item['price'], 'html');
            } else {
                $_FNC['allproducts'][$item['unirecid']]['txt_price'] = fnc_format_price($item['price']);
                $_FNC['allproducts'][$item['unirecid']]['price_tiers'] = '';
            }
            $_FNC['allproducts'][$item['unirecid']]['url_addtocart'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=addtocart&p={$item['unirecid']}&from_cat=$cat");
            //carrello--->
            $qta = FN_GetParam("qta{$item['unirecid']}", $_REQUEST, "flat");
            if ($qta != "" && $item['unirecid'] != "") {
                fnc_add_to_cart($item['unirecid'], $qta, true);
            }
            //carrello---<
        }
    $tablecategories = FN_XMDBForm("fnc_categories"); //new FieldFrm("fndatabase","fnc_categories",$_FN['datadir'],$_FN['lang'],$_FN['languages']);
    $_allcategories = $tablecategories->xmltable->GetRecords(false, false, false, "sort_order");
    //dprint_r($_allcategories);
    if (!$_allcategories) {
        $_allcategories = array();
    }
    foreach ($_allcategories as $item) {
        //if (empty($item['hidden']))
        {
            if ($_FN['lang'] != $_FN['listlanguages'][0])
                $_FNC['categories'][$item['unirecid']] = $tablecategories->GetRecordTranslated($item);
            else
                $_FNC['categories'][$item['unirecid']] = $item;
        }
        $_FNC['categories'][$item['unirecid']]['url_img'] = $tablecategories->xmltable->getFilePath($item, 'photo1');
        $_FNC['categories'][$item['unirecid']]['url'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&cat={$item['unirecid']}");
    }

    $tableproducts_to_categories = FN_XMDBTable("fnc_products_to_categories"); //new XMETATable("fndatabase","fnc_products_to_categories",$_FN['datadir']);
    $_allproducts_to_categories = $tableproducts_to_categories->GetRecords();
    if ($_allproducts_to_categories) {
        foreach ($_allproducts_to_categories as $item) {
            //dprint_r($item);
            $_FNC['products_to_categories'][$item['category']][$item['product']] = &$_FNC['allproducts'][$item['product']];
        }
    }
}

/**
 * Offerte speciali
 * 
 * 
 */
function fnc_get_offers()
{
    global $_FN;
    $tableproduct = FN_XMDBTable("fnc_offers"); //new XMETATable("fndatabase","fnc_offers",$_FN['datadir']);
    $_allproducts = $tableproduct->GetRecords(false, false, false, false);
    //dprint_r($_allproducts);
    $all = array();
    foreach ($_allproducts as $p) {
        $t = fnc_getproduct($p['product']);
        if ($t)
            $all[] = $t;
    }
    return $all;
}

/**
 * 
 * 
 */
function fnc_get_orders($restr = false)
{
    $table = FN_XMDBTable("fnc_orders"); // new XMETATable("fndatabase","fnc_orders",$_FN['datadir']);
    $ret = $table->GetRecords($restr, false, false, "unirecid");
    return $ret;
}

/**
 * taduce lo stato dell' ordine nella lingua corrente
 * 
 * 
 */
function fnc_translate_orderstatus($status)
{
    $table = FN_XMDBForm("fnc_orderstatus"); //new FieldFrm("fndatabase","fnc_orderstatus",$_FN['datadir'],$_FN['lang']);
    $ret = $table->GetRecordTranslated(array("unirecid" => $status));
    return isset($ret['name']) ? $ret['name'] : "";
}

/**
 * ricava la lista degli stati degli ordini
 * 
 * 
 */
function fnc_get_orderstatus()
{

    $table = FN_XMDBForm("fnc_orderstatus"); //new FieldFrm("fndatabase","fnc_orderstatus",$_FN['datadir'],$_FN['lang']);
    $all = $table->xmltable->GetRecords();
    $ret = array();
    foreach ($all as $k => $item) {
        $ret[$k] = $table->GetRecordTranslated(array("unirecid" => $item['unirecid']));
    }
    //dprint_r($ret);
    return $ret;
}

/**
 * 
 * @param string $idcountry
 */
function fnc_get_country($idcountry)
{
    $table = FN_XMDBForm("fnc_countries"); // new FieldFrm("fndatabase","fnc_countries",$_FN['datadir'],$_FN['lang']);
    $ret = $table->GetRecordTranslated(array("unirecid" => $idcountry));
    return $ret;
}

/**
 * 
 * @param string $idzone
 */
function fnc_get_zone($idzone)
{
    $table = FN_XMDBForm("fnc_zones"); // new FieldFrm("fndatabase","fnc_zones",$_FN['datadir'],$_FN['lang']);
    $ret = $table->GetRecordTranslated(array("unirecid" => $idzone));
    return $ret;
}

/**
 * 
 * 
 */
function fnc_get_order($idorder)
{
    global $_FN;
    $table = FN_XMDBForm("fnc_orders"); // new FieldFrm("fndatabase","fnc_orders",$_FN['datadir'],$_FN['lang']);
    $ret = $table->GetRecordTranslated(array("unirecid" => $idorder));
    $table = FN_XMDBTable("fnc_cart_items"); // new XMETATable("fndatabase","fnc_cart_items",$_FN['datadir']);
    $items = $table->GetRecords(array("order_id" => $idorder));
    $ret['cart'] = $items;
    $table = FN_XMDBTable("fnc_costs_items"); // new XMETATable("fndatabase","fnc_costs_items",$_FN['datadir']);
    $items = $table->GetRecords(array("order_id" => $idorder));
    $ret['costs'] = $items;
    return $ret;
}

/**
 * 
 * @global type $_FN
 * @param type $ordervalues
 * @return string
 */
function fnc_get_order_summary($ordervalues)
{
    global $_FN;
    $message = "<h2>" . FN_Translate("order summary") . "</h2><br />" . fnc_get_ordercost_details($ordervalues);
    $message .= "<br />";
    $message .= "<br />";
    //note sul pagamento dell' ordine
    if (isset($ordervalues['payments']) && file_exists("modules/fncommerce/modules/payments/{$ordervalues['payments']}/module.php")) {
        require_once("modules/fncommerce/modules/payments/{$ordervalues['payments']}/module.php");
        $classname = "fnc_payments_{$ordervalues['payments']}";
        $payment = new $classname($ordervalues);
        $message .= $payment->do_payment();
    }
    $message .= "<br /><br /><br />" . str_replace("\n", "<br />", $ordervalues['address']);
    if ($_FN['fnc_enable_recipient'] == 1) {
        $message .= "<br />" . FN_i18n("Delivery_address") . ":<br />" . str_replace("\n", "<br />", $ordervalues['shippingaddress']);
    }
    return $message;
}

/**
 * invia mail di conferma ordine
 * 
 * @param array stato dell' ordine
 */
function fnc_send_confirm($ordervalues)
{
    global $_FN;
    $user = FN_GetUser($ordervalues['username']);
    $to = $user['email'];
    $subject = fnc_applytpl(FN_Translate("{sitename} - Order confirmation N.{ordernumber}"), array("sitename" => $_FN['sitename'], "ordernumber" => $ordervalues['unirecid']));
    $message = fnc_get_order_summary($ordervalues);
    fnc_send_mail($to, $subject, $message, "", true);
    fnc_send_mail($_FN['log_email_address'], FN_Translate("new order") . " #" . $ordervalues['unirecid'], $message, "", true);
    return $message;
}

/**
 * invia mail di conferma ordine
 * 
 * @param array stato dell' ordine
 */
function fnc_send_changestatus($ordervalues)
{
    global $_FN;
    $user = FN_GetUser($ordervalues['username']);
    $to = $user['email'];
    $subject = FN_Translate("order status updating");
    $subject = fnc_applytpl($subject, array("sitename" => $_FN['sitename'], "ordernumber" => $ordervalues['unirecid']));
    $message = html_orderstatus($ordervalues['unirecid']);
    fnc_send_mail($to, $subject, $message, "", true);
}

/**
 * elimina dati ordine,carrello ecc. 
 * 
 * 
 */
function fnc_clear_order_temp()
{
    global $_FN;
    $time = time() + 999999999;
    $orderstatus = array();
    setcookie("orderstatus", serialize($orderstatus), $time, $_FN['urlcookie']);
    $_COOKIE['orderstatus'] = serialize($orderstatus);
    return $orderstatus;
}

/**
 * salva lo stato dell' orfine nei cookie
 * 
 */
function fnc_save_order_temp($orderstatus)
{
    global $_FN;
    $time = time() + 999999999;
    setcookie("orderstatus", serialize($orderstatus), $time, $_FN['urlcookie']);
    $_COOKIE['orderstatus'] = serialize($orderstatus);
    return $orderstatus;
}

/**
 * ricava lo stato dell' ordine salvato nei cookie 
 * 
 */
function fnc_get_order_temp()
{
    $ret = unserialize(FN_GetParam("orderstatus", $_COOKIE, "flat"));
    //dprint_r($ret);
    return $ret;
}

/**
 * salva l' ordine sul database
 * 
 */
function fnc_save_order_status($orderstatus)
{
    //return false;
    global $_FN;
    $orderstatus['time'] = date("Y-m-d G:i:s", time()); //2009-02-06 00:00:00
    $table = FN_XMDBTable("fnc_orders"); //new XMETATable("fndatabase","fnc_orders",$_FN['datadir']);
    $tablecart = FN_XMDBTable("fnc_cart_items"); //new XMETATable("fndatabase","fnc_cart_items",$_FN['datadir']);
    $tablecosts = FN_XMDBTable("fnc_costs_items"); //new XMETATable("fndatabase","fnc_costs_items",$_FN['datadir']);
    $idIsDate = true;
    if ($idIsDate) {
        $d = 1000;
        do {
            $d++;
            $orderstatus['unirecid'] = date("Ymd") . substr("$d", 1);
            $exists = $table->GetRecordByPrimaryKey($orderstatus['unirecid']);
        } while (!empty($exists['unirecid']));
    }
    $status = $table->InsertRecord($orderstatus);
    if (empty($status['unirecid'])) {
        return false;
    }
    foreach ($orderstatus['cart'] as $item) {
        $cart['order_id'] = $status['unirecid'];
        $cart['pid'] = $item['pid'];
        $pvalues = fnc_getproduct($item['pid']);
        $cart['name'] = $pvalues['name'];
        $cart['qta'] = $item['qta'];
        $cart['price'] = $pvalues['price'];
        $tablecart->InsertRecord($cart);
    }
    //elimino i vecchi costi associati all'ordine e tengo solo quelli attualment selezionati
    $oldcosts = $tablecosts->GetRecords(array("order_id" => $status['unirecid']));
    if (is_array($oldcosts)) {
        foreach ($oldcosts as $cost) {
            $tablecosts->DelRecord($cost['unirecid']);
        }
    }
    //costi aggiuntivi
    if (isset($orderstatus['costs']))
        foreach ($orderstatus['costs'] as $cost) {
            $cost['order_id'] = $status['unirecid'];
            $t = $tablecosts->InsertRecord($cost);
            if (!is_array($t)) {
                return false;
            }
        }
    return $status;
}

/**
 * aggiunge al carrello un prodotto
 * 
 */
function fnc_add_to_cart($pid, $qta = "", $forceqta = false)
{
    global $_FN;

    if ($pid == "")
        return;
    if ($qta == "")
        $qta = 1;
    $cart = fnc_get_cart();

    $in_stock = fnc_get_in_stock($pid, true);
    if ($forceqta !== false) {
        $newqta = $qta;
    } else {
        if (isset($cart[$pid]['qta'])) {
            $newqta = intval($cart[$pid]['qta']) + $qta;
        } else {
            $newqta = intval($qta);
        }
    }
    if ($newqta > $in_stock) {
        // die("$in_stock - $newqta");
        return (FN_Translate("exceeded the number of available products"));
    }
    $cart[$pid] = array(
        'pid' => $pid, //	'price' => $p['price'],		
        'qta' => $newqta
    );
    $time = time() + 999999999;
    if ($qta == 0)
        unset($cart[$pid]);
    $newcart = array();

    foreach ($cart as $k => $v) {
        if ($k != "" && isset($v['qta']) && $v['qta'] >= 0) {
            $newcart[$k] = $v;
        }
    }
    fnc_save_order_temp(array('cart' => $newcart));
    return "";
}

/**
 * 
 * svuota il carrello
 */
function fnc_empty_cart()
{
    global $_FN;
    $cart = array();
    fnc_save_order_temp(array('cart' => $cart));
}

/**
 * elimina un prodotto dal carrello
 * 
 */
function fnc_rem_from_cart($pid)
{
    global $_FN;
    $cart = fnc_get_cart();
    if (isset($cart[$pid]['qta']))
        unset($cart[$pid]);
    fnc_save_order_temp(array('cart' => $cart));
}

function fnc_get_cart_count()
{
    $ret = fnc_get_order_temp();
    if (isset($ret['cart']) && is_array($ret['cart']))
        return count($ret['cart']);
    return 0;
}

/**
 * recupera contenuto del carrello
 * 
 */
function fnc_get_cart()
{
    $ret = fnc_get_order_temp();
    if (isset($ret['cart']))
        return $ret['cart'];
    return array();
}

/**
 * recupera la categoria
 * @param string id categoria
 * 
 */
function fnc_getcategory($id)
{
    global $_FN, $_FNC;
    if (isset($_FNC['categories'][$id]))
        return $_FNC['categories'][$id];
    return false;
}

/**
 * recupera la lista di tutte le categorie
 * 
 * 
 */
function fnc_getcategories($r = false)
{
    global $_FN, $_FNC;
    $ret = array();
    if (is_array($r)) {
        foreach ($_FNC['categories'] as $cat) {
            $ok = true;
            foreach ($r as $k => $v) {
                if ($cat[$k] != $v) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                $ret[$cat['unirecid']] = $cat;
            }
        }
    } else
        return $_FNC['categories'];
    return $ret;
}

function fnc_getmanufacturers($r = false)
{
    $table = FN_XMDBForm("fnc_manufacturers"); //new FieldFrm("fndatabase","fnc_manufacturers",$_FN['datadir']);
    $all = $table->xmltable->GetRecords($r, false, false, "sort_order");
    return $all;
}

function fnc_getmanufacturersById($unirecid)
{
    global $_FN;
    $table = FN_XMDBForm("fnc_manufacturers"); //new FieldFrm("fndatabase","fnc_manufacturers",$_FN['datadir']);
    $all = $table->xmltable->GetRecords(array("unirecid" => $unirecid), false, false);
    $ret = isset($all[0]) ? $all[0] : array();
    $ret['image'] = "";
    if (isset($ret['photo1']) && $ret['photo1'] != "")
        $ret['image'] = $_FN['datadir'] . "/fndatabase/fnc_manufacturers/{$ret['unirecid']}/photo1/{$ret['photo1']}";
    return $ret;
}

/**
 * ricava indirizzo utente
 * 
 */
function fnc_get_shipping_values($user)
{
    global $_FN;
    $table = FN_XMDBTable("fnc_users"); //new XMETATable("fndatabase","fnc_users",$_FN['datadir']);
    $ret = $table->GetRecords(array("username" => "$user"));
    // se non ho un indirizzo di spedizione uso l'indirizzo di fatturazione
    //dprint_r($ret);
    if (isset($ret[0])) {
        if (!isset($ret[0]['doshippingaddress']) || (isset($ret[0]['doshippingaddress']) && $ret[0]['doshippingaddress'] == "no")) {
            $ret[0]['shippingcountry'] = $ret[0]['country'];
            $ret[0]['shippingzone'] = $ret[0]['zone'];
            $ret[0]['shippingzip'] = $ret[0]['zip'];
            $ret[0]['shippingcity'] = $ret[0]['city'];
            $ret[0]['shippingtelephone'] = $ret[0]['telephone'];
            $ret[0]['shippingaddress'] = $ret[0]['address'];
            if ($ret[0]['type'] == "company")
                $ret[0]['shippingname'] = $ret[0]['companyname'];
            else
                $ret[0]['shippingname'] = $ret[0]['firstname'] . " " . $ret[0]['lastname'];
        }
    }
    return $ret;
}

/**
 * ricava indirizzo utente
 * 
 */
function fnc_get_shipping_values_string($user)
{
    global $_FN;
    $fields = FN_XMDBForm("fnc_users"); //new FieldFrm("fndatabase","fnc_users",$_FN['datadir']);
    $uservalues = fnc_get_shipping_values($user);
    if (!isset($uservalues[0]))
        return false;
    $uservalues = $uservalues[0];
    if ($uservalues['type'] == 'company') {
        $ret = $uservalues['companyname'] . "\n";
        $ret .= $uservalues['vat'] . "\n\n";
    } else {
        $ret = $uservalues['firstname'] . " " . $uservalues['lastname'] . "\n";
        $ret .= $uservalues['fiscalcode'] . "\n\n";
    }
    $ret .= $uservalues['address'] . "\n" . $uservalues['zip'] . "\n";
    $ret .= $uservalues['city'] . "\n";
    $zone['name'] = "";
    if (isset($uservalues['zone']))
        $zone = fnc_get_zone($uservalues['zone']);
    $state = fnc_get_country($uservalues['country']);
    if (!empty($zone['name'])) {
        $ret .= $zone['name'] . "\n";
    }
    if (!empty($state['name'])) {
        $ret .= $state['name'] . "\n";
    }

    //if (isset($uservalues['doshippingaddress']))
    //{
    //	if ($uservalues['doshippingaddress'] == "yes")
    {
        //$ret = "{Billing_Data}\n$ret";
        $ret .= "\n\n{Delivery_address}\n";
        if (!empty($uservalues['shippingname']))
            $ret .= $uservalues['shippingname'] . "\n";
        if (!empty($uservalues['shippingaddress']))
            $ret .= $uservalues['shippingaddress'] . "\n" . $uservalues['shippingzip'] . "\n";
        if (!empty($uservalues['shippingcity']))
            $ret .= $uservalues['shippingcity'] . "\n";
        $zone['name'] = "";
        if (isset($uservalues['shippingzone']))
            $zone = fnc_get_zone($uservalues['shippingzone']);

        $state = fnc_get_country($uservalues['shippingcountry']);
        if (!empty($zone['name']))
            $ret .= $zone['name'] . "\n";
        $ret .= $state['name'] . "\n";
    }
    //}
    //else
    //{
    //	$ret .= "{Delivery_address}\n$ret";
    //}
    /*
      foreach ($fields->formvals as $key => $value)
      {
      if (isset ($value['frm_show']) && $value['frm_show'] != "1")
      continue;

      if (isset ($value['frm_group']) && $value['frm_group'] != "")
      $ret .= "" . $value['frm_group'] . "\n";
      if ($uservalues[$key] != "")
      $ret .= "" . $value['title'] . ": " . $uservalues[$key] . "\n";
      else
      $ret .= "" . $value['title'] . ": -\n";
      } */
    return $ret;
}

/**
 * ricava indirizzo utente
 * 
 */
function fnc_get_shipping_values_htmlstring($user)
{
    $ret = fnc_get_shipping_values_string($user);
    $ret = str_replace("\n", "<br />", $ret);
    $ret = str_replace("{Delivery_address}", "<b>" . FN_i18n("Delivery_address") . ":</b>", $ret);
    $ret = str_replace("{Billing_Data}", "<b>" . FN_i18n("Billing_Data") . ":</b>", $ret);

    return $ret;
}

/**
 * recupera la lista dei prodotti
 * 
 */
function fnc_getproducts($r = "")
{
    global $_FN, $_FNC;
    //$table = new FieldFrm("fndatabase", "fnc_products", $_FN['datadir']);
    //$all = $table->xmltable->GetRecords($restr);
    //$table = new XMETATable("fndatabase", "fnc_products_to_categories", $_FN['datadir']);
    $all = array();
    if (is_array($r)) {
        foreach ($_FNC['allproducts'] as $cat) {
            $ok = true;
            foreach ($r as $k => $v) {
                if ($cat[$k] != $v) {
                    $ok = false;
                    break;
                }
            }
            if ($ok)
                $all[$cat['unirecid']] = $cat;
        }
    } else
        $all = $_FNC['allproducts'];

    return $all;
}

/**
 * recupera i valori del prodotto
 * 
 * 
 */
function fnc_getproduct($pid)
{
    global $_FN;
    $table = FN_XMDBForm("fnc_products"); //new FieldFrm("fndatabase","fnc_products",$_FN['datadir'],$_FN['lang']);
    $all = $table->GetRecordTranslated(array("unirecid" => $pid));
    if (!isset($all['unirecid']))
        return false;
    $table = FN_XMDBTable("fnc_products_to_categories"); //new XMETATable("fndatabase","fnc_products_to_categories",$_FN['datadir']);
    // metto la prima categoria
    $cats = $table->GetRecords(array("product" => $pid));
    $cat = isset($cat[0]['unirecid']) ? $cat[0]['unirecid'] : "";
    $all['category'] = $cat;
    return $all;
}

/**
 * 
 * @param type $pid
 * @param type $no_mychart
 * @param type $no_othercharts
 * @return string
 */
function fnc_get_in_stock($pid, $no_mychart = false, $no_othercharts = false)
{
    $product = fnc_getproduct($pid);
    if ($product['quantity'] === "" || $product['quantity'] === null) {
        return "INFINITE";
    }
    $q = intval($product['quantity']);
    if ($no_othercharts == false) {
        $fnc_cart_items = FN_XMETADBQuery("SELECT * FROM fnc_cart_items WHERE pid LIKE '$pid'");
        if (is_array($fnc_cart_items)) {
            foreach ($fnc_cart_items as $fnc_cart_item) {
                $q -= $fnc_cart_item['qta'];
            }
        }
    }
    if ($no_mychart == false) {
        $r = fnc_get_order_temp();
        if (!empty($r['cart'])) {
            foreach ($r['cart'] as $fnc_cart_item) {
                $q -= $fnc_cart_item['qta'];
            }
        }
    }
    return intval($q);
}

/**
 * recupera la lista dei prodotti partendo dalla categoria
 * @param string $cat
 * 
 */
function fnc_getproductsbycategory($cat, $ranges = "")
{
    global $_FN, $_FNC;
    $products_to_categories = $_FNC['products_to_categories'];
    $products = array();
    $ret = array();
    if ($cat == "")
        return array();
    if (isset($_FNC['products_to_categories'][$cat]))
        foreach ($_FNC['products_to_categories'][$cat] as $prod) {
            if (isset($prod['unirecid']) && isset($ret[$prod['unirecid']])) {
                continue;
            }
            $m = "";
            $ok = true;
            if (is_array($ranges))
                foreach ($ranges as $k => $range) {
                    if ($range != "") {
                        if (ereg('-min$', $k)) {
                            $k = ereg_replace('-min$', '', $k);
                            if ($prod[$k] < intval($range)) {
                                $ok = false;
                                break;
                            }
                        } else {
                            $k = ereg_replace('-max$', '', $k);
                            if ($prod[$k] > intval($range)) {
                                $ok = false;
                                break;
                            }
                        }
                    }
                }
            if ($ok) {
                if (isset($prod['unirecid']))
                    $ret[$prod['unirecid']] = $prod;
            }
        }
    return $ret;
}

/**
 * recupera i prodotti all' interno di una categoria
 * 
 */
function fnc_getproductscountbycategory($cat, $ranges = array(), $filters = "")
{
    global $_FN, $_FNC;
    $filters = array();
    foreach ($_FNC['categories'] as $category) {
        $filter = FN_GetParam("cat_{$category['unirecid']}", $_GET, "flat");
        if ($filter == 1)
            $filters[$category['unirecid']] = $category['unirecid'];
    }
    static $allcats = "";
    static $table;
    $products = array();
    $ret = array();
    if ($cat == "")
        return 0;
    /* if ($ranges == "" || count($ranges) == 0)
      {
      return count($categories[$cat]);
      } */
    if (isset($_FNC['products_to_categories'][$cat]))
        foreach ($_FNC['products_to_categories'][$cat] as $prod) {
            $ok = true;
            foreach ($filters as $filter) {
                if (!isset($_FNC['products_to_categories'][$filter][$prod['unirecid']])) {
                    $ok = false;
                    break;
                }
                //else
                //	dprint_r($products_to_categories[$cat][$prod['unirecid']]);
            }
            $noused = "";
            if ($ok)
                foreach ($ranges as $k => $range) {
                    if ($range != "") {
                        if (ereg('-min$', $k,$noused)) {
                            $k = ereg_replace('-min$', '', $k);
                            if ($prod[$k] < intval($range)) {
                                $ok = false;
                                break;
                            }
                        } else {
                            $k = ereg_replace('-max$', '', $k);
                            if ($prod[$k] > intval($range)) {
                                $ok = false;
                                break;
                            }
                        }
                    }
                }
            if ($ok) {
                $ret[$prod['unirecid']] = $prod;
            }
        }
    //dprint_r($ret);
    return count($ret);
}

/**
 * recupera le categorie a cui Ã¨ associato
 * il prodotto
 * 
 * 
 */
function fnc_getcategoriesbyproduct($pid)
{
    global $_FN;
    $table = FN_XMDBTable("fnc_products_to_categories"); //new XMETATable("fndatabase","fnc_products_to_categories",$_FN['datadir']);
    $all = $table->GetRecords(array("product" => $pid));
    $cats = array();
    //dprint_r($all);
    foreach ($all as $item) {
        $cats[] = fnc_getcategory($item['category']);
    }
    return $cats;
}

/**
 * formatta il prezzo
 * @param price
 *
 */
function fnc_format_price($price)
{
    global $_FN;
    $price = round(floatval($price), 2);
    //$price = sprintf("%.3f {$_FN['currency_symbol']}", $price);
    $dec_sep = FN_i18n("decimal separator");
    $thousands_sep = FN_i18n("thousands separator");
    $price = number_format($price, 2, $dec_sep, $thousands_sep) . "&nbsp;" . $_FN['currency_symbol'];
    return $price;
}

/**
 * Calcola il prezzo unitario in base alla quantità e agli scaglioni
 * Formato prezzo scaglionato: 1:90,10:80,100:70
 * Significa: 1-9 unità a 90, 10-99 unità a 80, 100+ unità a 70
 *
 * @param string $price_string Stringa del prezzo (può contenere scaglioni)
 * @param int $quantity Quantità richiesta
 * @return float Prezzo unitario per la quantità specificata
 */
function fnc_get_price_by_quantity($price_string, $quantity)
{
    // Se non contiene ":", è un prezzo semplice
    if (strpos($price_string, ':') === false) {
        return floatval($price_string);
    }

    // Parsifica gli scaglioni
    $tiers = array();
    $parts = explode(',', $price_string);

    foreach ($parts as $part) {
        $part = trim($part);
        if (strpos($part, ':') !== false) {
            list($qty, $price) = explode(':', $part);
            $tiers[intval($qty)] = floatval($price);
        }
    }

    // Se non ci sono scaglioni validi, restituisce 0
    if (empty($tiers)) {
        return 0;
    }

    // Ordina gli scaglioni per quantità (dal più alto al più basso)
    krsort($tiers);

    // Trova il prezzo corretto per la quantità
    foreach ($tiers as $tier_qty => $tier_price) {
        if ($quantity >= $tier_qty) {
            return $tier_price;
        }
    }

    // Se non trova uno scaglione, usa il primo disponibile
    return reset($tiers);
}

/**
 * Visualizza gli scaglioni di prezzo in formato HTML
 * Formato prezzo scaglionato: 1:90,10:80,100:70
 *
 * @param string $price_string Stringa del prezzo (può contenere scaglioni)
 * @param string $format Formato di output: 'html' o 'text'
 * @return string HTML o testo con gli scaglioni formattati
 */
function fnc_format_price_tiers($price_string, $format = 'html')
{
    // Se non contiene ":", è un prezzo semplice
    if (strpos($price_string, ':') === false) {
        return fnc_format_price($price_string);
    }

    // Parsifica gli scaglioni
    $tiers = array();
    $parts = explode(',', $price_string);

    foreach ($parts as $part) {
        $part = trim($part);
        if (strpos($part, ':') !== false) {
            list($qty, $price) = explode(':', $part);
            $tiers[intval($qty)] = floatval($price);
        }
    }

    // Se non ci sono scaglioni validi, restituisce prezzo a 0
    if (empty($tiers)) {
        return fnc_format_price(0);
    }

    // Ordina gli scaglioni per quantità (dal più basso al più alto)
    ksort($tiers);

    // Crea l'output
    $output = array();
    $tiers_array = array_keys($tiers);
    $count = count($tiers_array);

    for ($i = 0; $i < $count; $i++) {
        $qty = $tiers_array[$i];
        $price = $tiers[$qty];
        $next_qty = isset($tiers_array[$i + 1]) ? $tiers_array[$i + 1] - 1 : null;

        if ($format == 'html') {
            if ($next_qty !== null) {
                $output[] = "<div class=\"price-tier\"><span class=\"qty-range\">$qty-$next_qty " . FN_i18n("pcs") . ":</span> <span class=\"price\">" . fnc_format_price($price) . "</span></div>";
            } else {
                $output[] = "<div class=\"price-tier\"><span class=\"qty-range\">$qty+ " . FN_i18n("pcs") . ":</span> <span class=\"price\">" . fnc_format_price($price) . "</span></div>";
            }
        } else {
            if ($next_qty !== null) {
                $output[] = "$qty-$next_qty " . FN_i18n("pcs") . ": " . fnc_format_price($price);
            } else {
                $output[] = "$qty+ " . FN_i18n("pcs") . ": " . fnc_format_price($price);
            }
        }
    }

    return ($format == 'html') ? implode("\n", $output) : implode(", ", $output);
}

/**
 * Valida un codice fiscale o tax ID generico
 * Accetta vari formati internazionali senza validazione ferrea
 *
 * @param string $code Codice fiscale/tax ID da validare
 * @return bool True se valido, false altrimenti
 */
function fnc_validate_fiscal_code($code)
{
    // Rimuove spazi e caratteri speciali comuni
    $code = trim($code);
    $code = str_replace(array(' ', '-', '.', '/'), '', $code);

    // Verifica che non sia vuoto
    if (empty($code)) {
        return false;
    }

    // Verifica lunghezza minima e massima ragionevole (tra 5 e 20 caratteri)
    $length = strlen($code);
    if ($length < 5 || $length > 20) {
        return false;
    }

    // Verifica che contenga solo caratteri alfanumerici
    if (!preg_match('/^[A-Z0-9]+$/i', $code)) {
        return false;
    }

    // Verifica che contenga almeno un carattere alfanumerico valido
    // (non solo numeri o solo lettere per codici molto corti)
    if ($length <= 8) {
        // Per codici corti, verifica che abbia un mix ragionevole
        $has_letter = preg_match('/[A-Z]/i', $code);
        $has_number = preg_match('/[0-9]/', $code);

        // Accetta solo numeri se la lunghezza è >= 6 (es. codici fiscali numerici)
        // Altrimenti richiede un mix
        if ($length < 6 && (!$has_letter || !$has_number)) {
            return false;
        }
    }

    // Codice valido
    return true;
}

/**
 * inizializza le tabelle
 *
 */
function fnc_initTables()
{
    global $_FN;
    if (!file_exists("{$_FN['datadir']}/fncommerce/")) {
        mkdir("{$_FN['datadir']}/fncommerce/");
    }
    //------------categorie-------------------------------->
    if (!file_exists("{$_FN['datadir']}/fndatabase/fnc_categories.php")) {
    }
    if (!file_exists("{$_FN['datadir']}/fndatabase/fnc_categories")) {
        mkdir("{$_FN['datadir']}/fndatabase/fnc_categories");
    }
    //------------categorie-------------------------------<
    //----------prodotti------------------------------------->
    if (!file_exists("{$_FN['datadir']}/fndatabase/fnc_products.php")) {
    }
    if (!file_exists("{$_FN['datadir']}/fndatabase/fnc_products")) {
        mkdir("{$_FN['datadir']}/fndatabase/fnc_products");
    }
    //----------prodotti-------------------------------------<
}

/**
 * invia una mail
 * 
 * @param string destinatario
 * @param string subject
 * @param string message
 * @param string from
 * @param bool is html
 * 
 */
function fnc_send_mail($to, $subject, $message, $from, $html = false)
{
    global $_FN;
    if ($from == "") {
        $from = $_FN['site_email_address'];
    }
    if (strtoupper(substr(PHP_OS, 0, 3) == 'WIN')) {
        $eol = "\r\n";
    } elseif (strtoupper(substr(PHP_OS, 0, 3) == 'MAC')) {
        $eol = "\r";
    } else {
        $eol = "\n";
    }
    if ($html) {
        $headers = "MIME-Version: 1.0$eol" . "Content-type: text/html; charset=iso-8859-1$eol" . "Reply-To: $from$eol";
    } else {
        $headers = "Reply-To: {$_FN['site_email_address']}";
    }
    $headers = str_replace("\r", "", $headers);
    $headers = str_replace("\n", $eol, $headers);
    return mail($to, $subject, $message, "From: $from$eol" . $headers);
}

/**
 * calcola il costo totale dell' ordine
 * @return array orderstatus aggiornato di total
 * 
 */
function fnc_calculate_order_cost($orderstatus)
{
    global $_FN;
    $total = 0;
    $cart = $orderstatus['cart'];
    //-------costi prodotti --------------------- ------>
    foreach ($cart as $k => $item) {
        $product = fnc_getproduct($item['pid']);
        if (isset($product['name'])) {
            // Calcola il prezzo unitario in base alla quantità (supporta prezzi scaglionati)
            $unit_price = fnc_get_price_by_quantity($product['price'], intval($item['qta']));
            $total += intval($item['qta']) * $unit_price;
            $orderstatus['cart'][$k]['name'] = $product['name'];
            $orderstatus['cart'][$k]['price'] = $item['qta'] * $unit_price;
        }
    }
    //-------costi prodotti --------------------- ------<
    //-------costi aggiuntivi dichiarati dai moduli ------>
    if (isset($orderstatus['costs']) && is_array($orderstatus['costs']))
        foreach ($orderstatus['costs'] as $itemcost) {
            $total += $itemcost['total'];
        }
    //-------costi aggiuntivi dichiarati dai moduli ------<
    $orderstatus['total'] = $total;
    return $orderstatus;
}

/**
 * ritorna la stringa formattata con i dettagli di un ordine
 * @param array stato dell ordine
 * 
 */
function fnc_get_ordercost_details($orderstatus)
{
    global $_FN;
    $total = 0;
    $cart = $orderstatus['cart'];
    //dprint_r($cart);
    $str = "<table border=\"1\" cellspacing=\"1\" cellpadding=\"4\">";
    foreach ($cart as $item) {
        if (!isset($item['name']) || !isset($item['price'])) {
            $tmp = fnc_getproduct($item['pid']);
            $item['name'] = $tmp['name'];
            // Calcola il prezzo totale in base alla quantità (supporta prezzi scaglionati)
            $unit_price = fnc_get_price_by_quantity($tmp['price'], intval($item['qta']));
            $item['price'] = $unit_price * intval($item['qta']);
        }
        $str .= "<tr>";
        $str .= "<td>{$item['name']} x {$item['qta']}</td><td style=\"text-align:right\">" . fnc_format_price($item['price']) . "</td>";
        $str .= "</tr>";
    }
    //-------costi aggiuntivi dichiarati dai moduli ------>
    if (isset($orderstatus['costs']) && is_array($orderstatus['costs']))
        foreach ($orderstatus['costs'] as $itemcost) {
            $total += $itemcost['total'];
            $str .= "<tr>";
            $str .= "<td>{$itemcost['title']}</td><td style=\"text-align:right\">" . fnc_format_price($itemcost['total']) . "</td>";
            $str .= "</tr>";
        }
    //-------costi aggiuntivi dichiarati dai moduli ------<
    //-------totale ------>
    $str .= "<tr>";
    $str .= "<td><b>" . FN_i18n("total order") . "</b></td><td style=\"text-align:right;font-weight:bold\">" . fnc_format_price($orderstatus['total']) . "</td>";
    $str .= "</tr>";
    //-------totale ------<
    $str .= "</table>";
    return $str;
}

/**
 * query sql
 * 
 */
function fnc_sql_query($sqltext, $connection, $databasename)
{
    if (!$connessione = mysql_connect($connection['host'] . ":" . $connection['port'], $connection['user'], $connection['password']))
        die(mysql_error());
    mysql_select_db($databasename);
    $result = mysql_query($sqltext);
    $res = array();
    if ($result)
        while ($tmp = mysql_fetch_assoc($result))
            $res[] = $tmp;
    return $res;
}

/**
 * 
 * 
 */
function fnc_applytpl($string, $values)
{
    foreach ($values as $k => $v) {
        $string = str_replace('{' . $k . '}', $v, $string);
    }
    return $string;
}

/**
 * 
 * 
 */
function fnc_get_order_steps()
{
    $steps = array();
    $steps_modules = "";
    include("modules/fncommerce/modules/config.php");
    $steps = explode(",", $steps_modules);
    return $steps;
}

/**
 * torna il primo step valido
 * 
 */
function fnc_get_next_order_step($step = "")
{
    $next = "";
    $steps = fnc_get_order_steps();
    if ($step == "")
        $next = isset($steps[0]) ? $steps[0] : "";
    for ($i = 0; $i < count($steps); $i++) {
        if ($steps[$i] == $step) {
            if (isset($steps[$i + 1]))
                $next = $steps[$i + 1];
        }
    }
    $orderstatus = fnc_get_order_temp();
    //se per l' ordine corrente lo step non e' abilitato vado direttamente al prossimo
    if (file_exists("modules/fncommerce/modules/$next/module.php")) {
        require_once("modules/fncommerce/modules/$next/module.php");
        $classname = "fnc_$next";
        $stepclass = new $classname($orderstatus);
        if ($stepclass->is_enabled() == false) {
            return fnc_get_next_order_step($next);
        }
    }
    if ($next == "")
        $next = "confirmorder";
    return $next;
}

/**
 * torna il primo step valido
 * 
 */
function fnc_get_prev_order_step($step = "")
{
    $prev = "";
    $steps = fnc_get_order_steps();
    if ($step == "")
        return "";

    for ($i = 0; $i < count($steps); $i++) {
        if ($steps[$i] == $step) {
            if (isset($steps[$i - 1])) {
                $prev = $steps[$i - 1];
                $orderstatus = fnc_get_order_temp();
                if (file_exists("modules/fncommerce/modules/$prev/module.php")) {
                    require_once("modules/fncommerce/modules/$prev/module.php");
                    $classname = "fnc_$prev";
                    $stepclass = new $classname($orderstatus);
                    if ($stepclass->is_enabled() == false) {
                        return fnc_get_prev_order_step($prev);
                    }
                }
            }
        }
    }
    return $prev;
}

/**
 * 
 * 
 */
function fnc_get_modules_in_step($step)
{
    $list_enabled_modules = "";
    include("modules/fncommerce/modules/$step/config.php");
    return explode(",", $list_enabled_modules);
}

/**
 * 
 * 
 */
if (!function_exists("fnc_validate_user_data")) {

    function fnc_validate_user_data($data)
    {
        $err = true;
        //dprint_r($_POST);
        $type = FN_GetParam("type", $_POST, "flat");
        if ($type == "company") {
            $vat = FN_GetParam("vat", $_POST, "flat");
            if ($vat == "")
                return "per le aziende devi inserire la partita iva";
        }
        if ($type == "private") {
            $firstname = FN_GetParam("firstname", $_POST, "flat");
            $lastname = FN_GetParam("lastname", $_POST, "flat");
            $fiscalcode = FN_GetParam("fiscalcode", $_POST, "flat");
            if ($firstname == "" || $lastname == "" || $fiscalcode == "")
                $err .= "Per i privati occorre inserire il nome, il cognome e il codice fiscale";
        }
        return $err;
    }
}

/**
 * Verifica se un utente ha fatto almeno un ordine
 * 
 */
function fnc_have_orders($user)
{
    $orders = fnc_get_orders(array("username" => $user));
    if (is_array($orders) && count($orders) > 0)
        return true;
    return false;
}
