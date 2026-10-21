<?php
/**
 * FNDBVIEW Export Trait
 * Data export (CSV, Sitemap, RSS, Offline) for dbview module
 *
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

trait FNDBVIEWExport
{
    /**
     * Export data in CSV format
     * @param array $data
     * @param string $filename
     */
    function SaveToCSV($data, $filename)
    {
        $sep = ",";
        $str = "";
        foreach ($data as $row) {
            $arraycols = array();
            foreach ($row as $cell) {
                $arraycols[] = "\"" . str_replace("\"", "\"\"", $cell) . "\"";
            }
            $str .= implode($sep, $arraycols) . "\n";
        }
        FN_SaveFile($str, $filename, "application/vnd.ms-excel");
    }

    /**
     * Generate XML sitemap for Google
     */
    function WriteSitemap()
    {
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];
        $titlef = explode(",", $config['titlefield']);
        $titlef = $titlef[0];

        if ($config['generate_googlesitemap']) {
            $sBasePath = $url = "http://" . $_SERVER["HTTP_HOST"] . DirName($_SERVER['PHP_SELF']);
            $Table = FN_XMDBTable($tablename);
            $fieldstoread = "$titlef|" . $Table->primarykey;
            $data = $Table->GetRecords(false, false, false, false, false, $fieldstoread);

            $handle = fopen("sitemap-$tablename.xml", "w");
            fwrite($handle, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.google.com/schemas/sitemap/0.84\">\n");
            fwrite($handle, "<url>\n\t<loc>$sBasePath/index-$tablename.html</loc>\n</url>\n");

            if (is_array($data)) {
                foreach ($data as $row) {
                    $id_record = $row[$Table->primarykey];
                    fwrite($handle, "<url>\n\t<loc>$sBasePath/" . FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=view&amp;id=$id_record") . "</loc>\n</url>\n");
                }
            }
            fwrite($handle, "\n</urlset>");
            fclose($handle);
        }
        $this->GenerateRSS();
    }

    /**
     * Generate RSS feed
     */
    function GenerateRSS()
    {
        // Placeholder method for future extensions
    }

    /**
     * Get translated record values
     * @param mixed $id
     * @return array
     */
    function GetRecordValues($id)
    {
        global $_FN;
        $table = FN_XMDBForm($this->config['tables']);
        return $table->GetRecordTranslatedByPrimarykey($id);
    }

    /**
     * Generate offline form for record update
     * @param mixed $id
     */
    function GenOfflineUpdate($id)
    {
        global $_FN;

        if (!$this->CanViewRecord($id)) {
            $this->GenOfflineInsert();
            return;
        }

        $frm = $this->offlineFormPrepare();
        $linkform = FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=edit&amp;id=$id", "&", true);
        $vals = $this->GetRecordValues($id);

        $strform = "<form id=\"form\" action=\"$linkform\"  enctype=\"multipart/form-data\" method=\"post\" target='_blank'>" . $frm->HtmlShowUpdateForm($id) . "</form>";

        $vars = $this->offlineFormVars($frm, "update");
        $vars['form'] = $strform;
        $vars['formtitle'] = FN_Translate("form for updating");
        $vars['mailsubject'] = $_FN['sections'][$_FN['mod']]['title'] . " - " . FN_Translate("form for updating");
        $vars['doctitle'] = $_FN['sections'][$_FN['mod']]['title'] . " - " . FN_Translate("form for updating") . " " . $this->offlineRecordTitle($vals);
        $str = file_get_contents(FN_FromTheme("{$this->moddir}/form_offline.html"));
        $str = FN_TPL_ApplyTplString($str, $vars);

        $code = isset($vals['code']) ? $vals['code'] : '';
        $filename = $this->sanitizeFilename($_FN['sections'][$_FN['mod']]['title'] . "-" . FN_Translate("form for updating") . "-$code");

        FN_SaveFile($str, "$filename.html");
    }

    /**
     * Generate offline form for record insert
     */
    function GenOfflineInsert()
    {
        global $_FN;

        $frm = $this->offlineFormPrepare();
        $linkform = FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=new", "&", true);

        $strform = "<form id=\"form\" action=\"$linkform\"  enctype=\"multipart/form-data\" method=\"post\" target='_blank'>" . $frm->HtmlShowInsertForm() . "</form>";

        $vars = $this->offlineFormVars($frm, "insert");
        $vars['form'] = $strform;
        $vars['formtitle'] = FN_Translate("insertion form");
        $vars['mailsubject'] = $_FN['sections'][$_FN['mod']]['title'] . " - " . FN_Translate("insertion form");
        $vars['doctitle'] = $_FN['sections'][$_FN['mod']]['title'] . " - " . FN_Translate("insertion form");
        $str = file_get_contents(FN_FromTheme("{$this->moddir}/form_offline.html"));
        $str = FN_TPL_ApplyTplString($str, $vars);

        $filename = $this->sanitizeFilename($_FN['sections'][$_FN['mod']]['title'] . "-" . FN_Translate("insertion form"));

        FN_SaveFile($str, "$filename.html");
    }

    /**
     * Build the XMETADBForm used by the offline form, adapted for people
     * who fill it in without access to the site:
     * - fields listed in config 'offlineform_hidden_fields' and file/image
     *   fields are not shown (a file cannot be stored in an HTML page);
     * - fields listed in config 'offlineform_text_fields' (foreign key lists,
     *   multicheck on big tables) become plain text inputs, which keeps the
     *   generated file small;
     * - rows get an id and css classes so the template can style them.
     * @return XMETADBForm
     */
    protected function offlineFormPrepare()
    {
        $frm = FN_XMDBform($this->config['tables']);

        // Legacy adaptation kept for existing installations
        if (isset($frm->formvals['COLL'])) {
            $frm->formvals['COLL']['title'] = "inserire numeri separati da virgola";
            $frm->formvals['COLL']['type'] = "text";
            $frm->formvals['COLL']['options'] = null;
        }

        $textfields = $this->offlineConfigList('offlineform_text_fields');
        foreach ($textfields as $name) {
            if (!isset($frm->formvals[$name])) {
                continue;
            }
            $frm->formvals[$name]['frm_type'] = "varchar";
            $frm->formvals[$name]['type'] = "string";
            $frm->formvals[$name]['options'] = null;
            $frm->formvals[$name]['foreignkey'] = "";
            if (empty($frm->formvals[$name]['frm_size'])) {
                $frm->formvals[$name]['frm_size'] = 60;
            }
        }
        if (count($textfields) > 0) {
            // Field classes are instantiated in the constructor: reload them
            $frm->LoadFieldsClasses();
        }

        $hiddenfields = $this->offlineConfigList('offlineform_hidden_fields');
        foreach ($frm->formvals as $name => $field) {
            if (!isset($field['name'])) {
                continue;
            }
            $hide = in_array($name, $hiddenfields) || $this->offlineIsFileField($field);
            // A field that opens or closes a group must stay, otherwise the
            // group markup would be lost
            if ($hide && empty($field['frm_group']) && empty($field['frm_endgroup'])) {
                $frm->formvals[$name]['frm_show'] = 0;
            }
        }

        $frm->SetlayoutTemplate("
<table class=\"offline-table\">
<!-- contents -->
<!-- group -->
	<tr class=\"offline-group\"><td colspan=\"2\"><b>{groupname}</b></td></tr>
<!-- end_group -->
<!-- item -->
	<tr id=\"offline_row_{fieldname}\" class=\"offline-row offline-type-{fieldtype}\">
		<td class=\"offline-label\">{title}<!-- error --><span style=\"color:red\"><br />{error}</span><!-- end_error --><div class=\"offline-help\">{help}</div></td>
		<td class=\"offline-input\">{input}</td>
	</tr>
<!-- end_item -->
<!-- endgroup -->
<!-- end_endgroup -->
<!-- end_contents -->
</table>
");
        return $frm;
    }

    /**
     * True if the field stores an uploaded file (widget "file" or "image").
     * Self contained so that it also works with older xmetadb versions.
     * @param array $field
     * @return bool
     */
    protected function offlineIsFileField($field)
    {
        $type = !empty($field['frm_type']) ? $field['frm_type'] : (isset($field['type']) ? $field['type'] : "");
        return ($type == "file" || $type == "image");
    }

    /**
     * Comma separated config option as array of trimmed names
     * @param string $key
     * @return array
     */
    protected function offlineConfigList($key)
    {
        if (empty($this->config[$key])) {
            return array();
        }
        $list = array_map('trim', explode(",", $this->config[$key]));
        return array_filter($list, 'strlen');
    }

    /**
     * Template variables shared by insert and update offline forms
     * @param XMETADBForm $frm
     * @param string $mode insert|update
     * @return array
     */
    protected function offlineFormVars($frm, $mode)
    {
        global $_FN;
        $titlefields = explode(",", $this->config['titlefield']);
        $vars = array();
        $vars['mode'] = $mode;
        $vars['version'] = date("Y-m-d");
        $vars['adminemail'] = $_FN['log_email_address'];
        $vars['titlefield'] = implode(",", array_map('trim', $titlefields));
        return $vars;
    }

    /**
     * Record title (title fields joined) used in the document title
     * @param array $vals
     * @return string
     */
    protected function offlineRecordTitle($vals)
    {
        $parts = array();
        foreach (explode(",", $this->config['titlefield']) as $field) {
            $field = trim($field);
            if ($field != "" && !empty($vals[$field])) {
                $parts[] = $vals[$field];
            }
        }
        return implode(" ", $parts);
    }

    /**
     * Sanitize filename by removing special characters
     * @param string $text
     * @return string
     */
    protected function sanitizeFilename($text)
    {
        $text = strtoupper(str_replace(" ", "_", $text));

        // Remove accents
        $accents = array(
            '/à|á/' => 'a',
            '/è|é/' => 'e',
            '/ì|í/' => 'i',
            '/ò|ó/' => 'o',
            '/ù|ú/' => 'u'
        );

        foreach ($accents as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        // Remove non-alphanumeric characters
        $text = preg_replace("/[^A-Za-z_0-9]/", "_", $text);
        $text = str_replace(array("-", "."), "_", $text);

        return $text;
    }
}
