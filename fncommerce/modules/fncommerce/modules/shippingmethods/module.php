<?php

/**
 * module.php created on 22/gen/2009
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
class fnc_shippingmethods
{

    var $orderstatus;

    function __construct($orderstatus)
    {
        $this->orderstatus = $orderstatus;
    }

    function is_enabled()
    {
        foreach ($this->orderstatus['cart'] as $item)
        {
            $prod = fnc_getproduct($item['pid']);
            if ($prod['freeshipping'] != 1)
            {
                $list_enabled_modules = "";
                include ("modules/fncommerce/modules/shippingmethods/config.php");
                $list_enabled_modules = explode(",", $list_enabled_modules);
                //se ho solo un opzione non la visualizzo ---->
                if (count($list_enabled_modules) == 1 && $list_enabled_modules != "")
                {
                    $step = "shippingmethods";
                    $step_option_selected = $list_enabled_modules[0];
                    FN_LoadMessagesFolder("modules/fncommerce/modules/$step/$step_option_selected/");
                    require_once ("modules/fncommerce/modules/$step/$step_option_selected/module.php");
                    $orderstatus = fnc_get_order_temp();
                    $classname = "fnc_$step" . "_" . $step_option_selected;
                    $stepclass = new $classname($orderstatus);

                    $orderstatus = $stepclass->get_total();
                    if (!is_array($orderstatus))
                        return true;
                    fnc_save_order_temp($orderstatus);
                    return false;
                }
                //se ho solo un opzione non la visualizzo ----<
                else
                    return true;
            }
        }
        return false;
    }

}

?>