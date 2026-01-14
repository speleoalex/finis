<?php
/**
 * FNDBVIEW Comments Trait
 * Comments management for dbview module
 *
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

trait FNDBVIEWComments
{
    /**
     * Get users who commented on a record
     * @param mixed $id_record
     * @return array|false
     */
    function GetUsersComments($id_record)
    {
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        $comments = FN_XMETADBQuery("SELECT DISTINCT username FROM {$tablename}_comments WHERE unirecidrecord LIKE '$id_record'");
        $ret = false;
        foreach ($comments as $comment) {
            $user = FN_GetUser($comment['username']);
            if (isset($user['email'])) {
                $ret[$user['email']] = $user;
            }
        }
        return $ret;
    }

    /**
     * Write a comment on a record
     * @param mixed $id_record
     * @return string HTML
     */
    function WriteComment($id_record)
    {
        global $_FN;
        $config = $this->config;
        $html = "";
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        $tablelinks = FN_XMDBForm("$tablename" . "_comments");
        $tablelinks->SetLayout("flat");
        $err = $newvalues = array();
        $exitlink = $this->MakeLink(array("op" => "view", "id" => $id_record), "&");
        $formlink = $this->MakeLink(array("op" => "writecomment", "id" => $id_record), "&");

        if (isset($_POST['comment'])) {
            $newvalues = $tablelinks->getbypost();
            $newvalues['comment'] = htmlspecialchars($newvalues['comment']);
            $newvalues['unirecidrecord'] = $id_record;
            $newvalues['username'] = $_FN['user'];
            $newvalues['insert'] = time();
            $err = $tablelinks->Verify($newvalues);

            if (count($err) == 0) {
                $tablelinks->xmltable->InsertRecord($newvalues);

                // Send email notification
                if (!empty($config['enable_comments_notify'])) {
                    $this->sendCommentNotifications($id_record, $newvalues, $tablename);
                }
            }
            $html .= FN_Translate("the message has been sent") . "<br />";
            $html .= "<button type=\"button\" class=\"button\" onclick=\"window.location='$exitlink'\" >" . FN_Translate("next") . "</button>";
            return $html;
        }

        if ($_FN['user'] != "" && $id_record != "") {
            $html .= "<br />";
            $html .= "\n<form method=\"post\" enctype=\"multipart/form-data\" action=\"$formlink\" >";
            $html .= "\n<table>";
            $html .= "\n<tr><td colspan=\"2\"><b>" . FN_Translate("add comment") . "</b></tr></td>";
            $html .= "\n<tr><td colspan=\"2\">" . FN_Translate("required fields") . "</tr></td>";
            $html .= "\n<tr><td colspan=\"2\">";
            $html .= FN_htmlBbcodesPanel("comment", "formatting");
            $html .= FN_htmlBbcodesPanel("comment", "emoticons");
            $html .= FN_htmlBbcodesJs();
            $html .= "<br />";
            $html .= $tablelinks->HtmlShowInsertForm(false, $newvalues, $err);
            $html .= "\n</td></tr>";
            $html .= "\n<tr><td colspan=\"2\"><input class=\"submit\" type=\"submit\" value=\"" . FN_Translate("save") . "\"/>";
            $html .= "<input type='button' class='button' onclick='window.location=(\"$exitlink\")'  value='" . FN_Translate("cancel") . "' />";
            $html .= "</tr></td>";
            $html .= "\n</table>";
            $html .= "\n</form>";
        }
        return $html;
    }

    /**
     * Send email notifications for comments
     * @param mixed $id_record
     * @param array $newvalues
     * @param string $tablename
     */
    protected function sendCommentNotifications($id_record, $newvalues, $tablename)
    {
        global $_FN;

        $Table = FN_XMDBForm($tablename);
        $row = $Table->xmltable->GetRecordByPrimarykey($id_record);
        $uservalues = FN_GetUser($newvalues['username']);
        $rname = $row[$Table->xmltable->primarykey];

        if (isset($row['name'])) {
            $rname = $row['name'];
        } else {
            foreach ($Table->xmltable->fields as $gk => $g) {
                if (!isset($g->frm_show) || $g->frm_show != 0) {
                    $rname = $row[$gk];
                    break;
                }
            }
        }

        $usercomments = $this->GetUsersComments($id_record);
        if (!empty($uservalues['email'])) {
            $usercomments[$uservalues['email']] = $uservalues;
        }

        $userlang = $_FN['lang_default'];
        $usersended = array();

        // Email to comment owners
        foreach ($usercomments as $usercomment) {
            if (isset($usercomment['lang']))
                $userlang = $usercomment['lang'];

            if ($uservalues['email'] == $usercomment['email']) {
                $body = $_FN['user'] . " " . FN_Translate("added a comment to your content", "aa");
            } else {
                $body = $_FN['user'] . " " . FN_Translate("added a comment", "aa");
            }

            $body .= "<br /><br />$rname<br /><br />" . FN_Translate("to see the comments go to this address", "aa", $userlang);
            $link = FN_RewriteLink("index.php?mod={$_FN['mod']}&op=view&id=$id_record", "&", true);
            $body .= "<br /><a href=\"$link\">$link</a><br /><br />";

            if (!isset($usersended[$usercomment['email']])) {
                FN_SendMail($usercomment['email'], $_FN['sitename'] . "-" . $_FN['sectionvalues']['title'], $body, true);
            }
            $usersended[$usercomment['email']] = $usercomment['email'];
        }

        // Email to record owner
        $MyTable = FN_XMDBForm($tablename);
        $Myrow = $MyTable->xmltable->GetRecordByPrimaryKey($id_record);
        $Myuser_record = FN_GetUser($Myrow['username']);
        if (!isset($usersended[$Myuser_record['email']])) {
            FN_SendMail($Myuser_record['email'], $_FN['sitename'] . "-" . $_FN['sectionvalues']['title'], $body, true);
        }
    }

    /**
     * Delete a comment
     * @param mixed $id_record
     * @return string HTML
     */
    function DelComment($id_record)
    {
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        $html = "";
        $tablelinks = FN_XMDBForm("$tablename" . "_comments");

        if (FN_IsAdmin() && isset($_GET['unirecidrecord']) && $_GET['unirecidrecord'] != "") {
            $r['id'] = $_GET['unirecidrecord'];
            $tablelinks->xmltable->DelRecord($r['id']);
            $html .= FN_Translate("the comment was deleted") . "<br />";
            FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename delete comments in record $id_record");

            $Table = FN_XMDBForm($_FN['database']);
            $newvalues = $Table->xmltable->GetRecordByPrimaryKey($id_record);
            $newvalues['update'] = time();
            $Table->xmltable->UpdateRecord($newvalues);
        }
        return $html;
    }
}
