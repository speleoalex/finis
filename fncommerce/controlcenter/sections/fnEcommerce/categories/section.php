<?php

/**
 * @package flatnux_controlcenter_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
$params = array();
$params['fields'] = "unirecid|sort_order|parent|name|photo1";
FNCC_XMETATableEditor("fnc_categories", $params);
