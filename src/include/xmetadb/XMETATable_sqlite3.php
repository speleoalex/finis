<?php
namespace Xmetadb;

/**
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2014
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 * @package xmetadb
 *
 */
/**
 * xmetadb_sqlite.php created on 13/feb/2014
 * sqlite3 driver for xmetadb
 * allows inserting data into a sqlite3 table
 * the table descriptor must contain:
 *
 * <driver>sqlite3</driver>
 * <host>sqlite3host</host>
 * <user>sqlite3username</user>
 * <password>sqlite3password</password>
 *
 *
 *
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
class XMETATable_sqlite3 extends \stdClass
{

    function __construct(& $xmltable, $params = false)
    {
        if (!class_exists("SQLite3"))
        {
            die("class SQLite3 doesn't exists");
        }
        $this->xmltable = &$xmltable;
        $this->tablename = & $xmltable->tablename;
        $this->databasename = & $xmltable->databasename;
        $this->fields = & $xmltable->fields;
        $this->path = & $xmltable->path;
        $this->numrecords = & $xmltable->numrecords;
        $this->primarykey = &$xmltable->primarykey;
        $this->xmldescriptor = &$xmltable->xmldescriptor;
        $this->sqlitefields = array();
        $this->nullfields = array();
        $this->sqlite_error = false;
        $this->maxautoincrement = array();
        if (is_array($params))
        {
            foreach ($params as $k => $v)
            {
                $this->$k = $v;
            }
        }
        $path = $this->path;
        $databasename = $this->databasename;
        $this->sqlitedatabasename = $this->databasename;
        $tablename = $this->tablename;
        $xml = $this->xmldescriptor;
        //----SQLite setup---->
        $sqlite['filename'] = get_xml_single_element("sqlite3filename", $xml);
        $sqlite['database'] = get_xml_single_element("database", $xml);
        $sqltable = get_xml_single_element("sqltable", $xml);
        if ($sqlite['filename'] == "")
            $sqlite['filename'] = $path . "/$databasename.sqlite3";
        if ($sqltable == "")
            $sqltable = $this->tablename;
        $this->sqltable = $sqltable;
        // if global connections are set, pass the table settings
        global $xmetadb_sqlitedatabase, $xmetadb_sqlitefilename;
        if ($xmetadb_sqlitedatabase != "")
        {
            $sqlite['database'] = $xmetadb_sqlitedatabase;
            $sqlite['filename'] = $xmetadb_sqlitefilename;
        }
        if (is_array($params))
        {
            foreach ($params as $k => $v)
            {
                $sqlite[$k] = $v;
            }
        }
        if ($sqlite['database'] == "")
            $sqlite['database'] = $this->databasename;
        $this->sqlitedatabasename = $sqlite['database'];

        if ($sqlite['filename'] != "")
        {
            $xmltable->connection = $sqlite;
            $this->connection = & $xmltable->connection;
        }
        try
        {
            $this->conn = new \SQLite3($sqlite['filename'], SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        }
        catch(\Exception $e)
        {
            trigger_error($e->getMessage(), E_USER_WARNING);
            $this->conn = false;
        }
        if ($this->conn)
        {

            $this->dbQuery('PRAGMA encoding = "UTF-8";');
            $result = $this->dbQuery("SELECT name FROM sqlite_master WHERE type='table'");
            $exists = false;
            if ($result)
            {
                foreach ($result as $tmp)
                {
                    if ($tmp['name'] == $this->sqltable)
                        $exists = true;
                }
            }
            // create table ----->
            if (!$exists)
            {
                $fields = $this->fields;
                $coldefs = array();
                foreach ($fields as $field)
                {
                    $field = get_object_vars($field);
                    if (!isset($field['type']) || $field['type'] == "string")
                        $field['type'] = "varchar";
                    if ($field['type'] == "innertable")
                        continue;
                    $field['size'] = isset($field['size']) ? $field['size'] : "";
                    $isPrimary = isset($field['primarykey']) && $field['primarykey'] == "1";
                    $isAutoincrement = isset($field['extra']) && $field['extra'] == "autoincrement" && $field['type'] == "int";
                    $coldef = "[" . $field['name'] . "] ";
                    if ($isPrimary && $isAutoincrement)
                    {
                        $coldef .= "INTEGER PRIMARY KEY AUTOINCREMENT";
                    }
                    else
                    {
                        switch ($field['type'])
                        {
                            case "text" :
                            case "html" :
                                $coldef .= "TEXT";
                                break;
                            case "int" :
                                $coldef .= "INTEGER";
                                break;
                            default :
                                $coldef .= "TEXT";
                                if ($field['size'] != "")
                                    $coldef .= "(" . $field['size'] . ")";
                                break;
                        }
                        if ($isPrimary)
                            $coldef .= " PRIMARY KEY";
                    }
                    $coldefs[] = $coldef;
                }
                $query = "CREATE TABLE {$this->sqltable} (" . implode(", ", $coldefs) . ")";
                if (!$this->dbQuery($query))
                {
                    echo("error:" . $this->sqlite_error);
                }
                // transfer xml data into sqlite
                $tmpRecords = xmetadb_readDatabase("$path/" . $databasename . "/" . $tablename, $tablename, false, false);
                foreach ($tmpRecords as $rec)
                {
                    $this->InsertRecord($rec);
                }
            }
            // create table -----<
            //--synchronize fields --->
            $xmlfield = $this->fields;
            $result = $this->dbQuery("PRAGMA table_info(" . $this->sqltable . "); ");
            $exists = false;
            $sqlite_fields = array();

            if ($result)
            {
                foreach ($result as $tmp)
                {
                    $sqlite_fields[$tmp['name']] = $tmp;
                    if ($tmp['notnull'] == "0")
                    {
                        $this->nullfields[$tmp['name']] = $tmp['name'];
                    }
                }
            }
            else
            {
                echo $this->sqlite_error;
                return false;
            }


            foreach ($xmlfield as $fieldname => $fieldvalues)
            {
                if (!isset($sqlite_fields[$fieldname]) && $fieldvalues->type != "innertable")
                {
                    $field = get_object_vars($fieldvalues);
                    $query = "ALTER TABLE " . $this->sqltable . " ADD COLUMN $fieldname ";
                    $field['size'] = isset($field['size']) ? $field['size'] : "";
                    switch ($field['type'])
                    {
                        case "text" :
                        case "html" :
                            $query .= " TEXT";
                            break;
                        case "int" :
                            $query .= " INT";
                            break;
                        default : // force everything to varchar
                            $query .= " VARCHAR";
                            $field['size'] = "255";
                            break;
                    }
                    if ($field['size'] != "")
                        $query .= "(" . $field['size'] . ")";
                    $query .= " ";
                    if (isset($field['extra']) && $field['extra'] == "autoincrement")
                    {
                        if ($field['type'] == "int")
                            $query .= " AUTOINCREMENT ";
                    }

                    if (!$this->dbQuery($query))
                    {
                        echo ("add field error");
                        return false;
                    }
                }
            }
            $this->sqlitefields = $sqlite_fields;
            //--synchronize fields ---<
        }
        else
        {
            echo ($this->sqlite_error);
            return false;
        }
        return true;
        //<----SQLite----
    }

    /**
     * get records in table
     *
     * @param array $restr
     * @param int $min
     * @param int $length
     * @param string $order
     * @param bool $reverse
     * @param array $fields
     * @return array
     */
    function GetRecords($restr = false, $min = false, $length = false, $order = false, $reverse = false, $fields = false)
    {
        if (!$fields)
        {
            $fields = "*";
        }
        else
        {
            if (is_array($fields))
                $fields = implode("|", $fields);
            $fields = '[' . str_replace("|", "],[", $fields) . ']';
        }
        $query = "SELECT $fields FROM {$this->sqltable}";
        if (is_array($restr) && count($restr) > 0)
        {
            $query .= " WHERE ";
            $and = "";
            foreach ($restr as $h => $v)
            {
                $query .= " $and [$h] LIKE '" . \SQLite3::escapeString($v) . "' ";
                $and = "AND";
            }
        }
        if (is_string($restr) && trim($restr) !== "")
        {
            $query .= " WHERE $restr";
        }

        if ($order !== false && $order !== "")
        {
            $query .= " ORDER BY ";
            $sepOrder = "";
            $order = explode(",", $order);
            $orders = array();
            foreach ($order as $v)
            {
                $newmode = $reverse ? "DESC" : "ASC";
                $newmodes = explode(":", $v);
                if (!empty($newmodes[1]))
                    $newmode = $newmodes[1];
                $orders[$newmodes[0]] = $newmode;
            }
            foreach ($orders as $orderField => $mode)
            {
                if (isset($this->fields[$orderField]))
                {
                    $query .= "$sepOrder [$orderField]";
                    $sepOrder = ",";
                    $query .= " $mode";
                }
            }
        }
        if ($min !== false)
        {
            $query .= " LIMIT $min";
            if ($length !== false)
            {
                $query .= ",$length";
            }
        }
        return $this->dbQuery($query);
    }

    /**
     * get single record
     *
     * @param array $restr
     * @return array
     */
    function GetRecord($restr = false)
    {
        $rec = $this->GetRecords($restr, 0, 1);
        if (is_array($rec) && isset($rec[0]))
        {
            return $rec[0];
        }
        return null;
    }

    /**
     * dbQuery
     *
     * @param string query
     */
    function dbQuery($query)
    {
        if (!isset($this->conn) || !$this->conn)
        {
            $this->sqlite_error = "connection error";
            return false;
        }
        $results = $this->conn->query($query);
        $res = null;
        if ($results)
        {
            $res = array();
            if (preg_match("/^INSERT /is", $query))
                return true;
            if (preg_match("/^UPDATE /is", $query))
            {
                $this->maxautoincrement = array();
                return true;
            }
            if (preg_match("/^CREATE /is", $query))
            {
                $this->maxautoincrement = array();
                return true;
            }
            if (preg_match("/^DELETE /is", $query))
            {
                $this->maxautoincrement = array();
                return true;
            }
            if (preg_match("/^DROP /is", $query))
            {
                $this->maxautoincrement = array();
                return true;
            }
            if (preg_match("/^TRUNCATE /is", $query))
            {
                $this->maxautoincrement = array();
                return true;
            }
            if (preg_match("/^ALTER /is", $query))
            {
                $this->maxautoincrement = array();
                return true;
            }

            while (false !== ($tmp = @$results->fetchArray(SQLITE3_ASSOC)))
            {
                if (!is_array($tmp))
                {
                    return true;
                }
                $tmp2 = array();
                foreach ($tmp as $k => $t)
                {
                    $tmp2[str_replace("'", "", $k)] = $t;
                }
                $res[] = $tmp2;
            }
        }
        else
        {
            trigger_error("SQLite3 query error: " . $this->conn->lastErrorMsg(), E_USER_WARNING);
            return false;
        }
        $this->sqlite_error = $this->conn->lastErrorMsg();
        return $res;
    }

    /**
     * alias GetRecordByPk
     *
     * @param string $pvalue
     * @return array
     */
    function GetRecordByPrimaryKey($pvalue)
    {
        return $this->GetRecordByPk($pvalue);
    }

    /**
     * GetRecordByPk
     * returns the record given the primary key
     * @param string $pvalue key value
     */
    function GetRecordByPk($pvalue)
    {
        $tablename = $this->tablename;
        $pkey = $this->primarykey;
        // if data is on a database --->
        if ($this->connection)
        {
            if (!$this->conn)
                die("error connection");
            $query = "SELECT * FROM {$this->sqltable} WHERE $pkey LIKE '" . \SQLite3::escapeString($pvalue) . "'";
            $result = $this->dbQuery($query);
            if (!isset($result[0]))
            {
                return null;
            }
            $res = $this->fix_null($result[0]);
            return $res;
        }
        // <--- if data is on a database
        return false;
    }

    /**
     * convert NULL in ""
     * @param $res
     */
    function fix_null($res)
    {
        if (is_array($this->nullfields) && is_array($res))
        {
            foreach ($res as $k => $v)
            {
                if ($res[$k] === NULL)
                    $res[$k] = "";
            }
        }
        return $res;
    }

    /**
     * DelRecord
     * Deletes a record.
     * @param string $unirecid
     * <b>$values[$this->primarykey] must be present</b>
     * @return array just inserted record or null
     * */
    function DelRecord($pkvalue)
    {
        $path = $this->path;
        $databasename = $this->databasename;
        $tablename = $this->tablename;
        if ($this->connection)
        {
            if (!$this->conn)
                die("SQLite connection error: " . $this->sqlite_error);
            $pkey = $this->primarykey;
            if ($this->fields[$this->primarykey]->type == "int")
                $query = "DELETE FROM {$this->sqltable} WHERE $pkey LIKE " . $pkvalue;
            else
                $query = "DELETE FROM {$this->sqltable} WHERE $pkey LIKE '" . \SQLite3::escapeString($pkvalue) . "'";
            $result = $this->dbQuery($query);
            if (!$result)
            {
                echo $this->sqlite_error;
                return false;
            }
            if (strpos($pkvalue, "..") === false && file_exists("$path/$databasename/$tablename/$pkvalue/") && is_dir("$path/$databasename/$tablename/$pkvalue/"))
                xmetadb_remove_dir_rec("$path/$databasename/$tablename/$pkvalue");
            return true;
        }
        return false;
    }

    /**
     * truncate table
     *
     * @return unknown
     */
    function Truncate()
    {
        if (!$this->conn)
            die("error truncate");
        $result = $this->dbQuery("DELETE FROM {$this->sqltable}");
        if (!$result)
        {
            echo $this->sqlite_error;
            return false;
        }
        return true;
    }

    /**
     * InsertRecord
     * Adds a record
     *
     * @param array $values
     * */
    function InsertRecord($values)
    {
        if ($this->connection)
        {
            if (!$this->conn)
                return false;

            $query = "INSERT INTO " . $this->sqltable . " (";
            if (!isset($values[$this->primarykey]))
                $values[$this->primarykey] = "";
            $tf = array();
            foreach ($values as $k => $v)
            {
                if (isset($this->fields[$k]))
                {
                    //------autoincrement--->
                    if (isset($this->fields[$k]->extra) && $this->fields[$k]->extra == "autoincrement")
                    {
                        if (!isset($this->fields[$k]->nativeautoincrement) || $this->fields[$k]->nativeautoincrement != 1)
                        {
                            if (!isset($values[$k]) || $values[$k] == "")
                            {
                                $newid = $this->GetAutoincrement($k);
                                $values[$k] = $newid;
                                $v = $newid;
                            }
                            else
                            {
                                $this->maxautoincrement = array();
                            }
                        }
                    }
                    //------autoincrement---<
                    $tf[] = "[$k]";
                }
            }
            $query .= implode(",", $tf);
            $query .= ") VALUES (";
            $tf = array();
            foreach ($values as $k => $v)
            {
                if (isset($this->fields[$k]))
                {
                    if (isset($this->sqlitefields[$k]['notnull']) && $this->sqlitefields[$k]['notnull'] == "0" && $v == "")
                    {
                        $tf[] = "NULL";
                    }
                    else
                    {
                        if ($this->fields[$k]->type == "int")
                            $tf[] = intval($v);
                        else
                        {
                            $v = \SQLite3::escapeString($v);
                            $tf[] = "'$v'";
                        }
                    }
                }
            }
            $query .= implode(",", $tf);
            $query .= ");";

            $ret = $this->dbQuery($query);
            if (!$ret)
            {
                trigger_error("SQLite3 insert error: " . $this->conn->lastErrorMsg(), E_USER_WARNING);
                return false;
            }

            if (!isset($values[$this->primarykey]) || $values[$this->primarykey] == "")
            {
                $lastid = $this->dbQuery("SELECT * FROM {$this->sqltable} where {$this->primarykey} LIKE last_insert_rowid();");
                $values = $lastid[0];
            }
            $this->gestfiles($values);
            return $values;
        }
        return false;
    }

    /**
     * UpdateRecordBypk
     * updates the record given the primary key
     * @param array $values
     * @param string $pkey
     * @param string $pvalue
     */
    function UpdateRecordBypk($values, $pkey, $pvalue)
    {
        $tablename = $this->tablename;
        if (is_array($this->connection))
        {
            if ($this->conn)
            {
                $existsvalues = $this->GetRecordByPk($pvalue);
                if (!isset($existsvalues[$pkey]))
                    return false;
                $oldvalues = $existsvalues;
                $query = "UPDATE {$this->sqltable} SET ";
                $values2 = array();
                foreach ($values as $k => $value)
                {
                    if (isset($this->fields[$k]))
                        $values2[$k] = $values[$k];
                }
                $n = count($values2);
                if ($n == 0) // nothing to update
                    return $existsvalues;

                foreach ($values2 as $k => $value)
                {
                    if (isset($this->fields[$k]))
                    {
                        $query .= "[$k]=";
                        if (isset($this->sqlitefields[$k]['notnull']) && $this->sqlitefields[$k]['notnull'] == "0" && $value == "")
                        {
                            $query .= "NULL";
                        }
                        else
                        {
                            if ($this->fields[$k]->type == "int")
                                $query .= \SQLite3::escapeString($value);
                            else
                                $query .= "'" . \SQLite3::escapeString($value) . "'";
                        }
                        if ($n-- > 1)
                            $query .= ",";
                    }
                }
                $query .= " WHERE $pkey=";
                if ($this->fields[$pkey]->type == "int")
                {
                    $query .= "$pvalue ";
                }
                else
                {
                    $query .= "'$pvalue' ";
                }
                $ret = $this->dbQuery($query);
                $this->gestfiles($values, $oldvalues);
                if (!$ret)
                {
                    return $this->sqlite_error;
                }
                $newvalues = $this->GetRecordByPk($values[$pkey]);
            }
            else
            {
                return $this->sqlite_error;
            }
            return $newvalues;
        }
        return false;
    }

    /**
     * GetNumRecords
     * return records count
     * 
     * @param array|string|null $restr
     * @return int
     */
    function GetNumRecords($restr = null)
    {
        $query = "SELECT COUNT(*) AS C FROM " . $this->sqltable;
        if (is_array($restr) && count($restr) > 0)
        {
            $query .= " WHERE ";
            $and = "";
            foreach ($restr as $h => $v)
            {
                $query .= " $and $h LIKE '" . \SQLite3::escapeString($v) . "' ";
                $and = "AND";
            }
        }
        if (is_string($restr))
        {
            $query .= " WHERE $restr";
        }

        $ret = $this->dbQuery($query);
        if (isset($ret[0]['C']))
            return $ret[0]['C'];
        return 0;
    }

    /**
     *
     * @param array $values
     * @param array|null $oldvalues
     */
    function gestfiles($values, $oldvalues = null)
    {
        $this->xmltable->gestfiles($values, $oldvalues);
    }

    /**
     *
     * @param array $recordvalues
     * @param string $recordkey
     * @return string|false 
     */
    function get_thumb($recordvalues, $recordkey)
    {
        $databasename = $this->databasename;
        $tablename = $this->tablename;
        $path = realpath($this->path);
        $unirecid = $recordvalues[$this->primarykey];
        if (!isset($recordvalues[$recordkey]))
            $recordvalues = $this->GetRecord($recordvalues);
        $value = $recordvalues[$recordkey];
        if (file_exists("$path/$databasename/$tablename/$unirecid/$recordkey/thumbs/$value.jpg"))
        {
            $php_self = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : "";
            $dirname = dirname($php_self);
            if ($dirname == "/" || $dirname == "\\")
            {
                $dirname = "";
            }
            $protocol = "http://";
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on")
                $protocol = "https://";
            $siteurl = "$protocol" . $_SERVER['HTTP_HOST'] . $dirname;
            if (substr($siteurl, strlen($siteurl) - 1, 1) != "/")
            {
                $siteurl = $siteurl . "/";
            }
            return "$siteurl" . $this->path . "/$databasename/$tablename/$unirecid/$recordkey/thumbs/$value.jpg";
        }
        return false;
    }

    /**
     * GetAutoincrement
     *
     * manages the autoincrement of a table field
     *
     * @param string field name
     * @return next available index
     */
    function GetAutoincrement($field)
    {
        if (!isset($this->maxautoincrement[$field]))
        {
            $record = $this->dbQuery("SELECT MAX(CAST($field AS Int)) AS $field FROM {$this->sqltable} WHERE $field NOT LIKE '%[a-z]%' ");
            if (!isset($record[0][$field]))
            {
                return 1;
            }
            $this->maxautoincrement[$field] = $record[0][$field];
        }
        $this->maxautoincrement[$field] = $this->maxautoincrement[$field] + 1;
        return $this->maxautoincrement[$field];
    }

}

class_alias('Xmetadb\XMETATable_sqlite3', 'XMETATable_sqlite3');
?>