<?php

/**
 * _mod_rewrite
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 1011
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
if (!function_exists("FN_BuildHtaccess"))
{

    /**
     *
     * @global int $_FN 
     */
    function FN_BuildHtaccess()
    {
        
        // return;
        global $_FN;
        $RewriteBase=FN_GetParam("PHP_SELF",$_SERVER);

        if ($RewriteBase== "")
            $RewriteBase="/";
        else
        {
            $RewriteBase=dirname($RewriteBase)."/";
            if ($RewriteBase== "//")
                $RewriteBase="/";
        }
        $sthtaccess="# BEGIN Finis
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase $RewriteBase
RewriteRule (^[0-9a-zA-z_][0-9a-zA-z_])\/([0-9a-zA-z_]+)\/([0-9a-zA-z_]+)\/([0-9a-zA-z_\.]+)\/ index.php?mod=$2&lang=$1&op=$3&id=$4 [L,QSA,NC]
RewriteRule (^[0-9a-zA-z_][0-9a-zA-z_])\/([0-9a-zA-z_]+)\/([0-9a-zA-z_]+)\/ index.php?mod=$2&lang=$1&op=$3 [L,QSA,NC]
RewriteRule (^[0-9a-zA-z_][0-9a-zA-z_])\/([0-9a-zA-z_]+)\/ index.php?mod=$2&lang=$1 [L,QSA,NC]
RewriteRule (^[0-9a-zA-z_][0-9a-zA-z_])\/ index.php?lang=$1 [L,QSA,NC]
</IfModule>
# END Finis
";
        $sthtaccess=FN_FixNewline($sthtaccess);
        if (!file_exists("{$_FN['src_application']}/.htaccess"))
        {
            $htcontents="";
        }
        else
        {
            $htcontents=file_get_contents("{$_FN['src_application']}/.htaccess");
        }
        if (strpos($htcontents,$sthtaccess)=== false)
        {
            if (strpos($htcontents,"# BEGIN Finis")=== false)
            {
                $newfilestring=$htcontents."\n".$sthtaccess;
            }
            else
            {
                $newfilestring=preg_replace("/# BEGIN Finis(.*)# END Finis/s",str_replace('$','\$',$sthtaccess),$htcontents);
            }
            $newfilestring=FN_FixNewline($newfilestring);
            if (!file_exists("{$_FN['src_application']}/.htaccess.lock") && FN_Write("0","{$_FN['src_application']}/.htaccess.lock"))
            {
                if (!FN_Write($newfilestring,"{$_FN['src_application']}/.htaccess"))
                {
                    echo ".htacces is not writable";
                    $_FN['enable_mod_rewrite']=0;
                }
                FN_Unlink("{$_FN['src_application']}/.htaccess.lock");
            }
            else
            {
                $_FN['enable_mod_rewrite']=0;
            }
        }
    }

}

if (!function_exists("FN_RewriteLink"))
{

    /**
     *
     * @global array $_FN
     * @param string $href
     * @param string $sep
     * @return string
     */
    function FN_RewriteLink($href,$sep="",$full=false)
    {
        global $_FN;
        $modok=false;
        $hrefori=$href;
        if ($sep== "")
        {
            if (fn_erg("&amp;",$href))
            {
                $sep="&amp;";
            }
            else
            {
                $sep="&";
            }
        }
        if ($_FN['enable_mod_rewrite'] > 0)
        {
            $urlinfo=parse_url($href);
            $scriptname=isset($urlinfo['path']) ? basename($urlinfo['path']) : "index.php";
            if ($scriptname!= "index.php")
                return $href;
            else
            {
                $href=$scriptname;
            }

            $var="";
            if (isset($urlinfo['query']))
            {
                $var=str_replace("&amp;","&",$urlinfo['query']);
            }
            $var=explode('&',$var);
            $arr=array();
            $lang=$_FN['lang'];
            $langid="";
            if ($lang!= $_FN['lang_default'])
                $langid="$lang";
            $op="";
            $id="";
            foreach($var as $val)
            {

                $x=explode('=',$val);
                if (isset($x[1]) /*&& $x[1]!= ""*/)
                {
                    if (strpos($x[1],"/")!== false || strpos($x[1],"-")!== false)
                    {
                        if ($x[1]!= "")
                            $arr[]=$x[0]."=".$x[1];
                        else
                            $arr[]=$x[0];
                    }
                    else
                    {
                        switch($x[0])
                        {
                            case "lang" :
                                $langid=$x[1];
                                break;
                            case "op" :
                                $op=$x[1];
                                break;
                            case "id" :
                                $id=$x[1];
                                break;
                            case "mod" :
                                if ($x[1]!= "")
                                {
                                    $href=$x[1];
                                    $modok=true;
                                }
                                break;
                            default :
                                if ($x[1]!= "")
                                    $arr[]=$x[0]."=".$x[1];
                                else
                                    $arr[]=$x[0];
                                break;
                        }
                    }
                }
            }
            if ($langid== "")
                $langid=$_FN['lang'];
            if ($op== "" && $id!= "")
            {
                return $hrefori;
            }
            if (!$modok)
            {
                if ($_FN['home_section']!= "")
                {
                    $href=$_FN['home_section'];
                }
            }
            $query=implode("$sep",$arr);
            $href="$langid/{$href}/";
            if ($op!== "")
            {
                $href.="$op/";
            }
            if ($id!== "")
            {
                $href.="$id/";
            }

            if ($query!= "")
            {
                $href.="?$query"; // . $urlinfo ['query'];
            }
        }
        if (true)
        {
            $siteurl=empty($_FN['use_urlserverpath']) ? $_FN['siteurl'] : $_FN['sitepath'];

            $href=$siteurl.$href;
        }
        return $href;
    }

}