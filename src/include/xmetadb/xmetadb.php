<?php
include_once __DIR__ . "/XMETATable.php";

/**
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2024
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 * @package xmetadb
 *
 */
// TODO: the primary key must always be the first field in the descriptor
define("_MAX_FILE_ACCESS_ATTEMPTS", "1000");
define("_MAX_FILES_PER_FOLDER", "10000");
define("_MAX_LOCK_TIME", "30"); // seconds

function removePhpTags($inputString)
{
    // Define the termination string
    $terminationString = '<?php exit(0);?>';
    // Find the position of the termination string
    $position = strpos($inputString, $terminationString);

    // If the termination string is found
    if ($position !== false)
    {
        // Calculate the starting position of the remaining content
        $startPosition = $position + strlen($terminationString);

        // Return the substring starting after the termination string
        return substr($inputString, $startPosition);
    }

    // If the termination string is not found, return the original string
    return $inputString;
}

function xmetadb_xml2array($data, $elem, $fields = false)
{
    // If data is not XML (doesn't start with "<?x"), try JSON decode first.
    // Falls through to XML parsing when json_decode returns null.
    if (!isset($data[2]) || $data[2] !== 'x') {
        $decoded = json_decode(removePhpTags($data), true);
        if ($decoded !== null) {
            return $decoded;
        }
    }
    $data = xmetadb_removexmlcomments($data);
    if (is_array($fields))
    {
        $fields = implode("|", $fields);
    }
    $out = "";
    $ret = null;
    if (preg_match("/<$elem>.*<$elem>[^<]+<\/$elem>/s", $data))
    {
        preg_match_all("#<$elem>(.*?<$elem>.*?</$elem>.*?)</$elem>#s", $data, $out);
    }
    else
    {
        preg_match_all("#<$elem>.*?</$elem>#s", $data, $out);
    }
    if (is_array($out[0]))
        foreach ($out[0] as $innerxml)
        {
            $tmp2 = $t1 = null;
            preg_match_all('/<(' . $fields . '[^\/]*?)>([^<]*)<\/\1>/s', $innerxml, $t1);
            foreach ($t1[1] as $k => $tt)
            {
                if ($t1[2][$k] != null)
                    $tmp2[$tt] = xmldec($t1[2][$k]);
                else
                    $tmp2[$tt] = "";
            }
            if ($tmp2 != null)
            {
                $ret[] = ($tmp2);
            }
        }
    return $ret;
}


/**
 * xmetadb_readDatabase
 * Reads an XML file and returns an array.
 * <db>
 * <elem>
 * <pippo>1</pippo>
 * <pluto>1</pluto>
 * </elem>
 * <elem>
 * <pippo>2</pippo>
 * <pluto>2</pluto>
 * </elem>
 * </db>
 *
 * xmetadb_readDatabase($filename,"elem")
 * returns:
 *
 * $ret[0]['pippo']=1
 * $ret[0]['pluto']=1
 * $ret[1]['pippo']=2
 * $ret[1]['pluto']=2
 *
 * or null if the file could not be read
 *
 * @todo Fix the issue when a field has the same name as the table.
 *
 * */
function xmetadb_readDatabase($filename, $elem, $fields = false, $usecache = true)
{
    if (!file_exists($filename))
        return false;
    $_fields = "_" . (is_array($fields) ? implode(",", $fields) : $fields);
    static $cache = array();
    static $lastmod = array();
    $filename = realpath($filename);
    if (!isset($lastmod[$filename]) || $lastmod[$filename] != filemtime($filename) . filesize($filename))
    {
        $lastmod[$filename] = filemtime($filename) . filesize($filename);
        $usecache = false;
    }
    if (is_dir($filename))
    {
        $usecache = false;
    }
    if ($usecache === false)
    {
        if (isset($cache[$filename][$_fields][$elem]))
        {
            unset($cache[$filename][$_fields][$elem]);
        }
    }
    else
    {
        //dprint_r("cache $filename");
    }
    if ($usecache === true && isset($cache[$filename][$_fields][$elem]))
    {
        return $cache[$filename][$_fields][$elem];
    }
    $tmp = array();
    // --- xml split across multiple files --------->
    if (is_dir($filename))
    {
        $data = null;
        $handle = opendir($filename);
        while (false !== ($file = readdir($handle)))
        {
            $tmp2 = null;
            if (preg_match('/.php$/is', $file))
                $tmp2 = xmetadb_readDatabase("$filename/$file", $elem, $fields, $usecache);
            if ($tmp2 != null)
                foreach ($tmp2 as $t)
                    $tmp[] = $t;
        }
        closedir($handle);
        $cache[$filename][$_fields][$elem] = $tmp;
        return $tmp;
    }
    //<--------- xml split across multiple files ---
    $data = file_get_contents($filename);
    //da xml ad array....
    $ret = xmetadb_xml2array($data, $elem, $fields); //null if data = ""
    //echo "fname=$filename";
    $cache[$filename][$_fields][$elem] = $ret;
    return $ret;
}

/**
 * xmlenc
 *
 * Encodes data for insertion between XML tags.
 * @param string $str
 * @return string encoded string
 */
function xmlenc($str)
{
    $str = str_replace("&", "&amp;", $str);
    $str = str_replace("<", "&lt;", $str);
    $str = str_replace(">", "&gt;", $str);
    return $str;
}

/**
 * xmldec
 *
 * Decodes data extracted from between XML tags.
 * @param string $str
 * @return string decoded string
 */
function xmldec($str)
{
    if (!is_string($str))
        return "";
    $str = str_replace("&gt;", ">", $str);
    $str = str_replace("&lt;", "<", $str);
    $str = str_replace("&amp;", "&", $str);
    return $str;
}

/**
 * xmetadb_create_thumb
 * Creates a file thumbnail for image-type fields. Requires the GD library.
 * @param string $filename file name
 * @param int $max maximum thumbnail size
 */
function xmetadb_create_thumb($filename, $max, $max_h = "", $max_w = "")
{
    if (!$filename)
        return;
    if ($max_h == "")
        $max_h = $max;
    if ($max_w == "")
        $max_w = $max;
    if (!function_exists("getimagesize"))
    {
        echo "<br />" . _FNNOGDINSTALL;
        return;
    }
    $new_height = $new_width = 0;
    if (!file_exists($filename))
    {
        echo "non esiste";
        return;
    }
    if (!getimagesize($filename))
    {
        echo "$filename is not image ";
        return;
    }
    list($width, $height, $type, $attr) = getimagesize($filename);
    if (function_exists("exif_read_data"))
    {
        $exif = @exif_read_data($filename);
        if (!empty($exif['Orientation']) && ($exif['Orientation'] == 6 || $exif['Orientation'] == 8))
        {
            $tmp = $height;
            $height = $width;
            $width = $tmp;
        }
    }

    $path = dirname($filename) . "/thumbs";
    $file_thumb = $path . "/" . basename($filename);
    if (!file_exists($path))
    {
        mkdir($path);
    }
    if (!file_exists($path))
    {
        echo "error make dir $path";
        return false;
    }
    if (!is_dir($path))
    {
        echo "<br />$path not exists";
    }
    $new_height = $height;
    $new_width = $width;
    if ($width >= $max_w)
    {
        $new_width = $max_w;
        $new_height = intval($height * ($new_width / $width));
    }
    //se troppo alta
    if ($new_height >= $max_h)
    {
        $new_height = $max_h;
        $new_width = intval($width * ($new_height / $height));
    }
    // se l' immagine e gia piccola
    if ($width <= $max_w && $height <= $max_h)
    {
        $new_width = $width;
        $new_height = $height;
        //return;
    }

    //die("h=$new_height w=$new_width");
    // Load
    $thumb = imagecreatetruecolor($new_width, $new_height);
    $white = imagecolorallocate($thumb, 255, 255, 255);
    $size = getimagesize($filename);
    //	dprint_r(IMAGETYPE_WBMP);
    try
    {
        switch ($size[2])
        {
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($filename);
                break;
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($filename);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($filename);
                break;
            case IMAGETYPE_WBMP:
                $source = imagecreatefromwbmp($filename);
                break;
            case IMG_XPM:
                $source = imagecreatefromxpm($filename);
                break;
            case 6:
                $source = xmetadb_ImageCreateFromBMP($filename);
                break;
            default:
                // unknown file format
                $source = imagecreatetruecolor(300, 300);
                $color = imagecolorallocate($source, 255, 255, 255);
                imagefill($source, 0, 0, $color);
                break;
        }
    } catch (Exception $e)
    {
        $source = false;
    }

    if (!$source)
    {
        return;
    }
    xmetadb_image_fix_orientation($source, $filename);
    // Resize
    imagefilledrectangle($thumb, 0, 0, $width, $width, $white);
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    // Output
    $file_to_open = $file_thumb;
    imagejpeg($thumb, $file_to_open . ".jpg");
}

/**
 *
 * @param string $filename
 * @return resource
 */
function xmetadb_ImageCreateFromBMP($filename)
{
    if (!$f1 = fopen($filename, "rb"))
        return FALSE;
    $FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1, 14));
    if ($FILE['file_type'] != 19778)
        return FALSE;
    $BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel' . '/Vcompression/Vsize_bitmap/Vhoriz_resolution' . '/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1, 40));
    $BMP['colors'] = pow(2, $BMP['bits_per_pixel']);
    if ($BMP['size_bitmap'] == 0)
        $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
    $BMP['bytes_per_pixel'] = $BMP['bits_per_pixel'] / 8;
    $BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
    $BMP['decal'] = ($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
    $BMP['decal'] -= floor($BMP['width'] * $BMP['bytes_per_pixel'] / 4);
    $BMP['decal'] = 4 - (4 * $BMP['decal']);
    if ($BMP['decal'] == 4)
        $BMP['decal'] = 0;
    $PALETTE = array();
    if ($BMP['colors'] < 16777216)
    {
        $PALETTE = unpack('V' . $BMP['colors'], fread($f1, $BMP['colors'] * 4));
    }
    $IMG = fread($f1, $BMP['size_bitmap']);
    $VIDE = chr(0);
    $res = imagecreatetruecolor($BMP['width'], $BMP['height']);
    $P = 0;
    $Y = $BMP['height'] - 1;
    while ($Y >= 0)
    {
        $X = 0;
        while ($X < $BMP['width'])
        {
            if ($BMP['bits_per_pixel'] == 24)
                $COLOR = unpack("V", substr($IMG, $P, 3) . $VIDE);
            elseif ($BMP['bits_per_pixel'] == 16)
            {
                $COLOR = unpack("n", substr($IMG, $P, 2));
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            }
            elseif ($BMP['bits_per_pixel'] == 8)
            {
                $COLOR = unpack("n", $VIDE . substr($IMG, $P, 1));
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            }
            elseif ($BMP['bits_per_pixel'] == 4)
            {
                $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                if (($P * 2) % 2 == 0)
                    $COLOR[1] = ($COLOR[1] >> 4);
                else
                    $COLOR[1] = ($COLOR[1] & 0x0F);
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            }
            elseif ($BMP['bits_per_pixel'] == 1)
            {
                $COLOR = unpack("n", $VIDE . substr($IMG, floor($P), 1));
                if (($P * 8) % 8 == 0)
                    $COLOR[1] = $COLOR[1] >> 7;
                elseif (($P * 8) % 8 == 1)
                    $COLOR[1] = ($COLOR[1] & 0x40) >> 6;
                elseif (($P * 8) % 8 == 2)
                    $COLOR[1] = ($COLOR[1] & 0x20) >> 5;
                elseif (($P * 8) % 8 == 3)
                    $COLOR[1] = ($COLOR[1] & 0x10) >> 4;
                elseif (($P * 8) % 8 == 4)
                    $COLOR[1] = ($COLOR[1] & 0x8) >> 3;
                elseif (($P * 8) % 8 == 5)
                    $COLOR[1] = ($COLOR[1] & 0x4) >> 2;
                elseif (($P * 8) % 8 == 6)
                    $COLOR[1] = ($COLOR[1] & 0x2) >> 1;
                elseif (($P * 8) % 8 == 7)
                    $COLOR[1] = ($COLOR[1] & 0x1);
                $COLOR[1] = $PALETTE[$COLOR[1] + 1];
            }
            else
                return FALSE;
            imagesetpixel($res, $X, $Y, $COLOR[1]);
            $X++;
            $P += $BMP['bytes_per_pixel'];
        }
        $Y--;
        $P += $BMP['decal'];
    }
    fclose($f1);
    return $res;
}

/**
 * xmetadb_removexmlcomments
 * Removes comments and processing instructions from XML data.
 *
 * @param string $data
 * @return string XML with comments removed
 *
 */
function xmetadb_removexmlcomments($data)
{
    $data = preg_replace("/<!--(.*?)-->/ms", "", $data);
    $data = preg_replace("/<\\?(.*?)\\?>/", "", $data);
    return $data;
}

//-------------------------DATABASE CREATION/MODIFICATION FUNCTIONS----------------
/**
 * XMETATable::createMetadbTable
 *
 * Creates a new XML table.
 * @param string $databasename database name
 * @param string $tablename table name
 * @param array $fields field definitions
 * @param string $path database path
 * @param mixed $singlefilename single-file name, or false for directory-based storage
 *
 * -- EXAMPLE : --
 * $fields[0]['name']="id";
 * $fields[0]['primarykey']=1;
 * $fields[0]['defaultvalue']=null;
 * $fields[0]['type']="varchar";
 * $fields[1]['name']="test";
 * $fields[1]['primarykey']=0;
 * $fields[1]['defaultvalue']="pippo";
 * $fields[1]['type']="varchar";
 * XMETATable::createMetadbTable("plugins","test",$fields,"misc");
 * */
function createxmltable($databasename, $tablename, $fields, $path = ".", $singlefilename = false)
{
    return XMETATable::createMetadbTable($databasename, $tablename, $fields, $path, $singlefilename);
}

/**
 * XMETATable::createMetadbDatabase
 * Creates a database directory.
 *
 * @param string $databasename
 * @param string $path
 * @return false on success, or an error string on failure
 */
function createxmldatabase($databasename, $path = ".")
{
    return XMETATable::createMetadbDatabase($databasename, $path);
}

/**
 * XMETATable::meteDatabaseExists
 * Checks whether a database exists.
 *
 * @param string $databasename
 * @param string $path
 */
function xmldatabaseexists($databasename, $path = ".", $conn = false)
{
    return XMETATable::meteDatabaseExists($databasename, $path, $conn);
}

function xmltableexists($databasename, $tablename, $path = ".")
{
    return XMETATable::metaTableExists($databasename, $tablename, $path);
}

/**
 * addfield
 * add field in table
 *
 * @param string $databasename
 * @param string $tablename
 * @param array $field
 * @param string $path
 * @param bool $force
 *
 */
function addxmltablefield($databasename, $tablename, $field, $path = ".", $force = true)
{
    if (!isset($field['name']))
        return null;
    if (is_array($tablename))
        return null;
    $newvalues = array();
    $values = $field;
    $pvalue = $field['name'];
    $pkey = "name";
    $old = "$path/$databasename/$tablename.php";
    if (!file_exists($old))
        return null;
    $readok = false;
    for ($i = 0; $i < 3; $i++)
    {
        $oldfilestring = file_get_contents($old);
        if (strpos($oldfilestring, "</tables>") !== false)
        {
            $readok = true;
            break;
        }
        if ($i < 2) usleep(5000);
    }
    if (!$readok)
    {
        return "error: could not read descriptor file";
    }
    $oldfilestring = xmetadb_removexmlcomments($oldfilestring);
    $oldvalues = $newvalues = getxmltablefield($databasename, $tablename, $field['name'], $path);
    foreach ($values as $key => $value)
    {

        $newvalues[$key] = $value;
    }
    $strnew = "<field>";
    foreach ($newvalues as $key => $value)
    {
        $strnew .= "\n\t\t<$key>" . xmlenc($value) . "</$key>";
    }
    $strnew .= "\n\t</field>";

    if ($oldvalues)
    {
        $pvalue = xmlenc($pvalue);
        $pvalue = xmetadb_encode_preg($pvalue);
        $strnew = str_replace('$', '\\$', $strnew);
        $newfilestring = preg_replace('/<field>([^(field)]*)<' . $pkey . '>' . $pvalue . '<\/' . $pkey . '>(.*?)<\/field>/s', $strnew, $oldfilestring);
        if (!is_writable($old))
        {
            echo ("$old is readonly,I can't update");
            return ("$old is readonly,I can't update");
        }
        if ($oldfilestring != $newfilestring && $force)
        {
            $handle = fopen($old, "w");
            fwrite($handle, $newfilestring);
            xmetadb_readDatabase($old, 'field', false, false); // refresh cache
        }
        return $newvalues;
    }
    else // new field
    {
        for ($i = 0; $i < 3; $i++)
        {
            $oldfilestring = file_get_contents("$path/$databasename/$tablename.php");
            if (strpos($oldfilestring, "</tables>") !== false)
            {
                $readok = true;
                break;
            }
        }
        if (!$readok)
        {
            return "error insert field";
        }
        $strnew = xmetadb_encode_preg_replace2nd($strnew);
        $newfilestring = preg_replace('/<\/tables>$/s', xmetadb_encode_preg_replace2nd($strnew) . "\n</tables>", trim($oldfilestring)) . "\n";
        $handle = fopen("$path/$databasename/$tablename.php", "w");
        fwrite($handle, $newfilestring);
        fclose($handle);
        xmetadb_readDatabase($old, 'field', false, false); // refresh cache
        return $newvalues;
    }
}

/**
 * getxmltablefield
 * Returns all properties of a field in an XML table.
 *
 * @param string $databasename
 * @param string $tablename
 * @param string $fieldname
 * @param string $path
 */
function getxmltablefield($databasename, $tablename, $fieldname, $path = ".")
{
    if (!file_exists("$path/$databasename/$tablename.php"))
        return null;
    $rows = xmetadb_readDatabase("$path/$databasename/$tablename.php", "field");
    foreach ($rows as $row)
    {
        if ($row['name'] == $fieldname)
        {
            return $row;
        }
    }
    return null;
}

/**
 * Elimina ricorsivamente una cartella
 *
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @param $dirtodelete cartella da eliminare
 *
 * */
function xmetadb_remove_dir_rec($dirtodelete)
{
    // Reject path traversal attempts in all common forms and null-byte injection.
    if (strpos($dirtodelete, '..') !== false || strpos($dirtodelete, "\0") !== false)
        die("xmetadberror:xmetadb_remove_dir_rec");
    if (false != ($objs = glob($dirtodelete . "/.*")))
    {
        foreach ($objs as $obj)
        {
            if (!is_dir($obj))
                unlink($obj);
            else
            {
                if (basename($obj) != "." && basename($obj) != "..")
                {
                    xmetadb_remove_dir_rec($obj);
                }
            }
        }
    }
    if (false !== ($objs = glob($dirtodelete . "/*")))
    {
        foreach ($objs as $obj)
        {
            is_dir($obj) ? xmetadb_remove_dir_rec($obj) : unlink($obj);
        }
    }
    if (file_exists($dirtodelete) && is_dir($dirtodelete))
        rmdir($dirtodelete);
}

/**
 * xmetadb_encode_preg_replace2nd
 * prepara la stringa per il secondo parametro
 * dell' preg_replace aggiungendo la \ savanti a \ e $

 *
 */
function xmetadb_encode_preg_replace2nd($str)
{
    $str = str_replace("\\", "\\\\", $str);
    $str = str_replace('$', '\\$', $str);
    return $str;
}

function xmetadb_encode_preg($str)
{
    return preg_quote($str, '/');
}

/**
 * Restituisce un elemento XML
 *
 * Restituisce un elemento XML da un file passato come parametro.
 *
 *
 * @param string $elem Nome dell'elemento XML da cercare
 * @param string $xml Nome del file XML da processare
 * @return string Stringa contenente il valore dell'elemento XML
 */
function get_xml_single_element($elem, $xml)
{
    $xml  = xmetadb_removexmlcomments($xml);
    $safe = preg_quote($elem, '/');
    $buff = preg_replace("/.*<" . $safe . ">/s", "", $xml);
    if ($buff == $xml)
        return "";
    $buff = preg_replace("/<\/" . $safe . ">.*/s", "", $buff);
    return $buff;
}

/**
 *
 * @param array $data
 * @param string $order
 * @param bool $desc
 */
function xmetadb_array_sort_by_key($data, $order, $desc = false)
{

    $mode = "asc";
    if ($desc)
        $mode = "desc";
    $order = explode(",", $order);
    foreach ($order as $v)
    {
        $newmode = $mode;
        $newmodes = explode(":", $v);
        if (isset($newmodes[1]))
            $newmode = $newmodes[1];
        $orders[$newmodes[0]] = $newmode;
    }
    $orders = array_reverse($orders);

    foreach ($orders as $order => $mode)
    {
        $newret = array();
        $ret = array();
        foreach ($data as $key => $value)
        {
            $ret[$value[$order]][] = $value;
        }
        ksort($ret);
        if ($mode == "desc")
        {
            $ret = array_reverse($ret);
        }
        foreach ($ret as $key => $value)
        {
            foreach ($value as $item)
            {
                $newret[] = $item;
            }
        }
        $data = $newret;
    }

    return $newret;
}

/**
 *
 * @param array $data
 * @param string $order
 * @param bool $desc
 */
function xmetadb_array_natsort_by_key($data, $order, $desc = false)
{

    $ret = array();
    if (!is_array($data))
        return false;
    $mode = "asc";
    if ($desc)
        $mode = "desc";
    $order = explode(",", $order);
    foreach ($order as $v)
    {
        $newmode = $mode;
        $newmodes = explode(":", $v);
        if (isset($newmodes[1]))
            $newmode = $newmodes[1];
        $orders[$newmodes[0]] = $newmode;
    }
    $orders = array_reverse($orders);
    foreach ($orders as $order => $mode)
    {
        $newret = array();
        $ret = array();
        foreach ($data as $key => $value)
        {
            if (!isset($value[$order]))
            {
                $value[$order] = null;
            }
            $ret[$value[$order]][] = $value;
        }
        uksort($ret, "xmetadb_NatSort_callback");
        if ($mode == "desc")
        {
            $ret = array_reverse($ret);
        }
        foreach ($ret as $key => $value)
        {
            foreach ($value as $item)
            {
                $newret[] = $item;
            }
        }
        $data = $newret;
    }
    return $data;
}

/**
 * @param string $a
 * @param string $b
 * @return int
 */
function xmetadb_NatSort_callback($a, $b)
{
    $a = strtolower($a);
    $b = strtolower($b);
    //if ( fn_erg("^[0-9]", $a) && fn_erg("^[0-9]", $b) )
    if (preg_match("/^[0-9]/", $a) && preg_match("/^[0-9]/", $b))
    {
        $aa = explode("_", $a);
        $bb = explode("_", $b);
        $aa = $aa[0];
        $bb = $bb[0];
        if (intval($aa) == intval($bb))
        {
            return strnatcmp($a, $b);
        }
        return (intval($aa) < intval($bb)) ? -1 : 1;
    }
    return strnatcmp($a, $b);
}

/**
 *
 * @staticvar boolean $tables
 * @param type $databasename
 * @param type $tablename
 * @param type $path
 * @param type $params
 * @return XMETATable 
 */
function xmetadb_table($databasename, $tablename, $path = "misc", $params = false)
{
    return XMETATable::xmetadbTable($databasename, $tablename, $path , $params );
}

/**
 * 
 * @param type $image
 * @param type $filename
 */
function xmetadb_image_fix_orientation(&$image, $filename)
{
    if (function_exists("exif_read_data"))
    {
        $exif = @exif_read_data($filename);
        if (!empty($exif['Orientation']))
        {
            switch ($exif['Orientation'])
            {
                default:
                    break;
                case 3:
                    $image = imagerotate($image, 180, 0);
                    break;

                case 6:
                    $image = imagerotate($image, -90, 0);
                    break;

                case 8:
                    $image = imagerotate($image, 90, 0);
                    break;
            }
        }
    }
    else
    {
        
    }
}
