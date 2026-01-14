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

        if (!$this->CanAddRecord() && !$this->UserCanEditField($username, $oldvalues) && !$this->IsAdminRecord($oldvalues))
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
                $newvalues['recordupdate'] = XMETATable::now();

                // History management
                if ($config['enable_history']) {
                    $this->saveRecordHistory($tablename, $Table, $oldvalues);
                }

                $Table->UpdateRecord($newvalues, $pkold);
                $this->InvalidateCache($tablename);
                FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $_FN['user'] . "||Table $tablename modified.");
                FN_Alert(FN_Translate("record updated"));
            }
        }

        // Callback function on update
        if (isset($_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_update'])) {
            $function = $_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_update'];
            if (function_exists($function)) {
                $function($newvalues);
            }
        }

        return $this->EditRecordForm($newvalues[$Table->xmltable->primarykey], $Table, $errors);
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
                    ($Table->xmltable->fields[$k]->type == "file" || $Table->xmltable->fields[$k]->type == "image")) {
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
            if (($v->type == "file" || $v->type == "image") && $oldvalues[$k] != "") {
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
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        $html = "";
        $username = $_FN['user'];

        if (!$this->CanAddRecord())
            die(FN_Translate("you may not do that"));

        $newvalues = $Table->getbypost();
        $newvalues['insert'] = time();
        $newvalues['update'] = time();
        $newvalues['username'] = $username;

        $newvalues = $this->applyFieldTransformations($newvalues, $Table);

        $errors = $Table->VerifyInsert($newvalues);

        if (count($errors) == 0) {
            $newvalues['recordupdate'] = XMETATable::now();
            $newvalues['recordinsert'] = XMETATable::now();
            $newvalues['userupdate'] = $_FN['user'];
            $newvalues['username'] = $_FN['user'];

            // Record permissions management
            if (!empty($config['enable_permissions_edit_each_records']) && $config['enable_permissions_edit_each_records'] == 1) {
                if ($config['permissions_records_edit_groups'] != "") {
                    $newvalues = $this->applyRecordPermissions($newvalues, $config);
                }
            }

            $record = $Table->xmltable->InsertRecord($newvalues);
            $this->InvalidateCache($tablename);

            // Update view counter if exists
            if (isset($record['view'])) {
                $nrec = array();
                $nrec['view'] = $record[$Table->xmltable->primarykey];
                $nrec[$Table->xmltable->primarykey] = $record[$Table->xmltable->primarykey];
                $record = $Table->xmltable->UpdateRecord($nrec);
            }

            // Update associated users table
            $users = FN_XMDBTable("fieldusers");
            $r = array();
            $r['tablename'] = $tablename;
            $r['username'] = $username;
            $r['table_unirecid'] = $record[$Table->xmltable->primarykey];
            $users->InsertRecord($r);

            // Callback function on insert
            if (isset($_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_insert'])) {
                $function = $_FN['modparams'][$_FN['mod']]['editorparams']['table']['function_on_insert'];
                if (function_exists($function)) {
                    $function($record);
                }
            }

            FN_Log("{$_FN['mod']}", $_SERVER['REMOTE_ADDR'] . "||" . $username . "||Table $tablename record added.");
            $html .= FN_HtmlAlert(FN_Translate("the data were successfully inserted"));

            // Send notification email
            if (!empty($config['mailalert'])) {
                $this->sendInsertNotification($record, $Table, $config, $r['username']);
            }

            $html .= $this->EditRecordForm($record[$Table->xmltable->primarykey], $Table, $errors, true);
        } else {
            $html .= $this->NewRecordForm($Table, $errors);
        }
        return $html;
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
