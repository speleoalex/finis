<?php
/**
 * FNDBVIEW Proposals Trait
 * Option enable_proposals: a logged-in user who can view a record but not edit it
 * may propose a change. The proposal is stored in dbview_proposals (one table for
 * all the sections) and never touches the record: the users who can edit the record
 * get a site notification (and an e-mail with proposals_mail) and, on the record
 * page, accept all the fields, some of them or none.
 * A user who can view the records but not add them may propose a new record too
 * (op=propose without id, row with an empty table_unirecid): the users who can add
 * records accept it on the page op=proposal, which creates the record as the insert form does.
 * Only the data fields of the record, without files. A proposed new record may also carry
 * rows of the linked tables listed in proposals_innertables (op=proposerow, added after the
 * proposal is saved): they are kept under the "_tables_" key of the JSON, their files in
 * dbview_proposals_files until the review, and they are inserted when the record is created.
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
     * files, admin-only, read-only, system fields and the fields of proposals_exclude_fields.
     * For a row of a linked table the files are allowed and only the field that links the
     * row to the record is left out.
     * @param object $Table xmetadb_frm
     * @param string $linkfield field of the linked table that holds the record key
     * @return array field names
     */
    function ProposalFields($Table, $linkfield = "")
    {
        $system = array("username", "userupdate", "recordinsert", "recordupdate", "recorddeleted",
            "groupview", "groupinsert", "view", "insert", "update");
        $row = $linkfield !== "";
        $excluded = $row ? array($linkfield) : array_map("trim", explode(",", isset($this->config['proposals_exclude_fields']) ? $this->config['proposals_exclude_fields'] : ""));
        $fields = array();
        foreach ($Table->formvals as $name => $field) {
            if (!empty($field['primarykey']) || in_array($name, $system, true) || in_array($name, $excluded, true) || (!$row && xmetadb_is_file_field($field)))
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
     * @param string $linkfield see ProposalFields
     */
    function HideNonProposalFields($Table, $linkfield = "")
    {
        $allowed = $this->ProposalFields($Table, $linkfield);
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
        if (!is_array($changes))
            return array();
        // the rows of the linked tables are not fields (see ProposalRows)
        unset($changes['_tables_']);
        return $changes;
    }

    /**
     * Rows of the linked tables carried by a proposed new record
     * @param array $proposal
     * @return array tablename => row key => field => value in form format
     */
    protected function ProposalRows($proposal)
    {
        $changes = json_decode((string) $proposal['changes'], true);
        return (is_array($changes) && isset($changes['_tables_']) && is_array($changes['_tables_'])) ? $changes['_tables_'] : array();
    }

    /**
     * JSON of the changes column: fields and, when there are any, the rows of the linked tables
     * @param array $changes field => array(old, new)
     * @param array $rows see ProposalRows
     * @return string|false false when it does not fit: the column is a MySQL TEXT
     * (65535 bytes) and a cut JSON would lose the whole proposal
     */
    protected function ProposalChangesJson($changes, $rows = array())
    {
        if (count($rows) > 0)
            $changes['_tables_'] = $rows;
        $json = json_encode($changes, JSON_UNESCAPED_UNICODE);
        return ($json !== false && strlen($json) <= 65535) ? $json : false;
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
        $json = $this->ProposalChangesJson($changes);
        if ($json === false)
            return FN_HtmlAlert(FN_Translate("the proposal is too long")) . $this->EditRecordForm($id_record, $Table, array(), false, true);
        $values = array(
            "changes" => $json,
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
        $this->ProposalNotify($this->ProposalReviewers($row), $text, $body, $this->ProposalRecordLink($id_record), true);

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
        $this->ProposalNotify(array($proposal['username']), $text, $body, $this->ProposalRecordLink($id_record), false);

        $done = array(
            "accepted" => "proposal accepted",
            "partial" => "proposal partially accepted",
            "rejected" => "proposal discarded",
        );
        return FN_HtmlAlert(FN_Translate($done[$status])) . $this->ViewRecordPage($id_record);
    }

    /**
     * True for a logged-in user who can view the records and cannot add them
     * (who can add records creates them directly)
     * @return bool
     */
    function CanProposeNewRecord()
    {
        global $_FN;
        return !empty($this->config['enable_proposals']) && $_FN['user'] != "" && $this->CanViewRecords() && !$this->CanAddRecord();
    }

    /**
     * Pending new record proposal by id, of the current user only when $mine
     * @param string $proposalid
     * @param bool $mine
     * @return array|null
     */
    protected function PendingNewRecordProposal($proposalid, $mine)
    {
        global $_FN;
        foreach ($this->PendingProposals($this->ProposalsTableName(), "", $mine ? $_FN['user'] : "") as $p) {
            if ((string) $p['id'] === (string) $proposalid)
                return $p;
        }
        return null;
    }

    /**
     * Insert form of a new record proposal (op=propose without id); with the
     * proposal parameter it changes a pending proposal of the user
     * @param object $Table
     * @return string HTML
     */
    function NewRecordProposalForm($Table)
    {
        if (!$this->CanProposeNewRecord())
            return FN_Translate("you may not do that");
        $pending = null;
        $proposalid = FN_GetParam("proposal", $_GET);
        if ($proposalid !== "") {
            $pending = $this->PendingNewRecordProposal($proposalid, true);
            if (!$pending)
                return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->NewRecordProposalsPage();
        }
        return $this->NewRecordForm($Table, array(), true, $pending);
    }

    /**
     * Saves a new record proposed with the insert form (op=propose without id).
     * Same row of dbview_proposals as a change, with an empty table_unirecid:
     * "old" is always empty and the record is created only when accepted.
     * @param object $Table
     * @return string HTML
     */
    function SaveNewRecordProposal($Table)
    {
        global $_FN;
        if (!$this->CanProposeNewRecord())
            return FN_Translate("you may not do that");
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $proposalid = FN_GetParam("proposal", $_GET);
        $pending = null;
        if ($proposalid !== "") {
            $pending = $this->PendingNewRecordProposal($proposalid, true);
            if (!$pending)
                return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->NewRecordProposalsPage();
        }

        $newvalues = $this->applyFieldTransformations($Table->getbypost(), $Table);
        $changes = array();
        $proposed = array();
        foreach ($this->ProposalFields($Table) as $field) {
            if (isset($newvalues[$field]) && trim((string) $newvalues[$field]) !== "") {
                $changes[$field] = array("old" => "", "new" => (string) $newvalues[$field]);
                $proposed[$field] = (string) $newvalues[$field];
            }
        }
        $proposals = FN_XMDBTable("dbview_proposals");
        if (count($changes) == 0) {
            if (!$pending)
                return FN_HtmlAlert(FN_Translate("the proposal contains no data")) . $this->NewRecordForm($Table, array(), true);
            $proposals->UpdateRecord(array("id" => $pending['id'], "status" => "withdrawn"));
            $this->RemoveProposalFiles($pending['id']);
            return FN_HtmlAlert(FN_Translate("proposal withdrawn")) . $this->NewRecordProposalsPage();
        }
        $errors = $Table->VerifyInsert($proposed);
        if (count($errors) > 0)
            return $this->NewRecordForm($Table, $errors, true, $pending);

        // the rows of the linked tables are added on their own page: keep them
        $json = $this->ProposalChangesJson($changes, $pending ? $this->ProposalRows($pending) : array());
        if ($json === false)
            return FN_HtmlAlert(FN_Translate("the proposal is too long")) . $this->NewRecordForm($Table, array(), true, $pending);
        $values = array(
            "changes" => $json,
            "recordinsert" => XMETATable::now(),
        );
        if ($pending) {
            $values['id'] = $pending['id'];
            $proposals->UpdateRecord($values);
        } else {
            $values['tablename'] = $tablename;
            $values['table_unirecid'] = "";
            $values['username'] = $_FN['user'];
            $values['status'] = "pending";
            $proposals->InsertRecord($values);
        }
        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename new record proposed.");

        $text = FN_Translate("new record proposed by") . " {$_FN['user']}: {$_FN['sectionvalues']['title']} - " . $this->ProposalRecordTitle($proposed);
        $body = $text . "\n" . FN_Translate("fields") . ": " . implode(", ", $this->ProposalFieldTitles($Table, array_keys($changes)));
        $this->ProposalNotify($this->ProposalReviewers(array()), $text, $body, $this->ProposalRecordLink(""), true);

        return FN_HtmlAlert(FN_Translate("your proposal has been sent to the users who manage this record")) . $this->NewRecordProposalsPage();
    }

    /**
     * Creates the record from a proposed new record or discards the proposal
     * (op=proposal without id, POST only). The author may withdraw it.
     * @param object $Table
     * @return string HTML
     */
    function ReviewNewRecordProposal($Table)
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $pk = $Table->xmltable->primarykey;
        $proposalid = FN_GetParam("proposal", $_POST);
        $action = FN_GetParam("action", $_POST);
        $isPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');
        $reviewer = $action !== "withdraw";
        if (!$isPost || empty($this->config['enable_proposals']) || $_FN['user'] == "" || ($reviewer && !$this->CanAddRecord()))
            return FN_Translate("you may not do that");
        if (!isset($_POST['fn_csrf']) || !hash_equals($this->CsrfToken("proposal|$tablename|$proposalid"), (string) $_POST['fn_csrf']))
            return FN_Translate("you may not do that");
        $proposal = $this->PendingNewRecordProposal($proposalid, !$reviewer);
        if (!$proposal)
            return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->NewRecordProposalsPage();
        if (!$reviewer) {
            FN_XMDBTable("dbview_proposals")->UpdateRecord(array("id" => $proposal['id'], "status" => "withdrawn"));
            $this->RemoveProposalFiles($proposal['id']);
            return FN_HtmlAlert(FN_Translate("proposal withdrawn")) . $this->NewRecordProposalsPage();
        }

        $changes = $this->ProposalChanges($proposal);
        $record = null;
        $rows = array();
        if ($action === "accept") {
            $values = array();
            $allowed = $this->ProposalFields($Table);
            foreach ($changes as $field => $change) {
                if (in_array($field, $allowed, true))
                    $values[$field] = $change['new'];
            }
            list($record, $errors) = $this->CreateRecord($Table, $values);
            if (!$record) {
                $msg = FN_Translate("the proposal cannot be accepted") . ":";
                foreach ($errors as $error)
                    $msg .= "<br />" . htmlspecialchars($error['title'] . ": " . $error['error']);
                return FN_HtmlAlert($msg) . $this->NewRecordProposalsPage();
            }
            $accepted = (isset($_POST['acceptrows']) && is_array($_POST['acceptrows'])) ? array_map("strval", $_POST['acceptrows']) : array();
            $rows = $this->InsertProposalRows($Table, $proposal, $record, $accepted);
        }
        $status = $record ? "accepted" : "rejected";
        $proposedrows = 0;
        foreach ($this->ProposalRows($proposal) as $tablerows)
            $proposedrows += count($tablerows);
        if ($record && count($rows) < $proposedrows)
            $status = "partial";
        FN_XMDBTable("dbview_proposals")->UpdateRecord(array(
            "id" => $proposal['id'],
            "table_unirecid" => $record ? $record[$pk] : "",
            "status" => $status,
            // rows of the linked tables as table:key, their ids in the log below
            "acceptedfields" => $record ? implode(",", array_merge(array_keys($values), array_keys($rows))) : "",
            "userreview" => $_FN['user'],
            "recordreview" => XMETATable::now(),
        ));
        // the names of the files stay in the proposal, the files are in the records or discarded
        $this->RemoveProposalFiles($proposal['id']);
        $inserted = "";
        foreach ($rows as $row => $id)
            $inserted .= " $row=$id";
        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename proposal {$proposal['id']} $status.$inserted");

        $title = $this->ProposalRecordTitle($record ? $record : $this->ProposalValues($proposal));
        $text = FN_Translate($record ? "your proposed new record has been accepted" : "your proposed new record has been discarded") . ": {$_FN['sectionvalues']['title']} - $title";
        $body = $text . "\n" . FN_Translate("reviewed by") . " {$_FN['user']}";
        $this->ProposalNotify(array($proposal['username']), $text, $body, $this->ProposalRecordLink($record ? $record[$pk] : ""), false);

        if (!$record)
            return FN_HtmlAlert(FN_Translate("proposal discarded")) . $this->NewRecordProposalsPage();
        // the reviewer completes the record (e.g. the cadastral number) in the edit form
        $msg = FN_Translate("the record has been created from the proposal");
        if (count($rows) > 0)
            $msg .= "<br />" . FN_Translate("linked records added") . ": " . count($rows) . ". " . FN_Translate("they are linked by the value that the record has now: if you change it, update them too");
        return FN_HtmlAlert($msg) . $this->EditRecordForm($record[$pk], $Table, array(), true);
    }

    /**
     * @param array $proposal
     * @return array field => proposed value
     */
    protected function ProposalValues($proposal)
    {
        $values = array();
        foreach ($this->ProposalChanges($proposal) as $field => $change)
            $values[$field] = $change['new'];
        return $values;
    }

    /**
     * Page of the proposed new records (op=proposal without id): all of them with
     * the review buttons for who can add records, the own ones for the authors
     * @return string HTML
     */
    function NewRecordProposalsPage()
    {
        global $_FN;
        $reviewer = $this->CanAddRecord();
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "" || (!$reviewer && !$this->CanProposeNewRecord()))
            return FN_Translate("you may not do that");
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        if ($reviewer)
            $this->ProposalNotificationsDisplayed($this->ProposalRecordLink(""));
        $nested = $this->ProposalNestedTables($Table);
        $items = array();
        foreach ($this->PendingProposals($tablename, "", $reviewer ? "" : $_FN['user']) as $proposal) {
            $addrows = array();
            foreach ($reviewer ? array() : $nested as $inner) {
                $addrows[] = array(
                    "title" => $this->ProposalNestedTitle($inner),
                    "link" => $this->MakeLink(array("op" => "proposerow", "id" => null, "proposal" => $proposal['id'], "table" => $inner['tablename']), "&amp;"),
                );
            }
            $rows = $this->ProposalRowItems($proposal, $nested, $reviewer);
            $fields = array();
            foreach ($this->ProposalChanges($proposal) as $field => $change) {
                $fields[] = array(
                    "title" => htmlspecialchars(isset($Table->formvals[$field]['title']) ? $Table->formvals[$field]['title'] : $field),
                    "proposed" => $this->ProposalDisplayValue($Table, $field, $change['new']),
                );
            }
            $items[] = array(
                "id" => intval($proposal['id']),
                // braces encoded: the template engine would read them as variables
                "recordtitle" => str_replace(array("{", "}"), array("&#123;", "&#125;"), htmlspecialchars($this->ProposalRecordTitle($this->ProposalValues($proposal)))),
                "user" => htmlspecialchars($proposal['username']),
                "date" => htmlspecialchars(FN_FormatDate($proposal['recordinsert'])),
                "token" => $this->CsrfToken("proposal|$tablename|" . $proposal['id']),
                "action" => $this->MakeLink(array("op" => "proposal", "id" => null), "&amp;"),
                "editlink" => $reviewer ? "" : $this->MakeLink(array("op" => "propose", "id" => null, "proposal" => $proposal['id']), "&amp;"),
                "reviewer" => $reviewer ? "1" : "",
                "fields" => $fields,
                "rows" => $rows,
                "hasrows" => count($rows) > 0 ? "1" : "",
                "addrows" => $addrows,
            );
        }
        $vars = array(
            "proposals" => $items,
            "empty" => count($items) == 0 ? "1" : "",
            "newlink" => $reviewer ? "" : $this->MakeLink(array("op" => "propose", "id" => null, "proposal" => null), "&amp;"),
            "listlink" => $this->MakeLink(array("op" => null, "id" => null, "proposal" => null), "&amp;"),
        );
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/newproposals.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/newproposals.tp.html" : FN_FromTheme("{$this->moddir}/newproposals.tp.html", false);
        return FN_TPL_ApplyTplFile($tplfile, $vars);
    }

    /**
     * Linked tables whose rows a proposed new record may carry: the innertables of the
     * main table listed in proposals_innertables
     * @param object $Table xmetadb_frm of the main table
     * @return array tablename => innertable (tablename, linkfield "recordfield,rowfield", titles)
     */
    protected function ProposalNestedTables($Table)
    {
        $enabled = array_filter(array_map("trim", explode(",", isset($this->config['proposals_innertables']) ? $this->config['proposals_innertables'] : "")));
        $ret = array();
        if (count($enabled) == 0 || !is_array($Table->innertables))
            return $ret;
        foreach ($Table->innertables as $inner) {
            $link = explode(",", isset($inner['linkfield']) ? $inner['linkfield'] : "");
            if (isset($inner['tablename']) && in_array($inner['tablename'], $enabled, true) && count($link) == 2 && $link[0] !== "" && $link[1] !== "")
                $ret[$inner['tablename']] = $inner;
        }
        return $ret;
    }

    /**
     * @param array $inner innertable
     * @return string title of the linked table, HTML from the table descriptor
     */
    protected function ProposalNestedTitle($inner)
    {
        global $_FN;
        $title = isset($inner["frm_{$_FN['lang']}"]) ? $inner["frm_{$_FN['lang']}"] : $inner['tablename'];
        return str_replace(array("{", "}"), array("&#123;", "&#125;"), $title);
    }

    /**
     * Folder of the files of the rows carried by a proposal. The data folder is served by
     * the web server and the files are not reviewed yet: the root gets a .htaccess that
     * denies the direct access, op=proposalfile sends them.
     * @param string $proposalid
     * @return string
     */
    protected function ProposalFilesDir($proposalid)
    {
        global $_FN;
        $root = "{$_FN['datadir']}/{$_FN['database']}/dbview_proposals_files";
        if (!file_exists("$root/.htaccess")) {
            FN_MkDir($root);
            FN_Write("<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n", "$root/.htaccess");
        }
        return "$root/" . intval($proposalid);
    }

    /**
     * @param string $proposalid
     * @param string $tablename
     * @param string $key row key
     * @param string $field
     * @param string $name file name stored in the row
     * @return string|false the file of a proposed row, false when missing
     */
    protected function ProposalRowFile($proposalid, $tablename, $key, $field, $name)
    {
        $name = XMETATable::SafeFileName($name);
        if ($name === "" || !preg_match('/^[A-Za-z0-9_]+$/', $tablename . $field))
            return false;
        $file = $this->ProposalFilesDir($proposalid) . "/$tablename/" . intval($key) . "/$field/$name";
        return is_file($file) ? $file : false;
    }

    /**
     * Deletes the files of a proposal, or of one of its rows
     * @param string $proposalid
     * @param string $tablename
     * @param string $key
     */
    protected function RemoveProposalFiles($proposalid, $tablename = "", $key = "")
    {
        $dir = $this->ProposalFilesDir($proposalid);
        if ($tablename !== "")
            $dir .= "/$tablename/" . intval($key);
        if (is_dir($dir))
            FN_RemoveDir($dir);
    }

    /**
     * Rows of the linked tables of a proposal for newproposals.tp.html
     * @param array $proposal
     * @param array $nested see ProposalNestedTables
     * @param bool $reviewer
     * @return array
     */
    protected function ProposalRowItems($proposal, $nested, $reviewer)
    {
        $items = array();
        foreach ($this->ProposalRows($proposal) as $tablename => $rows) {
            if (!isset($nested[$tablename]))
                continue;
            $Form = FN_XMDBForm($tablename);
            foreach ($rows as $key => $values) {
                $list = array();
                foreach ($values as $field => $value) {
                    if (isset($Form->formvals[$field]) && xmetadb_is_file_field($Form->formvals[$field])) {
                        $link = $this->MakeLink(array("op" => "proposalfile", "id" => null, "proposal" => $proposal['id'], "table" => $tablename, "row" => $key, "field" => $field), "&amp;");
                        $shown = "<a href=\"$link\">" . str_replace(array("{", "}"), array("&#123;", "&#125;"), htmlspecialchars($value)) . "</a>";
                    } else {
                        $shown = $this->ProposalDisplayValue($Form, $field, $value);
                    }
                    $list[] = array(
                        "title" => htmlspecialchars(isset($Form->formvals[$field]['title']) ? $Form->formvals[$field]['title'] : $field),
                        "value" => $shown,
                    );
                }
                $id = "$tablename:$key";
                $items[] = array(
                    "tabletitle" => $this->ProposalNestedTitle($nested[$tablename]),
                    "rowid" => htmlspecialchars($id),
                    "inputid" => "proposalrow_" . intval($proposal['id']) . "_" . preg_replace('/[^A-Za-z0-9_]/', '_', $id),
                    "reviewer" => $reviewer ? "1" : "",
                    "removeaction" => $reviewer ? "" : $this->MakeLink(array("op" => "proposerow", "id" => null, "proposal" => $proposal['id'], "table" => $tablename, "row" => $key), "&amp;"),
                    "values" => $list,
                );
            }
        }
        return $items;
    }

    /**
     * op=proposerow: the author of a pending proposed new record adds a row of a linked
     * table (form, then POST xmetadbsave) or removes one (POST removerow)
     * @return string HTML
     */
    function ProposalRowPage()
    {
        if (!$this->CanProposeNewRecord())
            return FN_Translate("you may not do that");
        $tablename = $this->ProposalsTableName();
        $nested = $this->ProposalNestedTables(FN_XMDBForm($tablename));
        $rowtable = FN_GetParam("table", $_GET);
        $proposal = $this->PendingNewRecordProposal(FN_GetParam("proposal", $_GET), true);
        if (!isset($nested[$rowtable]))
            return FN_Translate("you may not do that");
        if (!$proposal)
            return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->NewRecordProposalsPage();
        $inner = $nested[$rowtable];
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
            // over post_max_size PHP drops the whole body: the form would come back without a word
            if (count($_POST) == 0)
                return FN_HtmlAlert(FN_Translate("the files sent are too large")) . $this->ProposalRowForm($proposal, $inner);
            if (!isset($_POST['fn_csrf']) || !hash_equals($this->CsrfToken("proposal|$tablename|" . $proposal['id']), (string) $_POST['fn_csrf']))
                return FN_Translate("you may not do that");
            return isset($_POST['removerow']) ? $this->RemoveProposalRow($proposal, $inner) : $this->SaveProposalRow($proposal, $inner);
        }
        return $this->ProposalRowForm($proposal, $inner);
    }

    /**
     * Insert form of the linked table for a row of a proposed new record
     * @param array $proposal
     * @param array $inner innertable
     * @param array $errors
     * @return string HTML
     */
    protected function ProposalRowForm($proposal, $inner, $errors = array())
    {
        global $_FN;
        $Form = FN_XMDBForm($inner['tablename']);
        $link = explode(",", $inner['linkfield']);
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/form.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/form.tp.html" : FN_FromTheme("{$this->moddir}/form.tp.html", false);
        $template = FN_TPL_ApplyTplString(file_get_contents($tplfile), array(
            "formaction" => $this->MakeLink(array("op" => "proposerow", "id" => null, "proposal" => $proposal['id'], "table" => $inner['tablename']), "&amp;"),
            "urlcancel" => $this->MakeLink(array("op" => "proposal", "id" => null), "&"),
        ));
        // same token as the buttons of the proposal page
        $token = "<input type=\"hidden\" name=\"fn_csrf\" value=\"" . $this->CsrfToken("proposal|" . $this->ProposalsTableName() . "|" . $proposal['id']) . "\" />";
        $template = preg_replace('/(<form\b[^>]*>)/i', '$1' . $token, $template, 1);
        $Form->SetlayoutTemplate($this->FormTemplateWithoutAutofill($template));
        $html = "<h2>" . FN_Translate("add to the proposal") . ": " . $this->ProposalNestedTitle($inner) . "</h2>";
        $html .= "<p>" . FN_Translate("proposed new record") . ": " . htmlspecialchars($this->ProposalRecordTitle($this->ProposalValues($proposal))) . "</p>";
        // the form object is shared in the request: hide the fields only for this form
        $formvals = $Form->formvals;
        $this->HideNonProposalFields($Form, $link[1]);
        $html .= $Form->HtmlShowInsertForm(FN_IsAdmin(), $Form->getbypost(), $errors);
        $Form->formvals = $formvals;
        return $html;
    }

    /**
     * Adds to a proposed new record the row of a linked table sent with ProposalRowForm.
     * The files go to the folder of the proposal under the name they will have in the table.
     * @param array $proposal
     * @param array $inner innertable
     * @return string HTML
     */
    protected function SaveProposalRow($proposal, $inner)
    {
        global $_FN;
        $tablename = $inner['tablename'];
        $Form = FN_XMDBForm($tablename);
        $link = explode(",", $inner['linkfield']);
        $newvalues = $Form->getbypost();
        $row = array();
        foreach ($this->ProposalFields($Form, $link[1]) as $field) {
            if (isset($newvalues[$field]) && trim((string) $newvalues[$field]) !== "")
                $row[$field] = (string) $newvalues[$field];
        }
        if (count($row) == 0)
            return FN_HtmlAlert(FN_Translate("the proposal contains no data")) . $this->ProposalRowForm($proposal, $inner);
        $errors = $Form->VerifyInsert($row);
        $files = array();
        foreach ($row as $field => $value) {
            if (!xmetadb_is_file_field($Form->formvals[$field]) || isset($errors[$field]))
                continue;
            $upload = isset($_FILES[$field]) ? $_FILES[$field] : array();
            $name = XMETATable::SafeFileName($value);
            if ($name === "" || !isset($upload['error']) || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                $errors[$field] = array("title" => $Form->formvals[$field]['title'], "field" => $field, "error" => FN_Translate("file not accepted"));
                continue;
            }
            $row[$field] = $name;
            $files[$field] = $upload['tmp_name'];
        }
        if (count($errors) > 0)
            return $this->ProposalRowForm($proposal, $inner, $errors);

        $rows = $this->ProposalRows($proposal);
        $key = 1;
        foreach (isset($rows[$tablename]) ? array_keys($rows[$tablename]) : array() as $k)
            $key = max($key, intval($k) + 1);
        $rows[$tablename][$key] = $row;
        $json = $this->ProposalChangesJson($this->ProposalChanges($proposal), $rows);
        if ($json === false)
            return FN_HtmlAlert(FN_Translate("the proposal is too long")) . $this->ProposalRowForm($proposal, $inner);
        // a folder left by a removed row with the same key
        $this->RemoveProposalFiles($proposal['id'], $tablename, $key);
        $dir = $this->ProposalFilesDir($proposal['id']) . "/$tablename/$key";
        foreach ($files as $field => $tmpname) {
            FN_MkDir("$dir/$field");
            if (!move_uploaded_file($tmpname, "$dir/$field/{$row[$field]}")) {
                $this->RemoveProposalFiles($proposal['id'], $tablename, $key);
                return FN_HtmlAlert(FN_Translate("the file could not be saved")) . $this->ProposalRowForm($proposal, $inner);
            }
        }
        FN_XMDBTable("dbview_proposals")->UpdateRecord(array("id" => $proposal['id'], "changes" => $json));
        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename row $key added to proposal {$proposal['id']}.");
        return FN_HtmlAlert(FN_Translate("added to the proposal")) . $this->NewRecordProposalsPage();
    }

    /**
     * Removes a row (the row parameter) and its files from a proposed new record
     * @param array $proposal
     * @param array $inner innertable
     * @return string HTML
     */
    protected function RemoveProposalRow($proposal, $inner)
    {
        global $_FN;
        $tablename = $inner['tablename'];
        $key = FN_GetParam("row", $_GET);
        $rows = $this->ProposalRows($proposal);
        if (isset($rows[$tablename][$key])) {
            unset($rows[$tablename][$key]);
            if (count($rows[$tablename]) == 0)
                unset($rows[$tablename]);
            // shorter than before: it fits
            FN_XMDBTable("dbview_proposals")->UpdateRecord(array("id" => $proposal['id'], "changes" => $this->ProposalChangesJson($this->ProposalChanges($proposal), $rows)));
            $this->RemoveProposalFiles($proposal['id'], $tablename, $key);
            FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename row $key removed from proposal {$proposal['id']}.");
        }
        return FN_HtmlAlert(FN_Translate("removed from the proposal")) . $this->NewRecordProposalsPage();
    }

    /**
     * op=proposalfile: sends the file of a row of a pending proposed new record to its
     * author or to the users who can add records. Always as a download: nobody has
     * reviewed it yet and an SVG or HTML file opened in the browser could run scripts.
     * @return string HTML when the file cannot be sent
     */
    function ProposalFile()
    {
        global $_FN;
        $reviewer = $this->CanAddRecord();
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "" || (!$reviewer && !$this->CanProposeNewRecord()))
            return FN_Translate("you may not do that");
        $nested = $this->ProposalNestedTables(FN_XMDBForm($this->ProposalsTableName()));
        $proposal = $this->PendingNewRecordProposal(FN_GetParam("proposal", $_GET), !$reviewer);
        $tablename = FN_GetParam("table", $_GET);
        $key = FN_GetParam("row", $_GET);
        $field = FN_GetParam("field", $_GET);
        $rows = $proposal ? $this->ProposalRows($proposal) : array();
        $file = false;
        if (isset($nested[$tablename]) && isset($rows[$tablename][$key][$field])) {
            $Form = FN_XMDBForm($tablename);
            if (isset($Form->formvals[$field]) && xmetadb_is_file_field($Form->formvals[$field]))
                $file = $this->ProposalRowFile($proposal['id'], $tablename, $key, $field, $rows[$tablename][$key][$field]);
        }
        if (!$file)
            return FN_HtmlAlert(FN_Translate("file not found")) . $this->NewRecordProposalsPage();
        while (@ob_end_clean());
        $name = basename($file);
        header("Content-Type: application/octet-stream");
        header("X-Content-Type-Options: nosniff");
        header("Content-Disposition: attachment; filename=\"" . preg_replace('/[^A-Za-z0-9._-]/', '_', $name) . "\"; filename*=UTF-8''" . rawurlencode($name));
        header("Content-Length: " . filesize($file));
        readfile($file);
        exit;
    }

    /**
     * Inserts the accepted rows of a proposed new record into the linked tables, with
     * their files, linked to the record just created
     * @param object $Table xmetadb_frm of the main table
     * @param array $proposal
     * @param array $record record created from the proposal
     * @param array $accepted rows as table:key
     * @return array table:key => primary key of the inserted row
     */
    protected function InsertProposalRows($Table, $proposal, $record, $accepted)
    {
        $pk = $Table->xmltable->primarykey;
        // read again: function_on_insert may have set the linked field (e.g. the cadastral number)
        $record = $Table->xmltable->GetRecordByPrimaryKey($record[$pk]);
        $nested = $this->ProposalNestedTables($Table);
        $inserted = array();
        foreach ($this->ProposalRows($proposal) as $tablename => $rows) {
            if (!isset($nested[$tablename]))
                continue;
            $link = explode(",", $nested[$tablename]['linkfield']);
            if (!isset($record[$link[0]]) || (string) $record[$link[0]] === "")
                continue;
            $Form = FN_XMDBForm($tablename);
            $allowed = array_flip($this->ProposalFields($Form, $link[1]));
            foreach ($rows as $key => $values) {
                if (!in_array("$tablename:$key", $accepted, true))
                    continue;
                $values = array_intersect_key($values, $allowed);
                // the table object is shared in the request: no file of the previous row
                $Form->xmltable->uploadedFiles = array();
                foreach ($values as $field => $value) {
                    if (!xmetadb_is_file_field($Form->formvals[$field]))
                        continue;
                    $file = $this->ProposalRowFile($proposal['id'], $tablename, $key, $field, $value);
                    if ($file)
                        $Form->xmltable->SetFile($field, $file, $value);
                    else
                        unset($values[$field]);
                }
                $values[$link[1]] = $record[$link[0]];
                $new = $Form->InsertRecord($values);
                if (is_array($new) && isset($new[$Form->xmltable->primarykey]))
                    $inserted["$tablename:$key"] = $new[$Form->xmltable->primarykey];
            }
            $Form->xmltable->uploadedFiles = array();
        }
        return $inserted;
    }

    /**
     * Line on top of the record list: the button to propose a new record and the
     * own pending proposals for the authors, the pending proposals for the reviewers
     * @return string HTML
     */
    function NewRecordProposalsNotice()
    {
        global $_FN;
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "")
            return "";
        $reviewer = $this->CanAddRecord();
        if (!$reviewer && !$this->CanProposeNewRecord())
            return "";
        $count = count($this->PendingProposals($this->ProposalsTableName(), "", $reviewer ? "" : $_FN['user']));
        $page = $this->MakeLink(array("op" => "proposal", "id" => null), "&amp;");
        if ($reviewer) {
            if ($count == 0)
                return "";
            return "<div class=\"alert alert-warning\" role=\"status\">" . FN_Translate("new records proposed and waiting for review") . ": $count. <a href=\"$page\">" . FN_Translate("review the proposals") . "</a></div>";
        }
        $html = "<p class=\"dbview-propose-new\"><a class=\"btn btn-primary\" href=\"" . $this->MakeLink(array("op" => "propose", "id" => null), "&amp;") . "\">" . FN_Translate("propose a new record") . "</a>";
        if ($count > 0)
            $html .= " <a class=\"btn btn-outline-secondary\" href=\"$page\">" . FN_Translate("your proposed new records waiting for review") . ": $count</a>";
        return $html . "</p>";
    }

    /**
     * Users who receive the proposals of a record: creator, users enabled on the
     * record, section administrators and members of the groups that can edit it.
     * With no group that can edit (any logged-in user) only creator, enabled users
     * and administrators; the site administrators when nobody else is left.
     * For a new record (empty $row) the section administrators and the groups that can add records.
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
        if (count($row) > 0) {
            foreach ($this->GetFieldUserList($row, $tablename, false) as $r)
                $users[] = $r['username'];
        }
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
     * @param string $link absolute address of the page to open
     * @param bool $mailalert true: the mail goes to mailalert of the section too
     */
    protected function ProposalNotify($users, $text, $body, $link, $mailalert)
    {
        global $_FN;
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
     * Absolute address of the record page, used by notifications and mails;
     * with an empty $id_record the page of the proposed new records
     * @param string $id_record
     * @return string
     */
    protected function ProposalRecordLink($id_record)
    {
        global $_FN;
        $link = rtrim($_FN['siteurl'], "/") . "/index.php?mod=" . urlencode($_FN['mod']);
        return $link . ((string) $id_record === "" ? "&op=proposal" : "&op=view&id=" . urlencode($id_record));
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
        $this->ProposalNotificationsDisplayed($this->ProposalRecordLink($id_record));
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
     * Marks as read the proposal notifications of the current user that open a page
     * @param string $link absolute address, as passed to ProposalNotify
     */
    protected function ProposalNotificationsDisplayed($link)
    {
        global $_FN;
        if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/fn_notifications.php"))
            return;
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
