<?php
global $_FN;
class fnc_payments_Delegated_payment
{
    var $order;

    function __construct($order)
    {
        $this->order = $order;
    }

    function title()
    {
        return FN_Translate("Deferred payment");
    }

    function description()
    {
        return FN_Translate("You will receive payment details later");
    }

    /**
     * Returns option data for display
     */
    function show_option($order)
    {
        $config = FN_LoadConfig("modules/fncommerce/modules/payments/Delegated_payment/config.php");
        $costvalue = isset($config['cost']) ? $config['cost'] : 0;

        $option = array(
            'id' => 'Delegated_payment',
            'title' => FN_Translate("Deferred payment"),
            'description' => FN_Translate("You will receive payment details later"),
            'cost' => ($costvalue > 0) ? fnc_format_price($costvalue) : ''
        );

        return $option;
    }

    /**
     * Transaction cost
     */
    function get_total()
    {
        $config = FN_LoadConfig("modules/fncommerce/modules/payments/Delegated_payment/config.php");
        $costvalue = isset($config['cost']) ? $config['cost'] : 0;
        if ($costvalue > 0) {
            $cost = array(
                'title' => "Delegated_payment",
                'total' => $costvalue
            );
            $this->order['costs']["payments"] = $cost;
        }
        return $this->order;
    }

    /**
     * Payment info shown in order confirmation/summary
     */
    function do_payment()
    {
        $config = FN_LoadConfig("modules/fncommerce/modules/payments/Delegated_payment/config.php");
        $company_name = isset($config['company_name']) ? $config['company_name'] : "";
        $buyer_message = isset($config['buyer_message']) ? $config['buyer_message'] : "";

        $str = "<b>" . FN_Translate("Payment information") . ":</b><br />";
        $str .= fnc_format_price($this->order['total']) . "<br /><br />";
        if ($company_name != "") {
            $str .= FN_Translate("Payment managed by") . ": <b>" . htmlspecialchars($company_name) . "</b><br />";
        }
        $str .= FN_Translate("You will receive payment details later") . "<br />";
        if ($buyer_message != "") {
            $str .= "<br />$buyer_message<br />";
        }
        return $str;
    }

    /**
     * Called on order confirmation - sends email to third-party company
     */
    function on_order_confirm($order)
    {
        global $_FN;
        $config = FN_LoadConfig("modules/fncommerce/modules/payments/Delegated_payment/config.php");
        $company_email = isset($config['company_email']) ? $config['company_email'] : "";
        $company_lang = isset($config['company_lang']) ? trim($config['company_lang']) : "";

        if ($company_email == "")
            return;

        // Switch language for email if configured
        $original_lang = $_FN['lang'];
        if ($company_lang != "") {
            $_FN['lang'] = $company_lang;
            FN_LoadMessagesFolder("modules/fncommerce/");
            FN_LoadMessagesFolder("modules/fncommerce/modules/payments/Delegated_payment");
        }

        $user = FN_GetUser($order['username']);
        $buyer_data = fnc_get_shipping_values($order['username']);
        $buyer = isset($buyer_data[0]) ? $buyer_data[0] : array();

        $subject = fnc_applytpl(
            FN_Translate("{sitename} - New order N.{ordernumber} - payment request"),
            array("sitename" => $_FN['sitename'], "ordernumber" => $order['unirecid'])
        );

        $body = "<h2>" . FN_Translate("New order requiring payment") . "</h2>";
        $body .= "<p>" . FN_Translate("A new order has been placed and requires payment handling") . ".</p>";
        $body .= "<hr />";

        // Order details
        $body .= "<h3>" . FN_Translate("Order") . " #" . $order['unirecid'] . "</h3>";
        $body .= fnc_get_ordercost_details($order);

        // Buyer info
        $body .= "<hr />";
        $body .= "<h3>" . FN_Translate("Buyer information") . "</h3>";
        if (!empty($buyer)) {
            if (!empty($buyer['type']) && $buyer['type'] == "company") {
                if (!empty($buyer['companyname']))
                    $body .= "<b>" . FN_Translate("company name") . ":</b> " . htmlspecialchars($buyer['companyname']) . "<br />";
                if (!empty($buyer['vat']))
                    $body .= "<b>" . FN_Translate("VAT number") . ":</b> " . htmlspecialchars($buyer['vat']) . "<br />";
            } else {
                if (!empty($buyer['firstname']) || !empty($buyer['lastname']))
                    $body .= "<b>" . FN_Translate("first name") . ":</b> " . htmlspecialchars($buyer['firstname']) . " <b>" . FN_Translate("last name") . ":</b> " . htmlspecialchars($buyer['lastname']) . "<br />";
                if (!empty($buyer['fiscalcode']))
                    $body .= "<b>" . FN_Translate("fiscal code") . ":</b> " . htmlspecialchars($buyer['fiscalcode']) . "<br />";
            }
            if ($user && !empty($user['email']))
                $body .= "<b>" . FN_Translate("Email") . ":</b> " . htmlspecialchars($user['email']) . "<br />";
            if (!empty($buyer['telephone']))
                $body .= "<b>" . FN_Translate("telephone") . ":</b> " . htmlspecialchars($buyer['telephone']) . "<br />";
            if (!empty($buyer['address']))
                $body .= "<b>" . FN_Translate("address") . ":</b> " . htmlspecialchars($buyer['address']) . "<br />";
            if (!empty($buyer['zip']))
                $body .= "<b>" . FN_Translate("ZIP code") . ":</b> " . htmlspecialchars($buyer['zip']) . "<br />";
            if (!empty($buyer['city']))
                $body .= "<b>" . FN_Translate("city") . ":</b> " . htmlspecialchars($buyer['city']) . "<br />";
            if (!empty($buyer['zone'])) {
                $zone = fnc_get_zone($buyer['zone']);
                if (!empty($zone['name']))
                    $body .= "<b>" . FN_Translate("province") . ":</b> " . htmlspecialchars($zone['name']) . "<br />";
            }
            if (!empty($buyer['country'])) {
                $country = fnc_get_country($buyer['country']);
                if (!empty($country['name']))
                    $body .= "<b>" . FN_Translate("country") . ":</b> " . htmlspecialchars($country['name']) . "<br />";
            }

            // Shipping address if different
            if (!empty($buyer['doshippingaddress']) && $buyer['doshippingaddress'] == "yes") {
                $body .= "<br /><b>" . FN_Translate("alternative shipping address") . ":</b><br />";
                if (!empty($buyer['shippingname']))
                    $body .= htmlspecialchars($buyer['shippingname']) . "<br />";
                if (!empty($buyer['shippingaddress']))
                    $body .= htmlspecialchars($buyer['shippingaddress']) . "<br />";
                if (!empty($buyer['shippingzip']) || !empty($buyer['shippingcity']))
                    $body .= htmlspecialchars($buyer['shippingzip'] . " " . $buyer['shippingcity']) . "<br />";
            }
        }

        $body .= "<hr />";
        $body .= "<p>" . FN_Translate("Please contact the buyer to arrange payment") . ".</p>";

        FN_SendMail($company_email, $subject, $body, true);

        // Restore original language
        if ($company_lang != "") {
            $_FN['lang'] = $original_lang;
        }
    }
}
