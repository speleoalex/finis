<?php

/**
 * @package flatnux_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
global $_FN;
include_once(__DIR__ . "/functions/fncommerce.php");
//----inizializzazione tabelle ---->
fnc_initTables();
//----inizializzazione tabelle ----<
$cat = FN_GetParam("cat", $_GET, "html");
$pid = FN_GetParam("id", $_GET, "html");
$p = FN_GetParam("p", $_GET, "flat");
$op = FN_GetParam("op", $_REQUEST, "flat");
$orderstep = FN_GetParam("orderstep", $_GET, "flat");

$qta = FN_GetParam("qta", $_GET, "flat");
$orderid = FN_GetParam("orderid", $_GET, "flat");
$addcart = FN_GetParam("addtocart", $_GET, "flat");
//aggiunge al volo
if ($addcart != "") {
    fnc_add_to_cart($addcart, $qta, true);
}

if ($config['fnc_only_catalog']) {
    switch ($op) {
        case "view":
            echo html_product($pid);
            break;
        default:
            print_navigation();
            break;
    }
} else
    switch ($op) {
        case "orderstatus":
            if (empty($_FN['user'])) {
                // User not logged in - show login link
                echo "<h3>" . FN_Translate("orders status") . "</h3>";
                echo "<p>" . FN_Translate("please login to view your orders") . "</p>";
                echo "<p><a href=\"" . FN_RewriteLink("index.php?mod=login") . "\">" . FN_Translate("login") . "</a></p>";
            } elseif ($orderid == "") {
                print_orders($_FN['user']);
            } else {
                if (empty($_GET['pdf'])) {
                    echo "<h3>" . FN_Translate("my orders") . ":</h3>";
                    echo html_orderstatus($orderid);
                    //  echo "<br /><a href=\"pdf.php?mod=fncommerce&op=orderstatus&orderid=$orderid&pdf=1\"><img src=\"images/mime/pdf.png\" /> ".FN_Translate("Download order")."</a>";
                } else {
                    $_FN['site_title'] .= "-" . FN_Translate("order") . "_" . $orderid;
                    echo "<div style=\"margin:20px;\">" . html_orderstatus($orderid) . "</div>";
                }
            }
            break;
        case "addtocart":
            fnc_add_to_cart($p, $qta);
            print_cart();
            break;
        case "end_reg":
            require_once("{$_FN['src_finis']}/modules/login/functions_login.php");
            if (FNREG_ManageRegister(FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=shipping&amp;opmod=register"))) {
                echo "<div><br />";
                echo "<a href=\"" . FN_rewriteLink("?mod={$_FN['mod']}&op=shipping") . "\">" . FN_Translate("next") . " &gt;&gt;</a>";
                echo "</div>";
            }
            break;

        case "ordersteps":
            if ($orderstep == "confirmorder")
                print_confirm();
            else
                print_current_order_step($orderstep);
            break;
        case "setcart":
            print_cart();
            break;
        case "shipping":
            print_shipping();
            break;
        case "showcart":
            print_cart();
            break;
        case "empty_cart":
            fnc_empty_cart();
            break;
        case "view":
            echo html_product($pid);

            break;
        case "confirmorder":
            print_confirm();
            break;
        case "saveorder":
            print_saveorder();
            break;

        case "offers":
            print_offers();
            break;
        default:
            print_navigation();
            break;
    }
