<?php
/**
 * @package flatnux_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
/**
 *
 * @global array $_FN
 * @param array $tosearch_array
 * @param string $method
 * @param array $sectionvalues
 * @param int $maxres
 * @return array 
 */
function FNSEARCH_module_fncommerce($tosearch_array,$method,$sectionvalues,$maxres)
{
	global $_FN;
	$results=array();
	$section_to_search=$sectionvalues['id']; // current section 

	$Table=new XMETATable("fndatabase","fnc_products",$_FN['datadir']);
	$DB=new XMETADatabase("fndatabase",$_FN['datadir']);
	$query="SELECT unirecid,name,description FROM fnc_products WHERE  ";
	$tmpmethod="";
	foreach ($Table->fields as $fieldstoread=>$fieldvalues)
	{
		if ($fieldstoread != "insert" && $fieldstoread != "update" && $fieldstoread != "unirecid" && $fieldvalues->type != "check")
		{
			foreach ($tosearch_array as $f)
			{
				if ($f != "")
				{
					$query .= " $tmpmethod " . $fieldstoread . " LIKE '%" . addslashes($f) . "%' ";
					$tmpmethod=$method;
				}
			}
			$tmpmethod=" OR ";
		}
	}
	$query .=" AND status LIKE '1'";
	$query .= " LIMIT 1,$maxres";

	$records=$DB->Query("$query");
	$cont=0;
	if (is_array($records))
	{
		foreach ($records as $data)
		{
			$link=FN_RewriteLink("index.php?mod=$section_to_search&amp;op=view&amp;id={$data['unirecid']}");
			$results[$cont]['link']=$link;
			$results[$cont]['title']=$sectionvalues['title'] . ": " . $data['name'];
			$results[$cont]['text']=substr(strip_tags($data['description']),0,100);
			$cont++;
		}
	}
	return $results;
}
?>
