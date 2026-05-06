<?php
/**
 * module.php - Store Pickup Shipping Method
 *
 * Modulo per il ritiro in negozio - gratuito con indirizzo configurabile
 *
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

global $_FN;

class fnc_shippingmethods_STOREPICKUP
{
    var $order;

    /**
     * Constructor
     * @param array $order Order data
     */
    function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Returns the shipping method title
     * @return string
     */
    function title()
    {
        return FN_Translate("Store Pickup");
    }

    /**
     * Returns the shipping method description
     * @return string
     */
    function description()
    {
        return FN_Translate("(free pickup at our store)");
    }

    /**
     * Returns option data for display (new style)
     * Returns array with: id, title, description, cost
     * @param array $order Order data
     */
    function show_option($order)
    {
        global $_FN;

        // Load configuration
        $config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/STOREPICKUP/config.php");

        // Build description with store details
        $description = '<div class="text-muted">' . $this->description() . '</div>';
        $description .= '<div style="margin-top: 8px; font-size: 0.9em;">';

        if (!empty($config['store_name'])) {
            $description .= "<div><strong>" . FN_Translate("Store") . ":</strong> " . htmlspecialchars($config['store_name']) . "</div>";
        }

        if (!empty($config['store_address'])) {
            $description .= "<div><strong>" . FN_Translate("Address") . ":</strong> " . htmlspecialchars($config['store_address']) . "</div>";
        }

        if (!empty($config['store_hours'])) {
            $description .= "<div><strong>" . FN_Translate("Opening hours") . ":</strong> " . htmlspecialchars($config['store_hours']) . "</div>";
        }

        if (!empty($config['store_phone'])) {
            $description .= "<div><strong>" . FN_Translate("Phone") . ":</strong> " . htmlspecialchars($config['store_phone']) . "</div>";
        }

        if (!empty($config['store_email'])) {
            $description .= "<div><strong>" . FN_Translate("Email") . ":</strong> " . htmlspecialchars($config['store_email']) . "</div>";
        }

        if (!empty($config['pickup_notes'])) {
            $description .= "<div style=\"margin-top: 8px; color: #666; font-style: italic;\">";
            $description .= "<strong>" . FN_Translate("Note") . ":</strong> " . htmlspecialchars($config['pickup_notes']);
            $description .= "</div>";
        }

        $description .= "</div>";

        $cost = isset($config['cost']) ? $config['cost'] : 0;

        $option = array(
            'id' => 'STOREPICKUP',
            'title' => $this->title(),
            'description' => $description,
            'cost' => ($cost > 0) ? fnc_format_price($cost) : ''
        );

        return $option;
    }

    /**
     * Calculates the shipping cost (always 0 for store pickup)
     * @return array Modified order with shipping cost
     */
    function get_total()
    {
        global $_FN;

        // Load configuration
        $config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/STOREPICKUP/config.php");

        // Store pickup is always free (but use config value if specified)
        $cost = array(
            'title' => FN_Translate("Store Pickup"),
            'total' => isset($config['cost']) ? $config['cost'] : 0
        );

        $this->order['costs']["shippingmethods"] = $cost;
        return $this->order;
    }

    /**
     * Returns order status information with store pickup details
     * @return string HTML with pickup information
     */
    function get_orderstatus()
    {
        global $_FN;

        // Load configuration
        FN_LoadMessagesFolder("modules/fncommerce/modules/shippingmethods/STOREPICKUP");
        $config = FN_LoadConfig("modules/fncommerce/modules/shippingmethods/STOREPICKUP/config.php");

        $html = "<div class=\"store-pickup-info\" style=\"margin-top: 15px; padding: 15px; background: #f8f9fa; border-left: 4px solid #28a745;\">";
        $html .= "<h4 style=\"margin-top: 0; color: #28a745;\">" . FN_Translate("Store Pickup - Order Ready") . "</h4>";

        $html .= "<p><strong>" . FN_Translate("Your order is ready for pickup at") . ":</strong></p>";

        if (!empty($config['store_name'])) {
            $html .= "<p><strong>" . htmlspecialchars($config['store_name']) . "</strong></p>";
        }

        if (!empty($config['store_address'])) {
            $html .= "<p>" . htmlspecialchars($config['store_address']) . "</p>";
        }

        if (!empty($config['store_hours'])) {
            $html .= "<p><strong>" . FN_Translate("Opening hours") . ":</strong><br>" . htmlspecialchars($config['store_hours']) . "</p>";
        }

        if (!empty($config['store_phone'])) {
            $html .= "<p><strong>" . FN_Translate("Phone") . ":</strong> " . htmlspecialchars($config['store_phone']) . "</p>";
        }

        if (!empty($config['store_email'])) {
            $html .= "<p><strong>" . FN_Translate("Email") . ":</strong> " . htmlspecialchars($config['store_email']) . "</p>";
        }

        if (!empty($config['pickup_notes'])) {
            $html .= "<p style=\"margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 3px solid #ffc107;\">";
            $html .= "<strong>" . FN_Translate("Important") . ":</strong> " . htmlspecialchars($config['pickup_notes']);
            $html .= "</p>";
        }

        if (isset($this->order['code']) && $this->order['code'] != "") {
            $html .= "<p style=\"margin-top: 15px;\"><strong>" . FN_Translate("Order Number") . ":</strong> " . htmlspecialchars($this->order['code']) . "</p>";
        } elseif (isset($this->order['unirecid']) && $this->order['unirecid'] != "") {
            $html .= "<p style=\"margin-top: 15px;\"><strong>" . FN_Translate("Order Number") . ":</strong> " . htmlspecialchars($this->order['unirecid']) . "</p>";
        }

        $html .= "</div>";

        return $html;
    }
}
?>
