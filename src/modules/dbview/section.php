<?php
/**
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2011
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
#<fnmodule>dbview</fnmodule>
defined('_FNEXEC') or die('Restricted access');
global $_FN;
//extra editor params 
$_FN['modparams'][$_FN['mod']]['editorparams'] = isset($_FN['modparams'][$_FN['mod']]['editorparams']) ? $_FN['modparams'][$_FN['mod']]['editorparams'] : array();

if (isset($_GET['debug']))
{
    dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());    
}
require_once __DIR__ . "/FNDBVIEW.php";
if (isset($_GET['debug']))
{
    dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());
}
if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/custom_functions.php"))
    require_once "{$_FN['src_application']}/sections/{$_FN['mod']}/custom_functions.php";

$file = FN_GetParam("file", $_GET, "flat");
//$config = FN_LoadConfig("modules/dbview/config.php");
$config = FN_LoadConfig();


$dbview = new FNDBVIEW($config);

// ?file=<name>: a file of the section folder called directly instead of the module. Only a plain
// file name, never one of the files that the module (or FINIS) includes by itself: they expect
// the checks and the context of the module (bottom_edit.php shows the record being edited).
// The visitor must be allowed to view the records of the section; the file checks the rest.
$fn_dbview_hookfiles = array("custom_functions.php", "top.php", "grid_header.php", "grid_footer.php",
    "bottom.php", "bottom_edit.php", "viewheader.php", "viewfooter.php", "header.php", "footer.php",
    "section.php", "search.php", "pdf.php", "default.xml.php", "config.php");
$fn_dbview_file = "{$_FN['src_application']}/sections/{$_FN['mod']}/$file";
if ($file != "" && preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]*$/', $file) && !in_array(strtolower($file), $fn_dbview_hookfiles, true) && is_file($fn_dbview_file))
{
    if ($dbview->CanViewRecords())
        include $fn_dbview_file;
    else
        echo FN_i18n("you are not authorized to view the data");
}
else
{
    $dbview->Init();
    $id = FN_GetParam("id", $_GET, "html");
    $op = FN_GetParam("op", $_GET, "html");
    $downloadfile = FN_GetParam("downloadfile", $_GET);
    $mode = FN_GetParam("mode", $_GET);
//-------------------------------config---------------------------------------->    
    $tablename = $config['tables'];
    $recordsperpage = $config['recordsperpage'];
    $config['search_orders'] = explode(",", $config['search_orders']);
    $config['search_options'] = explode(",", $config['search_options']);
    $config['navigate_groups'] = explode(",", $config['navigate_groups']);
    $config['search_fields'] = explode(",", $config['search_fields']);
    $config['search_partfields'] = explode(",", $config['search_partfields']);
//-------------------------------config----------------------------------------<
    if ($id != "" && $op == "")
    {
        $op = "view";
    }
    if ($mode == "go")
    {
        $dbview->GoDownload($downloadfile);
        exit();
    }
    $html = "";
    require_once $_FN['src_finis'] . "/include/xmetadb_frm_search.php";
    $Table = FN_XMDBForm($tablename);
    $Search = new xmetadb_searchform($_FN['database'], $tablename, $_FN['datadir'], $_FN['lang'], $_FN['languages'], false);
    $html .= $Search->HtmlSearchForm();
    if ($config['enable_permissions_each_records'])
    {
        if (!isset($Table->formvals['groupview']))
        {
            $field = array();
            $field['name'] = 'groupview';
            $field['frm_i18n'] = 'limits the display of the content in these groups';
            $field['foreignkey'] = 'fn_groups';
            $field['fk_link_field'] = 'groupname';
            $field['fk_show_field'] = 'groupname';
            $field['frm_type'] = 'multicheck';
            $field['type'] = 'string';
            addxmltablefield($_FN['database'], $tablename, $field, $_FN['datadir']);
        }
        $Table->formvals['groupview']['frm_show'] = 1;
        if ($config['permissions_records_groups'] != "")
        {
            $allAllowedGroups = explode(",", $config['permissions_records_groups']);
            if (isset($Table->formvals['groupview']['options']))
            {
                foreach ($Table->formvals['groupview']['options'] as $k => $v)
                {
                    if (!in_array($v['value'], $allAllowedGroups))
                    {
                        unset($Table->formvals['groupview']['options'][$k]);
                    }
                }
            }
        }
    }
    else
    {
        if (isset($Table->formvals['groupview']))
        {
            $Table->formvals['groupview']['frm_show'] = 0;
        }
    }


    if ($config['enable_permissions_edit_each_records'])
    {
        if (!isset($Table->formvals['groupinsert']))
        {
            $field = array();
            $field['name'] = 'groupinsert';
            $field['frm_i18n'] = 'limits the edit of the content to these groups';
            $field['foreignkey'] = 'fn_groups';
            $field['fk_link_field'] = 'groupname';
            $field['fk_show_field'] = 'groupname';
            $field['frm_type'] = 'multicheck';
            $field['type'] = 'string';
            $field['frm_setonlyadmin'] = '1';
            $field['frm_allowupdate'] = 'onlyadmin';
            $field['type'] = 'string';
            addxmltablefield($_FN['database'], $tablename, $field, $_FN['datadir']);
        }
        if (!empty($config['groupadmin']) && FN_UserInGroup($_FN['user'], $config['groupadmin']))
        {
            $Table->formvals['groupinsert']['frm_show'] = 1;
            unset($Table->formvals['groupinsert']['frm_setonlyadmin']);
            $Table->formvals['groupinsert']['frm_allowupdate'] = "";
        }

        if ($config['permissions_records_edit_groups'] != "")
        {
            $allAllowedGroups = explode(",", $config['permissions_records_edit_groups']);
            if (isset($Table->formvals['groupinsert']['options']))
            {
                foreach ($Table->formvals['groupinsert']['options'] as $k => $v)
                {
                    if (!in_array($v['value'], $allAllowedGroups))
                    {
                        unset($Table->formvals['groupinsert']['options'][$k]);
                    }
                }
            }
        }
    }
    else
    {
        if (isset($Table->formvals['groupinsert']))
        {
            $Table->formvals['groupinsert']['frm_show'] = 0;
        }
    }
//-----------------------------principale ------------------------------------->
    if ($dbview->CanViewRecords())
    {
        // Sicurezza (fix 2026-07, report assistance 2026-07-15): le operazioni di SCRITTURA
        // richiedono i permessi corretti. Lo switch era protetto solo da CanViewRecords(), che su
        // contenuti pubblici (groupview vuoto) ritorna true anche per l'anonimo: cosi' un crawler
        // (es. Amazonbot) poteva raggiungere op=edit/op=del. Se l'utente non puo' scrivere si forza
        // la sola visualizzazione.
        // "offlineform" is not listed: it is a read-only download (GenOfflineUpdate checks
        // CanViewRecord) and is the public channel to submit updates by email.
        $writeops = array("edit", "new", "del", "updaterecord", "insertrecord", "users", "admingroups");
        if (in_array($op, $writeops, true) && !$dbview->CanEditRecords())
        {
            $op = "view";
        }
        switch ($op)
        {
            case "offlineform":
                if ($id)
                    $dbview->GenOfflineUpdate($id);
                else
                    $dbview->GenOfflineInsert();
                break;
            case "history" :
                $shownavigatebar = true;
                if ($config['enable_history'])
                    $html .= $dbview->ViewRecordHistory($id, false, $shownavigatebar); // visualizza la pagina col record
                break;
            case "view" :
                $shownavigatebar = true;
                if (isset($_GET['embed']))
                    $shownavigatebar = false;
                if (!empty($_GET['inner']))
                    $shownavigatebar = false;
                $html .= $dbview->ViewRecordPage($id, false, $shownavigatebar); // visualizza la pagina col record
                break;
            case "propose" : // change proposed by a user who cannot edit the record
                if (isset($_POST['xmetadbsave']))
                    $html .= $dbview->SaveProposal($Table);
                else
                    $html .= $dbview->EditRecordForm($id, $Table, array(), false, true);
                break;
            case "proposal" : // accept or discard a proposed change
                $html .= $dbview->ReviewProposal($id, $Table);
                break;
            case "request" :
                $html .= $dbview->Request($id);
                break;
            case "edit" :

                $html .= $dbview->EditRecordForm($id, $Table); // form edita record
                if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/bottom_edit.php"))
                {
                    include ("{$_FN['src_application']}/sections/{$_FN['mod']}/bottom_edit.php");
                }


                break;
            case "new" :
                if (isset($_POST['xmetadbsave']))
                {
                    $html .= $dbview->InsertRecord($Table);
                    $html .= $dbview->WriteSitemap();
                }
                else
                {
                    $html .= $dbview->NewRecordForm($Table); //  form nuovo record
                }
                break;
            case "users" :
                $html .= $dbview->UsersForm($id); //  form nuovo record
                break;
            case "admingroups" :
                $html .= $dbview->AdminPerm($id); //  permessi records
                break;
            case "del" :
                $html .= $dbview->DelRecordForm($id); //  form nuovo record
                break;
            case "updaterecord" :
                if (count($_POST) == 0 || isset($_POST['__NOSAVE']) || !isset($_POST['xmetadbsave']))
                {
                    $html .= $dbview->EditRecordForm($id, $Table);
                }
                else
                {
                    $html .= $dbview->UpdateRecord($Table); // esegue aggiornamento record
                }
                // Both branches render the edit form again: include the same
                // section hook used by op=edit so the form keeps its additions
                // (e.g. the record validator banner) after saving.
                if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/bottom_edit.php"))
                {
                    include ("{$_FN['src_application']}/sections/{$_FN['mod']}/bottom_edit.php");
                }
                break;
            case "insertrecord" : // esegue inserimento record
                break;
            case "updatesitemap" :
                $dbview->WriteSitemap();
                dprint_xml(file_get_contents("sitemap-$tablename.xml"));
                dprint_xml(file_get_contents("index-$tablename.html"));
                break;
            default :
                if (FN_IsAdmin() && isset($_GET["refresh_rss"]))
                {
                    $dbview->GenerateRSS();
                }
                $html .= FN_HtmlContent("{$_FN['src_application']}/sections/{$_FN['mod']}");
                $html .= $dbview->ViewGrid(); //griglia con tutti i records
                ob_start();
                if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/bottom.php"))
                {
                    include ("{$_FN['src_application']}/sections/{$_FN['mod']}/bottom.php");
                }
                $html .= ob_get_clean();
                break;
        }
    }
    else
    {
        $html = FN_i18n("you are not authorized to view the data");
    }
    echo $html;
//-----------------------------principale -------------------------------------<
}
?>

<script type="text/javascript">
    /**
     * Ajax navigation of the dbview lists (pages, category filters, search form).
     * The url is a normal page: the containers named in div (comma separated ids)
     * are replaced with the same containers of the loaded page, so the links keep
     * working without javascript. The browser url is kept in sync with the
     * history API: the address bar always shows a link to the current page and
     * filters, and back/forward reload the containers of the entry.
     * @param {string} url page to load
     * @param {string} div ids of the containers to replace, comma separated
     * @param {boolean} fromhistory true when called by popstate (no new entry)
     */
    function call_ajax(url, div, fromhistory)
    {
        var xsreq = new XMLHttpRequest();
        loading(div);
        xsreq.onreadystatechange = function () {
            if (xsreq.readyState != 4) {
                return;
            }
            if (xsreq.status >= 400) {
                window.location = url;
                return;
            }
            try {
                ajDone(url, div, xsreq);
                dbview_history(url, div, fromhistory);
            } catch (e) {
                window.location = url;
            }
        };
        // fnajax=1 asks finis for the section only, without the theme (an old finis
        // ignores it and sends the full page, which works too); the history gets the clean url
        var fetchurl = url + (url.indexOf("?") >= 0 ? "&" : "?") + "fnajax=1";
        xsreq.open("GET", fetchurl, true);
        xsreq.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        xsreq.send(null);
    }
    /**
     * Submits a search form of the list with call_ajax: the fields are added to
     * the query string of the action (GET), so the search is in the url. The
     * page number is removed, a new search starts from the first page.
     * @param {HTMLFormElement} form
     * @param {string} div ids of the containers to replace, comma separated
     * @returns {boolean} false, to cancel the normal submit
     */
    function call_ajax_form(form, div)
    {
        try {
            var url = new URL(form.getAttribute("action") || window.location.href, window.location.href);
            url.searchParams.delete("page");
            var data = new FormData(form);
            data.forEach(function (value, name) {
                url.searchParams.delete(name);
            });
            data.forEach(function (value, name) {
                if (value !== "") {
                    url.searchParams.append(name, value);
                }
            });
            call_ajax(url.href, div);
            return false;
        } catch (e) {
            return true;
        }
    }
    function ajDone(url, div, xsreq)
    {
        var divs = div.split(",");
        var el = document.createElement("div");
        el.innerHTML = xsreq.responseText;
        try {
            var olddiv = document.getElementById("__fnloading");
            olddiv.parentNode.removeChild(olddiv);
        } catch (e) {
        }
        for (var i = 0; i < divs.length; i++) {
            var id = divs[i].trim();
            var target = document.getElementById(id);
            var source = null;
            var nodes = el.querySelectorAll("div, span, td, tr");
            for (var j = 0; j < nodes.length; j++) {
                if (nodes[j].id == id) {
                    source = nodes[j];
                    break;
                }
            }
            if (target && source) {
                target.innerHTML = source.innerHTML;
                dbview_execJS(target);
            }
        }
    }
    /**
     * Runs the scripts of content inserted with innerHTML (the browser ignores them)
     */
    function dbview_execJS(el)
    {
        var scripts = el.getElementsByTagName("script");
        for (var i = 0; i < scripts.length; i++) {
            var old = scripts[i];
            var s = document.createElement("script");
            for (var j = 0; j < old.attributes.length; j++) {
                s.setAttribute(old.attributes[j].name, old.attributes[j].value);
            }
            s.text = old.text;
            old.parentNode.replaceChild(s, old);
        }
    }
    /**
     * Records an ajax navigation in the browser history. The first entry of the
     * page gets the same state, so going back to it reloads its containers too.
     */
    function dbview_history(url, div, fromhistory)
    {
        if (!window.history || !history.pushState || fromhistory) {
            return;
        }
        try {
            var state = {dbview: 1, divs: div};
            if (!history.state || !history.state.dbview) {
                history.replaceState(state, "", window.location.href);
            }
            history.pushState(state, "", url);
        } catch (e) {
        }
    }
    window.addEventListener("popstate", function (e) {
        if (e.state && e.state.dbview) {
            call_ajax(window.location.href, e.state.divs, true);
        }
    });
    function getScrollY() {
        var scrOfX = 0, scrOfY = 0;
        if (typeof (window.pageYOffset) == 'number') {
            //Netscape compliant
            scrOfY = window.pageYOffset;
            scrOfX = window.pageXOffset;
        } else if (document.body && (document.body.scrollLeft || document.body.scrollTop)) {
            //DOM compliant
            scrOfY = document.body.scrollTop;
            scrOfX = document.body.scrollLeft;
        } else if (document.documentElement && (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
            //IE6 standards compliant mode
            scrOfY = document.documentElement.scrollTop;
            scrOfX = document.documentElement.scrollLeft;
        }
        return scrOfY;
        //return [ scrOfX, scrOfY ];
    }
    function getScrollX() {
        var scrOfX = 0, scrOfY = 0;
        if (typeof (window.pageYOffset) == 'number') {
            //Netscape compliant
            scrOfY = window.pageYOffset;
            scrOfX = window.pageXOffset;
        } else if (document.body && (document.body.scrollLeft || document.body.scrollTop)) {
            //DOM compliant
            scrOfY = document.body.scrollTop;
            scrOfX = document.body.scrollLeft;
        } else if (document.documentElement && (document.documentElement.scrollLeft || document.documentElement.scrollTop)) {
            //IE6 standards compliant mode
            scrOfY = document.documentElement.scrollTop;
            scrOfX = document.documentElement.scrollLeft;
        }
        return scrOfX;
    }
    function loading(_div)
    {

        var divs = new Array();
        divs = _div.split(",");
        _div = divs[0];
        try {
            var d = document.getElementsByTagName('body')[0];
            var olddiv = document.getElementById("__fnloading");
            d.removeChild(olddiv);
        } catch (e) {
        }



        var div;
        div = document.createElement('div');
        div.innerHTML = 'loading...';
        div.setAttribute('id', "__fnloading");
        oHeight = document.getElementsByTagName('body')[0].clientHeight + getScrollY();
        oWidth = document.getElementsByTagName('body')[0].clientWidth + getScrollX();
        oHeight = oHeight + "px";
        oWidth = oWidth + "px";
        try {
            div.style.backgroundColor = '#000000';
            div.style.color = '#ffffff';
            div.style.zIndex = '8';
            div.style.display = 'block';
            div.style.position = 'absolute';
            div.style.width = oWidth;
            div.style.height = "auto";
            div.style.top = '0px';
            div.style.left = '0px';
            div.style.textAlign = 'center';
            div.style.opacity = '0.5';
            div.style.filter = 'alpha(opacity=50)';
        } catch (e)
        {
        }
        div.innerHTML = "<div style=\"color:#ffffff;margin-top:" + getScrollY() + "px\" ><br />loading...<br /><br /><span style=\"font-size:32px;line-height:32px\">&#x23F3;</span><br /><br /></div>";
        document.getElementsByTagName('body')[0].appendChild(div);
    }
</script>