<?php
global $_FN;

FN_LoadMessagesFolder("modules/fncommerce/modules/vouchers/voucher_code/");
class fnc_vouchers_voucher_code
{
	var
		$order;

	function __construct($order)
	{
		$this->order = $order;
	}

	function title()
	{
		return "Codice promozionale";
	}

	function description()
	{
		return "";
	}

	function is_enabled()
	{
		return true;
	}

	function show_option($order)
	{
		global $_FN;
		//dprint_r($order);
		$voucher_code = FN_GetParam("voucher_code",$_POST,"flat");
		if (!isset($_POST['voucher_code']))
		{
			echo FN_Translate("enter coupon code if you have one").":<br />";
			echo "<input name=\"voucher_code\" value=\"$voucher_code\" type=\"text\"><br />";
		}
		else
		{
			if ($voucher_code == "")
			{
				$nextstep = fnc_get_next_order_step("vouchers");
				FN_JsRedirect("?mod={$_FN['mod']}&op=ordersteps&orderstep=$nextstep");
				return;
			}
			$res = $this->is_valid($voucher_code);
			$discount = $res['discount'];
			if ($res['res'] !== false)
			{
				include ("modules/fncommerce/modules/vouchers/voucher_code/config.php");
				echo $res['message'].":".$voucher_code;
				echo "<br />".FN_Translate("discount").":".fnc_format_price($discount);
				echo "<input type=\"hidden\" name=\"vouchers\" value=\"voucher_code\" />";
				echo "<input type=\"hidden\" name=\"voucher_code\" value=\"$voucher_code\" />";
			}
			else
			{
				echo $res['message']."<br />";
				echo FN_Translate("enter coupon code if you have one").":<br />";
				echo "<input name=\"voucher_code\" value=\"\" type=\"text\"><br />";
			}
		}
	}

	function get_total()
	{
		$discount = 0;
		$voucher_code = FN_GetParam("voucher_code",$_POST,"flat");
		$res = $this->is_valid($voucher_code);
		if ($res['res'])
		{
			include ("modules/fncommerce/modules/vouchers/voucher_code/config.php");
			$cost = array(
				'title'=>"Voucher",
				'total'=>(0.0 - $res['discount']),
				'code'=>$voucher_code
			); //deve tornare dalla funzione del modulo
			$this->order['costs']["vouchers"] = $cost;
		}
		return $this->order;
	}

	function do_payment()
	{
		global $_FN;

		$paypal_email = "";
		$valuta = $_FN['currency'];
		include ("modules/fncommerce/modules/vouchers/voucher_code/config.php");
		$ret = "";
		return $ret;
	}

	/**
	 * 
	 * @param type $voucher_code
	 * @return boolean
	 */
	function is_valid($voucher_code)
	{
		$order = $this->order;
		$ret = array();
		$ret['res'] = false;
		$ret['message'] = FN_Translate("the code is not valid");
		$ret['discount'] = 0;


		$codes = FN_XMETADBQuery("SELECT * FROM fnc_vouchercodes WHERE code LIKE '$voucher_code'");
		$price = 0;
		
		foreach ($order['cart'] as $item)
		{
			$p = fnc_getproduct($item['pid']);
			$price+= $p['price'] * $item['qta'];
		}

		$used = FN_XMETADBQuery("SELECT COUNT(*) AS used FROM fnc_vouchercodesused WHERE code LIKE '$voucher_code'");
		
		
		$used = isset($used[0]['used']) ? $used[0]['used'] : 0;
		
		if (isset($codes[0]))
		{
			if ($codes[0]['enabled'] != "1")
			{
				return $ret;
			}
			if ($codes[0]['startdate'] != "")
			{
				$time = strtotime($codes[0]['startdate']);
				if ($time < time())
				{
					$ret['message'] = FN_Translate("the entered code will be valid from").FN_GetDateTime($time);
					return $ret;
				}
			}
			if ($codes[0]['enddate'] != "")
			{
				$time = strtotime($codes[0]['enddate']);
				if ($time > time())
				{
					$ret['message'] = FN_Translate("the code has expired");
					return $ret;
				}
			}
			$max_uses = $codes[0]['max_uses'];
			if ($used >= $max_uses)
			{
				$ret['message'] = FN_Translate("the code has been used for the maximum number of times possible");
				return $ret;
			}
			if ($codes[0]['minprice'] != "")
			{
				if ($codes[0]['minprice'] > $price)
				{
					$ret['message'] = str_replace("1",$codes[0]['minprice'],FN_Translate("this code is only valid with a minimum spend of 1 euro"));
					return $ret;
				}
			}
		}
		else
		{
			$ret['res'] = false;
			$ret['message'] = FN_Translate("the code is not valid");
			return $ret;
		}
		$discount = $codes[0]['discount'];
		$ret['res'] = true;
		$ret['message'] = FN_Translate("the code is valid");
		$ret['discount'] = $discount;
		return $ret;
	}

	function on_order_confirm($order)
	{

		if (isset($order['costs']['vouchers']['code']))
		{
			$t = FN_XMDBTable("fnc_vouchercodesused");
			$t->InsertRecord(array("order"=>$order['unirecid'],"code"=>$order['costs']['vouchers']['code']));
		}
	}

}
?>