<?php
namespace Xmetadb;
/**
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2024
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 * @package xmetadb
 * 
 */
class XMETADatabase
{
    private $path;
    private $databasename;    
    private $params;
    // number of SELECTs this object ran natively (see nativeSelect)
    public $nativequeries = 0;

    function __construct($databasename, $path = "misc",$params=array())
    {
        $this->databasename = $databasename;
        $this->path = $path;
        $this->params = $params;
       //dprint_r($this->params);
        
    }
    function encode_preg($str)
    {
        return preg_quote($str, '/');
    }

    /**
     * Split a VALUES(...) content string into individual values,
     * respecting single-quoted strings so commas inside quotes are not split.
     * Strips surrounding quotes and whitespace from each token.
     *
     * Examples:
     *   "'Alice', 'Rome'"         → ['Alice', 'Rome']
     *   "'hello, world', '42'"    → ['hello, world', '42']
     *   "'it''s here', 'x'"       → ["it's here", 'x']
     */
    private function splitValues($str)
    {
        $tokens = [];
        $len    = strlen($str);
        $i      = 0;
        $current = '';
        while ($i < $len) {
            $ch = $str[$i];
            if ($ch === "'" || $ch === '"') {
                // Consume quoted string
                $quote = $ch;
                $i++;
                while ($i < $len) {
                    if ($str[$i] === $quote) {
                        // Handle escaped quote ('' inside single-quoted string)
                        if ($i + 1 < $len && $str[$i + 1] === $quote) {
                            $current .= $quote;
                            $i += 2;
                        } else {
                            $i++;
                            break;
                        }
                    } else {
                        $current .= $str[$i];
                        $i++;
                    }
                }
            } elseif ($ch === ',') {
                $tokens[] = trim($current);
                $current  = '';
                $i++;
            } else {
                $current .= $ch;
                $i++;
            }
        }
        $tokens[] = trim($current);
        return $tokens;
    }
    /**
     * Execute a SQL-like query against the xmetadb database.
     *
     * Supported: SELECT, INSERT, UPDATE, DELETE, DESCRIBE, SHOW TABLES.
     * WHERE conditions are evaluated via PHP eval() — this is by design to
     * keep the implementation self-contained. Do not pass untrusted user
     * input directly as a query string without sanitisation.
     *
     * Example:
     *   SELECT * FROM users WHERE active = '1' ORDER BY name LIMIT 1,10
     */
    function Query($query)
    {
        $databasename = $this->databasename;
        // Cache key includes path so different XMETADatabase instances do not share entries
        static $tblcache = array();
        $qitems = array();
        $qitems['query'] = $query;
        $qitems['fields'] = false;
        $qitems['tablename'] = false;
        $qitems['option'] = false;
        $qitems['orderby'] = false;
        $qitems['min'] = false;
        $qitems['length'] = false;
        $qitems['where'] = false;
        $tbl = [];
        //SELECT
        if (preg_match("/^SELECT/is", $query))
        {
            if (preg_match("/^SELECT( DISTINCT | )([_a-zA-Z0-9, \(\)\*]+|\*|COUNT\(\*\)) FROM ([_a-zA-Z0-9,]+)(.+)/i", "$query ", $t1))
            {
                $qitems['fields'] = trim(ltrim($t1[2]));
                $qitems['tablename'] = trim(ltrim($t1[3]));
                $qitems['option'] = trim(ltrim($t1[1]));
                $tablenames = explode(",", $qitems['tablename']);
                $rightselect = $t1[4];
                foreach ($tablenames as $tablename)
                {
                    $cid = $this->path . '/' . $databasename . '/' . $tablename;
                    if (!isset($tblcache[$cid]))
                        $tblcache[$cid] = XMETATable::xmetadbTable($databasename, $tablename, $this->path,$this->params);
                    //	$tblcache[$cid] = new XMETATable($databasename,$tablename,$this->path);
                    $tbl[$tablename] = &$tblcache[$cid];
                    if (!file_exists($this->path . "/$databasename/{$tablename}.php"))
                        return "xmetadb: unknow table {$tablename}";
                }
                //$tbl = new XMETATable($databasename, $qitems['tablename'], $this->path);
                if ($qitems['tablename'] == "")
                    return "xmetadb: syntax error";
                if (preg_match("/WHERE (.+)/i", $rightselect, $t1))
                {
                    $qitems['where'] = $t1[1];
                }
                else
                {
                    $qitems['where'] = "";
                }
                //---pulisco where ---->
                if (preg_match("/(.+)(ORDER BY )/i", $qitems['where'], $dt3))
                {
                    $qitems['where'] = $dt3[1];
                }
                if (preg_match("/(.+)(LIMIT )/i", $qitems['where'], $dt3))
                {
                    $qitems['where'] = $dt3[1];
                }
                //---pulisco where ----<
                //limit a,b
                if (preg_match("/LIMIT ([0-9]+),([0-9]+)/i", $rightselect, $dt3))
                {
                    $qitems['min'] = $dt3[1];
                    $qitems['length'] = $dt3[2];
                }
                elseif (preg_match("/LIMIT ([0-9]+)/i", $rightselect, $dt3))
                {
                    $qitems['min'] = 0;
                    $qitems['length'] = $dt3[1];
                }
                // ORDER BY: capture everything after ORDER BY, strip trailing LIMIT if present
                if (preg_match("/ORDER BY\s+(.+)/i", $rightselect, $dt2))
                {
                    $tt = $this->iExplode(" LIMIT ", $dt2[1]);
                    $qitems['orderby'] = trim($tt[0]);
                }
            }
            //----CONDIZIONE---------------------->
            //dprint_r($qitems);
            //dprint_r($where2);
            //----CONDIZIONE----------------------<
            // On MySQL, run the query in the database (indexes, no full table
            // read): nativeSelect() returns null when it can't, and the PHP
            // engine below takes over
            if (count($tbl) == 1)
            {
                $native = $this->nativeSelect($qitems, reset($tbl));
                if ($native !== null)
                    return $native;
            }
            $ret = $this->getrecords($qitems, $tbl);
            $count = false;
            if (stristr($qitems['fields'], "COUNT(*)"))
            {
                // ADDED BY DANIELE FRANZA 2/02/2009: start
                if (preg_match("/ AS /is", $qitems['fields']))
                {
                    $as = $this->iExplode(" AS ", $qitems['fields']);
                    $k2 = trim(ltrim($as[1]));
                    $k1 = trim(ltrim($as[0]));
                }
                else
                {
                    $k1 = $k2 = 'COUNT(*)';
                }
                $count = true;
            }
            if ($count)
                return array(0 => array("$k2" => count($ret)));
            else
                return $ret;
        }
        //DESCRIBE  TODO
        if (preg_match("/^DESCRIBE/is", $query))
        {
            if (preg_match("/^DESCRIBE ([a-zA-Z0-9_]+)/is", "$query ", $t1))
            {
                $qitems['tablename'] = trim(ltrim($t1[1]));
                if (!file_exists($this->path . "/$databasename/{$qitems['tablename']}.php"))
                    return "xmetadb: unknown table {$qitems['tablename']}";
                $cid = $this->path . '/' . $databasename . '/' . $qitems['tablename'];
                if (!isset($tblcache[$cid]))
                    $tblcache[$cid] = XMETATable::xmetadbTable($databasename, $qitems['tablename'], $this->path, $this->params);
                $t = &$tblcache[$cid];
                $ret = array();
                foreach ($t->fields as $field)
                {
                    $ret[] = ['Field' => $field->name, 'Type' => $field->type, 'Null' => 'NO', 'Key' => $field->primarykey, 'Extra' => $field->extra];
                }
                return $ret;
            }
        }
        //SHOW TABLES
        if (preg_match("/^SHOW TABLES/is", trim(ltrim($query))))
        {
            $path = ($this->path . "/" . $this->databasename . "/*");
            $files = glob($path);
            $ret = array();
            foreach ($files as $file)
            {
                if (!is_dir($file))
                    $ret[] = array("Tables_in_" . $this->databasename => preg_replace('/.php$/s', '', basename($file)));
            }
            return $ret;
        }
        //INSERT
        if (preg_match("/^INSERT/is", $query))
        {
            // Field name pattern allows digits: [a-zA-Z0-9_ ,]+
            if (preg_match("/^INSERT[ ]+INTO([a-zA-Z0-9\\`\\._ ]+)\\(([a-zA-Z0-9_ ,]+)\\)[ ]+VALUES[ ]+\\((.*)\\)/i", "$query ", $t1))
            {
                $qitems['tablename'] = trim($t1[1]);
                $qitems['fields']    = trim($t1[2]);
                $qitems['values']    = trim($t1[3]);
                $cid = $this->path . '/' . $databasename . '/' . $qitems['tablename'];
                if (!isset($tblcache[$cid]))
                    $tblcache[$cid] = XMETATable::xmetadbTable($databasename, $qitems['tablename'], $this->path, $this->params);
                $tbl = &$tblcache[$cid];
                $fields = array_map('trim', explode(",", $qitems['fields']));
                $values = $this->splitValues($qitems['values']);
                $recordstoinsert = array();
                if (count($fields) === count($values))
                {
                    for ($i = 0; $i < count($fields); $i++)
                    {
                        $recordstoinsert[$fields[$i]] = $values[$i];
                    }
                }
                else
                    return "xmetadb: syntax error (fields/values count mismatch)";
                return $tbl->InsertRecord($recordstoinsert);
            }
        }
        //UPDATE EXPERIMENTAL TODO
        if (preg_match("/^UPDATE/is", $query))
        {
            if (preg_match("/^UPDATE[ ]+([_a-zA-Z0-9]+)[ ]+SET[ ]+(.*)/i", "$query ", $t1))
            {
                //dprint_r($t1);
                $qitems['tablename'] = trim(ltrim($t1[1]));
                $rightselect = $t1[2];
                $tablename = $qitems['tablename'];
                if (!file_exists($this->path . "/$databasename/{$tablename}.php"))
                    return "xmetadb: unknow table {$tablename}";
                $tbl[$tablename] = XMETATable::xmetadbTable($databasename, $tablename, $this->path,$this->params);
                $qitems['fields'] = $tbl[$tablename]->primarykey;

                if (preg_match("/WHERE (.+)/i", $rightselect, $t1))
                {
                    $pos = strrpos(strtolower($rightselect), " where ") + 7;
                    $qitems['where'] = trim(ltrim(substr($rightselect, $pos)));
                    $qitems['updatestring'] = trim(ltrim(substr($rightselect, 0, $pos - 7)));
                }
                else
                {
                    $qitems['where'] = "";
                }
                //---pulisco where ---->
                if (preg_match("/(.+)(ORDER BY )/i", $qitems['where'], $dt3))
                {
                    $qitems['where'] = $dt3[1];
                }
                if (preg_match("/(.+)(LIMIT )/i", $qitems['where'], $dt3))
                {
                    $qitems['where'] = $dt3[1];
                }
                //---pulisco where ----<
                //limit a,b
                if (preg_match("/LIMIT ([0-9]+),([0-9]+)/i", $rightselect, $dt3))
                {
                    $qitems['min'] = $dt3[1];
                    $qitems['length'] = $dt3[2];
                }
                elseif (preg_match("/LIMIT ([0-9]+)/i", $rightselect, $dt3))
                {
                    $qitems['min'] = 0;
                    $qitems['length'] = $dt3[1];
                }
                // ORDER BY: capture everything after ORDER BY, strip trailing LIMIT if present
                if (preg_match("/ORDER BY\s+(.+)/i", $rightselect, $dt2))
                {
                    $tt = $this->iExplode(" LIMIT ", $dt2[1]);
                    $qitems['orderby'] = trim($tt[0]);
                }
                //dprint_r($qitems);
                $allrecords = $this->getrecords($qitems, $tbl);
                if (!is_array($allrecords))
                    return null;
                //dprint_r($allrecords);
                $matchesarray = array();
                $updeteitems = explode(",", $qitems['updatestring']);
                foreach ($updeteitems as $v)
                {
                    $tmpupdate = explode("=", $v);
                    $newvalues[trim(ltrim($tmpupdate[0]))] = trim(ltrim($tmpupdate[1], " '\""), " '\"");
                }
                foreach ($allrecords as $recordtodel)
                {
                    $newvalues[$tbl[$tablename]->primarykey] = $recordtodel[$tbl[$tablename]->primarykey];
                    $tbl[$tablename]->UpdateRecord($newvalues);
                }
            }
            //UPDATE UPDATE users SET email = 'speleoalex@pippo',name = 'speleo' WHERE username LIKE '%s%' LIMIT 1,1
        }
        //DELETE TODO
        if (preg_match("/^DELETE/is", $query))
        {
            if (preg_match("/^DELETE[ ]+FROM ([_a-zA-Z0-9,]+)(.+)/i", "$query ", $t1))
            {
                //campi
                $qitems['tablename'] = trim(ltrim($t1[1]));
                $qitems['option'] = trim(ltrim($t1[1]));
                $tablename = $qitems['tablename'];
                $rightselect = $t1[2];
                if (!file_exists($this->path . "/$databasename/{$tablename}.php"))
                    return "xmetadb: unknow table {$tablename}";
                $tbl[$tablename] = XMETATable::xmetadbTable($databasename, $tablename, $this->path,$this->params);
                $qitems['fields'] = $tbl[$tablename]->primarykey;
                if ($qitems['tablename'] == "")
                    return "xmetadb: syntax error";
                if (preg_match("/WHERE (.+)/i", $rightselect, $t1))
                {
                    $qitems['where'] = $t1[1];
                }
                else
                {
                    $qitems['where'] = "";
                }
                //---pulisco where ---->
                if (preg_match("/(.+)(ORDER BY )/i", $qitems['where'], $dt3))
                {
                    $qitems['where'] = $dt3[1];
                }
                if (preg_match("/(.+)(LIMIT )/i", $qitems['where'], $dt3))
                {
                    $qitems['where'] = $dt3[1];
                }
                //---pulisco where ----<
                //limit a,b
                if (preg_match("/LIMIT ([0-9]+),([0-9]+)/i", $rightselect, $dt3))
                {
                    $qitems['min'] = $dt3[1];
                    $qitems['length'] = $dt3[2];
                }
                elseif (preg_match("/LIMIT ([0-9]+)/i", $rightselect, $dt3))
                {
                    $qitems['min'] = 0;
                    $qitems['length'] = $dt3[1];
                }
                // ORDER BY: capture everything after ORDER BY, strip trailing LIMIT if present
                if (preg_match("/ORDER BY\s+(.+)/i", $rightselect, $dt2))
                {
                    $tt = $this->iExplode(" LIMIT ", $dt2[1]);
                    $qitems['orderby'] = trim($tt[0]);
                }
                $allrecords = $this->getrecords($qitems, $tbl);
                if (!is_array($allrecords))
                    return null;
                foreach ($allrecords as $recordtodel)
                {
                    $pkkey = $tbl[$tablename]->primarykey;
                    $tbl[$tablename]->DelRecord($recordtodel[$pkkey]);
                }
                return "";
            }
        }
        return null;
    }

    /**
     * Runs a parsed single-table SELECT directly on the MySQL driver, giving the
     * same results as getrecords() without reading the whole table into PHP.
     *
     * The caller's SQL is never sent as is: the WHERE is tokenized and rebuilt
     * from comparisons between a descriptor field and a quoted string, an
     * integer or another field, joined by AND / OR and parentheses; field names
     * are quoted and values escaped. Anything else (several tables, ORDER BY on
     * more than one field, COUNT(*) with LIMIT or DISTINCT, unknown fields,
     * other drivers) returns null and the PHP engine runs the query instead.
     * $_FN['xmetadb_native_query'] = "0" turns this off.
     *
     * Kept identical to the PHP engine:
     *  - NULL values are compared as ''
     *  - LIKE / = '%x%', 'x%', '%x' are case-insensitive and '_' is literal;
     *    without % they are an exact match
     *  - ORDER BY sorts empty values first, then numbers numerically, then text
     *  - LIMIT a,b starts from row a counting from 1, LIMIT n is ignored
     *  - SELECT * returns the descriptor's fields, COUNT(*) an integer
     *
     * @return array|null
     */
    private function nativeSelect($qitems, $tbl)
    {
        if (isset($this->params['xmetadb_native_query']) && (string)$this->params['xmetadb_native_query'] === "0")
            return null;
        if (!is_object($tbl) || !isset($tbl->driverclass) || !($tbl->driverclass instanceof XMETATable_mysql))
            return null;
        $drv = $tbl->driverclass;
        if (empty($drv->conn) || empty($drv->sqltable) || !is_array($tbl->fields))
            return null;
        $fields = $tbl->fields;
        $distinct = strtoupper($qitems['option']) == "DISTINCT";
        $haslimit = (bool)$qitems['min'];

        // fields
        $select = trim($qitems['fields']);
        $count = false;
        $countkey = "COUNT(*)";
        $aliases = array();
        if ($select === "*")
        {
            $cols = array();
            foreach ($fields as $name => $field)
                $cols[] = "`$name`";
            $selectsql = implode(",", $cols);
        }
        elseif (preg_match('/^COUNT\(\*\)(\s+AS\s+([A-Za-z0-9_]+))?$/i', $select, $m))
        {
            if ($haslimit || $distinct)
                return null;
            $count = true;
            if (!empty($m[2]))
                $countkey = $m[2];
            $selectsql = "COUNT(*) AS `c`";
        }
        else
        {
            $cols = array();
            foreach (explode(",", $select) as $part)
            {
                if (!preg_match('/^\s*([A-Za-z_][A-Za-z0-9_]*)(\s+AS\s+([A-Za-z0-9_]+))?\s*$/i', $part, $m) || !isset($fields[$m[1]]))
                    return null;
                $alias = !empty($m[3]) ? $m[3] : $m[1];
                $aliases[] = $alias;
                $cols[] = "`{$m[1]}` AS `$alias`";
            }
            $selectsql = implode(",", $cols);
        }
        $sql = "SELECT " . ($distinct ? "DISTINCT " : "") . "$selectsql FROM {$drv->sqltable}";

        // WHERE
        if (trim($qitems['where']) !== "")
        {
            $tokens = $this->nativeTokenize($qitems['where']);
            if ($tokens === null)
                return null;
            $pos = 0;
            $where = $this->nativeExpr($tokens, $pos, $fields, $drv->conn);
            if ($where === null || $pos != count($tokens))
                return null;
            $sql .= " WHERE $where";
        }

        // ORDER BY (one field)
        if (!$count && trim($qitems['orderby']) !== "")
        {
            if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(\s+(ASC|DESC))?$/i', trim($qitems['orderby']), $m) || !isset($fields[$m[1]]))
                return null;
            $f = "`{$m[1]}`";
            $dir = (isset($m[3]) && strtoupper($m[3]) == "DESC") ? "DESC" : "ASC";
            $numeric = "$f REGEXP '^[[:space:]]*[-+]?([0-9]+[.]?[0-9]*|[.][0-9]+)([eE][-+]?[0-9]+)?[[:space:]]*$'";
            // ties keep the table order, as the PHP engine's stable sort does
            $tie = (is_string($tbl->primarykey) && isset($fields[$tbl->primarykey])) ? ", `{$tbl->primarykey}`" : "";
            $sql .= " ORDER BY CASE WHEN IFNULL($f,'') = '' THEN 0 WHEN $numeric THEN 1 ELSE 2 END $dir,"
                . " CASE WHEN $numeric THEN $f + 0 END $dir, IFNULL($f,'') $dir$tie";
        }

        // LIMIT
        if ($haslimit)
        {
            $offset = max(0, intval($qitems['min']) - 1);
            $length = $qitems['length'] ? intval($qitems['length']) : "18446744073709551615";
            $sql .= " LIMIT $offset,$length";
        }

        $res = $drv->dbQuery($sql);
        if (!is_array($res))
            return null;
        $this->nativequeries++;
        if ($count)
            return array(0 => array($countkey => isset($res[0]['c']) ? intval($res[0]['c']) : 0));
        if ($aliases)
        {
            // the PHP engine turns NULL into false in the selected fields
            foreach ($res as $k => $row)
                foreach ($aliases as $alias)
                    if (!isset($row[$alias]))
                        $res[$k][$alias] = false;
        }
        return $res;
    }

    /**
     * Splits a WHERE clause into tokens: array(type, value) with type one of
     * ( ) op word string number. Returns null on anything else.
     */
    private function nativeTokenize($where)
    {
        $tokens = array();
        $len = strlen($where);
        $i = 0;
        while ($i < $len)
        {
            $ch = $where[$i];
            if (ctype_space($ch))
            {
                $i++;
            }
            elseif ($ch == "(" || $ch == ")")
            {
                $tokens[] = array($ch, $ch);
                $i++;
            }
            elseif ($ch == "'" || $ch == '"')
            {
                // backslash escapes the quote and itself, as in a PHP string
                $str = "";
                $i++;
                $closed = false;
                while ($i < $len)
                {
                    $c = $where[$i];
                    if ($c == "\\" && $i + 1 < $len && ($where[$i + 1] == $ch || $where[$i + 1] == "\\"))
                    {
                        $str .= $where[$i + 1];
                        $i += 2;
                    }
                    elseif ($c == $ch)
                    {
                        $i++;
                        $closed = true;
                        break;
                    }
                    else
                    {
                        $str .= $c;
                        $i++;
                    }
                }
                if (!$closed)
                    return null;
                $tokens[] = array("string", $str);
            }
            elseif (preg_match('/\G(<>|>=|<=|=|>|<)/', $where, $m, 0, $i))
            {
                $tokens[] = array("op", $m[1]);
                $i += strlen($m[1]);
            }
            elseif (preg_match('/\G[0-9]+(?![A-Za-z_])/', $where, $m, 0, $i))
            {
                $tokens[] = array("number", $m[0]);
                $i += strlen($m[0]);
            }
            elseif (preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $where, $m, 0, $i))
            {
                $tokens[] = array("word", $m[0]);
                $i += strlen($m[0]);
            }
            else
            {
                return null;
            }
        }
        return $tokens;
    }

    /**
     * expr := term ((AND | OR) term)*
     */
    private function nativeExpr($tokens, &$pos, $fields, $conn)
    {
        $sql = $this->nativeTerm($tokens, $pos, $fields, $conn);
        if ($sql === null)
            return null;
        while ($pos < count($tokens) && $tokens[$pos][0] == "word" && in_array(strtoupper($tokens[$pos][1]), array("AND", "OR")))
        {
            $op = strtoupper($tokens[$pos][1]);
            $pos++;
            $right = $this->nativeTerm($tokens, $pos, $fields, $conn);
            if ($right === null)
                return null;
            $sql .= " $op $right";
        }
        return $sql;
    }

    /**
     * term := ( expr ) | field op value     value := string | number | field
     */
    private function nativeTerm($tokens, &$pos, $fields, $conn)
    {
        if (!isset($tokens[$pos]))
            return null;
        if ($tokens[$pos][0] == "(")
        {
            $pos++;
            $sql = $this->nativeExpr($tokens, $pos, $fields, $conn);
            if ($sql === null || !isset($tokens[$pos]) || $tokens[$pos][0] != ")")
                return null;
            $pos++;
            return "($sql)";
        }
        if (!isset($tokens[$pos + 2]) || $tokens[$pos][0] != "word" || !isset($fields[$tokens[$pos][1]]))
            return null;
        $f = "`{$tokens[$pos][1]}`";
        if ($tokens[$pos + 1][0] == "op")
            $op = $tokens[$pos + 1][1];
        elseif ($tokens[$pos + 1][0] == "word" && strtoupper($tokens[$pos + 1][1]) == "LIKE")
            $op = "=";
        else
            return null;
        list($type, $value) = $tokens[$pos + 2];
        $pos += 3;
        $ordering = in_array($op, array(">", ">=", "<", "<="));

        if ($type == "word")
        {
            if (!isset($fields[$value]))
                return null;
            return "IFNULL($f,'') $op IFNULL(`$value`,'')";
        }
        if ($type == "number")
            return $this->nativeNumericCompare($f, $op, $value);
        if ($type != "string")
            return null;

        $esc = function ($s) use ($conn) {
            return "'" . $conn->real_escape_string($s) . "'";
        };
        $like = function ($s) {
            return addcslashes($s, "\\%_");
        };
        $plen = strlen($value);
        if ($op == "=")
        {
            if ($plen >= 2 && $value[0] == "%" && $value[$plen - 1] == "%")
                return "LOWER(IFNULL($f,'')) LIKE LOWER(" . $esc("%" . $like(substr($value, 1, -1)) . "%") . ")";
            if ($plen >= 1 && $value[0] == "%")
                return "LOWER(IFNULL($f,'')) LIKE LOWER(" . $esc("%" . $like(substr($value, 1))) . ")";
            if ($plen >= 1 && $value[$plen - 1] == "%")
                return "LOWER(IFNULL($f,'')) LIKE LOWER(" . $esc($like(substr($value, 0, -1)) . "%") . ")";
            if ($value === "")
                return "($f = '' OR $f IS NULL)";
            return "$f = " . $esc($value);
        }
        if ($op == "<>")
        {
            if ($plen >= 2 && $value[0] == "%" && $value[$plen - 1] == "%")
                return "LOWER(IFNULL($f,'')) NOT LIKE LOWER(" . $esc("%" . $like(substr($value, 1, -1)) . "%") . ")";
            if ($value === "")
                return "$f <> ''";
            return "($f <> " . $esc($value) . " OR $f IS NULL)";
        }
        // > >= < <= : numeric strings compare as numbers, as in PHP
        if (preg_match('/^-?[0-9]+(\.[0-9]+)?$/', $value))
            return $this->nativeNumericCompare($f, $op, $value);
        return "IFNULL($f,'') $op " . $esc($value);
    }

    /**
     * Field compared with a number the way PHP 8 does it: as numbers when the
     * value is a numeric string, as strings otherwise (MySQL alone would turn
     * any text into 0).
     */
    private function nativeNumericCompare($f, $op, $number)
    {
        $v = "IFNULL($f,'')";
        $numeric = "$v REGEXP '^[[:space:]]*[-+]?([0-9]+[.]?[0-9]*|[.][0-9]+)([eE][-+]?[0-9]+)?[[:space:]]*$'";
        return "(CASE WHEN $numeric THEN $v + 0 $op $number ELSE $v $op '$number' END)";
    }

    private function getrecords($qitems, $tbl)
    {
        //dprint_r($qitems['where']);
        $where2 = $this->convertwhere($qitems['where']);
        //dprint_r($where2);
        //per ottimizzare prendo solo i fields che mi interessano--->
        if ($qitems['fields'] == "*" || preg_match('/COUNT\(\*\)/is', $qitems['fields']))
        {
            $fieldstoget = false;
        }
        else
        {
            //dprint_r($qitems);
            //per performance coinvolgo solamente i fields interessati dalla query
            if ($qitems['orderby'] != "")
            {
                $t = explode(",", $qitems['orderby']);
                foreach ($t as $tf)
                {
                    if (preg_match("/([a-zA-Z0-9_]+)/", $tf, $pm))
                        $fieldstoget[] = trim(ltrim($pm[0]));
                }
            }
            //nei campi
            $t = explode(",", $qitems['fields']);
            foreach ($t as $tf)
            {
                if (preg_match("/([a-zA-Z0-9_]+)/", $tf, $pm))
                    $fieldstoget[] = trim(ltrim($pm[0]));
            }
            //nel where
            $t = $this->iExplode("['", $where2);
            unset($t[0]);
            foreach ($t as $tf)
            {
                $tf = trim(ltrim($tf));
                if (preg_match("/([a-zA-Z0-9_]+)/", $tf, $pm))
                    $fieldstoget[] = trim(ltrim($pm[0]));
            }
            if (!is_array($fieldstoget))
                return "xmetadb: syntax error";
            $fieldstoget = array_unique($fieldstoget);
        }
        //per ottimizzare prendo solo i fields che mi interessano---<
        $allrecords = array();
        //dprint_r($fieldstoget);
        foreach ($tbl as $tablename => $tbl_t)
        {
            if ($fieldstoget)
                foreach ($fieldstoget as $fieldget)
                {
                    if (!isset($tbl_t->fields[$fieldget]))
                    {
                        trigger_error("xmetadb: unknown field '$fieldget' in table '$tablename'", E_USER_WARNING);
                        return false;
                    }
                }
            //dprint_r($tbl_t->fields[$fieldstoget]);
            //dprint_r($fieldstoget);
            $allrecords_tml = $tbl_t->GetRecords(false, false, false, false, false, $fieldstoget);

            if (is_array($allrecords_tml))
                $allrecords = array_merge($allrecords, $allrecords_tml);
        }
        //dprint_r($qitems);
        //ordinamento -------->
        if ($qitems['orderby'] != "")
        {
            $orders = explode(",", $qitems['orderby']);
            foreach ($orders as $order)
            {
                if (preg_match("/([a-zA-Z0-9_]+)(.*)/s", $order, $orderfields))
                {
                    //dprint_r($order);
                    //dprint_r($orderfields);
                    if (preg_match("/DESC/is", trim(ltrim($orderfields[2]))))
                    {
                        $isdesc = true;
                    }
                    else
                        $isdesc = false;
                    $allrecords = xmetadb_array_sort_by_key($allrecords, trim(ltrim($orderfields[1])), $isdesc);
                }
            }
        }

        //ordinamento --------<
        $i = 0;
        $ret = array();
        //filtro search condition -------------------->
        if (!is_array($allrecords))
            return null;
        foreach ($allrecords as $item)
        {
            $ok = false;
            if ($where2 == "")
            {
                $ok = true;
            }
            else
            {


                if ($this->isUnsafeEvalCode($where2))
                {
                    trigger_error("xmetadb WHERE contains unsafe constructs: " . $where2, E_USER_WARNING);
                    return false;
                }
                try
                {
                    eval("if ($where2) {" . '$ok=true;' . "} ");
                } catch (\ParseError $e)
                {
                    trigger_error("xmetadb WHERE eval error: " . $e->getMessage(), E_USER_WARNING);
                    return false;
                }
            }
            if ($ok == false)
                continue;
            if ($qitems['fields'] == "*")
            {
                $tmp = $item;
            }
            else
            {
                $fields = explode(",", $qitems['fields']);
                $tmp = null;
                //alias ------->
                foreach ($fields as $field)
                {
                    if (preg_match("/ AS /is", $field))
                    {
                        $as = $this->iExplode(" AS ", $field);
                        $k2 = trim(ltrim($as[1]));
                        $k1 = trim(ltrim($as[0]));
                    }
                    else
                        $k1 = $k2 = trim(ltrim($field));

                    //fix null value--->
                    foreach ($item as $nk => $v)
                    {
                        if (!isset($item[$nk]))
                            $item[$nk] = false;
                    }
                    //fix null value---<
                    if (!isset($item[$k1]) && strtoupper($k1) != "COUNT(*)")
                    {
                        return "xmetadb: unknow row '$k1' in table {$qitems['tablename']}";                        
                    }
                    if (strtoupper($k1) != "COUNT(*)")
                    {
                        $tmp[$k2] = $item[$k1];
                    }
                    else
                    {
                        if (isset($item[$k2]))
                        {
                            $tmp[$k2] = $item[$k2];                           
                        }                     
                    }
                }
                //alias -------<
            }

            //----distinct------------->
            if (strtoupper($qitems['option']) == "DISTINCT")
                if ($this->array_in_array($tmp, $ret))
                {
                    continue;
                }
            //----distinct-------------<
            $i++;
            //----min length----------->
            if (($qitems['min']) && $i < $qitems['min'])
                continue;
            if (($qitems['min'] && $qitems['length']) && ($i) >= ($qitems['min'] + $qitems['length']))
                break;
            //----min length----------->

            $ret[] = $tmp;
        }


        //filtro search condition --------------------<
        return $ret;
    }

    private function convertwhere($where)
    {
        $where2 = trim(ltrim($where));
        //fix cases: category LIKE '%Innamorati dell\'arte%' 
        $apice = "_apice_";
        while (false !== strpos($where2, $apice))
        {
            $apice .= "_";
        }
        $where2 = str_replace("\\'", "$apice", $where2);

        // SECURITY: the translated WHERE is run through eval() below. At this
        // point $where2 still holds only the caller's SQL-like input (field
        // names and quoted values); the $item[...] accessors are generated by
        // the preg_replace() calls that follow. PHP double-quoted strings
        // interpolate $var/${...}/{$...} and execute `backticks`, so a value
        // such as  name LIKE "%{${`cmd`}}%"  would otherwise run code once
        // eval()'d. None of these characters are meaningful in a LIKE/compare
        // value, so strip them before translation (defence in depth alongside
        // isUnsafeEvalCode()). Backslash is left intact so the \' handling
        // above still works.
        $where2 = str_replace(array('`', '$', '{', '}'), '', $where2);

        // = -> ==
        $where2 = preg_replace("/([a-zA-Z0-9_ ]+) = ([a-zA-Z0-9_'\"]+)/i", '${1} == ${2}', $where2);
        // LIKE -> ==
        $where2 = preg_replace("/([a-zA-Z0-9_ ]+) LIKE ([a-zA-Z0-9_'\"]+)/i", '${1} == ${2}', $where2);
        // field = 'b'
        $where2 = preg_replace("/(\\w+)( <> | LIKE | > | >= | == | <= | < )(['|\\\"])([^'|^\\\"|^%]+)(['|\\\"])/", '\$item[\'${1}\'] ${2} ${3}${4}${5}', $where2);
        // field = ''
        $where2 = preg_replace("/(\\w+)( <> | LIKE | > | >= | == | <= | < )(['|\\\"])(['|\\\"])/", '\$item[\'${1}\'] ${2} ${3}${4}', $where2);
        // field = 1  (no quotes: uses numeric comparison, avoids "9" > "10" lexicographic bug)
        $where2 = preg_replace("/(\\w+)( <> | LIKE | > | >= | == | <= | < )([0-9]+)/", '\$item[\'${1}\'] ${2} ${3}', $where2);
        // field1 = field2
        $where2 = preg_replace("/(\\w+)( <> | LIKE | > | >= | == | <= | < )([\\w]+)/", '\$item[\'${1}\'] ${2} $item[\'${3}\']', $where2);
        // fiels LIKE "%t%"
        $where2 = preg_replace('/(\w+)[\040]+(==)[\040]+([\'])%([^\']*?)%([\'])/i', 'preg_match("/".XMETADatabase::encode_preg("${4}")."/i",(\$item[\'${1}\']))', $where2);
        $where2 = preg_replace('/(\w+)[\040]+(==)[\040]+([\"])%([^\"]*?)%([\"])/i', 'preg_match("/".XMETADatabase::encode_preg("${4}")."/i",(\$item[\'${1}\']))', $where2);
        // fiels LIKE "%t"
        $where2 = preg_replace('/(\w+)[\040]+(==)[\040]+([\'])%([^\']*?)([\'])/i', 'preg_match("/".XMETADatabase::encode_preg("${4}")."\$/i",(\$item[\'${1}\']))', $where2);
        $where2 = preg_replace('/(\w+)[\040]+(==)[\040]+([\"])%([^\"]*?)([\"])/i', 'preg_match("/".XMETADatabase::encode_preg("${4}")."\$/i",(\$item[\'${1}\']))', $where2);
        // fiels LIKE "t%"
        $where2 = preg_replace('/(\w+)[\040]+(==)[\040]+([\'])([^\']*?)%([\'])/i', 'preg_match("/^".XMETADatabase::encode_preg("${4}")."/i",(\$item[\'${1}\']))', $where2);
        $where2 = preg_replace('/(\w+)[\040]+(==)[\040]+([\"])([^\"]*?)%([\"])/i', 'preg_match("/^".XMETADatabase::encode_preg("${4}")."/i",(\$item[\'${1}\']))', $where2);
        // fiels <> "%t%"
        $where2 = preg_replace('/(\w+)[\040]+(<>)[\040]+([\"])%([^\"]*?)%([\"])/i', '!preg_match("/".XMETADatabase::encode_preg("${4}")."/i",(\$item[\'${1}\']))', $where2);
        // fiels <> '%t%'
        $where2 = preg_replace('/(\w+)[\040]+(<>)[\040]+([\'])%([^\']*?)%([\'])/i', '!preg_match(\'/\'.XMETADatabase::encode_preg(\'${4}\').\'/i\',(\$item[\'${1}\']))', $where2);
        //dprint_r($where2);
        $where2 = str_replace("$apice", "\\'", $where2);
        //dprint_r("w2=$where2");
        return $where2;
    }

    function array_in_array($needle, $haystack)
    {
        //Make sure $needle is an array for foreach
        if (!is_array($haystack))
            return false;
        if (!is_array($needle))
            $needle = array($needle);
        //For each value in $needle, return TRUE if in $haystack
        foreach ($haystack as $line)
        {
            if (!strcmp(serialize($line), serialize($needle)))
                return true;
        }
        return false;
    }

    private function isUnsafeEvalCode($code)
    {
        $allowed = preg_replace(
            '/\$item\[\'[^\']*\'\]|' .
            'preg_match\([^()]*(?:\([^()]*\)[^()]*)*\)|' .
            '==|!=|<>|>=|<=|>|<|&&|\|\||!' .
            '|\bAND\b|\bOR\b|\bNOT\b|' .
            '[0-9]+|\'[^\']*\'|"[^"]*"|' .
            '\btrue\b|\bfalse\b|' .
            '\s|\(|\)/i',
            '',
            $code
        );
        return $allowed !== '';
    }

    function iExplode($Delimiter, $String, $Limit = '')
    {
        $tmpString = strtoupper($String);
        $tmpDelimiter = strtoupper($Delimiter);
        $tmpret = explode($tmpDelimiter, $tmpString);
        $start = 0;
        foreach ($tmpret as $r)
        {
            $length = strlen($r);
            $ret[] = substr($String, $start, $length);
            $start += strlen($r . $Delimiter);
        }
        return ($ret);
    }
}

class_alias('Xmetadb\XMETADatabase', 'XMETADatabase');
