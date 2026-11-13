<?php
/**
 * FNDBVIEW CRUD Trait
 * Create, Read, Update, Delete operations for dbview module
 *
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

trait FNDBVIEWCRUD
{
    /**
     * Update an existing record
     * @param object $Table
     * @return string HTML
     */
    function UpdateRecord($Table)
    {
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        $Table = FN_XMDBForm($tablename);
        $username = $_FN['user'];

        if ($username == "")
            die(FN_Translate("you may not do that"));

        $newvalues = $Table->getbypost();

        if (isset($_POST["_xmetadbform_pk_" . $Table->xmltable->primarykey]))
            $pkold = FN_GetParam("_xmetadbform_pk_" . $Table->xmltable->primarykey, $_POST);
        else
            $pkold = FN_GetParam($Table->xmltable->primarykey, $_POST);

        $pk = FN_GetParam($Table->xmltable->primarykey, $_POST);
        $oldvalues = $Table->xmltable->GetRecordByPrimarykey($pkold);

        if (!$this->CanUpdateRecord($oldvalues))
            return (FN_Translate("you may not do that"));

        $toupdate = $this->checkIfUpdateNeeded($oldvalues, $newvalues, $Table);

        $newvalues['update'] = time();
        $newvalues = $this->applyFieldTransformations($newvalues, $Table);

        $errors = $Table->VerifyUpdate($newvalues, $pkold);

        if ($pkold != $pk) {
            $newExists = $Table->xmltable->GetRecordByPrimaryKey($pk);
            if (isset($newExists[$Table->xmltable->primarykey])) {
                $newvalues[$Table->xmltable->primarykey] = $pkold;
                $errors[$Table->xmltable->primarykey] = array(
                    "title" => $Table->formvals[$Table->xmltable->primarykey]['title'],
                    "field" => $Table->xmltable->primarykey,
                    "error" => FN_Translate("there is already an item with this value")
                );
            }
        }

        if (count($errors) == 0) {
            if (FN_IsAdmin()) {
                if (!isset($_POST['userupdate']) || $_POST['userupdate'] == "") {
                    $_POST['userupdate'] = $newvalues['userupdate'] = $_FN['user'];
                }
            } else {
                $newvalues['userupdate'] = $_FN['user'];
            }

            if ($toupdate) {
                $newvalues = $this->saveRecordChanges($Table, $tablename, $oldvalues, $newvalues, $pkold);
                FN_Alert(FN_Translate("record updated"));
            }
        }

        $this->runUpdateHook($newvalues);

        return $this->EditRecordForm($newvalues[$Table->xmltable->primarykey], $Table, $errors);
    }

    /**
     * Writes the changes of a record: update date, previous version in the
     * history, cache invalidation and log. Used by the edit form and by the
     * accepted proposals, so both leave the same traces.
     * @param object $Table xmetadb_frm of the table
     * @param string $tablename
     * @param array $oldvalues the stored record
     * @param array $newvalues values in form format (all the fields or only the changed ones)
     * @param string $pkold primary key of the stored record
     * @return array $newvalues with recordupdate
     */
    protected function saveRecordChanges($Table, $tablename, $oldvalues, $newvalues, $pkold)
    {
        global $_FN;
        $newvalues['recordupdate'] = XMETATable::now();
        if ($this->config['enable_history']) {
            $this->saveRecordHistory($tablename, $Table, $oldvalues);
        }
        $Table->UpdateRecord($newvalues, $pkold);
        $this->InvalidateCache($tablename);
        $this->ViewCacheInvalidate($_FN['mod'], $pkold);
        if (isset($newvalues[$Table->xmltable->primarykey]) && $newvalues[$Table->xmltable->primarykey] != $pkold)
            $this->ViewCacheInvalidate($_FN['mod'], $newvalues[$Table->xmltable->primarykey]);
        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename modified.");
        return $newvalues;
    }

    /**
     * Calls the function_on_update callback of the section, if any
     * @param array $newvalues
     */
    protected function runUpdateHook($newvalues)
    {
        global $_FN;
        if (isset($_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_update'])) {
            $function = $_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_update'];
            if (function_exists($function)) {
                $function($newvalues);
            }
        }
    }

    /**
     * Check if record update is needed
     * @param array $oldvalues
     * @param array $newvalues
     * @param object $Table
     * @return bool
     */
    protected function checkIfUpdateNeeded($oldvalues, $newvalues, $Table)
    {
        $toupdate = false;
        if (is_array($oldvalues)) {
            foreach ($oldvalues as $k => $v) {
                if (isset($newvalues[$k]) && $oldvalues[$k] !== $newvalues[$k]) {
                    $toupdate = true;
                    break;
                }
                if (isset($newvalues[$k]) && $newvalues[$k] != "" && $oldvalues[$k] == $newvalues[$k] &&
                    xmetadb_is_file_field($Table->xmltable->fields[$k])) {
                    $filename = $Table->xmltable->getFilePath($oldvalues, $k);
                    if (filesize($filename) != filesize($_FILES[$k]['tmp_name'])) {
                        $toupdate = true;
                        break;
                    }
                }
            }
        }
        return $toupdate;
    }

    /**
     * Apply field transformations (uppercase, lowercase, callbacks)
     * @param array $newvalues
     * @param object $Table
     * @return array
     */
    protected function applyFieldTransformations($newvalues, $Table)
    {
        foreach ($Table->formvals as $f) {
            if (isset($newvalues[$f['name']]) && isset($Table->formvals[$f['name']]['frm_uppercase'])) {
                if ($Table->formvals[$f['name']]['frm_uppercase'] == "uppercase") {
                    $_POST[$f['name']] = $newvalues[$f['name']] = strtoupper($newvalues[$f['name']]);
                } elseif ($Table->formvals[$f['name']]['frm_uppercase'] == "lowercase") {
                    $_POST[$f['name']] = $newvalues[$f['name']] = strtolower($newvalues[$f['name']]);
                }
            }
            if (isset($Table->formvals[$f['name']]['frm_onrowupdate']) && $Table->formvals[$f['name']]['frm_onrowupdate'] != "") {
                $dv = $Table->formvals[$f['name']]['frm_onrowupdate'];
                $fname = $f['name'];
                $rv = "";
                eval("\$rv=$dv;");
                eval("\$newvalues" . "['$fname'] = '$rv' ;");
            }
        }
        return $newvalues;
    }

    /**
     * Save record history version
     * @param string $tablename
     * @param object $Table
     * @param array $oldvalues
     */
    protected function saveRecordHistory($tablename, $Table, $oldvalues)
    {
        $_FILES_bk = $_FILES;
        $_FILES = array();
        $tv = FN_XMDBTable($tablename . "_versions");

        foreach ($Table->xmltable->fields as $k => $v) {
            if (xmetadb_is_file_field($v) && $oldvalues[$k] != "") {
                $oldfile = $Table->xmltable->getFilePath($oldvalues, $k);
                $_FILES[$k]['name'] = $oldvalues[$k];
                $_FILES[$k]['tmp_name'] = $oldfile;
            }
        }
        $tv->InsertRecord($oldvalues);
        $_FILES = $_FILES_bk;
    }

    /**
     * Insert a new record
     * @param object $Table
     * @return string HTML
     */
    function InsertRecord($Table)
    {
        if (!$this->CanAddRecord())
            die(FN_Translate("you may not do that"));
        list($record, $errors) = $this->CreateRecord($Table, $Table->getbypost());
        if (!$record)
            return $this->NewRecordForm($Table, $errors);
        return FN_HtmlAlert(FN_Translate("the data were successfully inserted"))
            . $this->EditRecordForm($record[$Table->xmltable->primarykey], $Table, array(), true);
    }

    /**
     * Stores a new record owned by the current user: the values of the insert form
     * or of an accepted proposal, in form format. Runs the same steps for both:
     * field transformations, checks, record permissions, users of the record,
     * function_on_insert of the section and the mailalert notification.
     * @param object $Table
     * @param array $newvalues
     * @return array array(stored record or null, errors)
     */
    protected function CreateRecord($Table, $newvalues)
    {
        global $_FN;
        $config = $this->config;
        $tablename = $this->ProposalsTableName();
        $username = $_FN['user'];

        $newvalues['insert'] = time();
        $newvalues['update'] = time();
        $newvalues['username'] = $username;
        $newvalues = $this->applyFieldTransformations($newvalues, $Table);
        $errors = $Table->VerifyInsert($newvalues);
        if (count($errors) > 0)
            return array(null, $errors);

        $newvalues['recordupdate'] = XMETATable::now();
        $newvalues['recordinsert'] = XMETATable::now();
        $newvalues['userupdate'] = $username;

        // Record permissions management
        if (!empty($config['enable_permissions_edit_each_records']) && $config['enable_permissions_edit_each_records'] == 1) {
            if ($config['permissions_records_edit_groups'] != "") {
                $newvalues = $this->applyRecordPermissions($newvalues, $config);
            }
        }

        $record = $Table->xmltable->InsertRecord($newvalues);
        $this->InvalidateCache($tablename);
        $pk = $Table->xmltable->primarykey;

        // Update view counter if exists
        if (isset($record['view'])) {
            $record = $Table->xmltable->UpdateRecord(array("view" => $record[$pk], $pk => $record[$pk]));
        }

        // Update associated users table
        FN_XMDBTable("fieldusers")->InsertRecord(array("tablename" => $tablename, "username" => $username, "table_unirecid" => $record[$pk]));

        // Callback function on insert
        if (isset($_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_insert'])) {
            $function = $_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_insert'];
            if (function_exists($function)) {
                $function($record);
            }
        }

        FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $username . "||Table $tablename record added.");

        // Send notification email
        if (!empty($config['mailalert'])) {
            $this->sendInsertNotification($record, $Table, $config, $username);
        }
        return array($record, array());
    }

    /**
     * Apply permissions to inserting record
     * @param array $newvalues
     * @param array $config
     * @return array
     */
    protected function applyRecordPermissions($newvalues, $config)
    {
        global $_FN;
        $allAllowedGroups = explode(",", $config['permissions_records_edit_groups']);
        $groupinsert = array();

        foreach ($allAllowedGroups as $allAllowedGroup) {
            if ($allAllowedGroup != "" && FN_UserInGroup($_FN['user'], $allAllowedGroup)) {
                $groupinsert[] = $allAllowedGroup;
            }
        }
        $groupinsert = implode(",", $groupinsert);

        if (!$this->IsAdmin()) {
            $newvalues['groupinsert'] = $groupinsert;
        }
        return $newvalues;
    }

    /**
     * Send notification email for new record
     * @param array $record
     * @param object $Table
     * @param array $config
     * @param string $username
     */
    protected function sendInsertNotification($record, $Table, $config, $username)
    {
        global $_FN;

        $subject = FN_Translate("created new record in") . " {$_FN['sectionvalues']['title']}";
        if (!empty($record['name']))
            $subject .= ": " . $record['name'];

        $body = "\n" . FN_Translate("posted by") . " " . $username;
        $body .= "\n\n" . FN_Translate("to view go to the address") . ": ";
        $body .= "\n" . $_FN['siteurl'] . "/index.php?mod={$_FN['mod']}&op=view&id=" . $record[$Table->xmltable->primarykey];
        $body .= "\n\n" . $_FN['sitename'] . "";

        FN_SendMail($config['mailalert'], $subject, $body, false);
    }
}
