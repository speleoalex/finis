<?php

/**
 * Event log: the fn_log table (FN_Log, FN_LogEvent) with filters, and the monthly files
 * misc/log/*.php written by the versions before the table, when still present
 *
 * @package Finis_controlcenter
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2026
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
global $_FN;
$opt = FN_GetParam("opt", $_GET, "html");
$log = basename(FN_GetParam("log", $_GET, "flat"));
$logdir = "{$_FN['datadir']}/log";

if ($log != "" && preg_match('/^[0-9]{4}-[0-9]{2}-log\.php$/', $log) && file_exists("$logdir/$log"))
{
    FNLOG_PrintArchiveFile("$logdir/$log", "?fnapp=controlcenter&amp;opt=$opt");
    return;
}

//-----------------------------the fn_log table-------------------------------->
$tablename = "fn_log";
FN_Install("misc/fndatabase/fn_log.php");
$params = array();
$params['enableview'] = true;
$params['enabledelete'] = false;
$params['enablenew'] = false;
$params['enableedit'] = false;
$params['defaultorder'] = "date";
$params['defaultorderdesc'] = true;
$params['recordsperpage'] = 50;
$params['fields_filters'] = array("context", "user", "event%", "date><");
$params['link'] = "fnapp=controlcenter&opt=$opt&amp;mod={$_FN['mod']}";
FNCC_XMETATableEditor($tablename, $params);
//-----------------------------the fn_log table--------------------------------<

//-----------------------------the monthly files------------------------------->
$archives = glob("$logdir/*-log.php");
if ($archives)
{
    rsort($archives);
    echo "<div class=\"card\"><div class=\"card-header\"><h5 class=\"card-title mb-0\">" . FN_Translate("archive", "Aa") . "</h5></div>";
    echo "<div class=\"card-body\">";
    foreach ($archives as $archive)
    {
        $filename = basename($archive);
        echo "<a class=\"btn btn-outline-secondary btn-sm m-1\" href=\"?fnapp=controlcenter&amp;opt=$opt&amp;log=$filename\">&#x1F4C5; " . substr($filename, 0, 7) . "</a>";
    }
    echo "</div></div>";
}
//-----------------------------the monthly files-------------------------------<

/**
 * a monthly file of the old log: one event per line, fields separated by ";"
 *
 * @param string $file
 * @param string $urlback
 */
function FNLOG_PrintArchiveFile($file, $urlback)
{
    $rows = file($file);
    unset($rows[0]); // the first line is the php exit() that protects the file
    $headers = array("date", "ip", "file", "section", "username", "event");
    echo "<div class=\"card\"><div class=\"card-header\">";
    echo "<a class=\"btn btn-primary btn-sm\" href=\"$urlback\">&larr; " . FN_Translate("back", "Aa") . "</a> ";
    echo "<span class=\"ms-2\">" . FN_Translate("archive", "Aa") . ": " . substr(basename($file), 0, 7) . "</span>";
    echo "</div><div class=\"card-body\"><div class=\"table-responsive\">";
    echo "<table class=\"table table-striped table-hover table-sm\"><thead class=\"bg-primary\"><tr>";
    foreach ($headers as $header)
    {
        echo "<th class=\"text-light\">" . FN_Translate($header, "Aa") . "</th>";
    }
    echo "</tr></thead><tbody>";
    foreach ($rows as $row)
    {
        $row = trim($row);
        if ($row == "")
            continue;
        echo "<tr>";
        foreach (str_getcsv($row, ";", "\"", "\\") as $i => $item)
        {
            if ($i >= count($headers))
                break;
            echo "<td>" . htmlspecialchars($item, ENT_QUOTES) . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table></div></div></div>";
}
