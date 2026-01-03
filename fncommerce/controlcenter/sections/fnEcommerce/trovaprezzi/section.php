<?php
/**
 * @package flatnux_controlcenter_fncommerce
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2003-2005
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 *
 */
defined('_FNEXEC') or die('Restricted access');
require_once (__DIR__ . "/../../../../modules/fncommerce/functions/fncommerce.php");
/**
 * 10_Google_sitemap.php created on 10/dic/2008
 *
 * 
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */
global $_FN;
$op = FN_GetParam("opt", $_GET, "flat");
$opmod = FN_GetParam("opmod", $_GET, "flat");
$outputfile="trovaprezzi.txt";

if (!file_exists("$outputfile") && !is_writable("."))
{
	echo "<b>" . FN_Translate("permissions error") . "</b><br /><br />" . FN_Translate("folder is not wrietable");
	echo "<br /><pre>" . dirname($_SERVER['SCRIPT_FILENAME']) . "</pre>";
}
else
	if ( file_exists($outputfile) && !is_writable($outputfile))
	{
		echo "$outputfile : " . FN_Translate("is read-only") . "<br />";
	}
	else
	{
		if ($opmod == "update")
		{
			fn_create_trovaprezzi_sitemap("$outputfile");
			FN_Alert(FN_Translate("file updated"));
		}
		echo "<a class=\"btn btn-primary\" href=\"?fnapp=controlcenter&amp;opt=$op&amp;opmod=update\">" . FN_Translate("update") . " $outputfile</a><br />";
	}
$imghelp="<img style=\"vertical-align:middle\" alt=\"\" src=\"".FN_FromTheme("images/help.png")."\"/>";
echo "<div style=\"text-align:right\">$imghelp&nbsp;<a href=\"https://www.google.com/webmasters/tools/docs/{$_FN['lang']}/about.html\">" . FN_i18n("help") . "</a></div>";
if (file_exists("$outputfile"))
	echo "<pre style=\"border:1px inset;height:300px;overflow:auto;\">" . htmlspecialchars(file_get_contents("$outputfile")) . "</pre>";

echo "<br /><b>URL sitemap:</b> ".$_FN['siteurl']."$outputfile<br/>";

/**
 * Crea la sitemap
 *
 * @param string $filename path del file da scrivere
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 *
 */
function fn_create_trovaprezzi_sitemap($filename)
{
	global $_FN;
	$modlist = fnc_getproducts(array("status"=>1));
	$str = "";
	foreach ($modlist as $mod)
	{

/*
Prodotto                              (nome prodotto)
Marca
Descrizione                        (max 255 caratteri)
Prezzo                                Numerico
Codice Prodotto                 OBBLIGATORIO (UNICO PER CIASCUN PRODOTTO)!
Link          
Disponibilità                         (Possibili Valori: disponibile, non disponibile, in arrivo, vedere sito, limitata)
Categoria                            (esempio  Fotografia,Macchine Digitali) (le macrocategorie andrebbero separate dalle sottocategorie o da , o ; )
URL Immagine piccola         
Spese Spedizione               Numerico ( se incluse mettere 0, invece se impossibili da inserire mettere -1 )
Codice Produttore               (OBBLIGATORIO)

Per delimitare va bene il carattere | (pipe)
I campi Prezzo e Spese Spedizione devono avere lo stesso separatore di decimali (o il punto o la virgola)
I campi Prezzo e Spese Spedizione devono avere SOLO il separatore di decimali e NON devono avere il separatore delle migliaia
I campi Prezzo e Spese Spedizione devono contenere SOLO CIFRE e nessun altro testo (es. simbolo dell'Euro)

Alla fine di ogni record, aggiungere il seguente tag: <endrecord>
*/
		$spese="";
		$codiceproduttore="{$mod['model']}";
		$marca="";
		$description=str_replace("\n","",str_replace("\r","",(strip_tags($mod['description']))));
		$url = "{$_FN['siteurl']}" . fn_rewritelink("index.php?mod=fncommerce&amp;op=view&amp;id={$mod['unirecid']}");
		$str .="{$mod['name']}|$marca|".$description."|".$mod['price']."|{$mod['model']}|{$url}|{$spese}|$codiceproduttore<endrecord>\n";
	}
	$handle = fopen($filename, "w");
	fwrite($handle, $str);
	fclose($handle);
}
?>