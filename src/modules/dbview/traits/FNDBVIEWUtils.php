<?php
/**
 * FNDBVIEW Utils Trait
 * Various utilities for dbview module
 *
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

trait FNDBVIEWUtils
{
    /**
     * Invalidate table cache
     * @param string $tablename
     */
    function InvalidateCache($tablename)
    {
        global $_FN;
        $cache_id = "dbview_table_" . $tablename;
        $filename = "{$_FN['datadir']}/_cache/" . md5($cache_id) . ".cache";
        if (file_exists($filename)) {
            @unlink($filename);
        }
        $filename_time = "{$_FN['datadir']}/_cache/" . md5($cache_id . "_time") . ".cache";
        if (file_exists($filename_time)) {
            @unlink($filename_time);
        }
    }

    /**
     * Generate link with parameters
     * @param array $params
     * @param string $sep
     * @param bool $norewrite
     * @param int $onlyquery
     * @return string
     */
    function MakeLink($params = false, $sep = "&amp;", $norewrite = false, $onlyquery = 0)
    {
        global $_FN;

        $blank = "____k_____";
        $register = array("mod", "op", "q", "page", "order", "desc", "nav", "rule", "viewmode");

        $config = $this->config;
        $tmp = explode(",", $config['search_min']);
        foreach ($tmp as $key) {
            $register[] = "min_" . $key;
        }
        $tmp = explode(",", $config['search_fields']);
        foreach ($tmp as $key) {
            $register[] = "sfield_" . $key;
        }
        $tmp = explode(",", $config['search_partfields']);
        foreach ($tmp as $key) {
            $register[] = "spfield_" . $key;
        }

        $link = array();
        foreach ($_REQUEST as $key => $value) {
            if (in_array($key, $register) || fn_erg("^s_opt_", $key) || fn_erg("^mint_", $key) || fn_erg("^nv_", $key)) {
                $link[$key] = "$key=" . FN_GetParam("$key", $_REQUEST);
            }
        }

        if (is_array($params)) {
            foreach ($params as $key => $value) {
                if ($params[$key] === null)
                    unset($link[$key]);
                elseif ($params[$key] === "")
                    $link[$key] = "$key=$blank";
                else
                    $link[$key] = "$key=" . urlencode($params[$key]);
            }
        }

        if ($onlyquery) {
            if (is_string($onlyquery) && $onlyquery != 1 && $onlyquery != "1") {
                return "$onlyquery" . implode($sep, $link);
            }
            return "?" . implode($sep, $link);
        }

        $link = "index.php?" . implode($sep, $link);
        if ($norewrite)
            return $_FN['siteurl'] . str_replace($blank, "", $link);

        $link = str_replace($blank, "", $link);
        $link = FN_RewriteLink($link, $sep, true);
        return $link;
    }

    /**
     * Remove dangerous HTML tags
     * @param string $text
     * @param string $blacklist
     * @return string
     */
    function SecureHtml($text, $blacklist = "script,iframe,frame,object,embed")
    {
        $blacklist = explode(",", $blacklist);
        $ok = false;
        while ($ok == false) {
            $ok = true;
            foreach ($blacklist as $itemtag) {
                while (preg_match("/<$itemtag/s", $text)) {
                    $ok = false;
                    $text = preg_replace("/<$itemtag/s", "", $text);
                    $text = preg_replace("/<\\/$itemtag>/s", "", $text);
                }
            }
        }
        return $text;
    }

    /**
     * Handle file download
     * @param string $file
     */
    function GoDownload($file)
    {
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        // Prevent access to external directories
        if (stristr($file, ".."))
            die(FN_Translate("you may not do that"));

        if ($config['enablestats'] == 1) {
            $this->trackDownload($file, $tablename);
        }

        FN_SaveFile("{$_FN['datadir']}/{$_FN['database']}/$tablename/$file");
    }

    /**
     * Track download statistics
     * @param string $file
     * @param string $tablename
     */
    protected function trackDownload($file, $tablename)
    {
        global $_FN;

        if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/$tablename" . "_download_stat") ||
            !file_exists("{$_FN['datadir']}/{$_FN['database']}/$tablename" . "_stat.php")) {
            $sfields = array();
            $sfields[1]['name'] = "filename";
            $sfields[1]['primarykey'] = "1";
            $sfields[2]['name'] = "numdownload";
            $sfields[2]['defaultvalue'] = "0";
            XMETATable::createMetadbTable($_FN['database'], $tablename . "_download_stat", $sfields, $pathdatabase);
        }

        $stat = FN_XMDBTable($tablename . "_download_stat");
        $oldval = $stat->GetRecordByPrimaryKey($file);
        $r['filename'] = $file;

        if ($oldval == null) {
            $r['numdownload'] = 1;
            $stat->InsertRecord($r);
        } else {
            $r['numdownload'] = $oldval['numdownload'] + 1;
            $stat->UpdateRecord($r);
        }
    }

    /**
     * Handle content modification request
     * @param mixed $id_record
     * @return string HTML
     */
    function Request($id_record)
    {
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        $html = "";
        if ($_FN['user'] == "") {
            FN_JsRedirect(FN_RewriteLink("index.php?mod={$_FN['mod']}&op=view&id=$id_record"));
            return "";
        }

        if (isset($_POST['message'])) {
            $html .= $this->processRequest($id_record, $tablename);
        } else {
            $html .= $this->showRequestForm($id_record);
        }

        $link = $this->MakeLink(array("op" => null), "&");
        $html .= "<br /><br /><button onclick=\"window.location='$link'\">&larr; &nbsp;" . FN_Translate("go to the contents list") . "</button>";
        return $html;
    }

    /**
     * Process modification request
     * @param mixed $id_record
     * @param string $tablename
     * @return string HTML
     */
    protected function processRequest($id_record, $tablename)
    {
        global $_FN;
        $html = "";

        $Table = FN_XMDBForm($tablename);
        $row = $Table->xmltable->GetRecordByPrimaryKey($id_record);

        if (!empty($row['username'])) {
            $user_record = FN_GetUser($row['username']);
        } else {
            $user_record = array("username" => "");
        }

        $Table = FN_XMDBTable($tablename);
        $rname = $row[$Table->primarykey];

        if (isset($row['name'])) {
            $rname = $row['name'];
        } else {
            foreach ($Table->fields as $gk => $g) {
                if (!isset($g->frm_show) || $g->frm_show != 0) {
                    $rname = $row[$gk];
                    break;
                }
            }
        }

        $user = FN_GetUser($user_record['username']);

        $subject = "[{$_FN['sitename']}] " . $rname;
        $message = $_FN['user'] . " " . FN_Translate("has requested to modify this content", "aa") . " \"" . $rname . "\"\n\n\n";
        $message .= FN_Translate("to allow editing do login", "aa") . " " . $_FN['siteurl'] . "index.php?mod=login\n";
        $message .= FN_Translate("and login as user", "aa") . ": \"" . $user_record['username'] . "\"\n\n\n";
        $message .= FN_Translate("go to edit this content or log in", "aa") . " :\n" . $_FN['siteurl'] . "index.php?mod={$_FN['mod']}&op=edit&id=$id_record\n";
        $message .= FN_Translate("then click on -user allowed to edit- and manage the permissions", "aa") . " " . "\"{$_FN['user']}\"";
        $message .= "\n\n----------------------\n";
        $message .= "\n" . FN_StripPostSlashes($_POST['message']);

        if (!empty($user['email']) && FN_SendMail($user['email'], $subject, $message)) {
            $html .= "<br />" . FN_Translate("request sent") . "<br />";
        } else {
            $html .= "<br />" . FN_Translate("you can not send your request, please contact the administrator of the website") . "<br />";
        }

        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||request " . $rname . " in table $tablename.");

        return $html;
    }

    /**
     * Show modification request form
     * @param mixed $id_record
     * @return string HTML
     */
    protected function showRequestForm($id_record)
    {
        global $_FN;
        $html = "";

        $html .= FN_Translate("the creator of the object will be contacted to request you to be allowed. You can add comments in the box below.") . "<br />";
        $html .= "<form method=\"post\" action=\"index.php?mod={$_FN['mod']}&amp;op=request&amp;id=$id_record\">";
        $html .= "<textarea name=\"message\" cols=\"60\" rows=\"5\"></textarea><br />";
        $html .= "<input type=\"submit\"  name=\"send\" value=\"" . FN_Translate("demand modification") . "\" class=\"submit\" />";

        $link = $this->MakeLink(array("op" => null), "&");
        $html .= "\n<input type=\"button\" onclick=\"window.location='$link'\" class=\"button\" value=\"" . FN_Translate("cancel") . "\" />";
        $html .= "</form>";

        return $html;
    }

    /**
     * Get record rank
     * @param mixed $id
     * @param int $n (output)
     * @param string $tablename
     * @return int
     */
    function GetRank($id, &$n, $tablename)
    {
        global $_FN;
        $config = $this->config;

        if ($tablename == "") {
            $tables = explode(",", $config['tables']);
            $tablename = $tables[0];
        }

        $table = FN_XMDBTable($tablename . "_ranks");
        $res = $table->GetRecords(array("unirecidrecord" => "$id"));
        $total = 0;

        if (!is_array($res))
            $res = array();

        $n = count($res);
        if ($n == 0)
            return -1;

        foreach ($res as $r) {
            $total += $r['rank'];
        }

        $m = round(($total / $n), 0);
        return $m;
    }

    /**
     * Set record rank
     * @param mixed $id
     * @param int $rank
     * @param string $tablename
     */
    function SetRank($id, $rank, $tablename)
    {
        global $_FN;
        $config = $this->config;

        if ($tablename == "") {
            $tables = explode(",", $config['tables']);
            $tablename = $tables[0];
        }

        $table = FN_XMDBTable($tablename . "_ranks");

        $r = array();
        $r['unirecidrecord'] = $id;
        $r['rank'] = $rank;
        $r['username'] = $_FN['user'];
        $r['ip'] = $_SERVER['REMOTE_ADDR'];

        // Check if vote already exists
        $existing = $table->GetRecords(array(
            "unirecidrecord" => $id,
            "username" => $_FN['user']
        ));

        if (empty($existing)) {
            $table->InsertRecord($r);
        }
    }
}
