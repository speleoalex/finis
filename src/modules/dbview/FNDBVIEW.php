<?php

/**
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2011
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
#<fnmodule>dbview</fnmodule>
global $_FN;

// Carica i trait
require_once __DIR__ . '/traits/FNDBVIEWPermissions.php';
require_once __DIR__ . '/traits/FNDBVIEWComments.php';
require_once __DIR__ . '/traits/FNDBVIEWCRUD.php';
require_once __DIR__ . '/traits/FNDBVIEWExport.php';
require_once __DIR__ . '/traits/FNDBVIEWUtils.php';

/**
 * Classe FNDBVIEW - Gestione visualizzazione database
 *
 * Refactored per utilizzare trait separati:
 * - FNDBVIEWPermissions: gestione permessi utente
 * - FNDBVIEWComments: gestione commenti
 * - FNDBVIEWCRUD: operazioni Create, Read, Update, Delete
 * - FNDBVIEWExport: esportazione dati (CSV, Sitemap, RSS)
 * - FNDBVIEWUtils: utility varie (MakeLink, SecureHtml, etc.)
 */
class FNDBVIEW
{
    use FNDBVIEWPermissions;
    use FNDBVIEWComments;
    use FNDBVIEWCRUD;
    use FNDBVIEWExport;
    use FNDBVIEWUtils;

    var $config;
    function __construct($config)
    {
        $this->config = $config;
    }

    function Init()
    {
        global $_FN;
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " " . __LINE__ . " INIT : " . FN_GetExecuteTimer());
        }

        global $_FN;
        $htmlLog = "";
        if (!file_exists("{$_FN['datadir']}/fndatabase/fieldusers")) {
            $sfields = array();
            $sfields[0]['name'] = "id";
            $sfields[0]['primarykey'] = "1";
            $sfields[0]['extra'] = "autoincrement";
            $sfields[1]['name'] = "username";
            $sfields[2]['name'] = "tablename";
            $sfields[3]['name'] = "table_unirecid";
            $htmlLog .= XMETATable::createMetadbTable("fndatabase", "fieldusers", $sfields, $_FN['datadir']);
        }
        $config = $this->config;
        $tablename = $config['tables'];

        //--------------- creazione tabelle ------------------------------->
        if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/{$tablename}.php")) {
            $str_table = file_get_contents("{$_FN['src_finis']}/modules/dbview/install/fn_files.php");
            $str_table = str_replace("fn_files", $tablename, $str_table);
            FN_Write($str_table, $_FN['datadir'] . "/" . $_FN['database'] . "/$tablename.php");
        }

        if ($config['enable_history'] && !file_exists("{$_FN['datadir']}/{$_FN['database']}/{$tablename}_versions.php")) {
            $Table = FN_XMDBTable($tablename);
            if (!isset($Table->fields['recorddeleted'])) {
                $tfield['name'] = "userupdate";
                $tfield['type'] = "varchar";
                $tfield['frm_show'] = "0";
                addxmltablefield($Table->databasename, $Table->tablename, $tfield, $Table->path);
            }

            $str_table = file_get_contents($_FN['datadir'] . "/" . $_FN['database'] . "/$tablename.php");
            $str_table = str_replace("<primarykey>1</primarykey>", "", $str_table);
            $str_table = str_replace("<tables>", "<tables>
    <field>
		<name>idversions</name>
		<primarykey>1</primarykey>
		<extra>autoincrement</extra>
		<type>string</type>
	</field>", $str_table);
            FN_Write($str_table, $_FN['datadir'] . "/" . $_FN['database'] . "/{$tablename}_versions.php");
        }
        //------------------- tabella delle statistiche -------------------------
        if ($config['enable_statistics'] == 1) {
            if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/{$tablename}" . "_stat") || !file_exists("{$_FN['datadir']}/{$_FN['database']}/{$tablename}" . "_stat.php")) {
                //$htmlLog.= "<br>creazione statistiche $tablename";
                $sfields = array();
                $sfields[0]['name'] = "id";
                $sfields[0]['primarykey'] = "1";
                $sfields[1]['name'] = "view";
                $htmlLog .= XMETATable::createMetadbTable($_FN['database'], $tablename . "_stat", $sfields, $_FN['datadir']);
            }
        }
        //------------------- tabella permessi tabelle -------------------------
        if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/fieldusers")) {
            $sfields = array();
            $sfields[0]['name'] = "id";
            $sfields[0]['primarykey'] = "1";
            $sfields[0]['extra'] = "autoincrement";
            $sfields[1]['name'] = "username";
            $sfields[2]['name'] = "tablename";
            $sfields[3]['name'] = "table_unirecid";
            $htmlLog .= XMETATable::createMetadbTable($_FN['database'], "fieldusers", $sfields, $_FN['datadir']);
        }
        //------------------- tabella permessi tabelle -------------------------
        if ($config['enable_permissions_each_records'] && $config['permissions_records_groups'] != "") {
            $tmp = explode(",", $config['permissions_records_groups']);
            foreach ($tmp as $group) {
                FN_CreateGroupIfNotExists($group);
            }
        }
        //------------------- tabella commenti-------------------------
        //--------------- creazione tabelle -------------------------------<
        
    }

    /**
     *
     * @global array $_FN
     * @staticvar boolean $listok
     * @param array $config
     * @param array $params
     * @return array 
     */
    function GetResults($config = false, $params = false, &$idresult = false)
    {
        global $_FN;
        static $listok = false;
        //------------------------------load config-------------------------------->
        if ($config == false) {
            $config = $this->config;
        }

        $search_options = $config['search_options'] != "" ? explode(",", $config['search_options']) : array();
        $search_min = $config['search_min'] != "" ? explode(",", $config['search_min']) : array();
        $search_partfields = $config['search_partfields'] != "" ? explode(",", $config['search_partfields']) : array();
        $search_fields = $config['search_fields'] != "" ? explode(",", $config['search_fields']) : array();
        $tablename = $config['tables'];
        $_navifatefilters = $_REQUEST;
        if (!empty($params['navigate_groups'])) {
            $_navifatefilters = $params['navigate_groups'];
        }

        $groups = ($config['navigate_groups'] != "") ? explode(",", $config['navigate_groups']) : array();
        //------------------------------load config--------------------------------<
        if ($params === false)
            $params = $_REQUEST;
        $q = FN_GetParam("q", $params);

        $listfind = explode(" ", $q);
        $order = FN_GetParam("order", $params);
        $desc = FN_GetParam("desc", $params);
        $rule = FN_GetParam("rule", $params);



        $rulequery = "";
        if ($rule != "" && !empty($config['table_rules'])) {
            $tablerules = FN_XMDBTable($config['table_rules']);
            $rulevalues = $tablerules->GetRecordByPrimaryKey($rule);
            if (!empty($rulevalues['function']) && function_exists($rulevalues['function'])) {
                return $rulevalues['function']($rulevalues);
            } elseif (!empty($rulevalues['query'])) {
                $rulequery = "{$rulevalues['query']}";
            }
        }
        if ($order == "") {
            $order = $config['defaultorder'];
            if ($desc == "")
                $desc = 1;
        }
        $filters_items = array();
        $t = FN_XMDBForm($tablename);
        $query_filter = "";
        $and = "";
        foreach ($t->formvals as $k => $v) {
            $filters = FN_GetParam("filter_$k", $_REQUEST);
            if ($filters) {
                $filters = explode(",", $filters);
                $and = "";
                foreach ($filters as $filter) {
                    $query_filter .= "$and$k LIKE '$filter'";
                    $and = " OR ";
                }
            }
        }
        if ($query_filter) {
            $query_filter = "($query_filter) ";
            $and = " AND ";
        }






        $fields = array();
        $ftoread = $groups;
        $ftoread[] = $t->xmltable->primarykey;
        if (!empty($params['fields'])) {
            //die($params['fields']);
            $add_fields = explode(",", $params['fields']);
            foreach ($add_fields as $v) {
                if (isset($t->formvals[$v]))
                    $ftoread[] = $v;
            }
        }
        //dprint_r($ftoread);
        $ftoread = array_unique($ftoread);
        $ftoread = implode(",", $ftoread);

        $query = "SELECT $ftoread FROM $tablename WHERE   ";
        $wherequery = "$query_filter";

        if (!empty($rulequery)) {
            $wherequery = " ($rulequery) ";
            $and = "AND";
        }



        if ($config['enable_permissions_each_records'] && isset($t->formvals['groupview']) && !$this->IsAdmin()) {

            $exists_group = false;
            $wherequery .= "$and (";
            $usergroups = FN_GetUser($_FN['user']);
            $usergroups = isset($usergroups['group']) ? explode(",", $usergroups['group']) : array("");

            $wherequery .= "  groupview LIKE ''";
            $or = " OR";
            foreach ($usergroups as $usergroup) {
                if ($usergroup != "") {
                    $wherequery .= "$or groupview LIKE '$usergroup' OR groupview LIKE '%$usergroup' OR groupview LIKE '$usergroup%' ";
                    $or = "OR";
                    $exists_group = true;
                }
            }
            $wherequery .= ") ";
            $and = " AND ";
        }

        if ($order == "") {
            $order = $t->xmltable->primarykey;
            if ($desc == "")
                $desc = 1;
        }

        if (!empty($params['appendquery'])) {
            $wherequery .= "$and {$params['appendquery']}";
            $and = " AND ";
        }

        if (isset($t->xmltable->fields['recorddeleted'])) {
            $wherequery .= "$and recorddeleted <> '1'";
            $and = "AND";
        }
        if ($config['appendquery'] != "") {
            $wherequery .= "$and {$config['appendquery']} ";
            $and = "AND";
        }
        $method = " OR ";
        $endmethod = "";
        //-----------------------ricerca del testo ---------------------------->
        $findtextquery = "";
        $tmpmethod = "";
        foreach ($t->xmltable->fields as $fieldstoread => $fieldvalues) {
            if ($fieldstoread != "insert" && $fieldstoread != "update" && $fieldstoread != "id" && $fieldstoread != "id" && $fieldvalues->type != "check") {
                foreach ($listfind as $f) {
                    if ($f != "") {
                        if (isset($fieldvalues->foreignkey) && isset($fieldvalues->fk_link_field)) {
                            $fk = FN_XMDBTable($fieldvalues->foreignkey);
                            $fkshow = explode(",", $fieldvalues->fk_show_field);
                            $fkfields = "";
                            if ($fieldvalues->fk_show_field != "")
                                $fkfields = "," . $fieldvalues->fk_show_field;
                            //prendo il primo
                            $fk_query = "SELECT {$fieldvalues->fk_link_field}$fkfields FROM {$fieldvalues->foreignkey} WHERE ";
                            $or = "";
                            foreach ($fkshow as $fkitem) {
                                $fk_query .= "$or {$fkitem} LIKE '%" . addslashes($f) . "%'";
                                $or = "OR";
                            }
                            if (!isset($listok[$f][$fieldvalues->foreignkey])) {
                                $rt = FN_XMETADBQuery($fk_query);
                                $listok[$f][$fieldvalues->foreignkey] = $rt;
                            }
                            if (is_array($listok[$f][$fieldvalues->foreignkey]) && count($listok[$f][$fieldvalues->foreignkey]) > 0) {
                                $findtextquery_tmp = " $tmpmethod (";
                                $m = "";
                                $exists_tmp = false;
                                foreach ($listok[$f][$fieldvalues->foreignkey] as $fk_item) {
                                    //dprint_r($fk_item);
                                    $vv = "";
                                    if (isset($fk_item[$fieldvalues->fk_link_field])) {
                                        $exists_tmp = true;
                                        $vv = str_replace("'", "\\'", $fk_item[$fieldvalues->fk_link_field]);
                                        $findtextquery_tmp .= "$m $fieldstoread = '$vv'";
                                        $m = " OR ";
                                    }
                                }
                                $findtextquery_tmp .= ")";
                                if (!$exists_tmp)
                                    $findtextquery_tmp = "";
                                $tmpmethod = $method;
                            } else {
                                $findtextquery_tmp = " $tmpmethod (" . $fieldstoread . " LIKE '%" . addslashes($f) . "%') ";
                            }
                            $findtextquery .= $findtextquery_tmp;
                        } else {
                            $findtextquery .= " $tmpmethod " . $fieldstoread . " LIKE '%" . addslashes($f) . "%' ";
                        }
                        $tmpmethod = $method;
                    }
                }
                $tmpmethod = " OR ";
            }
        }
        if ($findtextquery != "") {
            $wherequery .= "$and ($findtextquery) ";
            $and = "AND";
        }
        //-----------------------ricerca del testo ----------------------------<
        //---check ---->
        $_tables[$tablename] = FN_XMDBForm($tablename);
        //dprint_r($_tables);
        foreach ($search_options as $option) {
            $checkquery = "";
            $tmet = "";
            if (isset($_tables[$tablename]->formvals[$option]['options']) && is_array($_tables[$tablename]->formvals[$option]['options'])) {
                foreach ($_tables[$tablename]->formvals[$option]['options'] as $c) {
                    $otitle = $c['title'];
                    $ovalue = $c['value'];
                    $ogetid = "s_opt_{$option}_{$tablename}_{$c['value']}";
                    $sopt = FN_GetParam($ogetid, $params, "html");
                    if ($sopt != "") {
                        $checkquery .= " $tmet $option LIKE '$ovalue' ";
                        $tmet = "OR";
                    }
                }
            }
            if ($checkquery != "") {
                $wherequery .= "$and ($checkquery) ";
                $and = "AND";
            }
        }
        //---check ----<
        //min---->
        $minquery = "";
        $tmet = "";
        foreach ($search_min as $min) {
            if (isset($_tables[$tablename]->formvals[$min])) {
                $getmin = FN_GetParam("min_$min", $params, "html");
                if ($getmin != "") {
                    $getmin = intval($getmin);
                    $minquery .= " $tmet $min > $getmin ";
                    $tmet = "AND";
                }
            }
        }
        if ($minquery != "") {
            $wherequery .= "$and ($minquery) ";
            $and = "AND";
        }
        //min----<
        //searchfields---->
        $sfquery = "";
        $tmet = "";
        foreach ($search_fields as $sfield) {
            if (isset($_tables[$tablename]->formvals[$sfield])) {
                $get_sfield = FN_GetParam("sfield_$sfield", $params, "html");
                if ($get_sfield != "") {
                    //                    $sfquery.=" $tmet ($sfield LIKE '$get_sfield' OR $sfield LIKE '$get_sfield.%') ";
                    $sfquery .= " $tmet ($sfield LIKE '$get_sfield') ";
                    $tmet = "AND";
                }
            }
        }
        if ($sfquery != "") {
            $wherequery .= "$and ($sfquery) ";
            $and = "AND";
        }
        //searchfields----<
        //searchpartfields---->
        $sfquery = "";
        $tmet = "";
        foreach ($search_partfields as $sfield) {
            if (isset($_tables[$tablename]->formvals[$sfield])) {
                $get_sfield = FN_GetParam("spfield_$sfield", $params, "html");
                if ($get_sfield != "") {
                    $sfquery .= " $tmet $sfield LIKE '%$get_sfield%' ";
                    $tmet = "AND";
                }
            }
        }
        if ($sfquery != "") {
            $wherequery .= "$and ($sfquery) ";
            $and = "AND";
        }
        //searchpartfields----<
        //-----------------------record is visible only creator---------------->
        if ($config['viewonlycreator'] == 1) {
            if (!$this->IsAdmin()) {

                if ($_FN['user'] != "") {
                    $wherequery .= "$and (username LIKE '{$_FN['user']}' OR username LIKE '%,{$_FN['user']}' OR username LIKE '%,{$_FN['user']},%' OR username LIKE '%,{$_FN['user']}') ";


                    $listusers = FN_XMDBTable("fieldusers");
                    $MyRecords = $listusers->GetRecords(array("tablename" => $tablename, "username" => $_FN['user']));
                    if (is_array($MyRecords)) {
                        foreach ($MyRecords as $MyRecord) {
                            $wherequery .= "OR {$_tables[$tablename]->xmltable->primarykey} = '{$MyRecord['table_unirecid']}'";
                        }
                    }
                }
            }
            $and = "AND";
        }
        //-----------------------record is visible only creator----------------<


        $groupquery = "";
        $tmet = "";
        foreach ($groups as $group) {
            if (isset($_navifatefilters["nv_{$group}"])) {
                $navigate = FN_GetParam("nv_{$group}", $_navifatefilters);
                $groupquery .= "$tmet $group LIKE '" . addslashes($navigate) . "' ";
                $tmet = "AND";
            }
        }
        if ($groupquery != "") {
            $wherequery .= "$and ($groupquery) ";
            $and = "AND";
        }


        if ($wherequery == "")
            $wherequery = "1";
        $orderquery = "";
        if ($order != "") {
            $orderquery .= " ORDER BY $order";
            if ($desc != "")
                $orderquery .= " DESC";
        }
        $query = "$query $wherequery $orderquery";
        $usenative = true;
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " pre query " . __LINE__ . " : " . FN_GetExecuteTimer());
        }
        $query = str_replace("\n", "", $query);
        $query = str_replace("\r", "", $query);

        // Execute query with $wherequery
        if (!empty($config['search_query_native_mysql'])) {
            $xmltable = FN_XMDBTable($tablename);
            $query = str_replace("FROM $tablename WHERE", "FROM {$xmltable->driverclass->sqltable} WHERE", $query);
            $res = $xmltable->driverclass->dbQuery($query);
        } else {
            $res = FN_XMETADBQuery($query);
        }

        if (!is_array($res)) {
            $res = array();
        }


        //dprint_r($query);
        //DEBUG: print query
        if (isset($_GET['debug'])) {
            dprint_r($query);
            dprint_r($_REQUEST);
            dprint_r($orderquery);
            dprint_r(__FILE__ . " post query " . __LINE__ . " : " . FN_GetExecuteTimer());
            @ob_end_flush();
        }
        //----------------export------------------------------------------------------->
        if (!empty($res) && !empty($config['enable_export']) && !empty($_GET['export'])) {
            $first = true;
            $csvres = array();
            foreach ($res as $row) {
                $rec = $_tables[$tablename]->xmltable->GetRecordByPrimarykey($row[$_tables[$tablename]->xmltable->primarykey]);
                if ($first) {
                    $first = false;
                    foreach ($rec as $k => $v) {
                        $title = $k;
                        if (isset($_tables[$tablename]->formvals[$k]['title']))
                            $title = $_tables[$tablename]->formvals[$k]['title'];
                        $r[$k] = $title;
                    }
                    $csvres[] = $r;
                }
                $csvres[] = $rec;
                //break;
            }
            $this->SaveToCSV($csvres, "export.csv");
        }
        //----------------export------------------------------------------------------->
        //dprint_r(__LINE__." : ".FN_GetExecuteTimer());

        return $res;
    }

    // I seguenti metodi sono ora nei trait:
    // - FNDBVIEWUtils: SaveToCSV, MakeLink, SecureHtml, GoDownload, Request, GetRank, SetRank, InvalidateCache
    // - FNDBVIEWComments: GetUsersComments, WriteComment, DelComment
    // - FNDBVIEWCRUD: UpdateRecord, InsertRecord
    // - FNDBVIEWExport: WriteSitemap, GenerateRSS, GetRecordValues, GenOfflineUpdate, GenOfflineInsert
    // - FNDBVIEWPermissions: IsAdmin, GetFieldUser, GetFieldUserList, IsAdminRecord, CanAddRecord, CanViewRecords, UserCanEditField, CanEditRecord, CanViewRecord

    /**
     *
     */
    function PrintList($results, $tplvars)
    {
        global $_FN;
        //--config-->
        $config = $this->config;
        $tablename = $config['tables'];
        $tplvars['items'] = array();
        $tplvars['pages'] = array();
        $tplvars['url_offlineforminsert'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform");
        $tplvars['querystring'] = $this->MakeLink(array("page" => null), "&", true, true);
        //--config--<
        $page = FN_GetParam("page", $_GET);
        $recordsperpage = FN_GetParam("rpp", $_GET);
        if ($recordsperpage == "")
            $recordsperpage = $config['recordsperpage'];
        if ($recordsperpage == "")
            $recordsperpage = 50;

        //---template------>
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/list.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/list.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/list.tp.html", false);
        if (file_exists("themes/{$_FN['theme']}/sections/{$_FN['mod']}/list.tp.html"))
            $tplfile = "themes/{$_FN['theme']}/sections/{$_FN['mod']}/list.tp.html";
        $templateString = file_get_contents($tplfile);
        $tplbasepath = dirname($tplfile) . "/";
        //---template------<
        $tplvars['linkpreviouspage'] = false;
        $tplvars['linknextpage'] = false;
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());
        }

        $t = FN_XMDBForm($tablename);
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());
        }
        $num_records = count($results);
        if (is_array($results) && ($c = $num_records) > 0) {
            //---------------------calcolo paginazione -------------------->
            if ($page == "")
                $page = 1;            //dprint_r("num_records=$num_records recordsperpage=$recordsperpage");
            $numPages = ceil($num_records / $recordsperpage);
            $start = ($page * $recordsperpage - $recordsperpage) + 1;
            $end = $start + $recordsperpage - 1;

            if ($end > $num_records)
                $end = $num_records;
            //---------------------calcolo paginazione --------------------<
            //---------------------tabella paginazione -------------------->
            $tpl_vars = array();
            $tp_str_navpages_theme = FN_TPL_GetHtmlPart("nav pagination", $templateString);
            if ($tp_str_navpages_theme != "") {
                $tp_str_navpages = $tp_str_navpages_theme;
                $templateString = str_replace($tp_str_navpages_theme, "{html_pages}", $templateString);
            }
            //----------------------------pages---------------------------->
            //risultati per pagina ----<
            if ($page > 1) {
                $linkpage = $this->MakeLink(array("page" => $page - 1, "addtocart" => null), "&amp;");
                $tplvars['linkpreviouspage'] = $linkpage;
                // Nuove variabili per compatibilità con il tema
                $tplvars['nav_page_prev'] = array('link' => $linkpage);
            } else {
                $tplvars['linkpreviouspage'] = false;
                $tplvars['nav_page_prev'] = false;
            }

            // Prima pagina
            if ($page > 1) {
                $linkfirstpage = $this->MakeLink(array("page" => 1, "addtocart" => null), "&amp;");
                $tplvars['nav_page_first'] = array('link' => $linkfirstpage);
            } else {
                $tplvars['nav_page_first'] = false;
            }

            $max_pages = 8;
            $startpage = $page;
            $scarto = $startpage / $max_pages;
            if ($scarto != 0) {
                $scarto = $startpage % $max_pages;
                $startpage -= ($scarto);
                if ($page < $startpage)
                    $startpage = $page;
                if ($startpage < 1)
                    $startpage = 1;
            }
            $ii = $startpage;
            $tp_pages = array();
            for ($i = $startpage; $i <= $numPages; $i++) {
                $tpPage = array();
                if ($ii >= $startpage + $max_pages)
                    break;
                $linkpage = $this->MakeLink(array("page" => $i, "addtocart" => null), "&");
                $hclass = "";
                if ($page == $i) {
                    $tpPage['active'] = true;
                } else {
                    $tpPage['active'] = false;
                }

                $tpPage['link'] = $linkpage;
                $tpPage['txt_page'] = $i;
                $tplvars['pages'][] = $tpPage;
                $ii++;
            }

            // Mappatura array pages per compatibilità con il nuovo tema
            $tplvars['nav_pages'] = array();
            foreach ($tplvars['pages'] as $page_item) {
                $nav_page = array(
                    'link' => $page_item['link'],
                    'title' => $page_item['txt_page'],
                    'current' => $page_item['active']
                );
                $tplvars['nav_pages'][] = $nav_page;
            }

            if ($page < $numPages) {
                $linkpage = $this->MakeLink(array("page" => $page + 1, "addtocart" => null), "&amp;");
                $tplvars['linknextpage'] = $linkpage;
                // Nuove variabili per compatibilità con il tema
                $tplvars['nav_page_next'] = array('link' => $linkpage);
            } else {
                $tplvars['linknextpage'] = false;
                $tplvars['nav_page_next'] = false;
            }

            // Ultima pagina
            if ($page < $numPages) {
                $linklastpage = $this->MakeLink(array("page" => $numPages, "addtocart" => null), "&amp;");
                $tplvars['nav_page_last'] = array('link' => $linklastpage);
            } else {
                $tplvars['nav_page_last'] = false;
            }

            $tplvars['txt_rsults'] = FN_Translate("search results", "Aa") . "  $start - $end  " . FN_i18n("of") . " $num_records" . "";
            $tplvars['txt_num_records'] = FN_Translate("search results", "Aa") . "  $start - $end  " . FN_i18n("of") . " $num_records" . "";
            //---------------------tabella paginazione --------------------<

            for ($c = $start - 1; $c <= $end - 1 && isset($results[$c]); $c++) {
                $item = $this->HtmlItem($tablename, $results[$c][$t->xmltable->primarykey]);
                $tplvars['items'][] = $item;
            }
        }




        //dprint_xml($templateString);
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());
        }
        $html = FN_TPL_ApplyTplString($templateString, $tplvars, $tplbasepath);
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());
        }
        // dprint_xml($html);
        //die();
        //dprint_r($tplvars);
        return $html;
    }

    /**
     *
     * @global array $_FN
     * @param string $id_record
     * @param string $tablename
     * @param bool $showbackbutton 
     */
    function ViewRecordHistory($id_record, $_tablename = "")
    {
        global $_FN;
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/history.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/history.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/history.tp.html", false);
        $tplbasepath = dirname($tplfile) . "/";
        $template = file_get_contents($tplfile);
        $tpvars = array();

        $shownavigatebar = true;
        $version = FN_GetParam("version", $_GET);
        $config = $this->config;
        $html = "";
        //--config--<
        $tables = explode(",", $config['tables']);
        if ($_tablename == "") {
            $tablename = $tables[0];
        } else {
            $tablename = $_tablename;
        }
        $t = FN_XMDBForm($tablename);
        $Table = FN_XMDBForm($tablename);
        $Table_history = FN_XMDBForm($tablename . "_versions");
        //del history------->
        $action = FN_GetParam("action", $_GET, "flat");
        if ($action == "delete") {
            $item = $t->xmltable->GetRecordByPrimarykey($id_record);
            if ($this->IsAdminRecord($item)) {
                $Table_history->xmltable->DelRecord($version);
                $version = "";
            }
        }
        //del history-------<




        if ($shownavigatebar == true) {
            $tpvars['navigationbar'] = $this->Toolbar($config, $t->xmltable->GetRecordByPrimarykey($id_record));
        } else {
            $tpvars['navigationbar'] = array();
        }

        $res = FN_XMETADBQuery("SELECT * FROM {$tablename}_versions WHERE {$t->xmltable->primarykey} LIKE $id_record ORDER BY recordupdate DESC");
        $tpvars['history_items'] = array();
        if (is_array($res)) {
            foreach ($res as $item) {
                $item_history = array();
                $item_history['title_inner'] = "";
                $item_history['is_admin'] = $this->IsAdminRecord($item);
                $link_deleteversion = $this->MakeLink(array("action" => "delete", "op" => "history", "id" => $id_record, "version" => $item['idversions']), "&");
                $link_version = $this->MakeLink(array("op" => "history", "id" => $id_record, "version" => $item['idversions']), "&");
                $item_history['url_view'] = $link_version;
                $item_history['version_date'] = FN_GetDateTime($item['recordupdate']);
                $item_history['url_delete'] = ($this->IsAdminRecord($item)) ? "javascript:check('$link_deleteversion')\"" : "";
                $item_history['version_user'] = $item['userupdate'];
                $item_history['htmlitem'] = "";
                if ($version == $item['idversions']) {
                    $item_history['title_inner'] = "";
                    $item_history['url_view'] = $this->MakeLink(array("op" => "history", "id" => $id_record), "&");
                    $item_history['htmlitem'] = $this->ViewRecordPage($item['idversions'], "{$tablename}_versions", false); // visualizza la pagina col record
                }
                $tpvars['history_items'][] = $item_history;
            }
        } else
            $html .= FN_Translate("no previous version is available");

        $tpvars['htmlitem'] = $html;
        $html = FN_TPL_ApplyTplString($template, $tpvars);


        return $html;
    }

    /**
     *
     * @global array $_FN
     * @param string $id_record
     * @param string $tablename
     * @param bool $showbackbutton 
     */
    function ViewRecordPage($id_record, $_tablename = "", $shownavigatebar = true, $tpvars = array())
    {
        global $_FN;
        $inner = false;
        //--config-->
        $config = $this->config;
        //--config--<

        if ($_tablename == "") {
            $tablename = $this->config['tables'];
        } else {
            if ($_tablename != $this->config['tables']) {
                $inner = true;
            }
            $tablename = $_tablename;
        }

        $t = FN_XMDBForm($tablename);
        $Table = FN_XMDBForm($tablename);


        if (!$this->CanViewRecord($id_record, $tablename)) {
            return "";
        }

        $forcelang = isset($_GET['forcelang']) ? $_GET['forcelang'] : $_FN['lang'];
        $row = $Table->xmltable->GetRecordByPrimaryKey($id_record);
        //-------statistiche---------------------->>
        if ($config['enable_statistics'] == 1) {
            if (isset($row['view']) && $row['view'] != $row[$Table->xmltable->primarykey]) {
                $Table2 = FN_XMDBTable($tablename);
                $ff = array();
                $ff['view'] = $id_record;
                $ff['id'] = $id_record;
                //dprint_r($ff);
                $Table2->UpdateRecord($ff);
                $row = $Table2->GetRecordByPrimaryKey($id_record);
            }
            if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/$tablename" . "_stat")) {
                $sfields = array();
                $sfields[0]['name'] = "id";
                $sfields[0]['primarykey'] = "1";
                $sfields[1]['name'] = "view";
                XMETATable::createMetadbTable($_FN['database'], $tablename . "_stat", $sfields, $_FN['datadir']);
            }
            $tbtmp = FN_XMDBTable($tablename . "_stat");

            $tmprow['id'] = $row[$t->xmltable->primarykey];
            if (($oldview = $tbtmp->GetRecordByPrimaryKey($row[$t->xmltable->primarykey])) == false) {
                $tmprow['view'] = 1;
                $rowtmp = $tbtmp->InsertRecord($tmprow);
            } else {
                $oldview['view']++;
                $rowtmp = $tbtmp->UpdateRecord($oldview); //aggiunge vista
                $Table2 = FN_XMDBTable($tablename);
                $row = $Table2->GetRecordByPrimaryKey($id_record);
            }
        }
        //-------statistiche----------------------<<
        $tablename = $Table->tablename;
        $id_record = isset($row[$t->xmltable->primarykey]) ? $row[$t->xmltable->primarykey] : null;


        //--- template item ----->
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/detail.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/detail.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/detail.tp.html", false);
        if ($inner) {
            $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/detail.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/detail_inner.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/detail_inner.tp.html", false);
        }

        $tplbasepath = dirname($tplfile) . "/";
        $template = file_get_contents($tplfile);

        $tpvars['url_offlineform'] = "";
        if ($this->config['enable_offlineform']) {
            $tpvars['url_offlineform'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform&id=$id_record");
        }

        //--- template item -----<
        //---------NAVIGATE BAR-------------------------------------------->
        $htmlNavigationbar = "";
        if ($shownavigatebar == true) {
            $tpvars['navigationbar'] = $this->Toolbar($config, $row);
        } else {
            $tpvars['navigationbar'] = array();
        }


        //---------NAVIGATE BAR--------------------------------------------<
        //
        //------------------------------visualizzazione-------------------------------->
        $linklist = $this->MakeLink(array("op" => null, null => null, "&amp;")); //link list
        $link = $this->MakeLink(array("op" => "view", "id" => "$id_record", "&amp;")); //link  to this page
        $htmlFooter = "";
        ob_start();
        if ($shownavigatebar && file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/viewfooter.php")) {
            include("{$_FN['src_application']}/sections/{$_FN['mod']}/viewfooter.php");
        }
        $htmlFooter = ob_get_clean();
        $htmlHeader = "";
        ob_start();
        if ($shownavigatebar && file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/viewheader.php")) {
            include("{$_FN['src_application']}/sections/{$_FN['mod']}/viewheader.php");
        }
        $htmlHeader = ob_get_clean();
        $tpvars['footer'] = $htmlFooter;
        $tpvars['header'] = $htmlHeader;
        //------------------------------ INNER TABLES---------------------------------->
        ob_start();
        $oldvalues = $row;
        $htmlout = "";
        if ($Table->innertables) {
            foreach ($Table->innertables as $k => $v) {
                $title = $v['tablename'];
                if (isset($v["frm_{$_FN['lang']}"]))
                    $title = $v["frm_{$_FN['lang']}"];
                $params = array();
                $params['echo'] = false;
                $tpvars['title_inner'] = "";
                $params['title_inner'] = "";
                $params['path'] = $Table->path;
                $params['enableedit'] = true;
                $params['enablenew'] = false;
                $params['enabledelete'] = false;
                $params['enableview'] = true;
                $tinner = explode(",", $v["linkfield"]);
                if (isset($tinner[1]) && $tinner[1] != "" && isset($oldvalues[$tinner[0]]))
                    $params['restr'] = array($tinner[1] => $oldvalues[$tinner[0]]);
                else
                    $params['restr'] = array($v["linkfield"] => $oldvalues[$Table->xmltable->primarykey]);
                if (isset($v["tablename"]) && isset($oldvalues[$Table->xmltable->primarykey]) && file_exists("{$_FN['datadir']}/{$_FN['database']}/{$v["tablename"]}.php")) {
                    $tmptable = FN_XMDBForm($v["tablename"], $params);
                    $sort = false;
                    $desc = false;
                    $allview = $tmptable->xmltable->getRecords($params['restr'], false, false, $sort, $desc);
                    if (!empty($tmptable->xmltable->fields['date'])) {
                        $allview = xmetadb_array_natsort_by_key($allview, "date", true);
                    }
                    if (!empty($tmptable->xmltable->fields['priority'])) {
                        $allview = xmetadb_array_natsort_by_key($allview, 'priority', true);
                    }


                    if (is_array($allview) && count($allview) > 0) {
                        $tpvars['title_inner'] = $title;
                        $params['title_inner'] = $title;
                        foreach ($allview as $view) {
                            if ($this->CanViewRecord($view[$tmptable->xmltable->primarykey], $v["tablename"])) {
                                echo $this->ViewRecordPage($view[$tmptable->xmltable->primarykey], $v["tablename"], false, $params);
                            }
                            $params['title_inner'] = $tpvars['title_inner'] = "";
                        }
                    }
                }
            }
        }
        $innerTables = ob_get_clean();

        $tpvars['innertables'] = $innerTables;
        //------------------------------ INNER TABLES----------------------------------<
        //xdprint_r($tpvars);
        //        dprint_xml($template);
        //dprint_r($tpvars['navigationbar']);
        $template = FN_TPL_ApplyTplString($template, $tpvars);
        //        dprint_xml($template);
        //        @ob_end_flush();
        $Table->SetlayoutTemplateView($template);
        $htmlView = $Table->HtmlShowView($Table->GetRecordTranslatedByPrimarykey($id_record));
        return $htmlView;

        //------------------------------visualizzazione--------------------------------<
    }

    /**
     * 
     * @global array $_FN
     * @return string
     */
    function AdminPerm()
    {
        global $_FN;
        //--config-->
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];
        $xmlform = FN_XMDBForm($tablename);
        $op = FN_GetParam("op", $_GET);
        $results = $this->GetResults($config);
        $query = "SELECT * FROM $tablename";
        $results = FN_XMETADBQuery($query);
        $titlefield = explode(",", $config['titlefield']);
        $permissions_records_groups = explode(",", $config['permissions_records_groups']);
        $permissions_records_edit_groups = explode(",", $config['permissions_records_edit_groups']);
        $html = "";
        if (!FN_IsAdmin())
            return "";
        if (isset($_POST['groups'])) {
            foreach ($_POST['groups'] as $k => $v) {
                if (is_array($v)) {
                    $newgroups[$k] = implode(",", $v);
                }
            }
        }
        if (isset($_POST['editgroups'])) {
            foreach ($_POST['editgroups'] as $k => $v) {
                if (is_array($v)) {
                    $neweditgroups[$k] = implode(",", $v);
                }
            }
        }
        //dprint_r($_POST);

        $html .= "<script>
		
select_allck = function(el){
	var name = el.name.replace('s_','');
	var cklist = document.getElementsByTagName('input');
	for (var i in cklist)
	{
		if (cklist[i].type=='checkbox' && cklist[i].name.indexOf('['+name+']')>=0 && cklist[i].name.indexOf('tgroups')<=0)
		{
			if (el.checked)
			{
				cklist[i].checked = true;
			}
			else
				cklist[i].checked = false;
		}
	}
	//console.log(cklist);
}
select_allcke = function(el){
	var name = el.name.replace('se_','');
	var cklist = document.getElementsByTagName('input');
	for (var i in cklist)
	{
		if (cklist[i].type=='checkbox' && cklist[i].name.indexOf('['+name+']')>=0 && cklist[i].name.indexOf('tgroups')>=0)
		{
			if (el.checked)
			{
				cklist[i].checked = true;
			}
			else
				cklist[i].checked = false;
		}
	}
	//console.log(cklist);
}
</script>";
        //dprint_r($_POST);
        $pagelink = FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=$op");
        $html .= "<h3>" . FN_Translate("manage permissions") . "</h3>";
        $html .= "<form method=\"post\" action=\"\">";
        $html .= "<table style=\"border:1px solid\">";
        $cst = count($titlefield);
        $csg = count($permissions_records_groups);
        $csgw = count($permissions_records_edit_groups);

        $html .= "<tr><td   style=\"border:1px solid\" colspan=\"$cst\"></td><td  style=\"border:1px solid\" colspan=\"$csg\">" . FN_Translate("read") . "</td><td  style=\"border:1px solid;background-color:#dadada;color:#000000\" colspan=\"$csgw\" >" . FN_Translate("write") . "</td>";
        $htmltitles = "<tr>";
        foreach ($titlefield as $t) {
            $htmltitles .= "<td style=\"border:1px solid\" >";
            $htmltitles .= $t;
            $htmltitles .= "</td>";
        }
        foreach ($permissions_records_groups as $t) {
            $htmltitles .= "<td style=\"border:1px  solid;text-align:center\">";
            $htmltitles .= $t;

            $htmltitles .= "<br /><input type=\"checkbox\" name=\"s_$t\" onchange=\"select_allck(this);\" />";
            $htmltitles .= "</td>";
        }
        foreach ($permissions_records_edit_groups as $t) {
            $htmltitles .= "<td style=\"border:1px  solid;text-align:center;background-color:#dadada;color:#000000\">";
            $htmltitles .= $t;

            $htmltitles .= "<br /><input type=\"checkbox\" name=\"se_$t\" onchange=\"select_allcke(this);\" />";
            $htmltitles .= "</td>";
        }




        $htmltitles .= "</tr>";

        $i = 0;
        $toupdate = false;
        $saveok = true;
        $html .= $htmltitles;
        //dprint_r($_POST);
        foreach ($results as $values) {
            //if ($i > 1000)
            //	break;
            $toupdateitem = false;
            if (isset($_POST['oldgroups'])) {
                $toupdate = true;

                //read
                if (!isset($newgroups[$values[$xmlform->xmltable->primarykey]])) {
                    $newgroups[$values[$xmlform->xmltable->primarykey]] = "";
                }
                if (isset($values['groupview']) && $values['groupview'] != $newgroups[$values[$xmlform->xmltable->primarykey]]) {
                    $toupdateitem = true;
                    $values['groupview'] = $newgroups[$values[$xmlform->xmltable->primarykey]];
                }
                //edit
                if (!isset($neweditgroups[$values[$xmlform->xmltable->primarykey]])) {
                    $neweditgroups[$values[$xmlform->xmltable->primarykey]] = "";
                }
                if (isset($values['groupinsert']) && $values['groupinsert'] != $neweditgroups[$values[$xmlform->xmltable->primarykey]]) {
                    $toupdateitem = true;
                    $values['groupinsert'] = $neweditgroups[$values[$xmlform->xmltable->primarykey]];
                }
            }
            if ($toupdateitem) {
                $res = $xmlform->xmltable->UpdateRecord($values);
                if (!is_array($res))
                    $saveok = false;
            }
            $html .= "<tr>";
            foreach ($titlefield as $t) {
                $html .= "<td style=\"border:1px  solid;\">";
                $html .= $values[$t];
                $html .= "</td>";
            }
            $usergroups = explode(",", $values['groupview']);
            $usereditgroups = explode(",", $values['groupinsert']);
            //read
            foreach ($permissions_records_groups as $t) {
                $html .= "<td title=\"$t\" style=\"border:1px  solid;text-align:center\">";
                $html .= "<input name=\"groups[{$values[$xmlform->xmltable->primarykey]}][$t]\" value=\"$t\" type=\"checkbox\" ";

                if (in_array($t, $usergroups)) {
                    $html .= "checked=\"checked\"";
                }
                $html .= " />";
                $html .= "</td>";
            }
            //modify
            foreach ($permissions_records_edit_groups as $t) {
                $html .= "<td title=\"$t\" style=\"border:1px  solid;text-align:center;background-color:#dadada;color:#000000\">";
                $html .= "<input name=\"editgroups[{$values[$xmlform->xmltable->primarykey]}][$t]\" value=\"$t\" type=\"checkbox\" ";

                if (in_array($t, $usereditgroups)) {
                    $html .= "checked=\"checked\"";
                }
                $html .= " />";
                $html .= "</td>";
            }
            $html .= "</tr>";
            $i++;
        }
        $html .= "</table>";
        if ($toupdate) {
            if ($saveok)
                $html .= FN_HtmlAlert(FN_Translate("the data were successfully updated"));
            else
                $html .= FN_HtmlAlert(FN_Translate("error"));
        }
        $html .= "<input name=\"oldgroups\" value=\"1\" type=\"hidden\" />";
        $l = FN_RewriteLink("index.php?mod={$_FN['mod']}", "&");
        $html .= "<button type=\"submit\">" . FN_Translate("save") . "</button>";
        $html .= "<button type=\"reset\">" . FN_Translate("reset") . "</button>";
        $html .= "<button onclick=\"window.location='$l'\" type=\"button\">" . FN_Translate("go to the contents list") . "</button>";
        $html .= "</form>";
        return $html;
    }

    /**
     *
     * @param string $config
     * @param array $row
     * @return string
     */
    function Toolbar($config, $row)
    {
        global $_FN;
        $ret = array();
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];
        $t = FN_XMDBForm($tablename);
        $op = FN_GetParam("op", $_GET, "html");
        $id_record = $row[$t->xmltable->primarykey];
        $results = $this->GetResults($config);
        $next = $prev = "";
        $k = 0;
        if (is_array($results))
            foreach ($results as $k => $item) {
                $id = $item[$t->xmltable->primarykey];
                if ($id == $id_record) {
                    $prev = isset($results[$k - 1]) ? $results[$k - 1][$t->xmltable->primarykey] : $results[count($results) - 1][$t->xmltable->primarykey];
                    $next = isset($results[$k + 1]) ? $results[$k + 1][$t->xmltable->primarykey] : $results[0][$t->xmltable->primarykey];

                    break;
                }
            }



        $linkusermodify = $this->MakeLink(array("op" => "users", "id" => $id_record), "&");
        $linkmodify = $this->MakeLink(array("op" => "edit", "id" => $id_record), "&");
        $linkprev = $this->MakeLink(array("id" => $prev), "&");
        $linkhistory = $this->MakeLink(array("op" => "history", "id" => $id_record), "&");
        $linknext = $this->MakeLink(array("id" => $next), "&");
        $linklist = $this->MakeLink(array("op" => null), "&");
        $linkview = $this->MakeLink(array("op" => "view", "id" => $id_record), "&");


        $vars['txt_rsults'] = ($k + 1) . "/" . count($results);
        $vars['txt_num_records'] = ($k + 1) . "/" . count($results);
        $vars['linkusermodify'] = $linkusermodify;
        $vars['linkmodify'] = $linkmodify;
        $vars['linklist'] = $linklist;
        $vars['linkpreviouspage'] = $linkprev;
        $vars['linknextpage'] = $linknext;
        $vars['linkhistory'] = $linkhistory;

        // Nuove variabili per compatibilità con il tema
        $vars['nav_page_prev'] = $linkprev ? array('link' => $linkprev) : false;
        $vars['nav_page_next'] = $linknext ? array('link' => $linknext) : false;
        // Per la vista singola non ci sono first/last page e nav_pages
        $vars['nav_page_first'] = false;
        $vars['nav_page_last'] = false;
        $vars['nav_pages'] = array();

        $ret = $vars;



        //-----next / prev / list buttons ----------------------------------------->
        $vars = array();
        $vars['title'] = FN_Translate("go to the contents list");
        $vars['link'] = $linklist;
        $vars['image'] = FN_FromTheme("images/up.png");
        $ret['viewlist'] = $vars;

        $vars = array();
        $vars['title'] = FN_Translate("previous record");
        $vars['link'] = $linkprev;
        $vars['image'] = FN_FromTheme("images/left.png");
        $ret['viewprev'] = $vars;

        $vars = array();
        $vars['title'] = FN_Translate("next record");
        $vars['image'] = FN_FromTheme("images/right.png");
        $vars['link'] = $linknext;
        $ret['viewnext'] = $vars;
        //-----next / prev / list buttons -----------------------------------------<
        //-----view/modify/history/users buttons ---------------------------------->
        $user_options = array();
        //view button
        $vars['title'] = FN_Translate("view");
        $vars['image'] = FN_FromTheme("images/mime/doc.png");
        $vars['link'] = $linkview;
        $vars['active'] = ($op == "view");
        $user_options['view'] = $vars;
        //history button
        if ($config['enable_history']) {
            $vars['title'] = FN_Translate("version history");
            $vars['image'] = FN_FromTheme("images/read.png");
            $vars['link'] = $linkhistory;
            $vars['active'] = ($op == "history");
            $user_options['history'] = $vars;
        }
        if ($this->IsAdminRecord($row)) {

            //edit button
            $vars['title'] = FN_Translate("modify");
            $vars['image'] = FN_FromTheme("images/modify.png");
            $vars['link'] = $linkmodify;
            $vars['active'] = ($op == "edit");
            $user_options['edit'] = $vars;

            //users button
            $vars['title'] = FN_Translate("edit qualified users to modify");
            $vars['image'] = FN_FromTheme("images/users.png");
            $vars['link'] = $linkusermodify;
            $vars['active'] = ($op == "users");
            $user_options['users'] = $vars;
        }
        /*
          if ($config['enable_offlineform'])
          {
          $vars['title']=FN_Translate("scarica scheda per l'aggiornamento");
          $vars['image']=FN_FromTheme("images/download.png");
          $vars['link']=FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform&id=$id_record");
          $vars['active']=false;
          $user_options['offlineform']=$vars;
          } */
        //-----view/modify/history/users buttons ----------------------------------<
        $ret['user_options'] = $user_options;
        return $ret;
    }

    /**
     *
     * @global array $_FN
     * @param type $id_record 
     */
    function DelRecordForm($id_record)
    {
        global $_FN;
        //--config-->
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];
        //--config--<
        $html = "";
        $Table = FN_XMDBTable($tablename);
        $row = $Table->GetRecordByPrimaryKey($id_record);
        if (empty($config['enable_delete']) || $row == null)
            die(FN_Translate("you may not do that"));

        if (!$this->IsAdminRecord($row))
            die(FN_Translate("you may not do that"));

        //hide record 
        if (!empty($config['hide_on_delete'])) {
            if (!isset($Table->fields['recorddeleted'])) {
                $tfield['name'] = "recorddeleted";
                $tfield['type'] = "bool";
                $tfield['frm_show'] = "0";

                addxmltablefield($Table->databasename, $Table->tablename, $tfield, $Table->path);
            }
            $newvalues = array("id" => $id_record, "recorddeleted" => 1);
            $this->InvalidateCache($tablename);
            $Table->UpdateRecord($newvalues);
        }
        //delete record
        else {
            if ($row != null)
                $Table->DelRecord($id_record);
            // elimino i permessi sul record
            $restr = array();
            $listusers = FN_XMDBTable("fieldusers");
            $restr['table_unirecid'] = $row[$Table->primarykey];
            $restr['tablename'] = $tablename;
            $list_field = $listusers->GetRecords($restr);
            if (is_array($list_field)) {
                foreach ($list_field as $field) {
                    $listusers->DelRecord($field['id']);
                }
            }
            $Table->DelRecord($id_record);
            if (isset($_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_delete'])) {
                $function = $_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_delete'];
                if (function_exists($function)) {
                    $function($newvalues);
                }
            }
        }
        $this->InvalidateCache($tablename);
        $this->WriteSitemap();
        $html .= "<br />" . FN_Translate("record was deleted");
        $html .= "";
        $link = $this->MakeLink(array("op" => null)); //list link
        $html .= "<br /><br /><button onclick=\"window.location='$link'\"><img border=\"0\" style=\"vertical-align:middle\" src=\"" . FN_FromTheme("images/left.png") . "\" alt=\"\">&nbsp;" . FN_Translate("go to the contents list") . "</button>";
        return $html;
    }

    /**
     *
     * @global array $_FN
     * @param string $id_record
     * @param object $Table
     * @param array $errors
     * @return type 
     */
    function EditRecordForm($id_record, $Table, $errors = array(), $reloadDataFromDb = false)
    {
        global $_FN;

        //--config-->
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];
        //--config--<
        $tb = FN_XMDBTable($tablename);
        $row = $tb->GetRecordByPk($id_record);
        $tpvars['navigationbar'] = $this->Toolbar($config, $row);

        $html = "";
        $html .= "
<script type=\"text/javascript\">
//<!--
function set_changed()
{
    var allButtons = document.getElementsByTagName('*');
    for (var i in allButtons)
    {
        if (allButtons[i].onclick)
        {
            try{
            if (allButtons[i].getAttribute && allButtons[i].getAttribute('onclick').indexOf('window.location')==0)
            {
                //console.log(allButtons[i].getAttribute('onclick'));
                allButtons[i].setAttribute('onclick','if (confirm_exitnosave()){'+allButtons[i].getAttribute('onclick')+'}');
            }
            }catch(e){ console.log(e);}
        }
    }
      var allLinks = document.getElementsByTagName('a');
	for (var i in allLinks)
	{
		if (!allLinks[i].onclick || allLinks[i].onclick=='' || allLinks[i].onclick==undefined && allLinks[i].href )
		{
                try{
			if (allLinks[i].setAttribute)
			{
				allLinks[i].setAttribute('onclick','return confirm_exitnosave()');
			}
                        }catch(e){ console.log(e);}
		}
	}
}
function confirm_exitnosave()
{
	if(confirm ('" . addslashes(FN_Translate("you exit without to save?")) . "'))
	{
		return true;
	}
	return false;
}
//-->
</script>	
";
        if (isset($_POST['__NOSAVE'])) {
            $html .= "
<script type=\"text/javascript\">
//<!--
set_changed();
//-->
</script>";
        }

        //----template--------->
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/formedit.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/formedit.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/formedit.tp.html", false);
        $template = file_get_contents($tplfile);

        $tplvars['url_offlineform'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform&id=$id_record");
        $tplvars['url_offlineforminsert'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform");

        $tpvars['formaction'] = $this->MakeLink(array("op" => "updaterecord", "id" => $id_record), "&amp;"); //index.php?mod={$_FN['mod']}&amp;op=updaterecord&amp;id=$id_record
        $tpvars['urlcancel'] = $this->MakeLink(array("op" => null, "id" => null), "&");


        //$esc =uniqid("_");
        //$template =str_replace("if {",$esc,$template);
        $template = FN_TPL_ApplyTplString($template, $tpvars);
        //$template =str_replace($esc,"if {",$template); 
        $Table->SetlayoutTemplate($template);    //----template---------<    
        $delinner = false;
        if ($Table->innertables) {
            foreach ($Table->innertables as $k => $v) {
                if (!empty($_GET['inner'])) {
                    if (isset($_GET["op___xdb_{$v['tablename']}"]) && $_GET["op___xdb_{$v['tablename']}"] == "del")
                        $delinner = true;
                }
            }
        }

        if (empty($_GET['inner']) || $delinner == true) {
            $forcelang = isset($_GET['forcelang']) ? $_GET['forcelang'] : $_FN['lang'];
            if ($reloadDataFromDb)
                $nv = $row;
            else
                $nv = $Table->getbypost();
            $html .= $Table->HtmlShowUpdateForm($id_record, FN_IsAdmin(), $nv, $errors);
            $pk = $Table->xmltable->primarykey;
        }

        //editor inner tables ----------------------------------------------------->
        if ($Table->innertables) {
            foreach ($Table->innertables as $k => $v) {
                if (!empty($_GET['inner']) && !$delinner) {
                    if (!isset($_GET["op___xdb_" . $v['tablename']])) {
                        //dprint_r($_FN);
                        continue;
                    }
                }

                $params = array();
                if (isset($_FN['modparams'][$_FN['mod']]['editorparams']['innertables'][$v["tablename"]]))
                    $params = $_FN['modparams'][$_FN['mod']]['editorparams']['innertables'][$v["tablename"]];

                $title = $v['tablename'];
                $innertablemaxrows = isset($v['innertablemaxrows']) ? $v['innertablemaxrows'] : "";

                $tmptable = FN_XMDBForm($v["tablename"], $params);
                if ($this->CanEditRecord($Table->xmltable->primarykey, $v["tablename"])) {
                    $v['enabledelete'] = true;
                }


                if (isset($v["frm_{$_FN['lang']}"]))
                    $title = $v["frm_{$_FN['lang']}"];
                $html .= "<div class=\"FNDBVIEW_innerform\">";
                $innertile = $title;

                if (!empty($_GET['inner']) && !$delinner) {
                    $innertile = "{$_FN['sections'][$_FN['mod']]['title']} -&gt; {$title}";
                    $tmptitle = explode(",", $config['titlefield']);
                    foreach ($tmptitle as $tmp_t) {
                        $sep = " -&gt; ";
                        if (!empty($row[$tmp_t])) {
                            $innertile .= "$sep" . $row[$tmp_t];
                            $sep = " ";
                        }
                    }
                }
                $html .= "<h3>$innertile</h3>";
                $params['path'] = $Table->path;
                $params['enableedit'] = true;
                $params['maxrows'] = $innertablemaxrows;
                $params['enablenew'] = (!isset($v["enablenew"]) || $v["enablenew"] == 1);
                $params['enabledelete'] = (!empty($v["enabledelete"]));
                $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/forminner.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/forminner.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/forminner.tp.html", false);
                $templateInner = file_get_contents($tplfile);
                $params['layout_template'] = $templateInner;
                $link = $this->MakeLink(array("op" => "edit", "id" => $id_record, "inner" => 1), "&", false);
                $params['link'] = $link;
                $link = $this->MakeLink(array("op" => "edit", "id" => $id_record, "inner" => null), "&", false);

                $params['link_listmode'] = $link;
                $params['textviewlist'] = "";
                if (isset($v['innertablefields']) && $v['innertablefields'] != "") {
                    $params['fields'] = str_replace(",", "|", $v['innertablefields']);  //innertablefields	
                }


                //op___xdb_
                $t = explode(",", $v["linkfield"]);
                if (isset($t[1]) && $t[1] != "" && isset($row[$t[0]]))
                    $params['restr'] = array($t[1] => $row[$t[0]]);
                $params['restr'] = isset($params['restr']) ? $params['restr'] : false;
                $params['forcenewvalues'] = $params['forceupdatevalues'] = $params['restr'];

                $params['link_cancel'] = $this->MakeLink(array("op" => "edit", "id" => $id_record, "inner" => null), "&", false);


                //ob_end_flush();
                if (isset($v["tablename"]) && isset($row[$Table->xmltable->primarykey])) {
                    ob_start();
                    $params['textnew'] = FN_Translate("add a new item into") . " " . $title;


                    FN_XMETATableEditor($v["tablename"], $params);
                    $html .= ob_get_clean();
                }
                $html .= "</div>";
            }
        }

        //editor inner tables -----------------------------------------------------<
        if (empty($_GET['embed']) && empty($_GET['inner']) || $delinner) {
            $listlink = $this->MakeLink(array("op" => null, "id" => null), "&");
            $html .= "<br /><br />";
            $linkCopyAndNew = FN_RewriteLink("index.php?op=new&id=$id_record", "&", false);
            $html .= "<button type=\"button\" onclick=\"document.getElementById('frmedit').action='$linkCopyAndNew';document.getElementById('frmedit').submit();\" ><img style=\"vertical-align:middle\" src=\"" . FN_FromTheme("images/modify.png") . "\" alt=\"\">&nbsp;" . FN_Translate("copy data and add new") . "</button>";

            $html .= "<button type=\"button\" onclick=\"window.location='$listlink'\"><img style=\"vertical-align:middle\" src=\"" . FN_FromTheme("images/up.png") . "\" alt=\"\">&nbsp;" . FN_Translate("view list") . "</button>";
            $link = $this->MakeLink(array("op" => "view", "id" => $id_record, "inner" => null));

            $html .= " <button type=\"button\" id=\"exitform2\"  onclick=\"window.location='$link'\"><img style=\"vertical-align:middle\" src=\"" . FN_FromTheme("images/left.png") . "\" alt=\"\">&nbsp;" . FN_Translate("exit and view") . "</button>";
        } else {

            $editlink = $this->MakeLink(array("op" => "edit", "id" => $id_record, "inner" => null), "&");
            $html .= "<br />
		<br />
		<button onclick=\"window.location='$editlink'\" >
		<img border=\"0\" style=\"vertical-align:middle\" src=\"" . FN_FromTheme("images/left.png") . "\" alt=\"\" />&nbsp;" . FN_Translate("back") . "</button>";
        }
        return $html;
    }

    /**
     *
     * @global array $_FN
     * @param object $Table
     * @param array $errors 
     */
    function NewRecordForm($Table, $errors = array())
    {
        global $_FN;
        //--config-->
        $config = $this->config;
        //--config--<
        //----template--------->
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/form.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/form.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/form.tp.html", false);
        $template = file_get_contents($tplfile);
        //die ($tplfile);
        $tpvars = array();
        $tpvars['formaction'] = $this->MakeLink(array("op" => "new"), "&amp;");
        $tpvars['urlcancel'] = $this->MakeLink(array("op" => null, "id" => null), "&");
        //$esc =uniqid("_");
        //$template =str_replace("if {",$esc,$template);
        global $_TPL_DEBUG;
        //$_TPL_DEBUG=1;
        $template = FN_TPL_ApplyTplString($template, $tpvars);
        //$template =str_replace($esc,"if {",$template);
        $Table->SetlayoutTemplate($template);
        $html = "";
        //----template---------<
        //----gestione esci senza salvare ------->
        $html .= "
<script type=\"text/javascript\">
function set_changed()
{
try{
    document.getElementById('exitform').setAttribute('onclick','confirm_exitnosave()');
    }catch(e){}
}
function confirm_exitnosave()
{
    if(confirm ('" . addslashes(FN_Translate("you exit without to save?")) . "'))
    {
        window.location='?mod={$_FN['mod']}';
    }
}
</script>";

        if (isset($_POST['__NOSAVE'])) {
            $html .= "
<script type=\"text/javascript\">
set_changed();
</script>";
        }
        //----gestione esci senza salvare -------<
        $nv = $Table->getbypost();
        $Table->ShowInsertForm(FN_IsAdmin(), $nv, $errors);
    }

    /**
     *
     * @global array $_FN
     * @param string $id_record
     */
    function UsersForm($id_record)
    {


        global $_FN;
        //--config-->
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];
        //--config--<
        $Table = FN_XMDBTable($tablename);
        $row = $Table->GetRecordByPrimaryKey($id_record);
        $pk = $Table->primarykey;
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/users.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/users.tp.html" : FN_FromTheme("{$_FN['src_finis']}/modules/dbview/users.tp.html", false);
        $template = file_get_contents($tplfile);
        $tpvars = array();
        $tpvars['navigationbar'] = $this->Toolbar($config, $row);
        $html = "";
        $titles = explode(",", $config['titlefield']);
        $t = array();
        foreach ($titles as $tt) {
            $t[] = $row[$tt];
        }
        $title = implode(" ", $t);
        $html .= "<h2>$title</h2>";
        $usertoadd = FN_GetParam("usertoadd", $_POST);
        $usertodel = FN_GetParam("usertodel", $_GET);
        if ($usertodel != "") {
            $fieldusers = FN_XMDBTable("fieldusers");
            $r = array();
            $r['tablename'] = $tablename;
            $r['username'] = $usertodel;
            $r['table_unirecid'] = $id_record;
            $old = $fieldusers->GetRecords($r);
            if (!isset($old[0]))
                $html .= "error delete:" . FN_Translate("this user not exists");
            $old = $old[0];
            $fieldusers->DelRecord($old[$fieldusers->primarykey]);
        }
        if ($usertoadd != "") {
            if (FN_GetUser($usertoadd) == null) {
                $html .= FN_Translate("this user not exists");
            } else
            if ($this->UserCanEditField($usertoadd, $row)) {
                $html .= FN_Translate("this user is already enabled");
            } else {
                $fieldusers = FN_XMDBTable("fieldusers");
                $r = array();
                $r['tablename'] = $tablename;
                $r['username'] = $usertoadd;
                $r['table_unirecid'] = $id_record;
                $fieldusers->InsertRecord($r);
                $rname = $row[$pk];
                if (isset($row['name']))
                    $rname = $row['name'];
                else
                    foreach ($Table->fields as $gk => $g) {
                        if (!isset($g->frm_show) || $g->frm_show != 0) {
                            $rname = $row[$gk];
                            break;
                        }
                    }
                //dprint_r($Table->fields);
                $message = FN_Translate("you were added to the users allowed to edit this content") . " \"" . $rname . "\" \n\n";
                $message .= FN_Translate("If you want to edit the content you have to login :") . "\n" . $_FN['siteurl'] . "index.php?mod=login\n";
                $message .= FN_Translate("and login as user") . ":\"$usertoadd\"\n";
                $message .= FN_Translate("then click on -user allowed to edit- and manage the permissions") . "\n" . $_FN['siteurl'] . "index.php?mod={$_FN['mod']}&op=edit&id=$id_record\n";
                $user_record = FN_GetUser($usertoadd);
                $subject = "[{$_FN['sitename']}] " . $rname;
                $to = FN_GetUser($usertoadd);
                FN_SendMail($to['email'], $subject, $message, false);
                FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||added user $usertoadd record: " . $rname . " in table $tablename.");
            }
        }
        if (!$this->IsAdminRecord($row)) {
            return (FN_Translate("you may not do that"));
            return;
        }
        $link = $this->MakeLink(array("op" => "users", "id" => $row[$pk]));
        $html .= "
	<form
		action=\"$link\"
		method=\"post\">
		<table>
			<tr>
				<td>";
        $html .= FN_Translate("add user");
        $html .= ": </td>
			<td></td>
			<td><input type=\"text\" name=\"usertoadd\" /></td>
		</tr>
		<tr>
			<td colspan=\"2\"><input type=\"hidden\" name=\"$pk\"
			  value=\"$id_record\" /> <input type=\"submit\" /></td>
		</tr>
	</table>
</form>
";
        $users = array();
        $users = $this->GetFieldUserList($row, $tablename, false);
        if (is_array($users))
            foreach ($users as $user) {
                $link = $this->MakeLink(array("op" => "users", "id" => $row[$pk], "usertodel" => $user['username']));
                $html .= "<br />" . $user['username'] . "<input type=\"button\" value=\"" . FN_Translate("delete") . "\" onclick=\"check('$link')\" />";
            }

        $tpvars['htmlusers'] = $html;
        $html = FN_TPL_ApplyTplString($template, $tpvars);
        return $html;
    }

    function GetSearchForm($orders, $tablename, $search_options, $search_min, $search_fields, $search_partfields = "")
    {

        global $_FN;
        $q = FN_GetParam("q", $_REQUEST);
        //--config-->
        $config = $this->config;
        $config['search_fields'] = explode(",", $config['search_fields']);
        $config['search_orders'] = explode(",", $config['search_orders']);
        $config['search_min'] = explode(",", $config['search_min']);
        $config['search_partfields'] = explode(",", $config['search_partfields']);
        $config['search_options'] = explode(",", $config['search_options']);
        //--config--<    
        $_table_form = FN_XMDBForm($tablename);
        $data = $config;
        $data['q'] = FN_GetParam("q", $_REQUEST, "html");
        $data['formaction'] = $this->MakeLink();


        $order = FN_GetParam("order", $_REQUEST);
        $desc = FN_GetParam("desc", $_REQUEST);
        if ($order == "") {
            $order = $config['defaultorder'];
            if ($desc == "")
                $desc = 1;
        }
        //-------------------------rules------------------------------------------->
        $rules = array();
        if ($config['table_rules']) {
            if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/{$config['table_rules']}.php")) {
                $xml = '<?php exit(0);?>
<tables>
	<field>
		<name>rule</name>
		<primarykey>1</primarykey>
		<frm_show>0</frm_show>
		<extra>autoincrement</extra>
	</field>
	<field>
		<name>title</name>
		<frm_i18n>rule title</frm_i18n>
		<frm_multilanguages>auto</frm_multilanguages>
		<frm_show>1</frm_show>
	</field>	
	<field>
		<name>query</name>
		<frm_i18n>query</frm_i18n>
		<frm_type>text</frm_type>
		<frm_cols>80</frm_cols>
		<frm_rows>10</frm_rows>
		<frm_show>1</frm_show>
	</field>
	<field>
		<name>function</name>
		<frm_i18n>function</frm_i18n>
		<frm_show>1</frm_show>
	</field>
</tables>';
                FN_Write($xml, "{$_FN['datadir']}/{$_FN['database']}/{$config['table_rules']}.php");
            }
            $tablerules = FN_XMDBForm($config['table_rules']);
            $rules = $tablerules->xmltable->GetRecords();
            foreach ($rules as $k => $rule) {
                $rules[$k]['selected'] = (!empty($_REQUEST['rule']) && $_REQUEST['rule'] == $rule['rule']) ? "selected=\"selected\"" : "";
                $rules[$k]['value'] = $rules[$k]['rule'];
            }
            $data['table_rules'] = array();
            $data['table_rules']['rules'] = $rules;
        } else {
            $data['table_rules'] = false;
            // $data['rules']=array();
        }

        //dprint_r($data);
        //-------------------------rules------------------------------------------->
        //----------------------search exact phrase-------------------------------->
        $search_fields_items = array();
        //dprint_r($rules);
        foreach ($search_fields as $fieldname) {
            if (isset($_table_form->formvals[$fieldname])) {
                $val = FN_GetParam("$fieldname", $_REQUEST);
                $search_fields_array['suffix'] = "";
                if (isset($_table_form->formvals[$fieldname]['frm_suffix']))
                    $search_fields_array['suffix'] = $_table_form->formvals[$fieldname]['frm_suffix'];
                $search_fields_array['title'] = $_table_form->formvals[$fieldname]['title'];
                $search_fields_array['value'] = $val;
                $search_fields_array['name'] = "sfield_$fieldname";
                $search_fields_items[] = $search_fields_array;
            }
        }
        $data['search_fields'] = $search_fields_items;
        //------------- looking for a part of the text ---------------------------->
        $search_fields_items = array();
        foreach ($config['search_partfields'] as $fieldname) {
            if (isset($_table_form->formvals[$fieldname])) {
                $search_fields_array = array();
                //dprint_r($_table_form->formvals[$partf]);
                $val = FN_GetParam("spfield_$fieldname", $_REQUEST);
                $search_fields_array['suffix'] = "";
                if (isset($_table_form->formvals[$fieldname]['frm_suffix']))
                    $search_fields_array['suffix'] = $_table_form->formvals[$fieldname]['frm_suffix'];
                $search_fields_array['title'] = $_table_form->formvals[$fieldname]['title'];
                $search_fields_array['value'] = $val;
                $search_fields_array['name'] = "spfield_$fieldname";
                $search_fields_items[] = $search_fields_array;
            }
        }
        $data['search_partfields'] = $search_fields_items;
        //------------------ looking for a part of the text -----------------------<    
        //---------------------- looking search_min ------------------------------->
        $search_fields_items = array();
        foreach ($config['search_min'] as $fieldname) {
            if (isset($_table_form->formvals[$fieldname])) {
                $search_fields_array = array();
                //dprint_r($_table_form->formvals[$partf]);
                $val = FN_GetParam("min_$fieldname", $_REQUEST);
                $search_fields_array['suffix'] = "";
                if (isset($_table_form->formvals[$fieldname]['frm_suffix']))
                    $search_fields_array['suffix'] = $_table_form->formvals[$fieldname]['frm_suffix'];
                $search_fields_array['title'] = $_table_form->formvals[$fieldname]['title'];
                $search_fields_array['value'] = $val;
                $search_fields_array['name'] = "min_$fieldname";
                $search_fields_items[] = $search_fields_array;
            }
        }
        $data['search_min'] = $search_fields_items;
        //---------------------- looking search_min -------------------------------< 
        //------------------------- search filters -------------------------------->
        $search_options = array();
        foreach ($config['search_options'] as $option) {
            $search_fields_items = array();
            if (isset($_table_form->formvals[$option]['options'])) {
                $search_fields_items['title'] = $_table_form->formvals[$option]['title'];
                //$htmlform.="<div class=\"navigatorformtitleCK\" ><span>$optiontitle:</span></div>";
                $options = array();
                if (is_array($_table_form->formvals[$option]['options'])) {
                    foreach ($_table_form->formvals[$option]['options'] as $c) {
                        $getid = "s_opt_{$option}_{$tablename}_{$c['value']}";
                        $search_fields_array['title'] = $c['title'];
                        $search_fields_array['value'] = $c['value'];
                        $search_fields_array['name'] = $getid;
                        $search_fields_array['id'] = "i_$getid";
                        $ck = "";
                        if (isset($_REQUEST[$getid]))
                            $ck = "checked=\"checked\"";
                        $search_fields_array['checked'] = $ck;
                        $options[] = $search_fields_array;
                    }
                }
                $search_fields_items['options'] = $options;
                $search_options[] = $search_fields_items;
            }
        }
        $data['search_options'] = $search_options;

        //------------------------- search filters --------------------------------<
        //----------------------------- order by ---------------------------------->
        $orderby = array();
        if (count($orders) > 0) {
            foreach ($orders as $o) {
                $orderby_item = array();
                if (!isset($_table_form->xmltable->fields[$o]))
                    continue;
                $tt = "frm_{$_FN['lang']}";
                if (isset($_table_form->xmltable->fields[$o]->$tt))
                    $no = $_table_form->xmltable->fields[$o]->$tt;
                elseif (isset($_table_form->xmltable->fields[$o]->frm_i18n)) {
                    $no = FN_Translate($_table_form->xmltable->fields[$o]->frm_i18n);
                } else
                    $no = $_table_form->xmltable->fields[$o]->title;
                if ($order == $o)
                    $s = "selected=\"selected\"";
                else
                    $s = "";

                $orderby_item['value'] = $o;
                $orderby_item['title'] = $no;
                $orderby_item['selected'] = $s;
                $orderby[] = $orderby_item;
            }
            $ck = ($desc == "") ? "" : "checked=\"checked\"";
            $data['checked_desc'] = $ck;
        }
        $data['order_by'] = $orderby;
        //----------------------------- order by ----------------------------------<    
        return $data;
    }

    /**
     * 
     * @param $orders
     * @param $tables
     * @param $config['search_options']
     */
    function SearchForm($orders, $tablename, $search_options, $search_min, $search_fields, $search_partfields = "")
    {
        global $_FN;
        $q = FN_GetParam("q", $_GET);
        $order = FN_GetParam("order", $_GET);
        $desc = FN_GetParam("desc", $_GET);
        //--config-->
        $config = $this->config;
        $config['search_fields'] = explode(",", $config['search_fields']);
        $config['search_orders'] = explode(",", $config['search_orders']);
        $config['search_min'] = explode(",", $config['search_min']);
        $config['search_partfields'] = explode(",", $config['search_partfields']);
        $config['search_options'] = explode(",", $config['search_options']);
        //--config--<
        if ($order == "") {
            $order = $config['defaultorder'];
            if ($desc == "")
                $desc = 1;
        }
        $_table_form = FN_XMDBForm($tablename);
        //------------------------------table rules-------------------------------->
        if ($config['table_rules']) {
            if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/{$config['table_rules']}.php")) {
                $xml = '<?php exit(0);?>
<tables>
	<field>
		<name>rule</name>
		<primarykey>1</primarykey>
		<frm_show>0</frm_show>
		<extra>autoincrement</extra>
	</field>
	<field>
		<name>title</name>
		<frm_i18n>rule title</frm_i18n>
		<frm_multilanguages>auto</frm_multilanguages>
		<frm_show>1</frm_show>
	</field>	
	<field>
		<name>query</name>
		<frm_i18n>query</frm_i18n>
		<frm_type>text</frm_type>
		<frm_cols>80</frm_cols>
		<frm_rows>10</frm_rows>
		<frm_show>1</frm_show>
	</field>
	<field>
		<name>function</name>
		<frm_i18n>function</frm_i18n>
		<frm_show>1</frm_show>
	</field>
</tables>';
                FN_Write($xml, "{$_FN['datadir']}/{$_FN['database']}/{$config['table_rules']}.php");
            }
        }
    }

    /**
     *
     */
    function ViewGrid()
    {
        global $_FN;
        //--config-->
        $config = $this->config;
        $tablename = $config['tables'];
        $search_fields = $config['search_fields'] != "" ? explode(",", $config['search_fields']) : array();
        $search_partfields = $config['search_fields'] != "" ? explode(",", $config['search_partfields']) : array();
        $search_orders = $config['search_orders'] != "" ? explode(",", $config['search_orders']) : array();
        $navigate_groups = $config['navigate_groups'] != "" ? explode(",", $config['navigate_groups']) : array();
        $search_options = $config['search_options'] != "" ? explode(",", $config['search_options']) : array();
        $search_min = $config['search_min'] != "" ? explode(",", $config['search_min']) : array();
        //--config--<
        $recordsperpage = FN_GetParam("rpp", $_GET);
        if ($recordsperpage == "")
            $recordsperpage = $config['recordsperpage'];
        if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/top.php")) {
            include("{$_FN['src_application']}/sections/{$_FN['mod']}/top.php");
        }
        $p = FN_GetParam("p", $_GET);
        $op = FN_GetParam("op", $_GET);
        $navigate = 1;
        $results = $this->GetResults($config);
        ob_start();
        if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/grid_header.php")) {
            include("{$_FN['src_application']}/sections/{$_FN['mod']}/grid_header.php");
        }
        $tplvars['html_header'] = ob_get_clean();
        $tplvars['html_categories'] = "";
        //----------------barra si navigazione categorie--------------------------->
        $tplvars['categories'] = array();
        if ($config['default_show_groups']) {
            $categories = $this->Navigate($results, $navigate_groups);
            $tplvars['categories'] = $categories['filters'];
            //dprint_r($tplvars['categories']);
        }
        //----------------barra si navigazione categorie---------------------------<
        //-----------------------pagina con i risultati---------------------------->
        $tplvars['html_export'] = "";
        $tplvars['url_export'] = "";
        $tplvars['url_exports'] = array();
        $tplvars['url_queryexport'] = "";
        $tplvars['num_records'] = 0;
        if ($results && !empty($config['enable_export'])) {
            $tplvars['num_records'] = count($results);
            //($params=false,$sep="&amp;",$norewrite=false,$onlyquery=0)
            $tplvars['url_queryexport'] = $this->MakeLink(array(), "&amp;", true, true);
            $tplvars['url_exports'][] = array("url_export" => $this->MakeLink(array("export" => 1), "&amp;"), "title" => "CSV");

            if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/exports.csv")) {

                $exports = FN_ReadCsvDatabase("{$_FN['src_application']}/sections/{$_FN['mod']}/exports.csv", ",");
                foreach ($exports as $export) {
                    $query_export = $tplvars['url_queryexport'];
                    $export_item = $export;
                    if (false !== strstr($export['script'], "?")) {
                        $query_export = $this->MakeLink(array(), "&amp;", true, "&amp;");
                    }
                    $export_item['url_export'] = $_FN['siteurl'] . $export['script'] . $query_export;
                    $tplvars['url_exports'][] = $export_item;
                }
            }
        }

        $tplvars['access_control_url'] = false;
        if (FN_IsAdmin() && $config['permissions_records_groups'] && $config['enable_permissions_each_records']) {

            $l = FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=admingroups");
            $tplvars['access_control_url'] = $l;
        }

        $tplvars['url_addnew'] = "";
        if ($this->CanAddRecord()) {
            $link = $this->MakeLink(array("op" => "new"), "&");
            $tplvars['url_addnew'] = $link;
        }
        $tplvars['html_footer'] = "";
        if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/grid_footer.php")) {
            include("{$_FN['src_application']}/sections/{$_FN['mod']}/grid_footer.php");
            $tplvars['html_footer'] .= ob_get_clean();
        }
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());
        }
        $searchform = array();
        $searchform = $this->GetSearchForm($search_orders, $tablename, $search_options, $search_min, $search_fields, $search_partfields);

        $tplvars = array_merge($tplvars, $searchform);

        $tplvars['url_offlineforminsert'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform");
        $res = $this->PrintList($results, $tplvars);
        if (isset($_GET['debug'])) {
            dprint_r(__FILE__ . " " . __LINE__ . " : " . FN_GetExecuteTimer());
        }

        return $res;
        //-----------------------pagina con i risultati----------------------------<
    }

    /**
     *
     * @global type $_FN
     * @param type $results
     * @param type $groups 
     */
    function Navigate($results, $groups)
    {
        global $_FN;
        $return = array();
        //--config-->
        $config = $this->config;
        $tablename = $config['tables'];
        //--config--<
        $gresults = array();
        $Table = FN_XMDBForm($tablename);

        //----foreign key ---->
        $i = 0;
        if (is_array($results))
            foreach ($results as $data) {
                //$data = $Table->xmltable->GetRecordByPrimaryKey($item[$Table->xmltable->primarykey]);
                foreach ($groups as $group) {
                    if (isset($Table->formvals[$group]['fk_show_field'])) {
                        $fs = $Table->formvals[$group]['fk_show_field'];
                    }
                    //echo "$group ";
                    if ($group != "" && isset($data[$group]))
                        $gresults[$group][$data[$group]] = isset($gresults[$group][$data[$group]]) ? $gresults[$group][$data[$group]] + 1 : 1;
                    $i++;
                }
            }
        //$return['gresults']=$gresults;
        $ret_groups = array();

        foreach ($gresults as $groupname => $group) {
            $fk = $Table->xmltable->fields[$groupname]->foreignkey;
            if (isset($Table->formvals[$groupname]['fk_link_field']))
                $pklink = $Table->formvals[$groupname]['fk_link_field'];
            else
                $pklink = "";
            $tablegroup = false;
            if ($fk != "" && file_exists("{$_FN['datadir']}/{$_FN['database']}/$fk.php")) {

                $tablegroup = FN_XMDBTable($fk); // xmetadb_table($_FN['database'], $fk, $_FN['datadir']);
            }
            $tplvars['filtertitle'] = $Table->formvals[$groupname]['title'];
            $tplvars['urlremovefilter'] = "";
            if (isset($_GET["nv_$groupname"])) {
                $link = $this->MakeLink(array("nv_$groupname" => null, "page" => 1));
                $tplvars['urlremovefilter'] = $link;
            } else {
                $tplvars['urlremovefilter'] = false;
            }
            $group2 = array();
            foreach ($group as $groupcontentsname => $groupcontentsnums) {
                $tmp['total'] = $groupcontentsnums;
                $tmp['name'] = $groupcontentsname;
                $group2[] = $tmp;
            }
            $group2 = FN_ArraySortByKey($group2, "name");
            foreach ($group2 as $group) {
                $groupcontentsnums = $group['total'];
                $groupcontentsname = $group['name'];
                if ($groupcontentsname == "")
                    $groupcontentstitle = FN_Translate("---");
                else {
                    if ($tablegroup && $pklink != "") {
                        $restr = array($pklink => $group['name']);
                        $t = $tablegroup->GetRecord($restr);
                        $ttitles = $groupname;
                        if (isset($Table->xmltable->fields[$groupname]->fk_show_field))
                            $ttitles = explode(",", $Table->xmltable->fields[$groupname]->fk_show_field);
                        $groupcontentstitle = "";
                        $sep = "";
                        foreach ($ttitles as $tt) {
                            if (isset($t[$tt]) && $t[$tt] != "") {
                                $groupcontentstitle .= $sep . $t[$tt];
                                $sep = " &bull; ";
                            }
                        }
                        if ($groupcontentstitle == "")
                            $groupcontentstitle = $group['name'];
                    } else
                        $groupcontentstitle = $group['name'];
                }

                $link = $this->MakeLink(array("nv_$groupname" => "$groupcontentsname", "page" => 1));
                $tplvars['urlfilteritem'] = $link;
                $tplvars['titleitem'] = $groupcontentstitle;
                $tplvars['counteritem'] = $groupcontentsnums;

                $ret_groups[$groupname]['groups'][$groupcontentsname] = $tplvars;
                foreach ($tplvars as $k => $v) {
                    $ret_groups[$groupname][$k] = $v;
                }
                //            $ret_groups[$groupname]['groups'][$group['name']]['items']=$tplvars;
                //$ret_groups[$groupname]['vals'][]=$tplvars;
                //array("group"=>$group,"vals"=>$tplvars);
            }
        }

        $return['filters'] = array();
        $return['filters'] = $ret_groups;
        return $return;
    }

    /**
     * 
     * @param string $tablename
     * @param string $res
     */
    function HtmlItem($tablename, $pk)
    {
        global $_FN;
        //--config-->
        $config = $this->config;
        $titles = explode(",", $config['titlefield']);
        //--config--<
        $tplvars = array();
        $Table = FN_XMDBForm($tablename);
        $data =array();
//        $data = $Table->GetRecordTranslatedByPrimarykey($pk, false);
        $data = $Table->xmltable->GetRecordByPrimaryKey($pk, false);
        //dprint_r("$tablename,$pk");
        //dprint_r($data);
        //-----image----------------------->
        $photo = isset($data[$config['image_titlefield']]) ? $Table->xmltable->getFilePath($data, $config['image_titlefield']) : "";
        $photo_fullsize = isset($data[$config['image_titlefield']]) ? $_FN['siteurl'] . $Table->xmltable->getFilePath($data, $config['image_titlefield']) : "";

        if ($photo != "") {
            //        $photo="{$_FN['datadir']}/fndatabase/{$tablename}/{$data[$Table->xmltable->primarykey]}/{$config['image_titlefield']}/{$data[$config['image_titlefield']]}";
        } elseif (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/default.png")) {
            $photo = "{$_FN['siteurl']}/sections/{$_FN['mod']}/default.png";
        } else
            $photo = FN_PathSite("{$_FN['src_finis']}/modules/dbview/default.png",false);
        if (empty($config['image_size']))
            $config['image_size'] = 200;
        $img = "{$_FN['siteurl']}index.php?fnapp=thumb&format=png&h={$config['image_size']}&w={$config['image_size_h']}&f=" . $photo;

        $counteritems = 0;
        //-----image-----------------------<
        $tplvars['item_urlview'] = $this->MakeLink(array("op" => "view", "id" => $pk), "&amp;");
        $tplvars['item_urledit'] = $this->MakeLink(array("op" => "edit", "id" => $pk), "&amp;");
        $tplvars['item_urldelete'] = $this->MakeLink(array("op" => "del", "id" => $pk), "&amp;");
        $tplvars['item_urlimage'] = $img;
        $tplvars['item_urlimage_fullsize'] = $photo_fullsize;

        $tplvars['url_offlineform'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform&id=$pk");
        $tplvars['url_offlineforminsert'] = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=offlineform");



        $dettlink = $this->MakeLink(array("op" => "view", "id" => $pk), "&amp;");

        //----title-------------------------------->
        $titlename = "";
        foreach ($titles as $titleitem)
            if (isset($data[$titleitem])) {
                if (!empty($Table->formvals[$titleitem]['fk_link_field'])) {
                    $titlename .= "{$data[$titleitem]}&nbsp;";
                } else {
                    $titlename .= "{$data[$titleitem]}&nbsp;";
                }
            } else {
                if (is_array($data))
                    foreach ($data as $tv) {
                        $titlename = $tv;
                        break;
                    }
                $titlename = isset($titlename[1]) ? $titlename[1] : "";
            }
        $tplvars['item_title'] = FN_FixEncoding($titlename);
        //----title--------------------------------<
        //-------------------------------valori----------------------------------->
        $row = $data;
        $t = FN_XMDBForm($tablename);
        $colsuffix = "1";
        $itemvalues = array();
        foreach ($Table->formvals as $fieldform_valuesk => $field) // $fieldform_valuesk=> $fieldform_values
        {

            if (isset($field['frm_showinlist']) && $field['frm_showinlist'] != 0)
                if (isset($row[$field['name']]) && $row[$field['name']] != "") {
                    $counteritems++;
                    $fieldform_values = $field;
                    $multilanguage = false;
                    $view_value = "";

                    //--------------get value from frm----------------------------->
                    $languagesfield = "";
                    if (isset($fieldform_values['frm_multilanguages']) && $fieldform_values['frm_multilanguages'] != "") {
                        $multilanguage = true;
                        $languagesfield = explode(",", $fieldform_values['frm_multilanguages']);
                    }
                    $fieldform_values['name'] = $fieldform_valuesk;
                    $fieldform_values['messages'] = $Table->messages;
                    $fieldform_values['value'] = XMETADB_FixEncoding($row[$fieldform_valuesk], $_FN['charset_page']);
                    $fieldform_values['values'] = $row;
                    $fieldform_values['fieldform'] = $Table;
                    $fieldform_values['oldvalues'] = $row;
                    $fieldform_values['oldvalues_primarikey'] = $pk;
                    $fieldform_values['multilanguage'] = $multilanguage;
                    $fieldform_values['lang_user'] = $_FN['lang'];
                    $fieldform_values['lang'] = $Table->lang;
                    $fieldform_values['languagesfield'] = $languagesfield;
                    $fieldform_values['frm_help'] = isset($fieldform_values['frm_help']) ? $fieldform_values['frm_help'] : "";
                    $row[$field['name']] = html_entity_decode($row[$field['name']]);

                    if (isset($fieldform_values['frm_functionview']) && $field['frm_functionview'] != "" && function_exists($field['frm_functionview'])) {
                        eval("\$view_value = " . $field['frm_functionview'] . '($data,$fieldform_valuesk);');
                        $showfield = false;
                    } else {
                        $fname = "xmetadb_frm_view_" . $field['frm_type'];
                        if (function_exists($fname)) {
                            $view_value = $fname($fieldform_values);
                        } elseif (method_exists($Table->formclass[$fieldform_valuesk], "view")) {
                            $view_value = $Table->formclass[$fieldform_valuesk]->view($fieldform_values);
                        } else {
                            $view_value = $data[$field['name']];
                        }
                    }
                    //--------------get value from frm-----------------------------<
                    $itemvalues[] = array("title" => $field['title'], "value" => $view_value, "fieldtype" => $field['frm_type'], "fieldname" => $fieldform_valuesk);
                    $tplvars['viewvalue_' . $field['name']] = $view_value;
                    $tplvars['title_' . $field['name']] = $field['title'];
                }
        }
        $tplvars['itemvalues'] = $itemvalues;
        //-------------------------------valori-----------------------------------<
        //-------------------------------footer----------------------------------->

        if ($this->IsAdminRecord($row, $tablename, $_FN['database'])) {
            if (empty($config['enable_delete'])) {
                $tplvars['item_urldelete'] = false;
            }
        } else {
            $tplvars['item_urldelete'] = false;
            $tplvars['item_urledit'] = false;
        }
        if (file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/pdf.php")) {
            $tplvars['url_pdf'] = "{$_FN['siteurl']}pdf.php?mod={$_FN['mod']}&amp;id=$pk";
        }
        $tplvars['counteritems'] = "$counteritems";
        $counteritems++;
        $tplvars['counteritems_1'] = "$counteritems";
        $counteritems++;
        $tplvars['counteritems_2'] = "$counteritems";
        $counteritems++;
        $tplvars['counteritems_3'] = "$counteritems";

        //-------------------------------footer-----------------------------------<

        return $tplvars;
    }

    // GetRecordValues(), GenOfflineUpdate(), GenOfflineInsert() sono ora nel trait FNDBVIEWExport
}
