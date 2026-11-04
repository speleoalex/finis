<?php
/**
 * @package Finis_controlcenter
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');

$params = array();
//the list shows only the essential fields, the others are in the form
$params['fields'] = "title|text|enabled|optional";
FNCC_XMETATableEditor("fn_conditions", $params);


?>