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
 * The data fields of the record and its files: a file field is proposed only by sending the
 * file. A proposal (new record or change) may also carry new rows of the linked tables listed
 * in proposals_innertables (op=proposerow, one at a time; on an existing record the first row
 * creates the proposal when there is none): they are kept under the "_tables_" key of the
 * JSON and the accepted ones are inserted linked to the record. All the files wait in
 * dbview_proposals_files until the review, those of the record as row 0 of the main table.
 * The author may withdraw a pending proposal: the reviewers are notified of that too. The
 * notifications open the page with &proposal=<id>, which says so when the proposal is no
 * longer pending (withdrawn, accepted or discarded).
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
     * Fields a proposal may change: the visible fields, files included, without primary key,
     * admin-only, read-only, system fields and the fields of proposals_exclude_fields.
     * For a row of a linked table only the field that links the row to the record is left out.
     * @param object $Table xmetadb_frm
     * @param string $linkfield field of the linked table that holds the record key
     * @return array field names
     */
    function ProposalFields($Table, $linkfield = "")
    {
        $system = array("username", "userupdate", "recordinsert", "recordupdate", "recorddeleted",
            "groupview", "groupinsert", "view", "insert", "update");
        $row = $linkfield !== "";
        $excluded = $row ? array($linkfield) : array_merge(array_map("trim", explode(",", isset($this->config['proposals_exclude_fields']) ? $this->config['proposals_exclude_fields'] : "")), $this->ValidationLockedFields());
        $fields = array();
        foreach ($Table->formvals as $name => $field) {
            if (!empty($field['primarykey']) || in_array($name, $system, true) || in_array($name, $excluded, true))
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
     * @param string|null $id_record "" = proposed new records, null = proposals of any record
     * @param string $username only the proposals of this user
     * @return array
     */
    protected function PendingProposals($tablename, $id_record, $username = "")
    {
        global $_FN;
        if (!file_exists("{$_FN['datadir']}/{$_FN['database']}/dbview_proposals.php"))
            return array();
        $restr = array("tablename" => $tablename, "status" => "pending");
        if ($id_record !== null)
            $restr['table_unirecid'] = $id_record;
        if ($username != "")
            $restr['username'] = $username;
        $rows = FN_XMDBTable("dbview_proposals")->GetRecords($restr);
        if (!is_array($rows))
            return array();
        // the driver may match without case or type: check again
        $ret = array();
        foreach ($rows as $r) {
            if ($r['tablename'] === $tablename && ($id_record === null || (string) $r['table_unirecid'] === (string) $id_record)
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
     * @param array $proposal
     * @return int number of rows of the linked tables carried by the proposal
     */
    protected function ProposalRowCount($proposal)
    {
        $count = 0;
        foreach ($this->ProposalRows($proposal) as $rows)
            $count += count($rows);
        return $count;
    }

    /**
     * Pending proposal of the section by id, new record or change
     * @param string $proposalid
     * @return array|null
     */
    protected function PendingProposalById($proposalid)
    {
        global $_FN;
        if ((string) $proposalid === "" || !file_exists("{$_FN['datadir']}/{$_FN['database']}/dbview_proposals.php"))
            return null;
        $p = FN_XMDBTable("dbview_proposals")->GetRecordByPrimaryKey($proposalid);
        // the driver may match without case or type: check again
        if (!is_array($p) || (string) $p['id'] !== (string) $proposalid || $p['tablename'] !== $this->ProposalsTableName() || $p['status'] !== "pending")
            return null;
        return $p;
    }

    /**
     * True for the reviewers of a pending proposal and for its author
     * @param array $proposal
     * @return bool
     */
    protected function CanSeeProposal($proposal)
    {
        global $_FN;
        $mine = $_FN['user'] != "" && $proposal['username'] === $_FN['user'];
        if ((string) $proposal['table_unirecid'] === "")
            return $this->CanReviewProposal() || $this->CanAddRecord() || ($mine && $this->CanProposeNewRecord());
        $row = FN_XMDBTable($this->ProposalsTableName())->GetRecordByPrimaryKey($proposal['table_unirecid']);
        return is_array($row) && ($this->CanReviewProposal($row) || $this->CanUpdateRecord($row) || ($mine && $this->CanProposeRecord($row)));
    }

    /**
     * Page the row actions go back to: the record of a change, the list of the proposed new records
     * @param array $proposal
     * @return string HTML
     */
    protected function ProposalPage($proposal)
    {
        $id_record = (string) $proposal['table_unirecid'];
        return $id_record === "" ? $this->ProposalsListPage() : $this->ViewRecordPage($id_record);
    }

    /**
     * Pending changes of existing records the current user may see: the ones of the
     * records he can edit and his own
     * @return array proposal rows, each with the record under "_record"
     */
    protected function VisibleChangeProposals()
    {
        $ret = array();
        $data = FN_XMDBTable($this->ProposalsTableName());
        foreach ($this->PendingProposals($this->ProposalsTableName(), null) as $proposal) {
            if ((string) $proposal['table_unirecid'] === "" || !$this->CanSeeProposal($proposal))
                continue;
            $proposal['_record'] = $data->GetRecordByPrimaryKey($proposal['table_unirecid']);
            $ret[] = $proposal;
        }
        return $ret;
    }

    /**
     * @param string $text
     * @return string text escaped for a template: the engine would read braces as variables
     */
    protected function ProposalTemplateText($text)
    {
        return str_replace(array("{", "}"), array("&#123;", "&#125;"), htmlspecialchars($text));
    }

    /**
     * Address of op=proposerow for a proposal: a change is reached by its record
     * (the proposal may not exist yet), a new record by the proposal id
     * @param array $proposal
     * @param string $table linked table
     * @param string $key row to remove
     * @return string
     */
    protected function ProposalRowLink($proposal, $table, $key = null)
    {
        $id_record = (string) $proposal['table_unirecid'];
        return $this->MakeLink(array("op" => "proposerow", "id" => $id_record === "" ? null : $id_record,
                "proposal" => $id_record === "" ? $proposal['id'] : null, "table" => $table, "row" => $key), "&amp;");
    }

    /**
     * CSRF token of the row actions of a proposal: for a new record the same as the
     * buttons of the proposal page
     * @param array $proposal
     * @return string
     */
    protected function ProposalRowToken($proposal)
    {
        $tablename = $this->ProposalsTableName();
        $id_record = (string) $proposal['table_unirecid'];
        return $this->CsrfToken($id_record === "" ? "proposal|$tablename|" . $proposal['id'] : "proposerow|$tablename|$id_record");
    }

    /**
     * Buttons to add the rows of the linked tables to a proposal
     * @param array $proposal
     * @param array $nested see ProposalNestedTables
     * @return array title, link
     */
    protected function ProposalAddRowItems($proposal, $nested)
    {
        $items = array();
        foreach ($nested as $inner)
            $items[] = array("title" => $this->ProposalNestedTitle($inner), "link" => $this->ProposalRowLink($proposal, $inner['tablename']));
        return $items;
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
        $Table = FN_XMDBForm($tablename);
        $pk = $Table->xmltable->primarykey;
        foreach ($this->PendingProposals($tablename, $row[$pk], $_FN['user']) as $proposal) {
            foreach ($this->ProposalChanges($proposal) as $field => $change) {
                // a proposed file is not stored with the record yet: the form keeps the stored one
                // and the proposed file stays in the proposal until a new one is sent
                if (!isset($Table->formvals[$field]) || !xmetadb_is_file_field($Table->formvals[$field]))
                    $row[$field] = $change['new'];
            }
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
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $pk = $Table->xmltable->primarykey;
        $id_record = FN_GetParam("_xmetadbform_pk_$pk", $_POST);
        $posted = $Table->getbypost();
        $result = $this->ProposeChangeValues($Table, $id_record, $posted, $this->UploadedFormFiles($posted));
        switch ($result['status']) {
            case "denied":
                return FN_Translate("you may not do that");
            case "invalid":
                return $this->EditRecordForm($id_record, $Table, $result['errors'], false, true);
            case "nosave":
                return FN_HtmlAlert(FN_Translate("the file could not be saved")) . $this->EditRecordForm($id_record, $Table, array(), false, true);
            case "unchanged":
                return FN_HtmlAlert(FN_Translate("no changes compared to the record")) . $this->ViewRecordPage($id_record);
            case "toolong":
                return FN_HtmlAlert(FN_Translate("the proposal is too long")) . $this->EditRecordForm($id_record, $Table, array(), false, true);
        }
        return FN_HtmlAlert(FN_Translate(empty($result['changed']) ? "your proposal has been sent to the users who manage this record" : "your proposal has been changed")) . $this->ViewRecordPage($id_record);
    }

    /**
     * Stores the change proposed by the current user. Shared by the web form (op=propose)
     * and the MCP server; the values are the ones the form would send, before the field
     * transformations.
     * @param object $Table xmetadb_frm of the main table
     * @param string $id_record
     * @param array $posted field => value
     * @param array $files field => absolute path of the file sent for a file field
     * @return array status denied|invalid|unchanged|toolong|nosave|sent; errors; proposal; changes
     */
    protected function ProposeChangeValues($Table, $id_record, $posted, $files = array())
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $row = $Table->xmltable->GetRecordByPrimaryKey($id_record);
        if (!is_array($row) || !$this->CanProposeRecord($row))
            return array("status" => "denied");

        $newvalues = $this->applyFieldTransformations($posted, $Table);
        $changes = array();
        $proposed = array();
        $filevalues = array();
        foreach ($this->ProposalFields($Table) as $field) {
            if (!array_key_exists($field, $newvalues))
                continue;
            if (xmetadb_is_file_field($Table->formvals[$field])) {
                // a file is proposed only by sending it: a name alone would point to nothing
                if (isset($files[$field]))
                    $filevalues[$field] = (string) $newvalues[$field];
                continue;
            }
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
        $this->LockUserProposals();
        $pending = $this->PendingProposals($tablename, $id_record, $_FN['user']);
        $errors = $Table->VerifyUpdate(array_merge($row, $proposed), $id_record);
        $sources = $this->ProposalFileSources($Table, $filevalues, $files, $errors);
        if (count($errors) > 0)
            return array("status" => "invalid", "errors" => $errors);
        foreach ($filevalues as $field => $name)
            $changes[$field] = array("old" => isset($row[$field]) ? (string) $row[$field] : "", "new" => $name);
        if (count($pending) > 0)
            $changes = $this->KeepProposedFiles($Table, $pending[0], $changes);

        $proposals = FN_XMDBTable("dbview_proposals");
        // the rows of the linked tables are added on their own page: keep them
        $rows = count($pending) > 0 ? $this->ProposalRows($pending[0]) : array();
        if (count($changes) == 0) {
            foreach ($pending as $p) {
                if (count($rows) > 0 && $p['id'] === $pending[0]['id'])
                    $proposals->UpdateRecord(array("id" => $p['id'], "changes" => $this->ProposalChangesJson(array(), $rows)));
                else
                    $this->WithdrawProposal($p);
            }
            return array("status" => "unchanged");
        }
        $json = $this->ProposalChangesJson($changes, $rows);
        if ($json === false)
            return array("status" => "toolong");
        // the same form sent again (double click, reload): already notified
        if (count($pending) > 0 && $pending[0]['changes'] === $json && count($sources) == 0)
            return array("status" => "sent", "proposal" => $pending[0], "changes" => $changes);
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
            $values = $proposals->InsertRecord($values);
        }
        if (!$this->StoreProposalFiles($values['id'], $tablename, 0, $filevalues, $sources)) {
            // nobody has been told of a new one yet
            if (count($pending) == 0)
                $this->WithdrawProposal($values, false);
            return array("status" => "nosave");
        }
        FN_Log("{$_FN['mod']}||" . $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename change proposed for $id_record.");

        // a proposal is announced once: when the author changes it while pending the
        // reviewers are told it changed, not sent it again as new (proposal 10, 2026-11-27)
        if (count($pending) > 0) {
            $this->ProposalNotifyChanged(array_merge($pending[0], $values), $row);
            return array("status" => "sent", "changed" => true, "proposal" => $values, "changes" => $changes);
        }
        $title = "{$_FN['sectionvalues']['title']} - " . $this->ProposalRecordTitle($row);
        $text = $this->ProposalText("change proposed by") . " {$_FN['user']}: $title";
        $this->ProposalNotifySent($this->ProposalReviewers($row), $text, $this->ProposalRecordLink($id_record, isset($values['id']) ? $values['id'] : ""), $title, $values, $row);

        return array("status" => "sent", "proposal" => $values, "changes" => $changes);
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
        if (!$isPost || empty($this->config['enable_proposals']) || !is_array($row) || !$this->CanReviewProposal($row))
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
        $accepted = $this->SetProposalFiles($Table, $proposal['id'], $tablename, 0, $accepted);
        if (count($accepted) > 0) {
            $newvalues = $accepted;
            $newvalues[$pk] = $id_record;
            $errors = $Table->VerifyUpdate(array_merge($row, $newvalues), $id_record);
            if (count($errors) > 0) {
                $Table->xmltable->uploadedFiles = array();
                $msg = FN_Translate("the proposal cannot be accepted") . ":";
                foreach ($errors as $error)
                    $msg .= "<br />" . htmlspecialchars($error['title'] . ": " . $error['error']);
                return FN_HtmlAlert($msg) . $this->ViewRecordPage($id_record);
            }
            $newvalues['userupdate'] = $_FN['user'];
            $newvalues = $this->saveRecordChanges($Table, $tablename, $row, $newvalues, $id_record);
            $Table->xmltable->uploadedFiles = array();
            $this->runUpdateHook(array_merge($row, $newvalues));
        }
        // after the fields: the rows are linked to the value the record has now
        $rows = array();
        if (FN_GetParam("action", $_POST) === "accept" && isset($_POST['acceptrows']) && is_array($_POST['acceptrows']))
            $rows = $this->InsertProposalRows($Table, $proposal, $row, array_map("strval", $_POST['acceptrows']));
        $done = count($accepted) + count($rows);
        $status = $done == 0 ? "rejected" : ($done == count($changes) + $this->ProposalRowCount($proposal) ? "accepted" : "partial");
        FN_XMDBTable("dbview_proposals")->UpdateRecord(array(
            "id" => $proposal['id'],
            "status" => $status,
            // rows of the linked tables as table:key, their ids in the log below
            "acceptedfields" => implode(",", array_merge(array_keys($accepted), array_keys($rows))),
            "userreview" => $_FN['user'],
            "recordreview" => XMETATable::now(),
        ));
        // the names of the files stay in the proposal, the files are in the records or discarded
        $this->RemoveProposalFiles($proposal['id']);
        $inserted = "";
        foreach ($rows as $key => $id)
            $inserted .= " $key=$id";
        FN_Log("{$_FN['mod']}||" . $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename proposal {$proposal['id']} $status.$inserted");

        $messages = array(
            "accepted" => "your proposed change has been accepted",
            "partial" => "your proposed change has been partially accepted",
            "rejected" => "your proposed change has been discarded",
        );
        $text = $this->ProposalText($messages[$status]) . ": {$_FN['sectionvalues']['title']} - " . $this->ProposalRecordTitle($row);
        $lines = array();
        if (count($accepted) > 0)
            $lines[] = $this->ProposalText("accepted fields") . ": " . implode(", ", $this->ProposalFieldTitles($Table, array_keys($accepted), $_FN['lang_default']));
        $rejected = array_diff(array_keys($changes), array_keys($accepted));
        if (count($rejected) > 0)
            $lines[] = $this->ProposalText("discarded fields") . ": " . implode(", ", $this->ProposalFieldTitles($Table, $rejected, $_FN['lang_default']));
        if (count($rows) > 0)
            $lines[] = $this->ProposalText("linked records added") . ": " . count($rows);
        $lines[] = $this->ProposalText("reviewed by") . " {$_FN['user']}";
        $this->ProposalNotify(array($proposal['username']), $text, $this->ProposalRecordLink($id_record, $proposal['id']), false, $lines);

        $messages = array(
            "accepted" => "proposal accepted",
            "partial" => "proposal partially accepted",
            "rejected" => "proposal discarded",
        );
        $msg = FN_Translate($messages[$status]);
        if (count($rows) > 0)
            $msg .= "<br />" . FN_Translate("linked records added") . ": " . count($rows);
        return FN_HtmlAlert($msg) . $this->ViewRecordPage($id_record);
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
                return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->ProposalsListPage();
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
        $this->LockUserProposals();
        $proposalid = FN_GetParam("proposal", $_GET);
        $pending = null;
        if ($proposalid !== "") {
            $pending = $this->PendingNewRecordProposal($proposalid, true);
            if (!$pending)
                return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->ProposalsListPage();
        }
        $posted = $Table->getbypost();
        $result = $this->ProposeNewRecordValues($Table, $posted, $pending, $this->UploadedFormFiles($posted));
        switch ($result['status']) {
            case "nodata":
                return FN_HtmlAlert(FN_Translate("the proposal contains no data")) . $this->NewRecordForm($Table, array(), true);
            case "withdrawn":
                return FN_HtmlAlert(FN_Translate("proposal withdrawn")) . $this->ProposalsListPage();
            case "invalid":
                return $this->NewRecordForm($Table, $result['errors'], true, $pending);
            case "nosave":
                return FN_HtmlAlert(FN_Translate("the file could not be saved")) . $this->NewRecordForm($Table, array(), true, $pending);
            case "toolong":
                return FN_HtmlAlert(FN_Translate("the proposal is too long")) . $this->NewRecordForm($Table, array(), true, $pending);
        }
        return FN_HtmlAlert(FN_Translate(empty($result['changed']) ? "your proposal has been sent to the users who manage this record" : "your proposal has been changed")) . $this->ProposalsListPage();
    }

    /**
     * Stores a new record proposed by the current user. Shared by the web form
     * (op=propose without id) and the MCP server; the values are the ones the form
     * would send, before the field transformations.
     * @param object $Table xmetadb_frm of the main table
     * @param array $posted field => value
     * @param array|null $pending proposal of the user being changed, null for a new one
     * @param array $files field => absolute path of the file sent for a file field
     * @return array status nodata|withdrawn|invalid|toolong|nosave|sent; errors; proposal; changes
     */
    protected function ProposeNewRecordValues($Table, $posted, $pending, $files = array())
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $this->LockUserProposals();

        $newvalues = $this->applyFieldTransformations($posted, $Table);
        $changes = array();
        $proposed = array();
        $filevalues = array();
        // the form comes filled with the defaults (frm_default): those alone are not data
        $data = 0;
        foreach ($this->ProposalFields($Table) as $field) {
            if (xmetadb_is_file_field($Table->formvals[$field])) {
                // a file is proposed only by sending it: a name alone would point to nothing
                if (isset($files[$field], $newvalues[$field]))
                    $filevalues[$field] = (string) $newvalues[$field];
                continue;
            }
            if (isset($newvalues[$field]) && trim((string) $newvalues[$field]) !== "") {
                $changes[$field] = array("old" => "", "new" => (string) $newvalues[$field]);
                $proposed[$field] = (string) $newvalues[$field];
                if (!isset($Table->formvals[$field]['frm_default']) || (string) $Table->formvals[$field]['frm_default'] !== $proposed[$field])
                    $data++;
            }
        }
        $errors = array();
        $sources = $this->ProposalFileSources($Table, $filevalues, $files, $errors);
        foreach ($filevalues as $field => $name)
            $changes[$field] = array("old" => "", "new" => $name);
        if ($pending)
            $changes = $this->KeepProposedFiles($Table, $pending, $changes);
        foreach ($changes as $field => $change) {
            if (xmetadb_is_file_field($Table->formvals[$field])) {
                $proposed[$field] = $change['new'];
                $data++;
            }
        }
        if ($data == 0 && count($errors) == 0) {
            if (!$pending)
                return array("status" => "nodata");
            $this->WithdrawProposal($pending);
            return array("status" => "withdrawn", "proposal" => $pending);
        }
        $errors = array_merge($Table->VerifyInsert($proposed), $errors);
        if (count($errors) > 0)
            return array("status" => "invalid", "errors" => $errors);

        // the rows of the linked tables are added on their own page: keep them
        $json = $this->ProposalChangesJson($changes, $pending ? $this->ProposalRows($pending) : array());
        if ($json === false)
            return array("status" => "toolong");
        // the same form sent again (double click, reload): already notified
        foreach (count($sources) > 0 ? array() : ($pending ? array($pending) : $this->PendingProposals($tablename, "", $_FN['user'])) as $p) {
            if ($p['changes'] === $json)
                return array("status" => "sent", "proposal" => $p, "changes" => $changes);
        }
        $proposals = FN_XMDBTable("dbview_proposals");
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
            $values = $proposals->InsertRecord($values);
        }
        if (!$this->StoreProposalFiles($values['id'], $tablename, 0, $filevalues, $sources)) {
            // nobody has been told of a new one yet
            if (!$pending)
                $this->WithdrawProposal($values, false);
            return array("status" => "nosave");
        }
        FN_Log("{$_FN['mod']}||" . $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename new record proposed.");

        // announced once, see ProposeChangeValues
        if ($pending) {
            $this->ProposalNotifyChanged(array_merge($pending, $values));
            return array("status" => "sent", "changed" => true, "proposal" => $values, "changes" => $changes);
        }
        $title = "{$_FN['sectionvalues']['title']} - " . $this->ProposalRecordTitle($proposed);
        $text = $this->ProposalText("new record proposed by") . " {$_FN['user']}: $title";
        $this->ProposalNotifySent($this->ProposalReviewers(array()), $text, $this->ProposalRecordLink("", isset($values['id']) ? $values['id'] : ""), $title, $values);

        return array("status" => "sent", "proposal" => $values, "changes" => $changes);
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
        if (!$isPost || empty($this->config['enable_proposals']) || $_FN['user'] == "" || ($reviewer && !$this->CanReviewProposal()))
            return FN_Translate("you may not do that");
        if (!isset($_POST['fn_csrf']) || !hash_equals($this->CsrfToken("proposal|$tablename|$proposalid"), (string) $_POST['fn_csrf']))
            return FN_Translate("you may not do that");
        $proposal = $this->PendingNewRecordProposal($proposalid, !$reviewer);
        if (!$proposal)
            return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->ProposalsListPage();
        if (!$reviewer) {
            $this->WithdrawProposal($proposal);
            return FN_HtmlAlert(FN_Translate("proposal withdrawn")) . $this->ProposalsListPage();
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
            $values = $this->SetProposalFiles($Table, $proposal['id'], $tablename, 0, $values);
            list($record, $errors) = $this->CreateRecord($Table, $values);
            $Table->xmltable->uploadedFiles = array();
            if (!$record) {
                $msg = FN_Translate("the proposal cannot be accepted") . ":";
                foreach ($errors as $error)
                    $msg .= "<br />" . htmlspecialchars($error['title'] . ": " . $error['error']);
                return FN_HtmlAlert($msg) . $this->ProposalsListPage();
            }
            $accepted = (isset($_POST['acceptrows']) && is_array($_POST['acceptrows'])) ? array_map("strval", $_POST['acceptrows']) : array();
            $rows = $this->InsertProposalRows($Table, $proposal, $record, $accepted);
        }
        $status = $record ? "accepted" : "rejected";
        if ($record && count($rows) < $this->ProposalRowCount($proposal))
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
        FN_Log("{$_FN['mod']}||" . $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename proposal {$proposal['id']} $status.$inserted");

        $title = $this->ProposalRecordTitle($record ? $record : $this->ProposalValues($proposal));
        $text = $this->ProposalText($record ? "your proposed new record has been accepted" : "your proposed new record has been discarded") . ": {$_FN['sectionvalues']['title']} - $title";
        $this->ProposalNotify(array($proposal['username']), $text, $this->ProposalRecordLink($record ? $record[$pk] : "", $proposal['id']), false, array($this->ProposalText("reviewed by") . " {$_FN['user']}"));

        if (!$record)
            return FN_HtmlAlert(FN_Translate("proposal discarded")) . $this->ProposalsListPage();
        // the reviewer completes the record (e.g. the cadastral number) in the edit form
        $msg = FN_Translate("the record has been created from the proposal");
        if (count($rows) > 0)
            $msg .= "<br />" . FN_Translate("linked records added") . ": " . count($rows) . ". " . FN_Translate("they are linked by the value that the record has now: if you change it, update them too");
        return FN_HtmlAlert($msg) . $this->EditRecordForm($record[$pk], $Table, array(), true);
    }

    /**
     * Html of the application under a proposed new record, e.g. a map with the
     * records around it: the function named in
     * $_FN['modparams'][mod]['function_proposal_preview'] gets the proposed values
     * @param array $values field => proposed value
     * @return string HTML, empty without the function
     */
    protected function ProposalPreview($values)
    {
        global $_FN;
        $function = isset($_FN['modparams'][$_FN['mod']]['function_proposal_preview']) ? $_FN['modparams'][$_FN['mod']]['function_proposal_preview'] : "";
        if ($function == "" || !function_exists($function))
            return "";
        // the template engine would read braces as variables
        return str_replace(array("{", "}"), array("&#123;", "&#125;"), (string) $function($values));
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
     * Page of the pending proposals (op=proposal without id). Proposed new records:
     * all of them with the review buttons for who can add records, the own ones for
     * the authors. Changes of existing records: the ones the user may see (see
     * VisibleChangeProposals), each linked to its record, where it is reviewed
     * @return string HTML
     */
    function ProposalsListPage()
    {
        global $_FN;
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "")
            return FN_Translate("you may not do that");
        $reviewer = $this->CanReviewProposal();
        // who may add records sees every proposal, read only when not a reviewer
        $viewer = $reviewer || $this->CanAddRecord();
        $newrecords = $viewer || $this->CanProposeNewRecord();
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $changes = array();
        foreach ($this->VisibleChangeProposals() as $proposal) {
            $rowcount = $this->ProposalRowCount($proposal);
            $changes[] = array(
                "recordtitle" => $this->ProposalTemplateText($this->ProposalRecordTitle(is_array($proposal['_record']) ? $proposal['_record'] : array())),
                "link" => $this->MakeLink(array("op" => "view", "id" => $proposal['table_unirecid'], "proposal" => null), "&amp;"),
                "user" => htmlspecialchars($proposal['username']),
                "date" => htmlspecialchars(FN_FormatDate($proposal['recordinsert'])),
                "fields" => $this->ProposalTemplateText(implode(", ", $this->ProposalFieldTitles($Table, array_keys($this->ProposalChanges($proposal))))),
                "rowcount" => $rowcount > 0 ? (string) $rowcount : "",
            );
        }
        if (!$newrecords && count($changes) == 0)
            return FN_Translate("you may not do that");
        if ($reviewer)
            $this->ProposalNotificationsDisplayed($this->ProposalRecordLink(""));
        $nested = $this->ProposalNestedTables($Table);
        $items = array();
        foreach ($newrecords ? $this->PendingProposals($tablename, "", $viewer ? "" : $_FN['user']) : array() as $proposal) {
            $author = !$reviewer && $proposal['username'] === $_FN['user'];
            $addrows = $author ? $this->ProposalAddRowItems($proposal, $nested) : array();
            $rows = $this->ProposalRowItems($proposal, $nested, $reviewer, $author);
            $fields = array();
            foreach ($this->ProposalChanges($proposal) as $field => $change) {
                $fields[] = array(
                    "title" => htmlspecialchars($this->ProposalFieldTitle($Table, $field)),
                    "proposed" => $this->ProposalValueHtml($Table, $proposal, $tablename, 0, $field, $change['new']),
                );
            }
            $items[] = array(
                "id" => intval($proposal['id']),
                "recordtitle" => $this->ProposalTemplateText($this->ProposalRecordTitle($this->ProposalValues($proposal))),
                "user" => htmlspecialchars($proposal['username']),
                "date" => htmlspecialchars(FN_FormatDate($proposal['recordinsert'])),
                "token" => $this->CsrfToken("proposal|$tablename|" . $proposal['id']),
                "action" => $this->MakeLink(array("op" => "proposal", "id" => null), "&amp;"),
                "editlink" => $author ? $this->MakeLink(array("op" => "propose", "id" => null, "proposal" => $proposal['id']), "&amp;") : "",
                "reviewer" => $reviewer ? "1" : "",
                "author" => $author ? "1" : "",
                "readonly" => $reviewer || $author ? "" : "1",
                "fields" => $fields,
                "rows" => $rows,
                "hasrows" => count($rows) > 0 ? "1" : "",
                "addrows" => $addrows,
                "preview" => $this->ProposalPreview($this->ProposalValues($proposal)),
            );
        }
        $vars = array(
            "newrecords" => $newrecords ? "1" : "",
            "proposals" => $items,
            "empty" => count($items) == 0 ? "1" : "",
            "changes" => $changes,
            "nochanges" => count($changes) == 0 ? "1" : "",
            "newlink" => $this->CanProposeNewRecord() ? $this->MakeLink(array("op" => "propose", "id" => null, "proposal" => null), "&amp;") : "",
            "listlink" => $this->MakeLink(array("op" => null, "id" => null, "proposal" => null), "&amp;"),
        );
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/newproposals.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/newproposals.tp.html" : FN_FromTheme("{$this->moddir}/newproposals.tp.html", false);
        return FN_TPL_ApplyTplFile($tplfile, $vars);
    }

    /**
     * Linked tables whose new rows a proposal may carry: the innertables of the
     * main table listed in proposals_innertables
     * @param object $Table xmetadb_frm of the main table
     * @return array tablename => innertable (tablename, linkfield "recordfield,rowfield", titles)
     */
    protected function ProposalNestedTables($Table)
    {
        $enabled = array_filter(array_map("trim", explode(",", isset($this->config['proposals_innertables']) ? $this->config['proposals_innertables'] : "")));
        $ret = array();
        // from the descriptor: the page may have dropped some from $Table->innertables
        // (the openkis cave sheet renders the surveys itself)
        $innertables = count($enabled) > 0 ? xmetadb_xml2array($Table->xmltable->xmldescriptor, "innertable", false) : false;
        if (!is_array($innertables))
            return $ret;
        foreach ($innertables as $inner) {
            $link = explode(",", isset($inner['linkfield']) ? $inner['linkfield'] : "");
            if (isset($inner['tablename']) && in_array($inner['tablename'], $enabled, true) && count($link) == 2 && $link[0] !== "" && $link[1] !== "")
                $ret[$inner['tablename']] = $inner;
        }
        return $ret;
    }

    /**
     * @param array $inner innertable
     * @param string $lang title in this language (the mails), else the current one
     * @return string title of the linked table, HTML from the table descriptor
     */
    protected function ProposalNestedTitle($inner, $lang = "")
    {
        global $_FN;
        $lang = $lang !== "" ? $lang : $_FN['lang'];
        $title = isset($inner["frm_$lang"]) ? $inner["frm_$lang"] : $inner['tablename'];
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
     * @param string $tablename main table for the files of the record, else the linked table
     * @param string $key row key, 0 for the record
     * @param string $field
     * @param string $name file name stored in the proposal
     * @return string|false the proposed file, false when missing
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
     * Files sent with a form, for the file fields of its values
     * @param array $posted values of the form (getbypost)
     * @return array field => uploaded temporary file
     */
    protected function UploadedFormFiles($posted)
    {
        $files = array();
        foreach ($posted as $field => $value) {
            if (isset($_FILES[$field]['error'], $_FILES[$field]['tmp_name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK
                && is_uploaded_file($_FILES[$field]['tmp_name']))
                $files[$field] = $_FILES[$field]['tmp_name'];
        }
        return $files;
    }

    /**
     * Checks the files sent for the file fields of a proposal (record or row of a linked
     * table): each needs its file and a name that may be stored, an image field a real
     * image (the form checks only the browser uploads, not the files of the MCP server)
     * @param object $Form xmetadb_frm of the table
     * @param array $filevalues file field => name sent; the names become the stored ones
     * @param array $files file field => absolute path of the file
     * @param array $errors errors of the form, the ones of the files are added
     * @return array file field => path of the file to store
     */
    protected function ProposalFileSources($Form, &$filevalues, $files, &$errors)
    {
        $sources = array();
        foreach ($filevalues as $field => $value) {
            $name = XMETATable::SafeFileName($value);
            $source = isset($files[$field]) ? $files[$field] : "";
            $error = "";
            if ($name === "" || $source === "" || !is_file($source) || !is_readable($source))
                $error = FN_Translate("file not accepted");
            elseif (xmetadb_frm_type($Form->formvals[$field]) == "image" && !@getimagesize($source))
                $error = $Form->messages["_XMLDBNOTVALIDIMAGE"];
            if ($error !== "") {
                $errors[$field] = array("title" => $Form->formvals[$field]['title'], "field" => $field, "error" => $error);
                continue;
            }
            $filevalues[$field] = $name;
            $sources[$field] = $source;
        }
        return $sources;
    }

    /**
     * Copies the files of a proposal into its folder, each in place of the file the
     * field had before (a file proposed again)
     * @param string $proposalid
     * @param string $tablename main table for the files of the record, else the linked table
     * @param string $key row key, 0 for the record
     * @param array $names file field => stored name
     * @param array $sources file field => path, see ProposalFileSources
     * @return bool false when a file could not be saved
     */
    protected function StoreProposalFiles($proposalid, $tablename, $key, $names, $sources)
    {
        $dir = $this->ProposalFilesDir($proposalid) . "/$tablename/" . intval($key);
        foreach ($sources as $field => $source) {
            if (is_dir("$dir/$field"))
                FN_RemoveDir("$dir/$field");
            FN_MkDir("$dir/$field");
            // the web form hands the uploaded file, the MCP server a temporary one:
            // copy and remove works for both (rename may cross filesystems)
            if (!@copy($source, "$dir/$field/{$names[$field]}"))
                return false;
            @unlink($source);
        }
        return true;
    }

    /**
     * Hands to the table the files of the accepted file fields of a proposal, to be
     * stored by the insert or update that follows; a field whose file is missing is dropped
     * @param object $Form xmetadb_frm of the table
     * @param string $proposalid
     * @param string $tablename see StoreProposalFiles
     * @param string $key see StoreProposalFiles
     * @param array $values field => value
     * @return array $values without the fields whose file is missing
     */
    protected function SetProposalFiles($Form, $proposalid, $tablename, $key, $values)
    {
        // the table object is shared in the request: no file of a previous record
        $Form->xmltable->uploadedFiles = array();
        foreach ($values as $field => $value) {
            if (!isset($Form->formvals[$field]) || !xmetadb_is_file_field($Form->formvals[$field]))
                continue;
            $file = $this->ProposalRowFile($proposalid, $tablename, $key, $field, $value);
            if ($file)
                $Form->xmltable->SetFile($field, $file, $value);
            else
                unset($values[$field]);
        }
        return $values;
    }

    /**
     * The files of the record already proposed and not sent again: the form cannot show
     * them (they are not stored with the record) and saving it again must not drop them
     * @param object $Table xmetadb_frm of the main table
     * @param array $pending proposal being changed
     * @param array $changes new changes
     * @return array $changes with the files kept
     */
    protected function KeepProposedFiles($Table, $pending, $changes)
    {
        foreach ($this->ProposalChanges($pending) as $field => $change) {
            if (!isset($changes[$field]) && isset($Table->formvals[$field]) && xmetadb_is_file_field($Table->formvals[$field])
                && $this->ProposalRowFile($pending['id'], $this->ProposalsTableName(), 0, $field, $change['new']))
                $changes[$field] = $change;
        }
        return $changes;
    }

    /**
     * Proposed value of a field as safe HTML; a file links to op=proposalfile
     * @param object $Form xmetadb_frm of the table
     * @param array $proposal
     * @param string $tablename see StoreProposalFiles
     * @param string $key see StoreProposalFiles
     * @param string $field
     * @param string $value
     * @return string
     */
    protected function ProposalValueHtml($Form, $proposal, $tablename, $key, $field, $value)
    {
        if ((string) $value === "" || !isset($Form->formvals[$field]) || !xmetadb_is_file_field($Form->formvals[$field]))
            return $this->ProposalDisplayValue($Form, $field, $value);
        $link = $this->MakeLink(array("op" => "proposalfile", "id" => null, "proposal" => $proposal['id'], "table" => $tablename, "row" => $key, "field" => $field), "&amp;");
        return "<a href=\"$link\">" . str_replace(array("{", "}"), array("&#123;", "&#125;"), htmlspecialchars($value)) . "</a>";
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
     * @param bool $reviewer the rows can be accepted
     * @param bool $author the rows can be removed (the author of the proposal)
     * @param string $lang titles in this language (the mails), else the current one
     * @return array
     */
    protected function ProposalRowItems($proposal, $nested, $reviewer, $author = false, $lang = "")
    {
        $items = array();
        foreach ($this->ProposalRows($proposal) as $tablename => $rows) {
            if (!isset($nested[$tablename]))
                continue;
            $Form = FN_XMDBForm($tablename);
            foreach ($rows as $key => $values) {
                $list = array();
                foreach ($values as $field => $value) {
                    $list[] = array(
                        "title" => htmlspecialchars($this->ProposalFieldTitle($Form, $field, $lang)),
                        "value" => $this->ProposalValueHtml($Form, $proposal, $tablename, $key, $field, $value),
                    );
                }
                $id = "$tablename:$key";
                $items[] = array(
                    "tabletitle" => $this->ProposalNestedTitle($nested[$tablename], $lang),
                    "rowid" => htmlspecialchars($id),
                    "inputid" => "proposalrow_" . intval($proposal['id']) . "_" . preg_replace('/[^A-Za-z0-9_]/', '_', $id),
                    "reviewer" => $reviewer ? "1" : "",
                    "author" => $author ? "1" : "",
                    "removeaction" => $author ? $this->ProposalRowLink($proposal, $tablename, $key) : "",
                    "values" => $list,
                );
            }
        }
        return $items;
    }

    /**
     * op=proposerow: the author of a pending proposal adds a row of a linked table (form,
     * then POST xmetadbsave) or removes one (POST removerow). A proposed new record is
     * reached by the proposal parameter, a change by the id of the record: there the first
     * row creates the proposal when the user has none.
     * @return string HTML
     */
    function ProposalRowPage()
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        $nested = $this->ProposalNestedTables(FN_XMDBForm($tablename));
        $rowtable = FN_GetParam("table", $_GET);
        $id_record = FN_GetParam("id", $_GET, "html");
        if ($id_record === "") {
            if (!$this->CanProposeNewRecord() || !isset($nested[$rowtable]))
                return FN_Translate("you may not do that");
            $proposal = $this->PendingNewRecordProposal(FN_GetParam("proposal", $_GET), true);
            if (!$proposal)
                return FN_HtmlAlert(FN_Translate("this proposal has already been reviewed")) . $this->ProposalsListPage();
        } else {
            if (!$this->CanProposeRecord(FN_XMDBTable($tablename)->GetRecordByPrimaryKey($id_record)) || !isset($nested[$rowtable]))
                return FN_Translate("you may not do that");
            $pending = $this->PendingProposals($tablename, $id_record, $_FN['user']);
            // id "": SaveProposalRow creates it
            $proposal = count($pending) > 0 ? $pending[0] : array("id" => "", "table_unirecid" => $id_record, "username" => $_FN['user'], "changes" => "");
        }
        $inner = $nested[$rowtable];
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST') {
            // over post_max_size PHP drops the whole body: the form would come back without a word
            if (count($_POST) == 0)
                return FN_HtmlAlert(FN_Translate("the files sent are too large")) . $this->ProposalRowForm($proposal, $inner);
            if (!isset($_POST['fn_csrf']) || !hash_equals($this->ProposalRowToken($proposal), (string) $_POST['fn_csrf']))
                return FN_Translate("you may not do that");
            return isset($_POST['removerow']) ? $this->RemoveProposalRow($proposal, $inner) : $this->SaveProposalRow($proposal, $inner);
        }
        return $this->ProposalRowForm($proposal, $inner);
    }

    /**
     * Insert form of the linked table for a row of a proposal
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
        $id_record = (string) $proposal['table_unirecid'];
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/form.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/form.tp.html" : FN_FromTheme("{$this->moddir}/form.tp.html", false);
        $template = FN_TPL_ApplyTplString(file_get_contents($tplfile), array(
            "formaction" => $this->ProposalRowLink($proposal, $inner['tablename']),
            "urlcancel" => $this->MakeLink($id_record === "" ? array("op" => "proposal", "id" => null) : array("op" => "view", "id" => $id_record), "&"),
        ));
        $token = "<input type=\"hidden\" name=\"fn_csrf\" value=\"" . $this->ProposalRowToken($proposal) . "\" />";
        $template = preg_replace('/(<form\b[^>]*>)/i', '$1' . $token, $template, 1);
        $Form->SetlayoutTemplate($this->FormTemplateWithoutAutofill($template));
        $html = "<h2>" . FN_Translate("add to the proposal") . ": " . $this->ProposalNestedTitle($inner) . "</h2>";
        if ($id_record === "")
            $html .= "<p>" . FN_Translate("proposed new record") . ": " . htmlspecialchars($this->ProposalRecordTitle($this->ProposalValues($proposal))) . "</p>";
        else
            $html .= "<p>" . FN_Translate("propose a change") . ": " . htmlspecialchars($this->ProposalRecordTitle(FN_XMDBTable($this->ProposalsTableName())->GetRecordByPrimaryKey($id_record))) . "</p>";
        // the form object is shared in the request: hide the fields only for this form
        $formvals = $Form->formvals;
        $this->HideNonProposalFields($Form, $link[1]);
        $html .= $Form->HtmlShowInsertForm(FN_IsAdmin(), $Form->getbypost(), $errors);
        $Form->formvals = $formvals;
        return $html;
    }

    /**
     * Adds to a proposal the row of a linked table sent with ProposalRowForm.
     * The files go to the folder of the proposal under the name they will have in the table.
     * @param array $proposal id "" for a change not proposed yet: created here
     * @param array $inner innertable
     * @return string HTML
     */
    protected function SaveProposalRow($proposal, $inner)
    {
        $Form = FN_XMDBForm($inner['tablename']);
        $posted = $Form->getbypost();
        $result = $this->ProposeRowValues($proposal, $inner, $posted, $this->UploadedFormFiles($posted));
        switch ($result['status']) {
            case "nodata":
                return FN_HtmlAlert(FN_Translate("the proposal contains no data")) . $this->ProposalRowForm($proposal, $inner);
            case "invalid":
                return $this->ProposalRowForm($proposal, $inner, $result['errors']);
            case "toolong":
                return FN_HtmlAlert(FN_Translate("the proposal is too long")) . $this->ProposalRowForm($proposal, $inner);
            case "nosave":
                return FN_HtmlAlert(FN_Translate("the file could not be saved")) . $this->ProposalRowForm($result['proposal'], $inner);
            case "denied":
                return FN_Translate("you may not do that");
        }
        return FN_HtmlAlert(FN_Translate("added to the proposal")) . $this->ProposalPage($result['proposal']);
    }

    /**
     * Adds a row of a linked table to a proposal. Shared by the web form
     * (op=proposerow) and the MCP server: $values are the ones the form would send,
     * $files the source of each file field (field => absolute path of the file).
     * @param array $proposal id "" for a change not proposed yet: created here
     * @param array $inner innertable
     * @param array $values field => value
     * @param array $files field => absolute path of the file to store
     * @return array status nodata|invalid|toolong|denied|nosave|saved; errors; proposal; key
     */
    protected function ProposeRowValues($proposal, $inner, $values, $files = array())
    {
        global $_FN;
        $tablename = $inner['tablename'];
        $Form = FN_XMDBForm($tablename);
        $link = explode(",", $inner['linkfield']);
        $row = array();
        foreach ($this->ProposalFields($Form, $link[1]) as $field) {
            if (isset($values[$field]) && trim((string) $values[$field]) !== "")
                $row[$field] = (string) $values[$field];
        }
        if (count($row) == 0)
            return array("status" => "nodata");
        $errors = $Form->VerifyInsert($row);
        $filevalues = array();
        foreach ($row as $field => $value) {
            if (xmetadb_is_file_field($Form->formvals[$field]) && !isset($errors[$field]))
                $filevalues[$field] = $value;
        }
        $sources = $this->ProposalFileSources($Form, $filevalues, $files, $errors);
        if (count($errors) > 0)
            return array("status" => "invalid", "errors" => $errors);
        $row = array_merge($row, $filevalues);

        $rows = $this->ProposalRows($proposal);
        $key = 1;
        foreach (isset($rows[$tablename]) ? array_keys($rows[$tablename]) : array() as $k)
            $key = max($key, intval($k) + 1);
        $rows[$tablename][$key] = $row;
        $json = $this->ProposalChangesJson($this->ProposalChanges($proposal), $rows);
        if ($json === false)
            return array("status" => "toolong");
        $proposals = FN_XMDBTable("dbview_proposals");
        $created = (string) $proposal['id'] === "";
        if ($created) {
            // the folder of the files is named after the proposal: it must exist first
            $proposal = $proposals->InsertRecord(array(
                "tablename" => $this->ProposalsTableName(),
                "table_unirecid" => $proposal['table_unirecid'],
                "username" => $_FN['user'],
                "status" => "pending",
                "changes" => "{}",
                "recordinsert" => XMETATable::now(),
            ));
            if (!is_array($proposal) || empty($proposal['id']))
                return array("status" => "denied");
        }
        // a folder left by a removed row with the same key
        $this->RemoveProposalFiles($proposal['id'], $tablename, $key);
        if (!$this->StoreProposalFiles($proposal['id'], $tablename, $key, $row, $sources)) {
            $this->RemoveProposalFiles($proposal['id'], $tablename, $key);
            if ($created) {
                // nobody has been told of it yet
                $this->WithdrawProposal($proposal, false);
                $proposal['id'] = "";
            }
            return array("status" => "nosave", "proposal" => $proposal);
        }
        $proposals->UpdateRecord(array("id" => $proposal['id'], "changes" => $json));
        // the caller may add the next row to the returned proposal (MCP propose with several
        // rows): with the old changes that row overwrote this one (proposal 9, 2026-11-23)
        $proposal['changes'] = $json;
        FN_Log("{$_FN['mod']}||" . $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename row $key added to proposal {$proposal['id']}.");
        if ($created) {
            // a proposed change made only of rows: the reviewers learn of it now
            $record = FN_XMDBTable($this->ProposalsTableName())->GetRecordByPrimaryKey($proposal['table_unirecid']);
            $title = "{$_FN['sectionvalues']['title']} - " . $this->ProposalRecordTitle($record);
            $text = $this->ProposalText("change proposed by") . " {$_FN['user']}: $title";
            $this->ProposalNotifySent($this->ProposalReviewers($record), $text, $this->ProposalRecordLink($proposal['table_unirecid'], $proposal['id']), $title, $proposal, $record);
        } else {
            $this->ProposalNotifyChanged($proposal);
        }
        return array("status" => "saved", "proposal" => $proposal, "key" => $key);
    }

    /**
     * Removes a row (the row parameter) and its files from a proposal; a change left
     * with no field and no row is withdrawn
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
        if ((string) $proposal['id'] !== "" && isset($rows[$tablename][$key])) {
            unset($rows[$tablename][$key]);
            if (count($rows[$tablename]) == 0)
                unset($rows[$tablename]);
            $changes = $this->ProposalChanges($proposal);
            FN_Log("{$_FN['mod']}||" . $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename row $key removed from proposal {$proposal['id']}.");
            if (count($changes) == 0 && count($rows) == 0) {
                $this->WithdrawProposal($proposal);
                return FN_HtmlAlert(FN_Translate("proposal withdrawn")) . $this->ProposalPage($proposal);
            }
            // shorter than before: it fits
            $proposal['changes'] = $this->ProposalChangesJson($changes, $rows);
            FN_XMDBTable("dbview_proposals")->UpdateRecord(array("id" => $proposal['id'], "changes" => $proposal['changes']));
            $this->RemoveProposalFiles($proposal['id'], $tablename, $key);
            $this->ProposalNotifyChanged($proposal);
        }
        return FN_HtmlAlert(FN_Translate("removed from the proposal")) . $this->ProposalPage($proposal);
    }

    /**
     * op=proposalfile: sends the file of a row of a pending proposal to its author or to
     * its reviewers. Always as a download: nobody has reviewed it yet and an SVG or HTML
     * file opened in the browser could run scripts.
     * @return string HTML when the file cannot be sent
     */
    function ProposalFile()
    {
        global $_FN;
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "")
            return FN_Translate("you may not do that");
        $nested = $this->ProposalNestedTables(FN_XMDBForm($this->ProposalsTableName()));
        $proposal = $this->PendingProposalById(FN_GetParam("proposal", $_GET));
        $tablename = FN_GetParam("table", $_GET);
        $key = FN_GetParam("row", $_GET);
        $field = FN_GetParam("field", $_GET);
        $values = $rows = array();
        if ($proposal && $this->CanSeeProposal($proposal)) {
            // the files of the record are row 0 of the main table
            if ($tablename === $this->ProposalsTableName() && (string) $key === "0")
                $values = $this->ProposalValues($proposal);
            elseif (isset($nested[$tablename]))
                $rows = $this->ProposalRows($proposal);
            if (isset($rows[$tablename][$key]))
                $values = $rows[$tablename][$key];
        }
        $file = false;
        if (isset($values[$field])) {
            $Form = FN_XMDBForm($tablename);
            if (isset($Form->formvals[$field]) && xmetadb_is_file_field($Form->formvals[$field]))
                $file = $this->ProposalRowFile($proposal['id'], $tablename, $key, $field, $values[$field]);
        }
        if (!$file)
            return FN_HtmlAlert(FN_Translate("file not found"));
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
     * Inserts the accepted rows of a proposal into the linked tables, with their files,
     * linked to the record (just created for a proposed new record)
     * @param object $Table xmetadb_frm of the main table
     * @param array $proposal
     * @param array $record record of the proposal
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
                $values = $this->SetProposalFiles($Form, $proposal['id'], $tablename, $key, array_intersect_key($values, $allowed));
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
     * Buttons on top of the record list: propose a new record (for who cannot add
     * records) and the page of the pending proposals with their count. Who can add
     * records always gets the second one, the others when they have proposals to see
     * @return string HTML
     */
    function ListProposalsButtons()
    {
        global $_FN;
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "")
            return "";
        $viewer = $this->CanReviewProposal() || $this->CanAddRecord();
        $proposer = $this->CanProposeNewRecord();
        $count = count($this->VisibleChangeProposals());
        if ($viewer || $proposer)
            $count += count($this->PendingProposals($this->ProposalsTableName(), "", $viewer ? "" : $_FN['user']));
        $html = "";
        if ($proposer)
            $html .= "<a class=\"btn btn-primary\" href=\"" . $this->MakeLink(array("op" => "propose", "id" => null), "&amp;") . "\">" . FN_Translate("propose a new record") . "</a> ";
        if ($viewer || $count > 0)
            $html .= "<a class=\"btn " . ($count > 0 ? "btn-warning" : "btn-outline-secondary") . "\" href=\"" . $this->MakeLink(array("op" => "proposal", "id" => null), "&amp;") . "\">" . FN_Translate("proposals waiting for review") . ": $count</a>";
        return $html === "" ? "" : "<p class=\"dbview-propose-new\">$html</p>";
    }

    /**
     * Users who receive the proposals of a record. With the validation on (enable_validation)
     * the validators (members of ValidationGroup()). Otherwise: creator, users enabled on the
     * record, section administrators and members of the groups that can edit it; with no
     * group that can edit (any logged-in user) only creator, enabled users and
     * administrators. For a new record (empty $row) the section administrators and the
     * groups that can add records. Disabled users (active != 1, as for the login) are left
     * out; the site administrators when nobody else is left.
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
        $active = array();
        foreach ($allusers as $u) {
            if (!empty($u['active']) && $u['active'] == 1)
                $active[$u['username']] = $u;
        }
        $allusers = $active;
        if ($this->ValidationEnabled()) {
            $users = array();
            $groups = array($this->ValidationGroup());
        }
        if (count($groups) > 0) {
            foreach ($allusers as $u) {
                if (count(array_intersect($groups, explode(",", (string) $u['group']))) > 0)
                    $users[] = $u['username'];
            }
        }
        $users = array_values(array_intersect(array_diff(array_unique(array_filter($users)), array($_FN['user'])), array_keys($allusers)));
        if (count($users) == 0) {
            foreach ($allusers as $u) {
                if (intval($u['level']) >= 10 && $u['username'] != $_FN['user'])
                    $users[] = $u['username'];
            }
        }
        return $users;
    }

    /**
     * Text of the notifications and their mails in the default language of the site,
     * whatever the language of the page or without one (MCP server, scripts, where
     * the messages of dbview are not loaded)
     * @param string $text english text
     * @return string
     */
    protected function ProposalText($text)
    {
        global $_FN;
        static $loaded = array();
        $lang = $_FN['lang_default'];
        if (!isset($loaded[$lang])) {
            $loaded[$lang] = true;
            // the messages of finis first: FN_i18n reads them only for a language it does not know yet
            FN_Translate("_CHARSET", "", $lang);
            $current = $_FN['lang'];
            $_FN['lang'] = $lang;
            FN_LoadMessagesFolder($this->moddir);
            $_FN['lang'] = $current;
        }
        return FN_Translate($text, "Aa", $lang);
    }

    /**
     * Site notification to the users and, with proposals_mail, an html e-mail made with
     * proposalmail.tp.html: the link to the page on top, the lines, then the fields
     * and the linked rows of the proposal
     * @param array $users
     * @param string $text short text (notification and mail subject)
     * @param string $link absolute address of the page to open
     * @param bool $mailalert true: the mail goes to mailalert of the section too
     * @param array $lines more lines of the mail, under the subject
     * @param array|null $proposal row of dbview_proposals whose fields and rows the mail lists
     * @param array|null $record record of a proposed change, for the current values
     */
    protected function ProposalNotify($users, $text, $link, $mailalert, $lines = array(), $proposal = null, $record = null)
    {
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
        if (count($emails) == 0)
            return;
        $html = $this->ProposalMailHtml($text, $link, $lines, $proposal, $record);
        foreach (array_unique($emails) as $email)
            FN_SendMail($email, $text, $html, true);
    }

    /**
     * Body of the proposal mails (template proposalmail.tp.html of the section or of dbview)
     * @param string $text subject
     * @param string $link
     * @param array $lines
     * @param array|null $proposal
     * @param array|null $record
     * @return string HTML
     */
    protected function ProposalMailHtml($text, $link, $lines, $proposal, $record)
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        $Table = FN_XMDBForm($tablename);
        $current = is_array($record) ? "1" : "";
        $fields = array();
        $rows = array();
        if (is_array($proposal)) {
            foreach ($this->ProposalChanges($proposal) as $field => $change) {
                $fields[] = array(
                    "title" => htmlspecialchars($this->ProposalFieldTitle($Table, $field, $_FN['lang_default'])),
                    "current" => $current ? $this->ProposalDisplayValue($Table, $field, isset($record[$field]) ? $record[$field] : "", true) : "",
                    "proposed" => $this->ProposalValueHtml($Table, $proposal, $tablename, 0, $field, $change['new']),
                    "hascurrent" => $current,
                );
            }
            $rows = $this->ProposalRowItems($proposal, $this->ProposalNestedTables($Table), true, false, $_FN['lang_default']);
        }
        $items = array();
        foreach ($lines as $line)
            $items[] = array("text" => $this->ProposalTemplateText($line));
        $vars = array(
            "subject" => $this->ProposalTemplateText($text),
            "link" => htmlspecialchars($link),
            "lines" => $items,
            "fields" => $fields,
            "hasfields" => count($fields) > 0 ? "1" : "",
            "hascurrent" => $current,
            "rows" => $rows,
            "openlabel" => $this->ProposalTemplateText($this->ProposalText("open the proposal")),
            "fieldlabel" => $this->ProposalTemplateText($this->ProposalText("field")),
            "currentlabel" => $this->ProposalTemplateText($this->ProposalText("current value")),
            "proposedlabel" => $this->ProposalTemplateText($this->ProposalText("proposed value")),
            "sitename" => $this->ProposalTemplateText($_FN['sitename']),
        );
        $tplfile = file_exists("{$_FN['src_application']}/sections/{$_FN['mod']}/proposalmail.tp.html") ? "{$_FN['src_application']}/sections/{$_FN['mod']}/proposalmail.tp.html" : FN_FromTheme("{$this->moddir}/proposalmail.tp.html", false);
        return FN_TPL_ApplyTplFile($tplfile, $vars);
    }

    /**
     * Notification of a sent proposal: the reviewers and mailalert get it, the author
     * a confirmation that it has been forwarded (the result comes with the review);
     * both mails list the fields and the rows of the proposal
     * @param array $reviewers usernames
     * @param string $text short text for the reviewers
     * @param string $link absolute address of the proposal
     * @param string $title section and record, e.g. "Grotte - LI1 ..."
     * @param array $proposal row of dbview_proposals
     * @param array|null $record record of a proposed change
     */
    protected function ProposalNotifySent($reviewers, $text, $link, $title, $proposal, $record = null)
    {
        global $_FN;
        $this->ProposalNotify($reviewers, $text, $link, true, array(), $proposal, $record);
        $confirm = $this->ProposalText("your proposal has been sent to the users who manage this record") . ": $title";
        $this->ProposalNotify(array($_FN['user']), $confirm, $link, false, array($this->ProposalText("you will receive the result of the review")), $proposal, $record);
    }

    /**
     * Tells the reviewers and mailalert that the author changed a pending proposal. Nothing
     * is sent when a notification of the same proposal (sent, changed) went out less than
     * an hour ago: a few corrections in a row make one message, and the page of the
     * proposal always shows it as it is now. The author is not notified of a change they
     * made themselves.
     * @param array $proposal row of dbview_proposals, with the changes as they are now
     * @param array|null $record record of a proposed change, read when not given
     * @return bool true when the notification has been sent
     */
    protected function ProposalNotifyChanged($proposal, $record = null)
    {
        global $_FN;
        $id_record = (string) $proposal['table_unirecid'];
        $link = $this->ProposalRecordLink($id_record, $proposal['id']);
        // the notifications keep the link escaped by FN_AddNotification
        $last = FN_XMDBTable("fn_notifications")->GetRecords(array("context" => "dbview_proposals", "link" => FN_GetParam("link", array("link" => $link), "html")), 0, 1, "date", true, "date");
        if (is_array($last) && isset($last[0]['date']) && time() - strtotime($last[0]['date']) < 3600)
            return false;
        if ($id_record !== "" && !is_array($record))
            $record = FN_XMDBTable($this->ProposalsTableName())->GetRecordByPrimaryKey($id_record);
        $title = "{$_FN['sectionvalues']['title']} - " . $this->ProposalRecordTitle($id_record !== "" ? $record : $this->ProposalValues($proposal));
        $text = $this->ProposalText("proposal changed by") . " {$_FN['user']}: $title";
        $this->ProposalNotify($this->ProposalReviewers($id_record !== "" ? $record : array()), $text, $link, true, array(), $proposal, $id_record !== "" ? $record : null);
        return true;
    }

    /**
     * Absolute address of the record page, used by notifications and mails;
     * with an empty $id_record the page of the proposed new records
     * @param string $id_record
     * @param string $proposalid the page says whether the proposal is still pending (see ProposalClosedNotice)
     * @return string
     */
    protected function ProposalRecordLink($id_record, $proposalid = "")
    {
        global $_FN;
        $link = rtrim($_FN['siteurl'], "/") . "/index.php?mod=" . urlencode($_FN['mod']);
        $link .= (string) $id_record === "" ? "&op=proposal" : "&op=view&id=" . urlencode($id_record);
        return $link . ((string) $proposalid === "" ? "" : "&proposal=" . intval($proposalid));
    }

    /**
     * Notice for the address of a notification (proposal parameter, see ProposalRecordLink)
     * when the proposal is no longer pending: who opens it learns that it was withdrawn
     * or already reviewed instead of finding nothing
     * @return string HTML
     */
    function ProposalClosedNotice()
    {
        global $_FN;
        $proposalid = FN_GetParam("proposal", $_GET);
        if (empty($this->config['enable_proposals']) || $_FN['user'] == "" || $proposalid === ""
            || !file_exists("{$_FN['datadir']}/{$_FN['database']}/dbview_proposals.php"))
            return "";
        $messages = array(
            "withdrawn" => "this proposal has been withdrawn by its author",
            "accepted" => "this proposal has been accepted",
            "partial" => "this proposal has been partially accepted",
            "rejected" => "this proposal has been discarded",
        );
        $p = FN_XMDBTable("dbview_proposals")->GetRecordByPrimaryKey($proposalid);
        // the driver may match without case or type: check again
        if (!is_array($p) || (string) $p['id'] !== (string) $proposalid || $p['tablename'] !== $this->ProposalsTableName()
            || !isset($messages[$p['status']]) || !$this->CanSeeProposal($p))
            return "";
        $msg = FN_Translate($messages[$p['status']]);
        if ($p['status'] !== "withdrawn" && (string) $p['userreview'] !== "")
            $msg .= ", " . FN_Translate("reviewed by") . " " . $p['userreview'];
        if ((string) $p['recordreview'] !== "")
            $msg .= " (" . FN_FormatDate($p['recordreview']) . ")";
        return "<div class=\"alert alert-info\" role=\"status\">" . htmlspecialchars($msg) . "</div>";
    }

    /**
     * Withdraws a pending proposal and tells the reviewers, who were notified of it and
     * would no longer find it
     * @param array $proposal
     * @param bool $notify false for a proposal nobody has been told of yet
     */
    protected function WithdrawProposal($proposal, $notify = true)
    {
        global $_FN;
        $tablename = $this->ProposalsTableName();
        // recordreview: when it was withdrawn
        FN_XMDBTable("dbview_proposals")->UpdateRecord(array("id" => $proposal['id'], "status" => "withdrawn", "recordreview" => XMETATable::now()));
        $this->RemoveProposalFiles($proposal['id']);
        FN_Log("{$_FN['mod']}||" . $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename proposal {$proposal['id']} withdrawn.");
        if (!$notify)
            return;
        $id_record = (string) $proposal['table_unirecid'];
        $record = $id_record === "" ? array() : FN_XMDBTable($tablename)->GetRecordByPrimaryKey($id_record);
        if (!is_array($record))
            $record = array();
        $title = $this->ProposalRecordTitle($id_record === "" ? $this->ProposalValues($proposal) : $record);
        $text = $this->ProposalText("proposal withdrawn by") . " {$_FN['user']}: {$_FN['sectionvalues']['title']} - $title";
        $this->ProposalNotify($this->ProposalReviewers($record), $text, $this->ProposalRecordLink($id_record, $proposal['id']), true);
    }

    /**
     * Saves of the proposals of the current user one at a time, until the request ends:
     * a double click sends the form twice at the same moment and both requests would
     * find no pending proposal and add one each
     */
    protected function LockUserProposals()
    {
        global $_FN;
        static $lock = null;
        if ($lock)
            return;
        FN_MkDir("{$_FN['datadir']}/_cache");
        $lock = @fopen("{$_FN['datadir']}/_cache/dbview_proposals_" . md5($_FN['user']) . ".lock", "c");
        if ($lock)
            flock($lock, LOCK_EX);
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
     * @param string $lang see ProposalFieldTitle
     * @return array titles of the fields
     */
    protected function ProposalFieldTitles($Table, $fields, $lang = "")
    {
        $titles = array();
        foreach ($fields as $field)
            $titles[] = $this->ProposalFieldTitle($Table, $field, $lang);
        return $titles;
    }

    /**
     * Title of a field as plain text: the titles of the table definitions may hold
     * HTML entities (Localit&agrave;), which would be escaped twice in the pages and
     * left as they are in the mails
     * @param object $Table
     * @param string $field
     * @param string $lang title in this language when the table has it (the mails), else the current one
     * @return string
     */
    protected function ProposalFieldTitle($Table, $field, $lang = "")
    {
        $title = isset($Table->formvals[$field]['title']) ? (string) $Table->formvals[$field]['title'] : $field;
        if ($lang !== "" && !empty($Table->formvals[$field]["frm_$lang"]))
            $title = (string) $Table->formvals[$field]["frm_$lang"];
        return trim(html_entity_decode(strip_tags($title), ENT_QUOTES, "UTF-8"));
    }

    /**
     * Value of a field as safe HTML: option titles instead of keys, text without tags.
     * Braces are encoded so that the template engine does not read them as variables.
     * @param object $Table
     * @param string $field
     * @param string $value
     * @param bool $mail true: the word for an empty value in the language of the mails
     * @return string
     */
    protected function ProposalDisplayValue($Table, $field, $value, $mail = false)
    {
        $value = (string) $value;
        if ($value === "")
            return "<em>" . ($mail ? $this->ProposalText("empty") : FN_Translate("empty")) . "</em>";
        return $this->EscapeDisplayText($this->FieldDisplayText($Table, $field, $value));
    }

    /**
     * Value of a field as plain text: option titles instead of keys, no tags
     * @param object $Table
     * @param string $field
     * @param string $value
     * @return string
     */
    protected function FieldDisplayText($Table, $field, $value)
    {
        $value = (string) $value;
        if ($value !== "" && !empty($Table->formvals[$field]['options']) && is_array($Table->formvals[$field]['options'])) {
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
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES, "UTF-8"));
    }

    /**
     * Plain text as HTML. Braces are encoded so that the template engine does not
     * read them as variables.
     * @param string $text
     * @return string
     */
    protected function EscapeDisplayText($text)
    {
        return str_replace(array("{", "}"), array("&#123;", "&#125;"), nl2br(htmlspecialchars($text, ENT_QUOTES, "UTF-8")));
    }

    /**
     * Notice on top of the record page: the pending proposals with the review form
     * for the users who can edit the record; for the author of a proposal its rows of
     * the linked tables, with the buttons to remove them and to add more
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
        $reviewer = $this->CanReviewProposal($row);
        // who may edit the record sees every proposal, read only when not a reviewer
        $viewer = $reviewer || $this->CanUpdateRecord($row);
        $pending = $this->PendingProposals($tablename, $id_record, $viewer ? "" : $_FN['user']);
        if (count($pending) == 0)
            return "";
        if ($reviewer)
            $this->ProposalNotificationsDisplayed($this->ProposalRecordLink($id_record));
        $nested = $this->ProposalNestedTables($Table);
        $items = array();
        foreach ($pending as $proposal) {
            $author = !$reviewer && $proposal['username'] === $_FN['user'];
            $fields = array();
            foreach ($this->ProposalChanges($proposal) as $field => $change) {
                $current = isset($row[$field]) ? (string) $row[$field] : "";
                $fields[] = array(
                    "name" => htmlspecialchars($field),
                    "inputid" => "proposal_" . intval($proposal['id']) . "_" . preg_replace('/[^A-Za-z0-9_]/', '_', $field),
                    "title" => htmlspecialchars($this->ProposalFieldTitle($Table, $field)),
                    "current" => $this->ProposalDisplayValue($Table, $field, $current),
                    "proposed" => $this->ProposalValueHtml($Table, $proposal, $tablename, 0, $field, $change['new']),
                    // the record changed after the proposal was made
                    "changed" => ($current !== (string) $change['old']) ? "1" : "",
                    "reviewer" => $reviewer ? "1" : "",
                );
            }
            $rows = $this->ProposalRowItems($proposal, $nested, $reviewer, $author);
            $items[] = array(
                "id" => intval($proposal['id']),
                "user" => htmlspecialchars($proposal['username']),
                "date" => htmlspecialchars(FN_FormatDate($proposal['recordinsert'])),
                "token" => $reviewer ? $this->CsrfToken("proposal|$tablename|" . $proposal['id']) : $this->ProposalRowToken($proposal),
                "action" => $this->MakeLink(array("op" => $reviewer ? "proposal" : "view", "id" => $id_record), "&amp;"),
                "reviewer" => $reviewer ? "1" : "",
                "author" => $author ? "1" : "",
                "readonly" => $reviewer || $author ? "" : "1",
                "editlink" => $author ? $this->MakeLink(array("op" => "propose", "id" => $id_record), "&amp;") : "",
                "fields" => $fields,
                "hasfields" => count($fields) > 0 ? "1" : "",
                "rows" => $rows,
                "hasrows" => count($rows) > 0 ? "1" : "",
                "addrows" => $author ? $this->ProposalAddRowItems($proposal, $nested) : array(),
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
            // FN_AddNotification stores the link html-escaped; the page shows all the
            // proposals, whatever proposal the link names
            $nlink = preg_replace('/&(amp;)?proposal=\d+$/', '', (string) $n['link']);
            if ($n['username'] === $_FN['user'] && in_array($nlink, array($link, htmlspecialchars($link)), true) && empty($n['displayed']))
                FN_SetNotificationDisplayed($n['id']);
        }
    }
}
