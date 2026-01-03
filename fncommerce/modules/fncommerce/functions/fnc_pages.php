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

/**
 * 
 */
function print_offers()
{
    $all = fnc_get_offers();
    //dprint_r($all);
    print_products_table($all);
}

/**
 * 
 */
function fnc_getcontents_config()
{
    $table = FN_XMDBForm("fnc_contents"); //new FieldFrm("fndatabase","fnc_contents",$_FN['datadir'],$_FN['lang'],$_FN['languages']);
    $all = $table->xmltable->GetRecords();
    $title = FN_i18n("catalog");
    $text = "";
    if (is_array($all) && isset($all[0]['title'])) {
        $contents = $table->GetRecordTranslated($all[0]);
        $text = $contents['text'];
        $title = $contents['title'] == "" ? FN_i18n("catalog") : $contents['title'];
    }
    $ret = array("text" => $text, "title" => $title);
    //dprint_r($ret);
    return $ret;
}

/**
 * navigazione tra le categorie
 * 
 */
function print_navigation()
{
    global $_FN;
    $cat = FN_GetParam("cat", $_GET, "html");
    $mode = FN_GetParam("mode", $_GET, "html");
    $vars = array();
    $vars = get_results();
    $vars['order'] = fnc_get_order_temp();
    $vars['cart_num_items'] = fnc_get_cart_count();
    $vars['url_cart'] = FN_RewriteLink("?mod={$_FN['mod']}&amp;op=showcart");
    $vars['navbar'] = get_category_path($cat);
    //dprint_r($vars);

    $htmltpl = file_get_contents(FN_FromTheme("modules/fncommerce/pages/navigation.tp.html"));
    $vars['url_orders'] = FN_RewriteLink("?mod={$_FN['mod']}&amp;op=orderstatus");
    if ($_FN['fnc_only_catalog']) {
        $vars['cart_num_items'] = false;
        $vars['url_orders'] = false;
    }
    echo FN_TPL_ApplyTplString($htmltpl, $vars);
    return;
}

/**
 *
 * @global array $_FNC
 * @global string $_FN 
 */
function print_results()
{
    global $_FNC, $_FN;
    if (!isset($_FN['fnc_sort_product']) || $_FN['fnc_sort_product'] == "")
        $_FN['fnc_sort_product'] = "sort_order";

    $cat = FN_GetParam("cat", $_GET, "flat");
    $mode = FN_GetParam("mode", $_GET, "flat");

    $filters = array();
    if ($_FN['show_categories_tree_check'] == "1") {

        $endres = false;
        $all = false;
        $allcats = $_FNC['categories'];
        if ($cat != "") {
            $_GET["cat_$cat"] = "1";
        }
        foreach ($allcats as $category) {
            $filter = FN_GetParam("cat_{$category['unirecid']}", $_GET, "flat");
            if ($filter == 1)
                $filters[] = $category['unirecid'];
        }
        foreach ($filters as $filter) {
            $ranges = fnc_get_range_restrictions();
            $res = fnc_getproductsbycategory($filter, $ranges);
            if ($all !== false) {
                $all = array_intersect_key($all, $res);
            } else
                $all = $res;
        }
    } else {
        $all = fnc_getproductsbycategory($cat);
    }
    if ($mode == "" || count($filters) == 0)
        print_subcategories($cat);
    if (is_array($all))
        $all = FN_ArraySortByKey($all, $_FN['fnc_sort_product']);
    print_products_table($all);
}

/**
 * 
 * @global array $_FNC
 * @global string $_FN
 * @return type
 */
function get_results()
{
    global $_FNC, $_FN;
    //$_FN['fnc_sort_product']="name";
    if (!isset($_FN['fnc_sort_product']) || $_FN['fnc_sort_product'] == "")
        $_FN['fnc_sort_product'] = "sort_order";

    $cat = FN_GetParam("cat", $_GET, "flat");
    $mode = FN_GetParam("mode", $_GET, "flat");

    $filters = array();
    if ($_FN['show_categories_tree_check'] == "1") {

        $endres = false;
        $all = array();
        $allcats = $_FNC['categories'];
        if ($cat != "") {
            $_GET["cat_$cat"] = "1";
        }
        foreach ($allcats as $category) {
            $filter = FN_GetParam("cat_{$category['unirecid']}", $_GET, "flat");
            if ($filter == 1)
                $filters[] = $category['unirecid'];
        }
        foreach ($filters as $filter) {
            $ranges = fnc_get_range_restrictions();
            $res = fnc_getproductsbycategory($filter, $ranges);
            if ($all !== array()) {
                $all = array_intersect_key($all, $res);
            } else
                $all = $res;
        }
    } else {
        if ($cat) {
            $all = fnc_getproductsbycategory($cat);
        } else {
            $all = fnc_getuncategorizedproducts();
        }
    }
    $cetegories = fnc_GetSubCategories($cat);


    if (is_array($all))
        $all = FN_ArraySortByKey($all, $_FN['fnc_sort_product']);



    return array("products" => $all, "categories" => $cetegories);
}

/**
 *
 *
 */
function html_orderstatus($idorder)
{
    $ordervalues = fnc_get_order($idorder);

    // Prepare template variables
    $vars = array();

    // Order information
    $vars['order_id'] = $idorder;
    $vars['order_status_raw'] = $ordervalues['orderstatus'];
    $vars['order_status'] = fnc_translate_orderstatus($ordervalues['orderstatus']);

    // Addresses
    $vars['billing_address'] = str_replace("\n", "<br />", $ordervalues['address']);
    $vars['shipping_address'] = str_replace("\n", "<br />", $ordervalues['shippingaddress']);

    // Order details
    $vars['order_cost_details'] = fnc_get_ordercost_details($ordervalues);

    // Translations
    $vars['txt_order_number'] = FN_Translate("order number");
    $vars['txt_order_status'] = FN_Translate("order status");
    $vars['txt_billing_address'] = FN_i18n("Billing address");
    $vars['txt_shipping_address'] = FN_i18n("Delivery address");
    $vars['txt_order_details'] = FN_Translate("order details");
    $vars['txt_payment'] = FN_Translate("payment");
    $vars['txt_shipping_status'] = FN_Translate("shipping status");

    // Payment module
    $vars['show_payment_module'] = false;
    $vars['payment_html'] = "";
    if ($ordervalues['orderstatus'] == "opened") {
        if (isset($ordervalues['payments']) && $ordervalues['payments'] != "" && file_exists(__DIR__ . "/../modules/payments/{$ordervalues['payments']}/module.php")) {
            require_once(__DIR__ . "/../modules/payments/{$ordervalues['payments']}/module.php");
            $classname = "fnc_payments_{$ordervalues['payments']}";
            $payment = new $classname($ordervalues);
            $vars['payment_html'] = $payment->do_payment();
            $vars['show_payment_module'] = true;
        }
    }

    // Shipping status
    $vars['show_shipping_status'] = false;
    $vars['shipping_status_html'] = "";
    if (isset($ordervalues['shippingmethods']) && $ordervalues['shippingmethods'] != "" && file_exists(__DIR__ . "/../modules/shippingmethods/{$ordervalues['shippingmethods']}/module.php")) {
        require_once(__DIR__ . "/../modules/shippingmethods/{$ordervalues['shippingmethods']}/module.php");
        $classname = "fnc_shippingmethods_{$ordervalues['shippingmethods']}";
        $shippingmethod = new $classname($ordervalues);
        $vars['shipping_status_html'] = $shippingmethod->get_orderstatus();
        $vars['show_shipping_status'] = true;
    }

    // Load and apply template
    $filetpl = FN_FromTheme("modules/fncommerce/pages/orderstatus.tp.html", false);
    $strtpl = file_get_contents($filetpl);

    return FN_TPL_ApplyTplString($strtpl, $vars);
}

/**
 *
 *
 */
function print_orders($user)
{
    global $_FN;
    $orders = fnc_get_orders(array(
        "username" => $user
    ));

    // Prepare template variables
    $vars = array();

    // Translations
    $vars['txt_orders_status'] = FN_Translate("orders status");
    $vars['txt_order_number'] = FN_Translate("order number");
    $vars['txt_date'] = FN_Translate("date");
    $vars['txt_status'] = FN_Translate("status");
    $vars['txt_total'] = FN_Translate("total");
    $vars['txt_actions'] = FN_Translate("actions");
    $vars['txt_view_status'] = FN_Translate("order status");
    $vars['txt_no_order'] = FN_Translate("no order");
    $vars['txt_continue_shopping'] = FN_translate("continue shopping");

    // Check if there are orders
    $vars['has_orders'] = is_array($orders) && count($orders) > 0;
    $vars['no_orders'] = !$vars['has_orders'];

    // Prepare orders array for template
    $vars['orders'] = array();
    if ($vars['has_orders']) {
        foreach ($orders as $ordervalues) {
            $order = array();
            $order['order_id'] = $ordervalues['unirecid'];
            $order['order_date'] = $ordervalues['time'];
            $order['order_status'] = fnc_translate_orderstatus($ordervalues['orderstatus']);
            $order['order_total'] = fnc_format_price($ordervalues['total']);
            $order['order_url'] = "?mod={$_FN['mod']}&amp;op=orderstatus&amp;orderid={$ordervalues['unirecid']}";
            $vars['orders'][] = $order;
        }
    }

    // URLs and images
    $vars['url_continue_shopping'] = FN_RewriteLink("?mod={$_FN['mod']}");
    $vars['img_continue'] = FN_FromTheme("images/fn_fastforward.png");

    // Load and apply template
    $filetpl = FN_FromTheme("modules/fncommerce/pages/orders.tp.html", false);
    $strtpl = file_get_contents($filetpl);

    echo FN_TPL_ApplyTplString($strtpl, $vars);
}

/**
 * salvataggio ordine
 *
 */
function print_saveorder()
{
    global $_FN;

    // Prepare template variables
    $vars = array();
    $vars['is_error'] = false;
    $vars['is_success'] = false;

    if ($_FN['user'] != '') {
        $table = FN_XMDBForm("fnc_conditions");
        $all = $table->xmltable->GetRecords(false, false, false, "sort_order");

        // Check if all conditions are accepted
        if (is_array($all) && count($all) > 0) {
            foreach ($all as $item) {
                if (!isset($_POST['conditions' . $item['unirecid']])) {
                    // Error: conditions not accepted
                    FN_Alert(FN_Translate("you must accept the terms of purchase"));
                    FN_JsRedirect("?mod={$_FN['mod']}&op=confirmorder");

                    $vars['is_error'] = true;
                    $vars['error_message'] = FN_Translate("you must accept the terms of purchase");
                    $vars['error_back_url'] = "?mod={$_FN['mod']}&amp;op=confirmorder";
                    $vars['txt_back'] = FN_translate("back");

                    // Load and apply template
                    $filetpl = FN_FromTheme("modules/fncommerce/pages/order_result.tp.html", false);
                    $strtpl = file_get_contents($filetpl);
                    echo FN_TPL_ApplyTplString($strtpl, $vars);
                    return;
                }
            }
        }

        $orderstatus = fnc_get_order_temp();

        if (isset($orderstatus['cart'])) {
            //--setto l' ordine ad aperto
            $orderstatus['orderstatus'] = "opened";
            $add = fnc_get_shipping_values_string($_FN['user']);
            $add = explode("{Delivery_address}", $add);
            $orderstatus['address'] = "{$add[0]}";
            $orderstatus['shippingaddress'] = "{$add[0]}";
            if (isset($add[1])) {
                $orderstatus['shippingaddress'] = "{$add[1]}";
            }

            //salva su db --->
            $newstate = fnc_save_order_status($orderstatus);
            //salva su db ---<
            //---invia stao dell'ordine--->
            fnc_send_confirm($newstate);
            //---invia stao dell'ordine---<
            //---chiama l' evento del modulo--->
            if (isset($newstate['costs']))
                foreach ($newstate['costs'] as $key => $v) {
                    if (isset($newstate[$key])) {
                        $classname = "fnc_$key" . "_{$newstate[$key]}";
                        require_once(__DIR__ . "/../modules/$key/{$newstate[$key]}/module.php");
                        $class = new $classname($newstate);
                        if (method_exists($class, "on_order_confirm"))
                            $class->on_order_confirm($newstate);
                    }
                }
            //---chiama l' evento del modulo---<
            //elimina ordine temporaneo --->
            fnc_clear_order_temp();
            //elimina ordine temporaneo ---<

            // Success: prepare variables for template
            $vars['is_success'] = true;
            $vars['txt_order_forwarded'] = FN_Translate("your order has been forwarded");
            $vars['order_status_html'] = html_orderstatus($newstate['unirecid']);
            $vars['txt_thanks_order'] = FN_Translate("thanks for your order");
            $vars['txt_view_orders'] = FN_Translate("view my orders");
            $vars['url_view_orders'] = "?mod={$_FN['mod']}&op=orderstatus";
        }
    }

    // Load and apply template
    $filetpl = FN_FromTheme("modules/fncommerce/pages/order_result.tp.html", false);
    $strtpl = file_get_contents($filetpl);
    echo FN_TPL_ApplyTplString($strtpl, $vars);
}

/**
 * schermata riassuntiva
 * per conferma ordine
 *
 *
 */
function print_confirm()
{
    global $_FN;
    $orderstatus = fnc_get_order_temp();
    $orderstatus['username'] = $_FN['user'];
    $cart = fnc_get_cart();

    // Prepare template variables
    $vars = array();
    $vars['form_action'] = "?mod={$_FN['mod']}&amp;op=saveorder";

    // Translations
    $vars['txt_confirm_order'] = FN_Translate("confirm this order");
    $vars['txt_my_orders'] = FN_Translate("my orders");
    $vars['txt_order_options'] = FN_Translate("order options");
    $vars['txt_customer_data'] = FN_Translate("customer data");
    $vars['txt_modify'] = FN_i18n("modify");
    $vars['txt_accept'] = FN_Translate("accept");

    // Order details
    $orderstatus = fnc_calculate_order_cost($orderstatus);
    $vars['order_details'] = fnc_get_ordercost_details($orderstatus);
    $vars['url_modify_cart'] = "?mod={$_FN['mod']}&amp;op=showcart";
    $orderstatus = fnc_save_order_temp($orderstatus);

    // Order steps (payment, shipping, etc.)
    $vars['order_steps'] = array();
    $steps = fnc_get_order_steps();
    foreach ($steps as $step) {
        if (isset($orderstatus[$step])) {
            $step_data = array();
            $title = FN_GetFolderTitle(__DIR__ . "/../modules/$step/");
            $step_data['step_title'] = $title;

            require_once(__DIR__ . "/../modules/$step/{$orderstatus[$step]}/module.php");
            $classname = "fnc_{$step}_{$orderstatus[$step]}";
            $stepclass = new $classname($orderstatus);

            $step_data['step_option_title'] = $stepclass->title();
            $des = $stepclass->description();
            $step_data['step_option_description'] = $des;
            $step_data['has_step_description'] = !empty($des);
            $step_data['step_modify_url'] = "?mod={$_FN['mod']}&amp;op=ordersteps&amp;orderstep=$step";

            $vars['order_steps'][] = $step_data;
        }
    }

    // Customer shipping data
    $vars['customer_data_html'] = fnc_get_shipping_values_htmlstring($orderstatus['username']);
    $vars['url_modify_shipping'] = FN_RewriteLink("?mod={$_FN['mod']}&amp;op=shipping");

    // Terms and conditions
    $table = FN_XMDBForm("fnc_conditions");
    $all = $table->xmltable->GetRecords(false, false, false, "sort_order");
    $vars['has_conditions'] = is_array($all) && count($all) > 0;
    $vars['conditions'] = array();

    if ($vars['has_conditions']) {
        foreach ($all as $item) {
            $item = $table->GetRecordTranslated(array("unirecid" => $item['unirecid']));
            $condition = array();
            $condition['condition_id'] = $item['unirecid'];
            $condition['condition_title'] = $item['title'];
            $condition['condition_text'] = $item['text'];
            $vars['conditions'][] = $condition;
        }
    }

    // Load and apply template
    $filetpl = FN_FromTheme("modules/fncommerce/pages/order_confirm.tp.html", false);
    $strtpl = file_get_contents($filetpl);

    echo FN_TPL_ApplyTplString($strtpl, $vars);
}

/**
 *
 * @global type $_FN 
 */
function print_cart()
{
    global $_FN;
    $op = FN_GetParam("op", $_GET, "html");
    $fromcat = FN_GetParam("from_cat", $_GET, "html");
    $t = FN_XMDBTable("fnc_products");

    $vars['shopping_cart'] = array();
    $filetpl = FN_FromTheme("modules/fncommerce/pages/shoppingcart.tp.html", false);
    $strtpl = file_get_contents($filetpl);

    $cart = fnc_get_cart();
    $products = array();
    if (is_array($cart) && count($cart) > 0) {
        $i = 0;
        $total = 0;
        foreach ($cart as $item) {
            $prod = fnc_getproduct($item['pid']);
            if (!$prod) {
                continue;
            }
            if (isset($prod['name'])) {
                if ($prod['photo1'] != "") {
                    $prod['product_img'] = $t->getFilePath($prod, "photo1");
                    $prod['url_photo1'] = $t->getFilePath($prod, "photo1");
                }
                $prod['url_remove'] = FN_RewriteLink("?op=$op&amp;mod={$_FN['mod']}&amp;addtocart={$item['pid']}&amp;qta=0");
            }
            $prod['qta'] = $item['qta'];
            $prod['ProductCode'] = $item['pid'];

            // Calcola il prezzo unitario in base alla quantità (supporta prezzi scaglionati)
            $unit_price = fnc_get_price_by_quantity($prod['price'], intval($item['qta']));
            $prod['price'] = $unit_price;
            $prod['price_txt'] = fnc_format_price($unit_price);
            $prod['total_price'] = intval($item['qta']) * $unit_price;
            $total += $prod['total_price'];
            $prod['total_price_txt'] = fnc_format_price($prod['total_price']);
            $products[] = $prod;
        }
        $vars['shopping_cart']['total_price'] = $total;
        $vars['shopping_cart']['total_price_txt'] = fnc_format_price($total);
        $vars['shopping_cart']['products'] = $products;
    }
    $vars['formaction'] = FN_RewriteLink("?mod={$_FN['mod']}&amp;op=setcart");
    $vars['urlcancel'] = FN_RewriteLink("?mod={$_FN['mod']}&cat=$fromcat");
    echo FN_TPL_ApplyTplString($strtpl, $vars);
}

/**
 * visualizza indirizzo di spedizione e/o fatturazione
 * 
 * @param array valori 
 */
function print_shipping_values($shippingvalues)
{
    global $_FN;
    echo fnc_get_shipping_values_htmlstring($shippingvalues['username']);
    return;
    $fields = FN_XMDBForm("fnc_users"); //new FieldFrm("fndatabase","fnc_users",$_FN['datadir']);
    $uservalues = $fields->xmltable->GetRecords(array(
        "username" => $shippingvalues['username']
    ));
    $uservalues = $uservalues['0'];
    $ret = "";
    foreach ($fields->formvals as $key => $value) {
        if (isset($value['frm_show']) && $value['frm_show'] != "1")
            continue;

        if (isset($fields->formvals[$key]['frm_group'])) {
            $ret .= "<h3>" . htmlspecialchars($fields->formvals[$key]['frm_group']) . ":</h3>";
        } else {
            if ($uservalues[$key] != "")
                $ret .= "<b>" . htmlspecialchars($value['title']) . "</b>: " . $uservalues[$key] . "<br />";
            else
                $ret .= "<b>" . htmlspecialchars($value['title']) . "</b>: -<br />";
        }
    }
    echo $ret;
}

/**
 * 
 * 
 */
function print_current_order_step($step)
{
    global $_FN;
    $list_modules_in_this_step = fnc_get_modules_in_step($step);
    $op = FN_GetParam("op", $_GET, "flat");
    $step_option_selected = FN_GetParam("$step", $_POST, "flat");
    FN_LoadMessagesFolder(__DIR__ . "/../modules/$step");
    $orderstatus = fnc_get_order_temp();
    if ($step == "" || !file_exists(__DIR__ . "/../modules/$step"))
        return print_navigation();
    //---------titolo step------------->
    $tmp = str_replace("_", " ", $step);
    $title = FN_GetFolderTitle(__DIR__ . "/../modules/$step/");
    //---------titolo step-------------<


    if ($step_option_selected != "") {
        $orderstatus['username'] = $_FN['user'];
        $orderstatus['cart'] = fnc_get_cart();
        $orderstatus[$step] = $step_option_selected;
        if (!isset($orderstatus['costs']))
            $orderstatus['costs'] = array();


        FN_LoadMessagesFolder(__DIR__ . "/../modules/$step/$step_option_selected");
        require_once(__DIR__ . "/../modules/$step/$step_option_selected/module.php");
        $classname = "fnc_$step" . "_" . $step_option_selected;
        $stepclass = new $classname($orderstatus);
        $orderstatus = $stepclass->get_total();
        fnc_save_order_temp($orderstatus);
        fnc_save_order_temp($orderstatus);
        $nextstep = fnc_get_next_order_step("$step");
        FN_JsRedirect("?mod={$_FN['mod']}&op=ordersteps&orderstep=$nextstep");
        return;
    }

    $old = fnc_get_order_temp();
    if (isset($old[$step]))
        $old = $old[$step];

    // Prepare template variables
    $vars = array();
    $vars['step_title'] = $title;
    $vars['step_name'] = $step;
    $vars['form_action'] = "?mod={$_FN['mod']}&amp;orderstep=$step&amp;op=$op";
    $vars['txt_back'] = FN_i18n("back");
    $vars['txt_next'] = FN_i18n("next");
    $vars['txt_cost'] = FN_i18n("cost");

    // Collect options from modules
    $options = array();
    $old_style_html = "";

    foreach ($list_modules_in_this_step as $payment) {
        $sid = $payment;
        if ($sid != "") {
            FN_LoadMessagesFolder(__DIR__ . "/../modules/$step/$sid");
            require_once(__DIR__ . "/../modules/$step/$sid/module.php");
            $classname = "fnc_$step" . "_$sid";
            $stepclass = new $classname($orderstatus);

            // Call show_option and check if it returns an array (new style) or echoes HTML (old style)
            ob_start();
            $option_data = $stepclass->show_option($orderstatus);
            $old_html = ob_get_clean();

            // If show_option returns an array, use new template system
            if (is_array($option_data)) {
                // New style: show_option returns structured data
                $option = array();
                $option['option_id'] = isset($option_data['id']) ? $option_data['id'] : $sid;
                $option['option_title'] = isset($option_data['title']) ? $option_data['title'] : $sid;
                $option['option_description'] = isset($option_data['description']) ? $option_data['description'] : '';
                $option['has_description'] = !empty($option['option_description']);
                $option['option_cost'] = isset($option_data['cost']) ? $option_data['cost'] : '';
                $option['has_cost'] = !empty($option['option_cost']);
                $option['checked'] = ($old == $option['option_id']) ? 'checked="checked"' : '';

                $options[] = $option;
            } else {
                // Old style: show_option echoed HTML directly
                $old_style_html .= $old_html;
            }
        }
    }

    // If we have new style options, pass them to template
    if (count($options) > 0) {
        $vars['options'] = $options;
    }
    // If we have old style HTML, we need to inject it (fallback for compatibility)
    if ($old_style_html != "") {
        // For old style modules, we'll need to handle this differently
        // We can add a special marker in the template or just echo before/after
        $vars['old_style_html'] = $old_style_html;
    }

    // Back link
    $pevstep = fnc_get_prev_order_step($step);
    if ($pevstep == "") {
        $vars['url_back'] = fn_rewritelink("?mod={$_FN['mod']}&op=shipping");
    } else {
        $vars['url_back'] = fn_rewritelink("?mod={$_FN['mod']}&op=$op&orderstep=$pevstep");
    }

    // Load and apply template
    $filetpl = FN_FromTheme("modules/fncommerce/pages/order_step.tp.html", false);
    $strtpl = file_get_contents($filetpl);

    echo FN_TPL_ApplyTplString($strtpl, $vars);
}

/**
 * visualizza schermata tipologia di pagamento
 * 
 */
function print_payments()
{
    global $_FN;
    $listpayments = list_sections_translated(__DIR__ . "/../modules/payments");
    $op = FN_GetParam("op", $_GET, "flat");
    $payment = FN_GetParam("payment", $_POST, "flat");
    echo _ADMIN_PAYMODE;
    if ($payment != "") {
        $orderstatus['username'] = $_FN['user'];
        $orderstatus['cart'] = fnc_get_cart();
        $orderstatus['payment'] = $payment;
        if (!isset($orderstatus['costs']))
            $orderstatus['costs'] = array();

        $cost = array(
            'title' => $payment,
            'total' => 10
        ); //deve tornare dalla funzione del modulo

        $orderstatus['costs'][$payment] = $cost;

        fnc_save_order_temp($orderstatus);
        FN_JsRedirect("?mod={$_FN['mod']}&op=confirmorder");
    }

    $old = fnc_get_order_temp();
    if (isset($old['payment']))
        $old = $old['payment'];
    echo "<form method=\"post\" action=\"?mod={$_FN['mod']}&amp;op=$op\" >";
    foreach ($listpayments as $payment) {
        $sid = get_section_id($payment['link']);
        $ck = "";
        if ($old == $sid)
            $ck = "checked=\"checked\"";
        echo "<br /><input $ck type=\"radio\" name=\"payment\" value=\"$sid\" />{$payment['title']}";
    }
    echo "<br /><br /><input type=\"submit\" value=\"" . FN_i18n("next") . " &gt;&gt;\" />";
    echo "</form>";
}

/**
 * visualizza schermata di login
 * 
 * 
 */
function print_login()
{
    global $_FN, $_FNMESSAGE;
    $op = FN_GetParam("op", $_GET, "flat");
    $opmod = FN_GetParam("opmod", $_GET, "flat");
    require_once("{$_FN['src_finis']}/modules/login/functions_login.php");
    switch ($opmod) {
        default:
            echo "<div style=\"text-align:center\"><table width=\"100%\" cellpadding=\"5\" cellspacing=\"5\" border=\"0\">";
            echo "<tr>";
            echo "<td style=\"text-align:center\"><b>" . FN_Translate("if you are a registered user, login to the site") . ":</b></td>";
            echo "<td style=\"text-align:center\"><b>" . FN_Translate("if you are a new customer please complete the form below") . "</b></td>";
            echo "</tr><tr>";

            echo "<td valign=\"top\" style=\"text-align:center\">";
            //-------utente password---->
            echo "<form method=\"post\" action=\"" . FN_RewriteLink("?mod=" . $_FN['mod'] . "&amp;op=$op&amp;fnlogin=login") . "\" >";
            echo FN_i18n("username") . "<br /><input type=\"edit\" size=\"15\" name=\"username\" /><br />";
            echo FN_i18n("password") . "<br /><input type=\"password\" size=\"15\" name=\"password\" /><br />";
            echo "<br /><input type=\"submit\" value=\"" . FN_i18n("next") . " &gt;&gt;\" />";
            echo "<br /><br /><b><a href=\"?mod=login&amp;op=rec_pass\">" . FN_Translate("password recovery") . "</a></b><br />";
            echo "<br />";
            echo "</form>";
            $fnlogin = FN_GetParam("fnlogin", $_GET, "flat");
            $fnuser = FN_GetParam("username", $_POST, "flat");
            $fnpwd = FN_GetParam("password", $_POST, "flat");
            if ($fnlogin == "login") {
                echo "<br />" . FN_Translate("authentication failure") . "<br />";
            }
            //-------utente password----<

            echo "</td>";
            echo "<td valign=\"top\" style=\"text-align:center\">";
            fnc_vis_reg();
            echo "</td>";
            echo "</tr></table></div>";
            break;
        case "vis_reg":
            FNREG_ManageRegister();
            break;
        case "register":
            $t = FNREG_ManageRegister(FN_RewriteLink("?mod={$_FN['mod']}&amp;op=shipping&amp;opmod=register"));
            if ($t == true) {
                $fnuser = FN_GetParam("username", $_POST, "flat");
                //FN_login($fnuser);
                //dprint_r($fnuser);

                FN_JsRedirect("?mod={$_FN['mod']}&op=$op");
                echo "<br /><a href=\"?mod={$_FN['mod']}&op=$op\">" . FN_i18n("complete your purchase") . "</a>";
                echo "&nbsp;&nbsp;<img style=\"vertical-align:middle\" src=\"" . FN_FromTheme("images/fn_fastforward.png") . "\" alt=\"\" title=\"" . FN_i18n("complete your purchase") . "\" />";
            }
            break;
    }
    //echo "<a href=\"?mod=login&amp;op=vis_reg&amp;from=".urlencode("?mod={$_FN['mod']}&op=$op")."\"><b>" . _REGORA . "</b></a>";
}

/**
 * 
 * @global array $_FN
 * @global type $forumborder
 * @global type $forumback
 * @return type
 */
function fnc_vis_reg()
{
    global $_FN, $forumborder, $forumback;
    if ($_FN['enable_registration'] == 0 && !FN_IsAdmin()) {
        echo FN_i18n("operation not allowed");
        return;
    }
    $op = FN_GetParam("op", $_GET, "flat");
    FNREG_ManageRegister(FN_RewriteLink("?mod={$_FN['mod']}&amp;op=shipping&amp;opmod=register"));
    return;

    $from = FN_GetParam("from", $_GET, "html");
    echo "<form enctype=\"multipart/form-data\" action=\"?mod=" . $_FN['mod'] . "&amp;op=$op&amp;opmod=register\" method=\"post\" name=\"registra\" >";
    //apre la tabella -->
    echo "\n<table>" .
        "\n<tbody>";
    echo "\n<tr>" .
        "\n<td align=\"center\" colspan=\"2\" >";
    //apre la tabella --<
    echo "<em>" . FN_i18n("required fields") . "</em><br />&nbsp;";
    echo "</td>";
    echo "</tr>";

    //dprint_r($_FN);
    $_fnextrauservalues = FN_XMDBForm("fnc_users"); // new FieldFrm("fndatabase","fn_users",$_FN['datadir'],$_FN['lang']);
    $_fnextrauservalues->setlayoutTags("<tr><td style=\"text-align:right;vertical-align:top\">", "</td>", "<td style=\"text-align:left;vertical-align:top\">", "</td></tr>");
    $_fnextrauservalues->ShowInsertForm(FN_IsAdmin());

    if (FN_IsAdmin()) {
        //livello-->
        echo "<tr><td>" . FN_Translate("level") . "</td>";
        echo "<td>";
        echo "<select name=\"level\">";
        for ($i = 0; $i < 11; $i++) {
            echo "<option value=\"$i\" >$i</option>";
        }
        echo "</select>";
        echo "</td></tr>";
        //livello--<
        //gruppo-->
        echo "<tr><td>" . FN_Translate("group") . "</td>";
        echo "<td>";
        $groups = FN_GetGroups();
        $group = "users";
        $grlist = explode(",", $group);
        foreach ($groups as $g) {
            $ck = in_array($g, $grlist) ? "checked=\"true\"" : "";
            print("<input $ck name=\"group-$g\" value=\"$group\" type=\"checkbox\" />$g<br />");
        }
        echo "</td></tr>";
        //gruppo--<
    }
    //invia--<
    echo "<tr><td>&nbsp;</td><td ><br /><input type=\"submit\" class=\"submit\" value=\"" . FN_i18n("next") . " &gt;&gt;\" /><br />&nbsp;</td></tr>";
    //invia--<
    echo "</tbody></table>";
    echo "</form>";
}

/**
 *
 * @global array $_FN
 * @param array $all  all products
 */
function print_products_table($all)
{
    global $_FN;
    //dprint_r($all);
    $cat = FN_GetParam("cat", $_GET, "flat");
    if (is_array($all) && count($all) > 0) {
        echo "<table width=\"100%\" border=\"0\">";
        foreach ($all as $prod) {
            echo "<tr>";
            echo "<td width=\"100\" style=\"text-align:center\">";

            if ($prod['photo1'] != "")
                if (file_exists("{$_FN['datadir']}/fndatabase/fnc_products/{$prod['unirecid']}/photo1/thumbs/{$prod['photo1']}.jpg")) {
                    if (isset($_FN['fnc_nothumbs']) && $_FN['fnc_nothumbs'] == "1")
                        echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;op=view&amp;id={$prod['unirecid']}&amp;cat=$cat") . "\"><img style=\"border:0px;\"  height=\"80\" src=\"{$_FN['datadir']}/fndatabase/fnc_products/{$prod['unirecid']}/photo1/{$prod['photo1']}\" /></a>";
                    else
                        echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;op=view&amp;id={$prod['unirecid']}&amp;cat=$cat") . "\"><img style=\"border:0px;\"  height=\"80\" src=\"{$_FN['datadir']}/fndatabase/fnc_products/{$prod['unirecid']}/photo1/thumbs/{$prod['photo1']}.jpg\" /></a>";
                } else
                    echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;op=view&amp;id={$prod['unirecid']}&amp;cat=$cat") . "\"><img style=\"border:0px;\"  height=\"80\" src=\"" . FN_FromTheme("fncommerce/images/product_default.png") . "\" /></a>";
            else
                echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;op=view&amp;id={$prod['unirecid']}") . "\"><img style=\"border:0px;\" height=\"80\" src=\"" . FN_FromTheme("fncommerce/images/product_default.png") . "\" /></a>";
            echo "</td>";
            echo "<td><a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;op=view&amp;id={$prod['unirecid']}&amp;cat=$cat") . "\">" . $prod['name'] . "</a>";
            //pk___xdb_fnc_products=7&op___xdb_fnc_products=insnew
            //index.php?fnapp=controlcenter&page___xdb_fnc_products=1&order___xdb_fnc_products=unirecid&op___xdb_fnc_products=insnew&pk___xdb_fnc_products=1&page___xdb_fnc_products=&mod=home&opt=fnEcommerce/products
            if (FN_IsAdmin())
                echo "<br /><br /><div><a href=\"index.php?fnapp=controlcenter&opt=fnEcommerce/products&op___xdb_fnc_products=insnew&pk___xdb_fnc_products={$prod['unirecid']}\">[" . FN_i18n("modify") . "]</a></div>";
            echo "</td>";
            if ($_FN['fnc_show_prices'])
                echo "<td style=\"text-align:right\">" . fnc_format_price($prod['price']) . "</td>";
            if (!$_FN['fnc_only_catalog']) {
                echo "<td style=\"text-align:center\">";

                //aggiunge e visualizza carrello
                echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;op=addtocart&amp;p={$prod['unirecid']}&amp;from_cat=$cat") . "\">" .
                    "<img style=\"border:0px;vertical-align:middle\" src=\"" . FN_FromTheme("fncommerce/images/cart.png") . "\" />" .
                    FN_Translate("add to chart") . "</a>";

                // aggiunge al carrello
                //	echo "<br /><a href=\"?mod={$_FN['mod']}&amp;cat=$cat&amp;addtocart={$prod['unirecid']}\">" . 
                //	"<img style=\"border:0px;vertical-align:middle\" src=\"".FN_FromTheme("fncommerce/images/cart.png")."\" />".
                //	FN_Translate("add to chart") . "</a>";

                echo "</td>";
            }
            echo "</tr>";
            echo "<tr><td colspan=\"4\"><hr /></td></td>";
        }
        echo "</table>";
    }
}

/**
 * 
 * stampa tutte le cattegorie una a fianco all' altra
 * 
 */
function print_categories_flat()
{
    global $_FN;
    $current = FN_GetParam("cat", $_GET, "flat");
    $all = fnc_GetCategories();
    foreach ($all as $cat) {
        if (!empty($cat['hidden']))
            continue;

        $num = fnc_getproductsbycategory($cat['unirecid']);
        if (!is_array($num))
            $num = 0;
        else
            $num = count($num);

        if ($num > 0) {
            if ($cat['unirecid'] == $current)
                echo "<b>";
            echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;cat={$cat['unirecid']}") . "\">" . str_replace(" ", "&nbsp;", $cat['name']) . "</a>";
            if ($cat['unirecid'] == $current)
                echo "</b>";
            echo "&nbsp;($num)";
            echo " |&nbsp;";
        }
    }
}

/**
 * 
 * @param type $cat
 * @return type
 */
function fnc_GetSubCategories($cat)
{
    return fnc_GetCategories(array("parent" => $cat));
}

/**
 * 
 * 
 */
function print_subcategories($cat)
{
    global $_FN;
    $items = fnc_GetCategories(array("parent" => $cat));
    //dprint_r($items);
    //die();
    //$w=145;
    //$h=155;
    if ($items) {
        echo "<div class=\"fnc_subcategories\" style=\"\">";
        foreach ($items as $cat) {
            if (!empty($cat['hidden']))
                continue;
            echo "<div class=\"fnc_category fnc_category_{$cat['unirecid']}\" style=\"\">";

            //echo "<table style=\"display:inline;zoom:1;height:{$h}px;width:{$w}px;margin:2px;\"><tr><td width=\"{$w}\" height=\"{$h}\"valign=\"center\" style=\"text-align:center\">";
            if (isset($cat['photo1']) && $cat['photo1'] != "") {
                echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;cat={$cat['unirecid']}") . "\"><img title=\"{$cat['name']}\" style=\"\"  src=\"{$_FN['datadir']}/fndatabase/fnc_categories/{$cat['unirecid']}/photo1/{$cat['photo1']}\" /></a>";
            } else
                echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;cat={$cat['unirecid']}") . "\"><img title=\"{$cat['name']}\" style=\"\"  src=\"" . FN_FromTheme("fncommerce/images/category_default.png") . "\" /></a>";
            echo "<br /><a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;cat={$cat['unirecid']}") . "\">{$cat['name']}</a>";
            //echo "</td></tr></table>  ";
            echo "</div>";
        }
        echo "<br class=\"\" style=\"\"/>";
        echo "</div>";
    }
}

function fnc_get_range_restrictions()
{
    global $_FN;
    $ret = array();
    if ($_FN['show_ranges'] != "") {
        $ranges = explode(",", $_FN['show_ranges']);
        foreach ($ranges as $range) {
            $min = FN_GetParam($range . '-min', $_GET, "html");
            $max = FN_GetParam($range . '-max', $_GET, "html");
            if ($min != "")
                $ret[$range . '-min'] = FN_GetParam($range . '-min', $_GET, "html");
            if ($max != "")
                $ret[$range . '-max'] = FN_GetParam($range . '-max', $_GET, "html");
        }
    }
    return $ret;
}

function fnc_get_range_restrictions_vars()
{
    global $_FN;
    $ret = "";
    if ($_FN['show_ranges'] != "") {
        $ranges = explode(",", $_FN['show_ranges']);
        foreach ($ranges as $range) {
            $ret .= "&amp;$range-min=" . FN_GetParam($range . '-min', $_GET, "html");
            $ret .= "&amp;$range-max=" . FN_GetParam($range . '-max', $_GET, "html");
        }
    }
    return $ret;
}

/**
 * stampa il menu con i checkbox per negozi
 * che hanno molte categorie e gli stessi prodotti
 * su piu' categorie, permette una ricerca avanzata
 * 
 * 
 */
function print_categories_tree_check($parent = "")
{
    global $_FN;
    echo "<form id=\"fnc_categories_form\" method=\"get\" action=\"?mod={$_FN['mod']}\">";
    echo "<input type=\"hidden\" name=\"mod\" value=\"{$_FN['mod']}\" />";
    echo "<input type=\"hidden\" name=\"mode\" value=\"categories\" />";
    $restr_ranges = fnc_get_range_restrictions();
    foreach ($restr_ranges as $k => $v)
        echo "<input type=\"hidden\" name=\"$k\" value=\"$v\" />";

    print_categories_tree_check_rec();
    echo "</form>";
}

function print_categories_tree_check_rec($parent = "")
{
    global $_FN;
    static $lev = 0;
    $ret = array();
    $current = FN_GetParam("cat", $_GET, "flat");
    $items = fnc_GetCategories(array("parent" => "$parent"));
    if (!$items || count($items) == 0)
        return $ret;
    foreach ($items as $item) {
        if (!empty($item['hidden']))
            continue;
        $ck = "";
        $c = FN_GetParam("cat_{$item['unirecid']}", $_GET, "flat");
        if ($c == 1 || $current == $item['unirecid'])
            $ck = "checked=\"checked\"";
        $restr = fnc_get_range_restrictions();
        $num = fnc_getproductscountbycategory($item['unirecid'], $restr);
        echo "<nobr>";
        $dis = ($num == 0) ? "disabled=\"disabled\"" : "";
        //stampa identazione
        for ($i = 0; $i < $lev; $i++)
            echo "&nbsp;";

        // se e' il livello zero e non ci sono prodotti non stampo il check
        // poche' si tratta di un gruppo di categorie
        $is_group = false;
        if ($lev == 0 && $num == 0) {
            echo "<b>" . $item['name'] . "</b>";
            $is_group = true;
        } else {
            echo "<input $dis name=\"cat_{$item['unirecid']}\" value=\"1\"  onclick=\"document.getElementById('fnc_categories_form').submit()\" type=\"checkbox\" $ck />";
            echo $item['name'] . "&nbsp;($num)";
        }
        echo "</nobr><br />";
        if ($is_group) {
            echo "<div style=\"border:1px solid;overflow:auto;height:200px;\">";
        }
        $lev++;
        print_categories_tree_check_rec($item['unirecid']);
        $lev--;
        if ($is_group) {
            echo "</div>";
        }
    }
    return;
}

/**
 * mostra il menu con tutte le categorie
 * 
 * 
 */
function print_categories_tree($parent = "")
{
    global $_FN;
    static $lev = 0;
    $ret = array();
    $current = FN_GetParam("cat", $_GET, "flat");
    $items = fnc_GetCategories(array("parent" => "$parent"));
    $num = "";
    if (!$items || count($items) == 0)
        return $ret;
    echo "";
    foreach ($items as $item) {
        if (!empty($item['hidden']))
            continue;
        $restr = fnc_get_range_restrictions();
        //dprint_r($restr);
        $num = fnc_getproductscountbycategory($item['unirecid'], $restr);
        echo "<br /><nobr>";
        for ($i = 0; $i < $lev; $i++)
            echo "&nbsp;&nbsp;&nbsp;&nbsp;";
        if ($current == $item['unirecid'])
            echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&cat={$item['unirecid']}") . "\"><b>" . $item['name'] . "</b>&nbsp;($num)</a>";
        else
            echo "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&cat={$item['unirecid']}") . "\">" . $item['name'] . "&nbsp;($num)</a>";
        echo "</nobr>";
        $lev++;
        print_categories_tree($item['unirecid']);
        $lev--;
    }
    echo "";
    //	dprint_r($items);
    //	die();
    return;
}

function order_is_freeeshipping($orderstatus)
{
    foreach ($orderstatus['cart'] as $item) {
        $prod = fnc_getproduct($item['pid']);
        if ($prod['freeshipping'] != 1) {
            return false;
        }
    }
    return true;
}

/**
 * scrive dati di fatturazione e indirizzo spedizione
 * 
 * 
 */
function print_shipping()
{
    global $_FN;
    if (!fnc_get_cart()) {
        // Empty cart - use template
        $vars = array();
        $vars['txt_cart_empty'] = FN_Translate("your cart is empty");
        $vars['txt_continue_shopping'] = FN_translate("continue shopping");
        $vars['url_continue_shopping'] = fn_rewritelink("?mod={$_FN['mod']}");

        $filetpl = FN_FromTheme("modules/fncommerce/pages/shipping_empty_cart.tp.html", false);
        $strtpl = file_get_contents($filetpl);
        echo FN_TPL_ApplyTplString($strtpl, $vars);
        return;
    }
    $op = FN_GetParam("op", $_REQUEST, "flat");
    //$edit = FN_GetParam("edit", $_GET, "flat");
    $save = FN_GetParam("save", $_POST, "flat");
    $update = FN_GetParam("update", $_GET, "flat");
    $Table = FN_XMDBForm("fnc_users"); //new FieldFrm("fndatabase","fnc_users",$_FN['datadir'],$_FN['lang']);
    $newvalues = $Table->getbypost();
    //nascondo indirizzo di spedizione
    $err = "";
    $order = fnc_get_order_temp();
    if ($_FN['fnc_enable_recipient'] == 0 || order_is_freeeshipping($order)) {
        $newvalues['doshippingaddress'] = "no";
        $Table->formvals['doshippingaddress']['frm_show'] = 0;
        $Table->formvals['doshippingaddress']['required'] = 0;
        foreach ($Table->formvals as $key => $val) {
            if (isset($Table->formvals[$key]['frm_group']))
                unset($Table->formvals[$key]['frm_group']);
            if (isset($Table->formvals[$key]['frm_endgroup']))
                unset($Table->formvals[$key]['frm_endgroup']);

            if (fn_erg("^shipping", $key)) {
                $Table->formvals[$key]['frm_show'] = 0;
            }
        }
    }

    $notes = isset($order['notes']) ? $order['notes'] : "";
    //---salva le note sull' ordine --->
    if (isset($_POST['notes'])) {
        $order['notes'] = FN_GetParam("notes", $_POST, "flat");
        fnc_save_order_temp($order);
    }
    //---salva le note sull' ordine ---<

    foreach ($newvalues as $key => $value) {
        $newvalues[$key] = htmlspecialchars($value, ENT_QUOTES, $_FN['charset_page']);
    }

    $newvalues['username'] = $_FN['user'];

    $edit = "";
    if ($_FN['user'] == "") {
        print_login();
    } else {
        if ($save != "") {
            if ($update)
                $update = true;
            $err = $Table->Verify($newvalues, $update);
            //dprint_r($err);
            if (count($err) > 0) {
                FN_Alert(FN_i18n("you skipped some fields or you made some error"));
            } else {
                if ($update)
                    $Table->xmltable->UpdateRecord($newvalues);
                else
                    $Table->xmltable->InsertRecord($newvalues);

                $nextstep = fnc_get_next_order_step();
                FN_JsRedirect("" . fn_rewritelink("?mod={$_FN['mod']}&op=ordersteps&orderstep=$nextstep") . "");
            }
        }
        $listaddress = fnc_get_shipping_values($_FN['user']);
        if (isset($listaddress[0]['unirecid'])) {
            $edit = $listaddress[0]['unirecid'];
        }



        //----template--------->

        $tplfile =  FN_FromTheme("modules/fncommerce/pages/dbform.tp.html", false);
        $template = file_get_contents($tplfile);
        //die ($tplfile);
        $tpvars = array();
        $tpvars['formaction'] = "";
        $tpvars['urlcancel'] = "";
        $template = FN_TPL_ApplyTplString($template, $tpvars);
        //$template =str_replace($esc,"if {",$template);
        $Table->SetlayoutTemplate($template);


        //----template---------<


        //------visualizzazione degli errori di compilazione------>
        $errortext = "";
        if (is_array($err))
            foreach ($err as $key => $value) {
                $htmlerror = "<img src=\"" . FN_FromTheme("images/icons/error.png") . "\" alt=\"\" title=\"{$value['error']}\" />";
                $errortext .= "<b>{$value['title']}</b>: {$value['error']}<br />";
                if (!isset($Table->formvals[$key]['frm_endtagvalue']))
                    $Table->formvals[$key]['frm_endtagvalue'] = "";
                $Table->formvals[$key]['frm_endtagvalue'] = $htmlerror . $Table->formvals[$key]['frm_endtagvalue'];
            }
        //------visualizzazione degli errori di compilazione------<
        if (order_is_freeeshipping($order)) {
            $Table->formvals['doshippingaddress']['frm_show'] = 0;
            $Table->formvals['doshippingaddress']['frm_required'] = 0;

            $Table->formvals['shippingname']['frm_show'] = 0;
            $Table->formvals['shippingname']['frm_required'] = 0;

            $Table->formvals['shippingaddress']['frm_show'] = 0;
            $Table->formvals['shippingaddress']['frm_required'] = 0;


            $Table->formvals['shippingcity']['frm_show'] = 0;
            $Table->formvals['shippingcity']['frm_required'] = 0;


            $Table->formvals['shippingcountry']['frm_show'] = 0;
            $Table->formvals['shippingcountry']['frm_required'] = 0;

            $Table->formvals['shippingzone']['frm_show'] = 0;
            $Table->formvals['shippingzone']['frm_required'] = 0;

            $Table->formvals['shippingzip']['frm_show'] = 0;
            $Table->formvals['shippingzip']['frm_required'] = 0;

            $Table->formvals['shippingtelephone']['frm_show'] = 0;
            $Table->formvals['shippingtelephone']['frm_required'] = 0;

            $Table->formvals['shippingname']['frm_group'] = "";
            $Table->formvals['shippingname']['frm_required'] = "";
        }
        // Prepare template variables
        $vars = array();
        $vars['txt_shipping_title'] = FN_Translate("please complete the shipping information");
        $vars['txt_required_fields'] = FN_i18n("required fields");
        $vars['txt_notes'] = FN_Translate("notes");
        $vars['txt_back'] = FN_i18n("back");
        $vars['txt_next'] = FN_i18n("next");
        $vars['notes_value'] = htmlspecialchars($notes);
        $vars['url_back'] = "?mod={$_FN['mod']}&op=showcart";
        $vars['has_errors'] = !empty($errortext);
        $vars['error_text'] = $errortext;

        // Generate dynamic form HTML
        if ($edit != "") {
            $vars['is_update'] = true;
            $vars['edit_id'] = $edit;
            $vars['form_action'] = "?mod={$_FN['mod']}&amp;op=$op&amp;update=1&amp;edit=$edit";

            $values = $Table->xmltable->GetRecordByPrimaryKey($edit);
            if ($_FN['user'] != $values['username'] && !FN_IsAdmin())
                die(FN_i18n("operation not allowed"));
            $vars['form_html'] = $Table->HtmlShowUpdateForm($edit, FN_IsAdmin(), $newvalues);
        } else {
            $vars['is_update'] = false;
            $vars['form_action'] = "?mod={$_FN['mod']}&amp;op=$op";
            $vars['form_html'] = $Table->HtmlShowInsertForm(FN_IsAdmin(), $newvalues);
        }

        // Load and apply template
        $filetpl = FN_FromTheme("modules/fncommerce/pages/shipping_form.tp.html", false);
        $strtpl = file_get_contents($filetpl);
        echo FN_TPL_ApplyTplString($strtpl, $vars);
    }
}

/**
 * html di vai al carrello
 * 
 */
function html_cart()
{
    global $_FN;
    return "<div style=\"text-align:right;\">" .
        "<img style=\"vertical-align:middle;border:0px;\" src=\"" . FN_FromTheme("fncommerce/images/mycart.png") . "\" alt=\"\" title=\"" . FN_Translate("my chart") . "\"/>&nbsp;<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;op=showcart") . "\">"
        . FN_Translate("my chart") . " (" . fnc_get_cart_count() . ")</a></div>";
}

function FN_TPL_create_product_details($html)
{
    echo "$html";
}

/**
 * stampa la scheda prodotto
 * 
 */
function html_product($pid)
{
    global $_FN;
    $config = FN_LoadConfig("modules/fncommerce/config.php", "fncommerce");
    $cat = FN_GetParam("cat", $_GET, "html");
    $ret = "";
    $t = FN_XMDBTable("fnc_products");
    $prod = fnc_getproduct($pid);

    $vars = $config;
    $vars['is_admin'] = FN_IsAdmin();
    foreach ($prod as $k => $v) {
        $vars['product_' . $k] = $v;
    }
    foreach ($config as $k => $v) {
        $vars['config_' . $k] = $v;
    }
    $man = fnc_getmanufacturersById($prod['manufacturers']);
    foreach ($man as $k => $v) {
        $params['manufacture_' . $k] = $v;
    }
    $vars['in_stock'] = true;
    $vars['txt_in_stock'] = "";
    if (!$_FN['fnc_only_catalog']) {
        $in_stock = fnc_get_in_stock($prod['unirecid'], true);
        if ($in_stock == "INFINITE") {
            $vars['txt_in_stock'] = FN_Translate("in stock");
        } elseif ($in_stock <= 0) {
            $vars['txt_in_stock'] = FN_Translate("currently not available");
            $vars['in_stock'] = false;
        } else {
            $vars['txt_in_stock'] = "$in_stock " . FN_Translate("in stock");
        }
    }
    $vars['url_modify'] = "index.php?fnapp=controlcenter&opt=fnEcommerce/products&op___xdb_fnc_products=insnew&pk___xdb_fnc_products={$prod['unirecid']}";
    $vars['product_img'] = $t->getFilePath($prod, "photo1");
    $vars['navbar'] = get_category_path($prod['category']);
    $vars['order'] = fnc_get_order_temp();
    $vars['cart_num_items'] = fnc_get_cart_count();
    $vars['url_cart'] = FN_RewriteLink("?mod={$_FN['mod']}&amp;op=showcart");

    // Supporto prezzi scaglionati
    if (strpos($prod['price'], ':') !== false) {
        $vars['product_price_tiers'] = fnc_format_price_tiers($prod['price'], 'html');
        $vars['product_has_tiered_pricing'] = true;
        $vars['product_price_from'] = FN_i18n("from") . " " . fnc_format_price(fnc_get_price_by_quantity($prod['price'], 1));
    } else {
        $vars['product_price_tiers'] = '';
        $vars['product_has_tiered_pricing'] = false;
        $vars['product_price_from'] = '';
    }

    $vars['url_addtocart'] = FN_RewriteLink("?mod={$_FN['mod']}&amp;op=addtocart&amp;p={$prod['unirecid']}&amp;from_cat=$cat");
    $vars['url_cancel'] = FN_RewriteLink("?mod={$_FN['mod']}&cat=$cat");
    $filetpl = FN_FromTheme("modules/fncommerce/pages/product.tp.html", false);
    $str = file_get_contents($filetpl);
    $str = FN_TPL_ApplyTplString($str, $vars, dirname($filetpl) . "/");
    echo $str;
    return;
}

/**
 * 
 * @global string $_FN
 * @param type $id
 * @param type $sep
 * @return string
 */
function get_category_path($id = "")
{
    global $_FN;
    $t = fnc_getcontents_config();
    $t = $t['title'];
    $items = array();
    $item['title'] = $t;
    $item['url'] = FN_RewriteLink("?mod={$_FN['mod']}", "&amp;", true);
    $items[] = $item;
    if ($id == "")
        return $items;
    $tree = array();
    $cat = fnc_getcategory($id);
    $tree[] = $cat;
    if (!$cat)
        return $items;
    while ($cat['parent'] != "") {
        $cat = fnc_getcategory($cat['parent']);
        $tree[] = $cat;
    }
    $tree = array_reverse($tree);
    foreach ($tree as $item) {
        $item['title'] = $item['name'];
        $item['url'] = fn_rewritelink("?mod={$_FN['mod']}&amp;cat={$item['unirecid']}");
        $items[] = $item;
    }
    return $items;
}

/**
 * stama la barra di navigazione delle categorie
 * 
 */
function html_category_path($id, $sep = "-&gt;")
{
    global $_FN;
    $t = fnc_getcontents_config();
    $t = $t['title'];
    $ret = "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}") . "\">$t</a>";
    if ($id == "")
        return $ret;
    $tree = array();
    $cat = fnc_getcategory($id);
    $tree[] = $cat;

    if (!$cat)
        return $ret;
    while ($cat['parent'] != "") {
        $cat = fnc_getcategory($cat['parent']);
        $tree[] = $cat;
    }
    //	dprint_r($tree);
    $tree = array_reverse($tree);

    foreach ($tree as $item) {
        $ret .= $sep;
        $ret .= "<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;cat={$item['unirecid']}") . "\">{$item['name']}</a>";
    }
    $ret .= "";
    return $ret;
}

/**
 *
 * @global array $_FN
 * @global array $_FNC
 * @param string $parent
 * @param string $level
 * @return string 
 */
function html_categories($parent = "", $level = "")
{
    global $_FN, $_FNC;
    $ret = "";
    $current = FN_GetParam("cat", $_GET, "flat");
    $all = $_FNC['categories'];
    foreach ($all as $cat) {
        if ($cat['parent'] == $parent) {
            if ($cat['unirecid'] == $current)
                $ret .= "<b>";
            echo "<br />$level<a href=\"" . fn_rewritelink("?mod={$_FN['mod']}&amp;cat={$cat['unirecid']}") . "\">" . $cat['name'] . "</a>";
            if ($cat['unirecid'] == $current)
                $ret .= "</b>";
            $num = fnc_getproductsbycategory($cat['unirecid']);
            if (!is_array($num))
                $num = 0;
            else
                $num = count($num);
            $ret .= "($num)";
            $ret .= html_categories($cat['unirecid'], "---");
        }
    }
    return $ret;
}

/**
 *
 * @global array $_FN
 * @param string $fieldnames
 * @return string 
 */
function html_range($fieldnames)
{
    global $_FN;
    $cat = FN_GetParam("cat", $_GET, "flat");

    $ret = "";
    $table = FN_XMDBForm("fnc_products"); //new FieldFrm("fndatabase","fnc_products",$_FN['datadir'],$_FN['lang'],$_FN['languages']);

    $products = $table->xmltable->GetRecords();
    $fieldnames = explode(",", $fieldnames);

    $ret .= "<form method=\"get\" action=\"\">";
    $ret .= "<input type=\"hidden\" name=\"mod\" value=\"{$_FN['mod']}\" />";
    $ret .= "<input type=\"hidden\" name=\"cat\" value=\"$cat\" />";
    foreach ($fieldnames as $fieldname) {
        $max = $min = intval(round($products[0][$fieldname], 0));
        foreach ($products as $product) {
            $min = min(intval(round($product[$fieldname], 0) - 1), intval($min));
            $max = max(intval(round($product[$fieldname], 0)), intval($max));
        }
        $searchmin = FN_GetParam("$fieldname-min", $_GET, "html");
        $searchmax = FN_GetParam("$fieldname-max", $_GET, "html");
        if ($min < 0)
            $min = 0;
        if ($searchmin == "")
            $searchmin = $min;
        if ($searchmax == "")
            $searchmax = $max;

        $fieldtitle = $table->formvals[$fieldname]['title'];;
        $ret .= "<nobr>$fieldtitle da:<input size=\"5\" name=\"$fieldname-min\" type=\"text\" name=\"$fieldname-min\" value=\"$searchmin\" />";
        $ret .= "a:<input size=\"5\" name=\"$fieldname-max\" type=\"text\" name=\"$fieldname-max\" value=\"$searchmax\" /></nobr> ";
    }
    $ret .= "<input type=\"submit\" name=\"op\" value=\"" . FN_Translate("search") . "\" />";
    $ret .= "</form>";
    return $ret;
}
