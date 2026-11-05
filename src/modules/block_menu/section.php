<?php
/**
 * @package Finis_module_block_menu
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 * Page type "Menu" (block_menu): a menu group with no content of its own. Its page shows
 * the text of the section, if any, and the pages under it that the visitor may open
 * (FN_GetMenuTree(): the same permissions and visibility as the menu). Config: parent
 * ("__submenu__" = this page, "" = the first level, or a section), recursive (also the pages
 * under them). Drawn by section.tp.html, which a theme can replace with
 * themes/<theme>/modules/block_menu/section.tp.html.
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;
$config = FN_LoadConfig();
$parent = isset($config['parent']) ? $config['parent'] : "__submenu__";
if ($parent == "__submenu__")
{
    $parent = $_FN['mod'];
}
$items = FN_GetMenuTree($parent);
if (isset($config['recursive']) && empty($config['recursive']))
{
    foreach ($items as $k => $item)
    {
        $items[$k]['childs'] = array();
        $items[$k]['havechilds'] = "";
    }
}
echo FN_HtmlStaticContent("sections/{$_FN['mod']}");
$vars = array("items" => $items, "hasitems" => count($items) > 0 ? "1" : "");
echo FN_TPL_ApplyTplFile(FN_FromTheme("{$_FN['src_finis']}/modules/block_menu/section.tp.html", false), $vars);
