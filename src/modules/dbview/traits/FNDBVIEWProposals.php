<?php
/**
 * FNDBVIEW Proposals Trait
 * Option enable_proposals: a logged-in user who can view a record but not edit it
 * may propose a change. The proposal is stored in dbview_proposals (one table for
 * all the sections) and never touches the record: the users who can edit the record
 * get a site notification (and an e-mail with proposals_mail) and, on the record
 * page, accept all the fields, some of them or none.
 * Only the data fields of the record: files and linked tables are left out.
 *
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

trait FNDBVIEWProposals
{
    /**
     * Creates the descriptor of dbview_proposals if missing
     */
    function InitProposalsTable()
    {
        global $_FN;
        // installed here and not only by FN_AddNotification: the theme opens fn_notifications
        // before the section runs, and a table opened before its descriptor exists stays
        // unusable for the whole request (the first notification would be lost)
        FN_Install("misc/fndatabase/fn_notifications.php");
        if (file_exists("{$_FN['datadir']}/{$_FN['database']}/dbview_proposals.php"))
            return;
        $fields = array(
            array("name" => "id", "type" => "int", "primarykey" => "1", "extra" => "autoincrement"),
            array("name" => "tablename", "type" => "varchar"),
            array("name" => "table_unirecid", "type" => "varchar"),
            array("name" => "username", "type" => "varchar"),
            array("name" => "recordinsert", "type" => "varchar"),
            // JSON {field: {"old": stored value, "new": value in form format}}
            array("name" => "changes", "type" => "text"),
            // pending, accepted, partial, rejected, withdrawn
            array("name" => "status", "type" => "varchar"),
            array("name" => "acceptedfields", "type" => "text"),
            array("name" => "userreview", "type" => "varchar"),
            array("name" => "recordreview", "type" => "varchar"),
        );
        XMETATable::createMetadbTable($_FN['database'], "dbview_proposals", $fields, $_FN['datadir']);
    }

    /**
     * Main table of the section
     * @return string
     */
    protected function ProposalsTableName()
    {
        $tables = explode(",", $this->config['tables']);
        return $tables[0];
    }

    /**
     * True for a logged-in user who can view the record and cannot edit it
     * (who can edit it changes it directly)
     * @param array $row
     * @return bool
     */
    function CanProposeRecord($row)
    {
        global $_FN;
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "" || !is_array($row) || count($row) == 0)
            return false;
        $tablename = $this->ProposalsTableName();
        $pk = FN_XMDBTable($tablename)->primarykey;
        if (!isset($row[$pk]) || !$this->CanViewRecord($row[$pk], $tablename))
            return false;
        return !$this->CanUpdateRecord($row);
    }

    /**
     * Fields a proposal may change: the visible data fields, without primary key,
     * files, admin-only, read-only, system fields and the fields of proposals_exclude_fields
     * @param object $Table xmetadb_frm
     * @return array field names
     */
    function ProposalFields($Table)
    {
        $system = array("username", "userupdate", "recordinsert", "recordupdate", "recorddeleted",
            "groupview", "groupinsert", "view", "insert", "update");
        $excluded = array_map("trim", explode(",", isset($this->config['proposals_exclude_fields']) ? $this->config['proposals_exclude_fields'] : ""));
        $fields = array();
        foreach ($Table->formvals as $name => $field) {
            if (!empty($field['primarykey']) || in_array($name, $system, true) || in_array($name, $excluded, true) || xmetadb_is_file_field($field))
                continue;
            if (isset($field['frm_show']) && ($field['frm_show'] === "0" || $field['frm_show'] === 0 || $field['frm_show'] === false))
                continue;
            if (!empty($field['frm_setonlyadmin']) || !empty($field['frm_readonly']))
                continue;
            if (isset($field['frm_allowupdate']) && ($field['frm_allowupdate'] === "onlyadmin" || (string) $field['frm_allowupdate'] === "0"))
                continue;
            $fields[] = $name;
        }
        return $fields;
    }

    /**
     * Hides in the form the fields a proposal may not change
     * @param object $Table xmetadb_frm
     */
    function HideNonProposalFields($Table)
    {
        $allowed = $this->ProposalFields($Table);
        foreach ($Table->formvals as $name => $field) {
            if (!in_array($name, $allowed, true))
                $Table->formvals[$name]['frm_show'] = 0;
        }
    }

    /**
     * Pending proposals of a record, oldest first
     * @param string $tablename
     * @param string $id_record
     * @param string $username only the proposals of this user
     * @return array
     */
    protected function PendingProposals($tablename, $id_record, $username = "")
    {
        global $_FN;
        if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/dbview_proposals.php"))
            return array();
        $restr = array("tablename" => $tablename, "table_unirecid" => $id_record, "status" => "pending");
        if ($username != "")
            $restr['username'] = $username;
        $rows = FN_XMDBTable("dbview_proposals")->GetRecords($restr);
        if (!is_array($rows))
            return array();
        // the driver may match without case or type: check again
        $ret = array();
        foreach ($rows as $r) {
            if ($r['tablename'] === $tablename && (string) $r['table_unirecid'] === (string) $id_record
                && $r['status'] === "pending" && ($username == "" || $r['username'] === $username))
                $ret[] = $r;
        }
        usort($ret, function ($a, $b) {
            return intval($a['id']) - intval($b['id']);
        });
        return $ret;
    }

    /**
     * Changes of a proposal
     * @param array $proposal
     * @return array field => array(old, new)
     */
    protected function ProposalChanges($proposal)
    {
        $changes = json_decode((string) $proposal['changes'], true);
        return is_array($changes) ? $changes : array();
    }

    /**
     * The record with the pending proposal of the current user applied: the form of a
     * new proposal starts from the previous one
     * @param array $row
     * @param string $tablename
     * @return array
     */
    function PendingProposalValues($row, $tablename)
    {
        global $_FN;
        $pk = FN_XMDBTable($tablename)->primarykey;
        foreach ($this->PendingProposals($tablename, $row[$pk], $_FN['user']) as $proposal) {
            foreach ($this->ProposalChanges($proposal) as $field => $change)
                $row[$field] = $change['new'];
        }
        return $row;
    }

    /**
     * Saves the change proposed with the edit form (op=propose)
     * @param object $Table
     * @return string HTML
     */
    function SaveProposal($Table)
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $pk = $Table->xmltable->primarykey;
        $id_record = FN_GetParam("_xmetadbform_pk_$pk", $_POST);
        $row = $Table->xmltable->GetRecordByPrimaryKey($id_record);
        if (!is_array($row) || !$this->CanProposeRecord($row))
            return FN_Translate("you may not do that");

        $newvalues = $this->applyFieldTransformations($Table->getbypost(), $Table);
        $changes = array();
        $proposed = array();
        foreach ($this->ProposalFields($Table) as $field) {
            if (!array_key_exists($field, $newvalues))
                continue;
            $new = (string) $newvalues[$field];
            // compared in storage format, kept in form format (applied with xmetadb_frm::UpdateRecord)
            $stored = $new;
            if (isset($Table->formclass[$field]) && method_exists($Table->formclass[$field], "formtovalue"))
                $stored = (string) $Table->formclass[$field]->formtovalue($new, $Table->formvals[$field]);
            $old = isset($row[$field]) ? (string) $row[$field] : "";
            // browsers send CRLF, records may hold LF: not a change
            if (str_replace("\r\n", "\n", $stored) !== str_replace("\r\n", "\n", $old)) {
                $changes[$field] = array("old" => $old, "new" => $new);
                $proposed[$field] = $new;
            }
        }
        $errors = $Table->VerifyUpdate(array_merge($row, $proposed), $id_record);
        if (count($errors) > 0)
            return $this->EditRecordForm($id_record, $Table, $errors, false, true);

        $proposals = FN_XMDBTable("dbview_proposals");
        $pending = $this->PendingProposals($tablename, $id_record, $_FN['user']);
        if (count($changes) == 0) {
            foreach ($pending as $p)
                $proposals->UpdateRecord(array("id" => $p['id'], "status" => "withdrawn"));
            return FN_HtmlAlert(FN_Translate("no changes compared to the record")) . $this->ViewRecordPage($id_record);
        }
        $values = array(
            "changes" => json_encode($changes, JSON_UNESCAPED_UNICODE),
            "recordinsert" => XMETATable::now(),
        );
        if (count($pending) > 0) {
            $values['id'] = $pending[0]['id'];
            $proposals->UpdateRecord($values);
        } else {
            $values['tablename'] = $tablename;
            $values['table_unirecid'] = $id_record;
            $values['username'] = $_FN['user'];
            $values['status'] = "pending";
            $proposals->InsertRecord($values);
        }
        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename change proposed for $id_record.");

        $title = $this->ProposalRecordTitle($row);
        $text = FN_Translate("change proposed by") . " {$_FN['user']}: {$_FN['sectionvalues']['title']} - $title";
        $body = $text . "\n" . FN_Translate("fields") . ": " . implode(", ", $this->ProposalFieldTitles($Table, array_keys($changes)));
        $this->ProposalNotify($this->ProposalReviewers($row), $text, $body, $id_record, true);

        return FN_HtmlAlert(FN_Translate("your proposal has been sent to the users who manage this record")) . $this->ViewRecordPage($id_record);
    }

    /**
     * Accepts the selected fields of a proposal or discards it (op=proposal, POST only)
     * @param string $id_record
     * @param object $Table
     * @return string HTML
     */
    function ReviewProposal($id_record, $Table)
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $pk = $Table->xmltable->primarykey;
        $row = $Table->xmltable->GetRecordByPrimaryKey($id_record);
        $proposalid = FN_GetParam("proposal", $_POST);
        $isPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');
        if (!$isPost || empty($this->config['enable_proposals']) || !is_array($row) || !$this->CanUpdateRecord($row))
            return FN_Translate("you may not do that");
        if (!isset($_POST['fn_csrf']) || !hash_equals($this->CsrfToken("proposal|$tablename|$proposalid"), (string) $_POST['fn_csrf']))
            return FN_Translate("you may not do that");
        $proposal = null;
        foreach ($this->PendingProposals($tablename, $id_record) as $p) {
            if ((string) $p['id'] === (string) $proposalid)
                $proposal = $p;
        }
        if (!$proposal)
            return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->ViewRecordPage($id_record);

        $changes = $this->ProposalChanges($proposal);
        $accepted = array();
        // not "fields": dbview reads that request parameter for the result list
        if (FN_GetParam("action", $_POST) === "accept" && isset($_POST['proposal_fields']) && is_array($_POST['proposal_fields'])) {
            $allowed = $this->ProposalFields($Table);
            foreach ($changes as $field => $change) {
                if (in_array($field, $_POST['proposal_fields'], true) && in_array($field, $allowed, true))
                    $accepted[$field] = $change['new'];
            }
        }
        if (count($accepted) > 0) {
            $newvalues = $accepted;
            $newvalues[$pk] = $id_record;
            $errors = $Table->VerifyUpdate(array_merge($row, $newvalues), $id_record);
            if (count($errors) > 0) {
                $msg = FN_Translate("the proposal cannot be accepted") . ":";
                foreach ($errors as $error)
                    $msg .= "<br />" . htmlspecialchars($error['title'] . ": " . $error['error']);
                return FN_HtmlAlert($msg) . $this->ViewRecordPage($id_record);
            }
            $newvalues['userupdate'] = $_FN['user'];
            $newvalues = $this->saveRecordChanges($Table, $tablename, $row, $newvalues, $id_record);
            $this->runUpdateHook(array_merge($row, $newvalues));
        }
        $status = count($accepted) == 0 ? "rejected" : (count($accepted) == count($changes) ? "accepted" : "partial");
        FN_XMDBTable("dbview_proposals")->UpdateRecord(array(
            "id" => $proposal['id'],
            "status" => $status,
            "acceptedfields" => implode(",", array_keys($accepted)),
            "userreview" => $_FN['user'],
            "recordreview" => XMETATable::now(),
        ));
        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename proposal {$proposal['id']} $status.");

        $messages = array(
            "accepted" => "your proposed change has been accepted",
            "partial" => "your proposed change has been partially accepted",
            "rejected" => "your proposed change has been discarded",
        );
        $text = FN_Translate($messages[$status]) . ": {$_FN['sectionvalues']['title']} - " . $this->ProposalRecordTitle($row);
        $body = $text;
        if (count($accepted) > 0)
            $body .= "\n" . FN_Translate("accepted fields") . ": " . implode(", ", $this->ProposalFieldTitles($Table, array_keys($accepted)));
        $rejected = array_diff(array_keys($changes), array_keys($accepted));
        if (count($rejected) > 0)
            $body .= "\n" . FN_Translate("discarded fields") . ": " . implode(", ", $this->ProposalFieldTitles($Table, $rejected));
        $body .= "\n" . FN_Translate("reviewed by") . " {$_FN['user']}";
        $this->ProposalNotify(array($proposal['username']), $text, $body, $id_record, false);

        $done = array(
            "accepted" => "proposal accepted",
            "partial" => "proposal partially accepted",
            "rejected" => "proposal discarded",
        );
        return FN_HtmlAlert(FN_Translate($done[$status])) . $this->ViewRecordPage($id_record);
    }

    /**
     * Users who receive the proposals of a record: creator, users enabled on the
     * record, section administrators and members of the groups that can edit it.
     * With no group that can edit (any logged-in user) only creator, enabled users
     * and administrators; the site administrators when nobody else is left.
     * @param array $row
     * @return array usernames
     */
    function ProposalReviewers($row)
    {
        global $_FN;
        $config = $this->config;
        $tablename = $this->ProposalsTableName();
        $users = array();
        if (!empty($row['username']))
            $users[] = $row['username'];
        foreach ($this->GetFieldUserList($row, $tablename, false) as $r)
            $users[] = $r['username'];
        $groups = $config['groupadmin'];
        if (!empty($config['enable_permissions_edit_each_records']) && !empty($row['groupinsert']))
            $groups .= "," . $row['groupinsert'];
        elseif ($config['groupinsert'] != "")
            $groups .= "," . $config['groupinsert'];
        $groups = array_filter(explode(",", $groups));
        $allusers = FN_GetUsers();
        if (!is_array($allusers))
            $allusers = array();
        if (count($groups) > 0) {
            foreach ($allusers as $u) {
                if (count(array_intersect($groups, explode(",", (string) $u['group']))) > 0)
                    $users[] = $u['username'];
            }
        }
        $users = array_values(array_diff(array_unique(array_filter($users)), array($_FN['user'])));
        if (count($users) == 0) {
            foreach ($allusers as $u) {
                if (intval($u['level']) >= 10 && $u['username'] != $_FN['user'])
                    $users[] = $u['username'];
            }
        }
        return $users;
    }

    /**
     * Site notification to the users and, with proposals_mail, an e-mail
     * @param array $users
     * @param string $text short text (notification and mail subject)
     * @param string $body mail text
     * @param string $id_record
     * @param bool $mailalert true: the mail goes to mailalert of the section too
     */
    protected function ProposalNotify($users, $text, $body, $id_record, $mailalert)
    {
        global $_FN;
        $link = $this->ProposalRecordLink($id_record);
        FN_AddNotification(array("context" => "dbview_proposals", "text" => $text, "link" => $link), $users);
        if (empty($this->config['proposals_mail']))
            return;
        $emails = array();
        foreach ($users as $user) {
            $values = FN_GetUser($user);
            if (!empty($values['email']))
                $emails[] = $values['email'];
        }
        if ($mailalert && !empty($this->config['mailalert']))
            $emails[] = $this->config['mailalert'];
        $body .= "\n\n" . FN_Translate("to view go to the address") . ":\n$link\n\n" . $_FN['sitename'];
        foreach (array_unique($emails) as $email)
            FN_SendMail($email, $text, $body, false);
    }

    /**
     * Absolute address of the record page, used by notifications and mails
     * @param string $id_record
     * @return string
     */
    protected function ProposalRecordLink($id_record)
    {
        global $_FN;
        return rtrim($_FN['siteurl'], "/") . "/index.php?mod=" . urlencode($_FN['mod']) . "&op=view&id=" . urlencode($id_record);
    }

    /**
     * @param array $row
     * @return string title of the record (titlefield of the section)
     */
    protected function ProposalRecordTitle($row)
    {
        $title = array();
        foreach (explode(",", $this->config['titlefield']) as $field) {
            if (!empty($row[$field]))
                $title[] = $row[$field];
        }
        return implode(" ", $title);
    }

    /**
     * @param object $Table
     * @param array $fields
     * @return array titles of the fields
     */
    protected function ProposalFieldTitles($Table, $fields)
    {
        $titles = array();
        foreach ($fields as $field)
            $titles[] = isset($Table->formvals[$field]['title']) ? $Table->formvals[$field]['title'] : $field;
        return $titles;
    }

    /**
     * Value of a field as safe HTML: option titles instead of keys, text without tags.
     * Braces are encoded so that the template engine does not read them as variables.
     * @param object $Table
     * @param string $field
     * @param string $value
     * @return string
     */
    protected function ProposalDisplayValue($Table, $field, $value)
    {
        $value = (string) $value;
        if ($value === "")
            return "<em>" . FN_Translate("empty") . "</em>";
        if (!empty($Table->formvals[$field]['options']) && is_array($Table->formvals[$field]['options'])) {
            $titles = array();
            foreach (explode(",", $value) as $item) {
                $title = $item;
                foreach ($Table->formvals[$field]['options'] as $option) {
                    if (isset($option['value']) && (string) $option['value'] === $item)
                        $title = $option['title'];
                }
                $titles[] = $title;
            }
            $value = implode(", ", $titles);
        }
        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES, "UTF-8"));
        return str_replace(array("{", "}"), array("&#123;", "&#125;"), nl2br(htmlspecialchars($text, ENT_QUOTES, "UTF-8")));
    }

    /**
     * Notice on top of the record page: the pending proposals with the review form
     * for the users who can edit the record, one line for the author of a proposal
     * @param array $row
     * @param object $Table
     * @return string HTML
     */
    function ProposalsNotice($row, $Table)
    {
        global $_FN;
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "" || !is_array($row))
            return "";
        $tablename = $this->ProposalsTableName();
        $pk = $Table->xmltable->primarykey;
        if ($Table->xmltable->tablename != $tablename || !isset($row[$pk]))
            return "";
        $id_record = $row[$pk];
        if (!$this->CanUpdateRecord($row)) {
            $mine = $this->PendingProposals($tablename, $id_record, $_FN['user']);
            if (count($mine) == 0)
                return "";
            $link = $this->MakeLink(array("op" => "propose", "id" => $id_record), "&amp;");
            return "<div class=\"alert alert-info\" role=\"status\">" . FN_Translate("your proposed change is waiting for review") . " (" . htmlspecialchars(FN_FormatDate($mine[0]['recordinsert'])) . "). <a href=\"$link\">" . FN_Translate("modify the proposal") . "</a></div>";
        }
        $pending = $this->PendingProposals($tablename, $id_record);
        if (count($pending) == 0)
            return "";
        $this->ProposalNotificationsDisplayed($id_record);
        $action = $this->MakeLink(array("op" => "proposal", "id" => $id_record), "&amp;");
        $items = array();
        foreach ($pending as $proposal) {
            $fields = array();
            foreach ($this->ProposalChanges($proposal) as $field => $change) {
                $current = isset($row[$field]) ? (string) $row[$field] : "";
                $fields[] = array(
                    "name" => htmlspecialchars($field),
                    "inputid" => "proposal_" . intval($proposal['id']) . "_" . preg_replace('/[^A-Za-z0-9_]/', '_', $field),
                    "title" => htmlspecialchars(isset($Table->formvals[$field]['title']) ? $Table->formvals[$field]['title'] : $field),
                    "current" => $this->ProposalDisplayValue($Table, $field, $current),
                    "proposed" => $this->ProposalDisplayValue($Table, $field, $change['new']),
                    // the record changed after the proposal was made
                    "changed" => ($current !== (string) $change['old']) ? "1" : "",
                );
            }
            $items[] = array(
                "id" => intval($proposal['id']),
                "user" => htmlspecialchars($proposal['username']),
                "date" => htmlspecialchars(FN_FormatDate($proposal['recordinsert'])),
                "token" => $this->CsrfToken("proposal|$tablename|" . $proposal['id']),
                "action" => $action,
                "fields" => $fields,
            );
        }
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/proposals.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/proposals.tp.html" : FN_FromTheme("{$this->moddir}/proposals.tp.html", false);
        return FN_TPL_ApplyTplFile($tplfile, array("proposals" => $items));
    }

    /**
     * Marks as read the proposal notifications of the current user for a record
     * @param string $id_record
     */
    protected function ProposalNotificationsDisplayed($id_record)
    {
        global $_FN;
        if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/fn_notifications.php"))
            return;
        $link = $this->ProposalRecordLink($id_record);
        $rows = FN_XMDBTable("fn_notifications")->GetRecords(array("username" => $_FN['user'], "context" => "dbview_proposals"));
        if (!is_array($rows))
            return;
        foreach ($rows as $n) {
            // FN_AddNotification stores the link html-escaped
            if ($n['username'] === $_FN['user'] && in_array($n['link'], array($link, htmlspecialchars($link)), true) && empty($n['displayed']))
                FN_SetNotificationDisplayed($n['id']);
        }
    }
}
