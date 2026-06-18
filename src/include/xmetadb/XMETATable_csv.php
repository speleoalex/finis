<?php
/**
 * csv driver for Xmltable
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2009
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 * @package xmetadb
 */
class XMETATable_csv
{

    var $xmltable;
    var $path;
    var $numrecords;
    var $usecachefile;
    var $driver;
    var $xmldescriptor;
    var $databasename;
    var $tablename;
    var $primarykey;
    var $filename;
    var $indexfield;
    var $fields;
    var $separator;
    var $records;

    function __construct(&$xmltable)
    {
        $this->xmltable=&$xmltable;
        $this->tablename=&$xmltable->tablename;
        $this->databasename=&$xmltable->databasename;
        $this->fields=&$xmltable->fields;
        $this->path=&$xmltable->path;
        $this->numrecords=&$xmltable->numrecords;
        $this->usecachefile=&$xmltable->usecachefile;
        $this->filename=&$xmltable->filename;
        $this->indexfield=&$xmltable->indexfield;
        $this->primarykey=&$xmltable->primarykey;
        $this->driver=&$xmltable->driver;
        $this->records=array();
        $this->xmldescriptor=&$xmltable->xmldescriptor;

        // properties relative to xml files
        $path=$this->path;
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        //separator --->
        $this->separator=get_xml_single_element("separator",file_get_contents("$path/$databasename/$tablename.php"));
        if ($this->separator == "")
            $this->separator=",";
        //separator ---<
        //filename--->
        $this->filename=get_xml_single_element("filename",file_get_contents("$path/$databasename/$tablename.php"));
        if ($this->filename == "")
            $this->filename="$tablename.csv";
        //filename---<
        $csv="$path/$databasename/$tablename/{$this->filename}";
        $this->filename=$csv;
        if (!file_exists($csv))
        {
            $f=array();
            foreach($this->fields as $k=> $v)
            {
                $f[]=$k;
            }
            $data=implode($this->separator,$f);
            $h=fopen($csv,"w");
            fwrite($h,$data);
            fclose($h);
        }
        return true;
    }

    /**
     * GetNumRecords
     * Returns the number of records
     *
     * @param array $restr
     */
    function GetNumRecords($restr=null)
    {
        $cacheid=$restr;
        if (is_array($restr))
            $cacheid=implode("|",$restr);
        if ($restr == null)
            $cacheid=" ";
        $cacheid=md5($cacheid);
        if (isset($this->numrecords[$cacheid]))
        {
            return $this->numrecords[$cacheid];
        }
        $c=count($this->GetRecords($restr,false,false,false,false,$this->primarykey));
        if (!is_array($this->numrecords))
            $this->numrecords=array();
        $this->numrecords[$cacheid]=$c;
        return $c;
    }

    function ClearCachefile()
    {
        $this->records=array();
        if ($this->usecachefile != 1)
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
     *
     * @param mixed $restr
     * @param mixed $min
     * @param mixed $length
     * @param mixed $order
     * @param mixed $reverse
     * @param mixed $fields
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
        if ($fields != false && is_array($restr))
        {
            foreach($restr as $key=> $value)
                $fields .= "|$key";
        }
        $rc=$restr;
        if (is_array($restr))
            $rc=implode("|",$restr);
        if ($restr && is_string($restr))
        {
            die("TODO xmetadb: not yet implemented function for this driver");
        }

        $cacheindex=$rc.$min.$length.$order.$reverse.$fields;
        // file cache ---->
        if ($this->usecachefile == 1)
        {
            if (!file_exists("$path/".$databasename."/cache"))
                mkdir("$path/".$databasename."/cache");
            $cachefile="$path/".$databasename."/cache/".$tablename.".".md5($cacheindex).".cache";
            if (file_exists($cachefile))
            {
                $ret=file_get_contents($cachefile);
                $ret=@unserialize($ret);
                if ($ret !== false)
                    return $ret;
            }
        }
        // file cache ----<
        $all=$this->readCSVDatabase($this->filename,true);
        if (!is_array($all))
            return null;
        if (is_array($restr))
        {
            $ret=array();
            foreach($all as $r)
            {
                $ok=true;
                foreach($restr as $key=> $value)
                {
                    if ("{$r[$key]}" != "{$restr[$key]}")
                    {
                        $ok=false;
                        break;
                    }
                }
                if ($ok == true)
                {
                    $ret[]=$r;
                }
            }
        }
        else
        {
            $ret=$all;
        }
        // sort records
        if ($order !== false && $order !== "" && isset($this->fields[$order]) && is_array($ret))
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
        if ($min != false && $length != false)
            $ret=array_slice($ret,$min - 1,$length);
        $ret=array_values($ret);
        // file cache ---->
        if ($this->usecachefile == 1)
        {
            $cachestring=serialize($ret);
            $fp=fopen($cachefile,"wb");
            fwrite($fp,$cachestring);
            fclose($fp);
        }
        // file cache ----<
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
        if (isset($this->maxautoincrement[$field]))
            return $this->maxautoincrement[$field] + 1;
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        $records=$this->GetRecords();
        $max=0;
        $contamax=0;
        foreach($records as $rec)
        {
            $contamax++;
            if (isset($rec[$field]) && $rec[$field] > $max)
                $max=$rec[$field];
        }
        $this->numrecords=$contamax;
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
            if (!isset($values[$f->name]) || (isset($values[$f->name]) && $values[$f->name] == ""))
                if (isset($this->fields[$f->name]->extra) && $this->fields[$f->name]->extra == "autoincrement")
                {
                    $newid=$this->GetAutoincrement($f->name);
                    $values[$f->name]=$newid;
                    $this->maxautoincrement[$f->name]=$newid;
                }
            if ((!isset($values[$f->name]) || $values[$f->name] === null) && (isset($this->fields[$f->name]->defaultvalue) && $this->fields[$f->name]->defaultvalue != ""))
            {
                $dv=$this->fields[$f->name]->defaultvalue;
                $fname=$f->name;
                $rv="";
                eval("\$rv=$dv;");
                $rv=str_replace("\\","\\\\",$rv);
                $rv=str_replace("'","\\'",$rv);
                eval("\$values"."['$fname'] = '$rv' ;");
            }
        }
        if (!isset($values[$this->primarykey]) || $values[$this->primarykey] == "")
        {
            return "missing primary key in table $tablename";
        }
        if (!file_exists("$path/$databasename/$tablename/"))
            mkdir("$path/$databasename/$tablename");
        $f=array();
        //--header and new record---->
        foreach($this->fields as $k=> $v)
        {
            $f[]=$k;
            $nl[$k]=isset($values[$k]) ? $this->CSVencode($values[$k]) : "";
            $newvalues[$k]=isset($values[$k]) ? $values[$k] : "";
        }
        $newline=implode($this->separator,$nl);
        $header=implode($this->separator,$f);

        //--header and new record----<
        global $xmetadb_csvfastinsert;
        if ($xmetadb_csvfastinsert)
        {
            if (!file_exists($this->filename) || file_get_contents($this->filename) == "")
                $add=false;
            else
                $add=true;
        }
        else
        {
            $all=$this->readCSVDatabase($this->filename,false);
            $add=false;
            foreach($all as $k=> $records)
            {
                $add=true;
                // if a record with the same primary key already exists
                if ($values[$this->primarykey] == $records[$this->primarykey])
                    return false;
            }
        }


        if ($add)
        {
            $str="\n$newline";
            $h=fopen($this->filename,"a");
        }
        else
        {
            $str="$header\n$newline";
            $h=fopen($this->filename,"w");
        }
        fwrite($h,$str);
        fclose($h);
        $this->xmltable->gestfiles($values);
        $this->ClearCachefile();
        return $newvalues;
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
        $this->numrecords=-1;
        $f=array();
        //--header and new record---->
        foreach($this->fields as $k=> $v)
        {
            $f[]=$k;
        }
        $header=implode($this->separator,$f);
        $filename=$this->GetFileRecord($this->primarykey,$pkvalue);
        $all=$this->readCSVDatabase($filename,false);
        //--header and new record----<
        $add=false;
        $str=$header;
        foreach($all as $k=> $records)
        {
            $add=true;
            // if a record with the same primary key already exists
            if ($pkvalue == $records[$this->primarykey])
                continue;
            $tnv=array();
            foreach($records as $record)
            {
                $tnv[]=$this->CSVencode($record);
            }
            $line=implode($this->separator,$tnv);
            $str .= "\n$line";
        }
        $h=fopen($filename,"w");
        fwrite($h,$str);
        fclose($h);
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
        return $this->filename;
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
        $r=$this->GetRecords(array($this->primarykey=>$pvalue));
        if (isset($r[0]))
            return $r[0];
        return false;
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
        $f=array();
        //--header and new record---->
        $oldvalues=$newval=$this->GetRecordByPrimaryKey($pvalue);
        foreach($this->fields as $k=> $v)
        {
            $f[]=$k;
            $nl[$k]=isset($values[$k]) ? $this->CSVencode($values[$k]) : $newval[$k];
            if (isset($values[$k]))
                $newval[$k]=$values[$k];
        }
        $newline=implode($this->separator,$nl);
        $header=implode($this->separator,$f);
        $filename=$this->GetFileRecord($pkey,$pvalue);
        $all=$this->readCSVDatabase($filename,false);
        //--header and new record----<
        $add=false;
        $str=$header;
        foreach($all as $k=> $records)
        {
            $add=true;
            // replace the row
            if ($pvalue == $records[$pkey])
            {
                $str .= "\n$newline";
                continue;
            }
            $tnv=array();
            foreach($records as $record)
            {
                $tnv[]=$this->CSVencode($record);
            }
            $line=implode($this->separator,$tnv);
            $str .= "\n$line";
        }
        $oldvalues[$this->primarykey]=$pvalue;
        $this->xmltable->gestfiles($values,$oldvalues);
        //dprint_r($values);
        $h=fopen($filename,"w");
        fwrite($h,$str);
        fclose($h);
        $all=$this->readCSVDatabase($filename,false);
        $this->ClearCachefile();
        $n=$this->GetRecordByPrimaryKey($newval[$pkey]);
        //dprint_r($newval[$pkey]);
        return $n;
    }

    /**
     * 
     */
    function Truncate()
    {
        $databasename=$this->databasename;
        $tablename=$this->tablename;
        $path=$this->path;
        $this->numrecords=-1;
        $this->numrecordscache=array();
        xmetadb_remove_dir_rec("$path/$databasename/$tablename");
        $this->ClearCachefile();
        return true;
    }

    /**
     * 
     * @param string $filename
     * @param bool $usecache
     */
    function readCSVDatabase($filename,$usecache=false)
    {
        //$usecache=true;
        //clearstatcache() ;
        static $cache=false;
        if ($usecache && isset($cache[$filename]))
            return $cache[$filename];
        $row=1;
        if (!file_exists($filename))
            return array();
        $handle=fopen("$filename","r");
        $ret=array();
        while(($data=fgetcsv($handle,5000,$this->separator,"\"","\\")) !== false)
        {
            if ($row == 1)
            {
                $this->syncfields($data);
                $row++;
                continue;
            }
            $num=count($data);
            $tmp=false;
            foreach($this->csvfields as $key=> $val)
            {
                if (isset($data[$val]))
                    $tmp[$key]=$data[$val];
                else
                    $tmp[$key]="";
            }
            if ($tmp)
                $ret[]=$tmp;
            $row++;
        }
        fclose($handle);
        $cache[$filename]=$ret;
        return $ret;
    }

    /**
     * 
     * @param array $row
     */
    function syncfields($row)
    {
        $i=0;
        foreach($row as $v)
        {
            if (isset($this->fields[$v]))
            {
                $this->csvfields[$v]=$i;
            }
            $i++;
        }
    }

    /**
     * 
     * @param $str
     */
    function CSVencode($str)
    {
        $str="\"".str_replace("\"","\"\"",$str)."\"";
        return $str;
    }

}
