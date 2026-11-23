<?php
//---------image--------------------------------------->
class xmetadbfrm_field_image
{
    var $fieldvalues;
    function __construct($field)
    {
        $this->fieldvalues = $field['fieldvalues'];
    }

    function show($params)
    {
        $html = "";
        $attributes = isset($params["htmlattributes"]) ? $params["htmlattributes"] : "";
        $oldvalues = $params['oldvalues'];
        $primarykey = $params['oldvalues_primarikey'];
        $oldval = $params['value'];
        $toltips = "";
        $size = isset($params['frm_size']) ? $params['frm_size'] : 20;
        $tsize = isset($params['thumbsize']) ? $params['thumbsize'] : 20;
        $html .= "<table style=\"border:0px;padding:0px\" cellpadding=\"0\" cellspacing=\"0\"><tr><td><input $attributes $toltips size=\"" . $size . "\" name=\"" . $params['name'] . "\" type=\"file\" />\n";
        $html .= "<br />";
        if ($oldval != "" && isset($oldvalues[$primarykey]))
        {
            $width = $height = "";
            $imgthsrc = $params['fieldform']->xmltable->getThumbPath($oldvalues, $params['name']);
            if (file_exists($imgthsrc))
                list($width, $height) = getimagesize($imgthsrc);
            if ($height >= $width)
                $res = "height=\"$tsize\"";
            else
                $res = "width=\"$tsize\"";
            $imgthurl = $params['fieldform']->xmltable->getFileUrl($oldvalues, $params['name'], true);
            $html .= "<table cellpadding=\"0\" cellspacing=\"0\" style=\"border:1px dotted;height:$tsize" . "px;width:$tsize" . "px\"><tr><td valign=\"center\"><img style=\"vertical-align: middle;\" $res src=\"" . $imgthurl . "\" alt=\"$oldval\" border=\"0\" />";
            $html .= "<span style=\"white-space:nowrap\" ><label><input type=\"checkbox\" value=\"null\" name=\"__isnull__" . $params['name'] . "\" />" . $params['messages']["_XMLDBDELETE"] . "</label>";
            $html .= "</span></td></tr></table>";
        }
        $html .= "</td></tr></table>";
        return $html;
    }

    function view($params)
    {
        $htmlout = "";
        $xmltable = $params['fieldform']->xmltable;
        $values = $params['values'];
        $fileimage = $xmltable->getFilePath($values, $params['name']);
        if ($fileimage != "" && file_exists($fileimage))
        {
            $urlimage = $xmltable->getFileUrl($values, $params['name']);
            $urlthumb = $xmltable->getFileUrl($values, $params['name'], true);
            // the record pages of dbview open it in an overlay (modules/dbview/image_overlay.js),
            // elsewhere and without javascript it opens in a new tab
            $htmlout .= "\n<a class=\"fn-image-zoom\" href=\"$urlimage\" target=\"_blank\" rel=\"noopener\"><img alt=\"\" title=\"";
            $htmlout .= XMETADB_i18n("click to zoom in") . "\"";
            $htmlout .= " border=\"0\" src=\"$urlthumb\"></a><br />";
        }
        elseif ($fileimage != "")
        {
            $htmlout .= "<br />" . basename($fileimage) . "<br />";
        }
        return $htmlout;
    }

    //TODO
    function Verify($newvalues, $update = false)
    {
        
    }

}

//---------image---------------------------------------<
