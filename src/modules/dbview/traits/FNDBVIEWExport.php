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

        $str = file_get_contents(FN_FromTheme("{$_FN['src_finis']}/modules/dbview/form_offline.html"));
        $frm = FN_XMDBform($this->config['tables']);
        $linkform = FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=edit&amp;id=$id", "&", true);
        $vals = $this->GetRecordValues($id);

        $strform = "<form id=\"form\" action=\"$linkform\"  enctype=\"multipart/form-data\" method=\"post\" target='_blank'>" . $frm->HtmlShowUpdateForm($id) . "</form>";

        $vars = array();
        $vars['form'] = $strform;
        $vars['version'] = date("Y-m-d");
        $vars['adminemail'] = $_FN['log_email_address'];
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

        $str = file_get_contents(FN_FromTheme("{$_FN['src_finis']}/modules/dbview/form_offline.html"));
        $frm = FN_XMDBform($this->config['tables']);
        $linkform = FN_RewriteLink("index.php?mod={$_FN['mod']}&amp;op=new", "&", true);

        // Special fields adaptation
        $frm->formvals['COLL']['title'] = "inserire numeri separati da virgola";
        $frm->formvals['COLL']['type'] = "text";
        $frm->formvals['COLL']['options'] = null;

        $strform = "<form id=\"form\" action=\"$linkform\"  enctype=\"multipart/form-data\" method=\"post\" target='_blank'>" . $frm->HtmlShowInsertForm() . "</form>";

        $vars = array();
        $vars['form'] = $strform;
        $vars['version'] = date("Y-m-d");
        $vars['adminemail'] = $_FN['log_email_address'];
        $str = FN_TPL_ApplyTplString($str, $vars);

        $filename = $this->sanitizeFilename($_FN['sections'][$_FN['mod']]['title'] . "-" . FN_Translate("insert form"));

        FN_SaveFile($str, "$filename.html");
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
