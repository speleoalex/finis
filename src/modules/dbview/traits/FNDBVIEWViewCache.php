<?php
/**
 * Cache of the html of a record sheet (FNDBVIEW::ViewRecordPage) for the
 * anonymous visitors, on files.
 *
 * Enabled per section by $_FN['dbview_view_cache'] = array(section => seconds
 * of validity) or an int for every section (0 = off). The sheet is cached only
 * for a GET with no parameter besides mod, op=view, id and lang, when nobody is
 * logged in and the record is visible to the anonymous visitors (checked by
 * the caller): the html is the same for all of them.
 *
 * Files: {datadir}/_cache/pages/<section>/<theme>/<lang>/<id div 1000>/<id>.html,
 * one per record and language with a deterministic name, always overwritten:
 * their number is bounded by the data, not by the traffic. A file is valid
 * when it is newer than the tables of the sheet ($_FN['dbview_view_cache_tables']
 * [section] = table names, default the table of the section: the "updated"
 * stamp touched by XMETATable at every write) and than the code that renders
 * it (the files of the section, of dbview and of the forms, plus
 * $_FN['dbview_view_cache_files'] = extra glob patterns), and younger than its
 * validity. A record saved or deleted from dbview drops its own files at once.
 * Files of records that are never asked again (deleted records, unused
 * languages) are removed by a sweep run on average once every 100 writes
 * (older than $_FN['dbview_view_cache_keep'] days, default 30); FN_ClearCache()
 * drops the whole folder.
 */
trait FNDBVIEWViewCache
{

    /**
     * Validity in seconds of the cache of a section, 0 when it is off.
     *
     * @param string $mod section
     * @return int
     */
    function ViewCacheMaxAge($mod)
    {
        global $_FN;
        if (empty($_FN['dbview_view_cache']))
            return 0;
        $setting = $_FN['dbview_view_cache'];
        if (is_array($setting))
            return isset($setting[$mod]) ? intval($setting[$mod]) : 0;
        return intval($setting);
    }

    /**
     * True when the current request may be served from the cache or stored
     * into it: cache on for the section, anonymous visitor, plain GET of the
     * sheet (mod, op=view, id, lang and nothing else).
     *
     * @param string $mod section
     * @return bool
     */
    function ViewCacheUsable($mod)
    {
        global $_FN;
        if ($this->ViewCacheMaxAge($mod) <= 0)
            return false;
        if (!empty($_FN['user']))
            return false;
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != "GET" || !empty($_POST))
            return false;
        if (!isset($_GET['op']) || $_GET['op'] != "view" || !isset($_GET['id']))
            return false;
        foreach ($_GET as $k => $v)
        {
            if (!in_array($k, array("mod", "op", "id", "lang")))
                return false;
        }
        return true;
    }

    /**
     * Folder of the cached sheets of a section.
     *
     * @param string $mod section
     * @return string
     */
    function ViewCacheDir($mod)
    {
        global $_FN;
        return "{$_FN['datadir']}/_cache/pages/" . preg_replace('/[^A-Za-z0-9_-]/', '', $mod);
    }

    /**
     * File of the cached sheet of a record in the current theme and language.
     *
     * @param string $mod section
     * @param string $id record id
     * @return string|false false when the id cannot be a file name
     */
    function ViewCacheFile($mod, $id)
    {
        global $_FN;
        $id = (string) $id;
        if (!preg_match('/^[A-Za-z0-9_.-]{1,64}$/', $id) || $id[0] == ".")
            return false;
        $theme = preg_replace('/[^A-Za-z0-9_-]/', '', isset($_FN['theme']) ? (string) $_FN['theme'] : "");
        $lang = preg_replace('/[^a-z]/', '', strtolower(isset($_FN['lang']) ? (string) $_FN['lang'] : ""));
        $sub = ctype_digit($id) ? strval(intval(intval($id) / 1000)) : "x";
        return $this->ViewCacheDir($mod) . "/$theme/$lang/$sub/$id.html";
    }

    /**
     * Time of the last change of the data and of the code of the sheets of a
     * section: a cached file older than this is stale.
     *
     * @param string $mod section
     * @return int unix time
     */
    function ViewCacheStamp($mod)
    {
        global $_FN;
        static $stamps = array();
        if (isset($stamps[$mod]))
            return $stamps[$mod];
        $stamp = 0;
        //---- data: the "updated" stamp of the tables of the sheet ------------>
        $tables = array();
        if (!empty($_FN['dbview_view_cache_tables'][$mod]) && is_array($_FN['dbview_view_cache_tables'][$mod]))
            $tables = $_FN['dbview_view_cache_tables'][$mod];
        elseif (!empty($this->config['tables']))
            $tables = array($this->config['tables']);
        foreach ($tables as $table)
        {
            $dir = "{$_FN['datadir']}/{$_FN['database']}/$table";
            clearstatcache(true, "$dir/updated");
            if (file_exists("$dir/updated"))
                $stamp = max($stamp, (int) filectime("$dir/updated"), (int) filemtime("$dir/updated"));
            elseif (file_exists("$dir.php"))
                $stamp = max($stamp, (int) filemtime("$dir.php"));
        }
        //---- code: the files that render the sheet ---------------------------->
        $patterns = array(
            "{$_FN['src_application']}/sections/$mod/*.php",
            "{$_FN['src_application']}/sections/$mod/*.tp.html",
            "{$_FN['src_application']}/modules/dbview/*.tp.html",
            "{$_FN['src_finis']}/modules/dbview/*.php",
            "{$_FN['src_finis']}/modules/dbview/traits/*.php",
            "{$_FN['src_finis']}/modules/dbview/*.tp.html",
            "{$_FN['src_finis']}/include/xmetadb_frm.php",
        );
        if (!empty($_FN['theme']))
        {
            $patterns[] = "{$_FN['src_application']}/themes/{$_FN['theme']}/modules/dbview/*.tp.html";
            $patterns[] = "{$_FN['src_finis']}/themes/{$_FN['theme']}/modules/dbview/*.tp.html";
        }
        if (!empty($_FN['dbview_view_cache_files']) && is_array($_FN['dbview_view_cache_files']))
            $patterns = array_merge($patterns, $_FN['dbview_view_cache_files']);
        foreach ($patterns as $pattern)
        {
            $files = glob($pattern);
            if (is_array($files))
            {
                foreach ($files as $file)
                    $stamp = max($stamp, (int) filemtime($file));
            }
        }
        $stamps[$mod] = $stamp;
        return $stamp;
    }

    /**
     * Cached html of a sheet, when present and still valid.
     *
     * @param string $mod section
     * @param string $id record id
     * @return string|false
     */
    function ViewCacheGet($mod, $id)
    {
        $file = $this->ViewCacheFile($mod, $id);
        if ($file === false)
            return false;
        clearstatcache(true, $file);
        if (!is_file($file))
            return false;
        $mtime = (int) filemtime($file);
        if (time() - $mtime > $this->ViewCacheMaxAge($mod))
            return false;
        // same second as a change: regenerated (the stamp has a resolution of one second)
        if ($mtime <= $this->ViewCacheStamp($mod))
            return false;
        $html = file_get_contents($file);
        return ($html === false || $html === "") ? false : $html;
    }

    /**
     * Stores the html of a sheet (temporary file and rename: a reader never
     * sees a partial file) and, now and then, sweeps a folder of old files.
     *
     * @param string $mod section
     * @param string $id record id
     * @param string $html
     * @return bool
     */
    function ViewCacheSet($mod, $id, $html)
    {
        $file = $this->ViewCacheFile($mod, $id);
        if ($file === false || !is_string($html) || $html === "")
            return false;
        $dir = dirname($file);
        if (!is_dir($dir))
        {
            FN_MkDir($dir);
            if (!is_dir($dir))
                return false;
        }
        $tmp = $file . "." . uniqid("", true) . ".tmp";
        if (@file_put_contents($tmp, $html) === false)
            return false;
        @chmod($tmp, 0666);
        if (!@rename($tmp, $file))
        {
            @unlink($tmp);
            return false;
        }
        if (mt_rand(1, 100) == 1)
            $this->ViewCacheSweep($mod);
        return true;
    }

    /**
     * Drops the cached sheets of a record in every theme and language, e.g.
     * after it was saved or deleted.
     *
     * @param string $mod section
     * @param string $id record id
     */
    function ViewCacheInvalidate($mod, $id)
    {
        $file = $this->ViewCacheFile($mod, $id);
        if ($file === false)
            return;
        $dir = $this->ViewCacheDir($mod);
        $tail = substr($file, strlen($dir)); // /<theme>/<lang>/<sub>/<id>.html
        $parts = explode("/", ltrim($tail, "/"));
        if (count($parts) != 4)
            return;
        $files = glob("$dir/*/*/{$parts[2]}/{$parts[3]}");
        if (is_array($files))
        {
            foreach ($files as $f)
                @unlink($f);
        }
    }

    /**
     * Drops every cached sheet of a section (every section when empty).
     *
     * @param string $mod section
     */
    function ViewCacheClear($mod = "")
    {
        global $_FN;
        $dir = ($mod == "") ? "{$_FN['datadir']}/_cache/pages" : $this->ViewCacheDir($mod);
        if (is_dir($dir))
            FN_RemoveDir($dir);
    }

    /**
     * Removes the files older than $_FN['dbview_view_cache_keep'] days (30) in
     * one folder of the section, chosen at random, and the whole folder of the
     * section when its files are more than twice the records of the table
     * times the languages (something is wrong with the keys: start again).
     *
     * @param string $mod section
     */
    function ViewCacheSweep($mod)
    {
        global $_FN;
        $dir = $this->ViewCacheDir($mod);
        if (!is_dir($dir))
            return;
        $keep = isset($_FN['dbview_view_cache_keep']) ? intval($_FN['dbview_view_cache_keep']) : 30;
        if ($keep <= 0)
            $keep = 30;
        $limit = time() - $keep * 86400;
        $folders = glob("$dir/*/*/*", GLOB_ONLYDIR);
        if (is_array($folders) && count($folders))
        {
            $folder = $folders[mt_rand(0, count($folders) - 1)];
            $files = glob("$folder/*.html");
            if (is_array($files))
            {
                foreach ($files as $file)
                {
                    if ((int) filemtime($file) < $limit)
                        @unlink($file);
                }
            }
            $tmps = glob("$dir/*/*/*/*.tmp");
            if (is_array($tmps))
            {
                foreach ($tmps as $tmp)
                {
                    if ((int) filemtime($tmp) < time() - 3600)
                        @unlink($tmp);
                }
            }
        }
        $all = glob("$dir/*/*/*/*.html");
        if (is_array($all) && count($all) > 1000 && !empty($this->config['tables']))
        {
            $table = FN_XMDBTable($this->config['tables']);
            $records = $table ? intval($table->GetNumRecords()) : 0;
            $languages = isset($_FN['languages']) ? count(explode(",", (string) $_FN['languages'])) : 1;
            if ($records > 0 && count($all) > 2 * $records * max(1, $languages))
                $this->ViewCacheClear($mod);
        }
    }
}
