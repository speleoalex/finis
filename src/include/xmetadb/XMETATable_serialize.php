<?php
namespace Xmetadb;

/**
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2009
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 * @package xmetadb
 * 
 */

/**
 * serialize driver for Xmltable
 *
 */
class XMETATable_serialize  extends \stdClass
{

    var $databasename;
    var $tablename;
    var $xmltablename;
    var $primarykey;
    var $filename;
    var $path;
    var $usecachefile;
    var $indexfield;
    var $fields;
    var $records;
    var $driver;
    var $numrecords;
    var $xmltable;
    var $maxautoincrement;
    function __construct(&$xmltable,$params=false)
    {
        $this->xmltable=&$xmltable;
        $this->tablename=&$xmltable->tablename;
        $this->databasename=&$xmltable->databasename;
        $this->fields=&$xmltable->fields;
        $this->path=&$xmltable->path;
        $this->usecachefile=&$xmltable->usecachefile;
        $this->filename=&$xmltable->filename;
        $this->indexfield=&$xmltable->indexfield;
        $this->primarykey=&$xmltable->primarykey;
        $this->driver=&$xmltable->driver;
        $this->records=array();
        // properties relative to xml files
        $path=$this->path;
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        // data on a single file
        $this->filename=get_xml_single_element("filename",file_get_contents("$path/$databasename/$tablename.php"));
        if (is_array($params))
        {
            foreach($params as $k=> $v)
            {
                $this->$k=$v;
            }
        }
        return true;
    }

    /**
     * GetNumRecords
     * Returns the number of records
     */
    function GetNumRecords($restr=null)
    {
        $c=count($this->GetRecords($restr,false,false,false,false,$this->primarykey));
        return $c;
    }

    function ClearCachefile()
    {
        $this->records=array();
        if ($this->usecachefile!= 1)
            return;
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        $files=glob($cachefile="$path/".$databasename."/cache/$tablename*");
        if (is_array($files))
            foreach($files as $file)
            {
                @unlink($file);
            }
    }

    /**
     * GetRecords
     * retrieves all records
     */
    function GetRecords($restr=false,$min=false,$length=false,$order=false,$reverse=false,$fields=false)
    {
        $ret=null;
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        if (is_array($fields))
        {
            $fields=implode("|",$fields);
        }
        $tmf="";
        if ($fields!= false && is_array($restr))
        {
            foreach($restr as $key=> $value)
                $fields.="|$key";
        }
        $rc=$restr;
        if (is_array($restr))
            $rc=implode("|",$restr);
        if ($restr && is_string($restr))
        {
            die("TODO xmetadb: not yet implemented function for this driver");
        }

        $cacheindex=$rc.$min.$length.$order.$reverse.$fields;
        /* 		if (isset($this->records[md5($cacheindex)]))
          {
          return $this->records[md5($cacheindex)];
          } */
        // file cache ---->
        if ($this->usecachefile== 1)
        {
            if (!file_exists("$path/".$databasename."/cache"))
                mkdir("$path/".$databasename."/cache");
            $cachefile="$path/".$databasename."/cache/".$tablename.".".md5($cacheindex).".cache";
            if (file_exists($cachefile))
            {
                $ret=file_get_contents($cachefile);
                $ret=@unserialize($ret);
                if ($ret!== false)
                    return $ret;
            }
        }
        // file cache ----<
        // filter fields not associated with the table
        if ($fields=== false)
        {
            $fields=array();
            foreach($this->fields as $v)
            {
                $fields[]=$v->name;
            }
            $fields=implode("|",$fields);
        }
        $files=glob("$path/".$databasename."/".$tablename."/*.s.php");
        $all=array();
        foreach($files as $file)
        {
            $all[]=readSerialDatabase($file);
        }
        if (!is_array($all))
            return null;
        // if the field is missing, force it to default or null
        foreach($all as $k=> $r)
        {
            foreach($this->fields as $field)
            {
                if (!isset($r[$field->name]))
                    $r[$field->name]=isset($this->fields[$field->name]->defaultvalue) ? $this->fields[$field->name]->defaultvalue : null;
            }
            $all[$k]=$r;
        }
        if (is_array($restr))
        {
            $ret=array();
            foreach($all as $r)
            {
                //dprint_r($r);
                $ok=true;
                foreach($restr as $key=> $value)
                {
                    //dprint_r("r key={$r[$key]}");
                    if ("{$r[$key]}"!= "{$restr[$key]}")
                    {
                        //dprint_r("'{$r[$key]}' != '$restr[$key]' ($restr $key) ");
                        $ok=false;
                        break;
                    }
                }
                if ($ok== true)
                {
                    $ret[]=$r;
                }
            }
        }
        else
            $ret=$all;
        // sort records
        if ($order!== false && $order!== "" && isset($this->fields[$order]) && is_array($ret))
        {
            $newret=array();
            foreach($ret as $key=> $value)
            {
                if (isset($value[$order]))
                {
                    $i=0;
                    $r=$value[$order];
                    while(isset($newret[$r.$i]))
                    {
                        $i++;
                    }
                    $newret[$r.$i]=$ret[$key];
                }
                else
                {
                    $i=0;
                    $r="";
                    while(isset($newret[$r.$i]))
                    {
                        $i++;
                    }
                    $newret[$r.$i]=$ret[$key];
                }
            }
            ksort($newret);
            $ret=$newret;
        }
        if ($reverse)
        {
            $ret=array_reverse($ret);
        }
        // minimum and maximum
        if ($min!= false && $length!= false)
            $ret=array_slice($ret,$min - 1,$length);
        $ret=array_values($ret);
        // file cache ---->
        if ($this->usecachefile== 1)
        {
            $cachestring=serialize($ret);
            $fp=fopen($cachefile,"wb");
            fwrite($fp,$cachestring);
            fclose($fp);
        }
        // file cache ----<
        /* $this->records[md5($cacheindex)]=$ret; */
        return $ret;
    }

    /**
     * GetRecord
     * retrieves a single record
     *
     * @param array restriction
     */
    function GetRecord($restr=false)
    {
        $rec=$this->GetRecords($restr,0,1);
        if (is_array($rec) && isset($rec[0]))
        {
            return $rec[0];
        }
        return null;
    }

    /**
     * GetRecordByUnirecid
     *
     * Returns a record as an array starting from the unirecid (filename)
     * */
    function GetRecordByPrimaryKey($unirecid)
    {
        return $this->GetRecordByPk($unirecid);
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
        $records=$this->GetRecords();
        $max=0;
        $contrec=0;
        if (is_array($records))
        {
            foreach($records as $rec)
            {
                $contrec++;
                if (isset($rec[$field]) && intval($rec[$field]) > intval($max))
                    $max=intval($rec[$field]);
            }
        }
        $this->numrecords=$contrec;
        return $max + 1;
    }

    /**
     * InsertRecord
     * Adds a record
     *
     * @param array $values
     * */
    function InsertRecord($values)
    {
        $this->numrecords=-1;
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        foreach($this->fields as $f)
        {
            if (!isset($values[$f->name]) || (isset($values[$f->name]) && $values[$f->name]== ""))
                if (isset($this->fields[$f->name]->extra) && $this->fields[$f->name]->extra== "autoincrement")
                {
                    $newid=$this->GetAutoincrement($f->name);
                    $values[$f->name]=$newid;
                    $this->maxautoincrement[$f->name]=$newid;
                }
            if ((!isset($values[$f->name]) || $values[$f->name]=== null) && (isset($this->fields[$f->name]->defaultvalue) && $this->fields[$f->name]->defaultvalue!= ""))
            {
                // Assign default value directly — no eval() to prevent code injection via descriptor.
                $values[$f->name] = $this->fields[$f->name]->defaultvalue;
            }
        }
        if (!isset($values[$this->primarykey]) || $values[$this->primarykey]== "")
        {
            return "missing primary key in table $tablename";
        }
        if (!file_exists("$path/$databasename/$tablename/"))
            mkdir("$path/$databasename/$tablename");
        $unirecid=urlencode($values[$this->primarykey]);
        {
            $str=serialize($values);
            $handle=fopen("$path/$databasename/$tablename/$unirecid.s.php","w");
            fwrite($handle,$str);
            fclose($handle);
        }
        $this->xmltable->gestfiles($values);
        $this->ClearCachefile();
        $values=readSerialDatabase("$path/$databasename/$tablename/$unirecid.s.php",true);
        return $values;
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
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        $this->numrecords=-1;
        $old=$this->GetFileRecord($this->primarykey,$pkvalue);
        $dirold=dirname($old)."/".basename($old,".php");
        if (!file_exists($old))
            return false;
        if (strpos($pkvalue, "..") === false && file_exists("$path/$databasename/$tablename/$pkvalue/") && is_dir("$path/$databasename/$tablename/$pkvalue/"))
            xmetadb_remove_dir_rec("$path/$databasename/$tablename/$pkvalue");
        $this->ClearCachefile();
        @ unlink($old);
        $values=readSerialDatabase("$old",true);
        if (file_exists($old) && is_dir($old))
        {
            xmetadb_remove_dir_rec($old);
        }
        return true;
    }

    /**
     * GetFileRecord
     * returns the name of the file containing the record
     * @param string $pkey
     * @param string $pvalue
     */
    function GetFileRecord($pkey,$pvalue)
    {
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        if (file_exists("$path/$databasename/$tablename/".urlencode($pvalue).".s.php"))
        {
            return "$path/$databasename/$tablename/".urlencode($pvalue).".s.php";
        }
        return false;
    }

    /**
     * GetRecordByPk
     * returns the record given the primary key
     * @param string $pvalue key value
     */
    function GetRecordByPk($pvalue)
    {
        $pkey=$this->primarykey;
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        $old=$this->GetFileRecord($pkey,$pvalue);
        $ret=readSerialDatabase($old);
        // fill missing fields
        if ($ret)
            foreach($this->fields as $field)
            {
                if (!isset($ret[$field->name]))
                    $ret[$field->name]=isset($field->defaultvalue) ? $field->defaultvalue : null;
            }
        return $ret;
    }

    /**
     * UpdateRecordBypk
     * updates the record given the primary key
     * @param array $values
     * @param string $pkey
     * @param string $pvalue
     */
    function UpdateRecordBypk($values,$pkey,$pvalue)
    {
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        $strnew="";
        {
            $old=$this->GetFileRecord($pkey,$pvalue);
            if (!file_exists($old))
                return false;
            $readok=false;
            for($i=0; $i < _MAX_FILE_ACCESS_ATTEMPTS; $i++)
            {
                $oldvalues=readSerialDatabase($old);
                if ($oldvalues!== false)
                {
                    $readok=true;
                    break;
                }
            }
            $newvalues=$oldvalues;
            if (!$readok)
            {
                return "error update";
            }
            foreach($values as $key=> $value)
            {
                $newvalues[$key]=$value;
            }
            $this->xmltable->gestfiles($values,$oldvalues);
            $strnew=serialize($newvalues);
            if (!is_writable($old))
            {
                echo ("$old is readonly,I can't update");
                return ("$old is readonly,I can't update");
            }
            $handle=fopen($old,"w");
            fwrite($handle,$strnew);
            if ($pvalue!= $newvalues[$pkey])
                rename($old,"$path/$databasename/$tablename/".urlencode($newvalues[$pkey]).".s.php");
            $this->ClearCachefile();
            $newvalues=readSerialDatabase("$path/$databasename/$tablename/".urlencode($newvalues[$pkey]).".s.php",true);
            if (!isset($newvalues[$pkey]))
                return false;
            return $newvalues;
        }
    }

    function Truncate()
    {
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        xmetadb_remove_dir_rec("$path/$databasename/$tablename");
        $this->ClearCachefile();
        return true;
    }

}

//class XMETATable

function readSerialDatabase($file,$clearcache=false)
{
    static $cache=array();
    if ($clearcache)
        unset($cache[$file]);
    if (!file_exists($file))
        return null;
    $file=realpath($file);
    if (isset($cache[$file]))
    {
        //	dprint_r($file);
        return $cache[$file];
    }
    $ret=unserialize(file_get_contents($file));
    $cache[$file]=$ret;
    return $ret;
}

class_alias('Xmetadb\XMETATable_serialize', 'XMETATable_serialize');
?>