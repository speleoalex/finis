<?php

/**
 * Presentation helpers for the control center sections.
 *
 * A section must not decide how the panel looks: it describes what it shows
 * (a button, a card, a table, a help box) and these helpers emit neutral
 * markup with .fncc-* classes. The look comes from controlcenter/css/fncc-ui.css
 * and from the tokens a theme redefines, so the same section works on every
 * control center theme and in the dark palette.
 *
 * Rules when writing a section:
 *   - no inline style="" and no hard coded colour;
 *   - no inline onclick: use the data-fncc-* attributes read by fncc-ui.js;
 *   - every text passes through FN_Translate()/FN_i18n().
 *
 * @package Finis_controlcenter
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
defined('_FNEXEC') or die('Restricted access');

/**
 * Escape a value for HTML, using the charset of the page.
 *
 * Use this for urls and for anything built by the code. For a text coming
 * from the database use FNCC_UI_EscapeText(), which keeps the entities it
 * may already contain.
 *
 * @param string $text
 * @return string
 */
function FNCC_UI_Escape($text)
{
    global $_FN;
    $charset = empty($_FN['charset_page']) ? "UTF-8" : $_FN['charset_page'];
    return htmlspecialchars((string) $text, ENT_QUOTES, $charset);
}

/**
 * Turn a stored text back into plain text.
 *
 * Titles are stored with html entities ("Cavit&agrave;"): escaping them again
 * would show "Cavit&amp;agrave;" to the user. Decoding first and letting
 * FNCC_UI_Escape() encode the result keeps one single escaping point, and a
 * tag hidden in the text is still neutralised.
 *
 * @param string $text
 * @return string
 */
function FNCC_UI_PlainText($text)
{
    global $_FN;
    $charset = empty($_FN['charset_page']) ? "UTF-8" : $_FN['charset_page'];
    return html_entity_decode((string) $text, ENT_QUOTES, $charset);
}

/**
 * Build the attributes of a tag from an associative array.
 * A null or false value drops the attribute, true prints it bare (required).
 *
 * @param array $attributes
 * @return string
 */
function FNCC_UI_Attributes($attributes)
{
    $html = "";
    foreach ($attributes as $name => $value)
    {
        if ($value === null || $value === false || $value === "")
        {
            continue;
        }
        if ($value === true)
        {
            $html .= " " . $name;
            continue;
        }
        $html .= " " . $name . "=\"" . FNCC_UI_Escape($value) . "\"";
    }
    return $html;
}

/**
 * Copy an asset of FINIS into the application and return its public url.
 *
 * FN_PathSite() only copies a file that is missing: during an upgrade the
 * stale copy would stay in the site, so the modification time is compared.
 * The ?v= of the url is that same time, so the browser cache follows.
 *
 * @param string $file path relative to the FINIS sources
 * @return string url of the published file
 */
function FNCC_UI_PublishAsset($file)
{
    global $_FN;
    $source = "{$_FN['src_finis']}/$file";
    $destination = FN_NormalizePath("{$_FN['src_application']}/$file");
    if (!file_exists($source))
    {
        return "";
    }
    if (!file_exists($destination) || filemtime($source) > filemtime($destination))
    {
        FN_Copy($source, $destination, true);
    }
    $version = @filemtime($destination);
    return $_FN['siteurl'] . $file . ($version ? "?v=$version" : "");
}

/**
 * The stylesheet and the script shared by every control center theme.
 *
 * @return string
 */
/**
 * Remember whether the shared assets have already been put in the page.
 *
 * Both the theme (through {cc_assets}) and FNCC_UI_Section() can place them:
 * whoever gets there first wins, the other one keeps quiet.
 *
 * @param bool $markAsDone true to record that they are in the page
 * @return bool
 */
function FNCC_UI_AssetsEmitted($markAsDone = false)
{
    static $done = false;
    if ($markAsDone)
    {
        $done = true;
    }
    return $done;
}

function FNCC_UI_AssetsHtml()
{
    global $_FN;
    FNCC_UI_AssetsEmitted(true);
    $theme = $_FN['controlcenter_theme'];
    // The style of the theme comes after the components, so that a theme can
    // override them. A theme publishes its own css/theme.css and js/theme.js
    // this way instead of linking them from its template: the copy of a file
    // referenced in a template is only made once, and would stay behind after
    // an upgrade of FINIS.
    $files = array(
        "css" => array("controlcenter/css/fncc-ui.css", "controlcenter/themes/$theme/css/theme.css"),
        "js" => array("controlcenter/js/fncc-ui.js", "controlcenter/themes/$theme/js/theme.js"),
    );
    $html = "";
    foreach ($files['css'] as $file)
    {
        $url = FNCC_UI_PublishAsset($file);
        if ($url)
        {
            $html .= "\n\t<link rel=\"stylesheet\" href=\"" . FNCC_UI_Escape($url) . "\" />";
        }
    }
    foreach ($files['js'] as $file)
    {
        $url = FNCC_UI_PublishAsset($file);
        if ($url)
        {
            $html .= "\n\t<script src=\"" . FNCC_UI_Escape($url) . "\" defer=\"defer\"></script>";
        }
    }
    return $html;
}

/**
 * Ask for the shared assets to be put in the <head>, once per request.
 *
 * For the themes whose template has no {cc_assets}: the html is parked here
 * and controlcenter.php writes it before </head>. It does not go through
 * $_FN['section_header_footer'], which the control center prints twice (once
 * from FN_HtmlHeader and once before </head>).
 */
function FNCC_UI_IncludeAssets()
{
    global $_FN;
    if (FNCC_UI_AssetsEmitted())
    {
        return;
    }
    $_FN['cc_assets_pending'] = FNCC_UI_AssetsHtml();
}

/**
 * A link or a button.
 *
 * @param string $label text of the button (already translated)
 * @param array $options href, icon, variant (primary|danger|quiet|icon),
 *                       confirm, title, type, name, value, class, attributes
 * @return string
 */
function FNCC_UI_Button($label, $options = array())
{
    $class = "fncc-btn";
    if (!empty($options['variant']))
    {
        foreach (explode(" ", $options['variant']) as $variant)
        {
            $class .= " fncc-btn--" . $variant;
        }
    }
    if (!empty($options['class']))
    {
        $class .= " " . $options['class'];
    }

    $icon = empty($options['icon']) ? "" : "<span aria-hidden=\"true\">" . FNCC_UI_Escape($options['icon']) . "</span>";
    $text = ($label === "") ? "" : "<span>" . FNCC_UI_Escape($label) . "</span>";
    // An icon only button still needs a name for screen readers.
    if ($label !== "" && !empty($options['icon']) && strpos($class, "fncc-btn--icon") !== false)
    {
        $text = "<span class=\"fncc-visually-hidden\">" . FNCC_UI_Escape($label) . "</span>";
    }

    $attributes = array(
        "class" => $class,
        "title" => isset($options['title']) ? $options['title'] : null,
        "data-fncc-confirm" => isset($options['confirm']) ? $options['confirm'] : null,
    );
    if (!empty($options['attributes']) && is_array($options['attributes']))
    {
        $attributes = array_merge($attributes, $options['attributes']);
    }

    if (isset($options['href']))
    {
        $attributes['href'] = $options['href'];
        return "<a" . FNCC_UI_Attributes($attributes) . ">$icon$text</a>";
    }
    $attributes['type'] = isset($options['type']) ? $options['type'] : "button";
    $attributes['name'] = isset($options['name']) ? $options['name'] : null;
    $attributes['value'] = isset($options['value']) ? $options['value'] : null;
    return "<button" . FNCC_UI_Attributes($attributes) . ">$icon$text</button>";
}

/**
 * A row of buttons.
 *
 * @param array $items html of the buttons; the string "spacer" pushes what
 *                     follows to the right
 * @return string
 */
function FNCC_UI_Toolbar($items)
{
    $html = "<div class=\"fncc-toolbar\">";
    foreach ($items as $item)
    {
        $html .= ($item === "spacer") ? "<span class=\"fncc-toolbar__spacer\"></span>" : $item;
    }
    return $html . "</div>";
}

/**
 * A message box.
 *
 * @param string $text already translated, html allowed when $isHtml
 * @param string $type info|success|warning|danger
 * @param bool $isHtml
 * @return string
 */
function FNCC_UI_Alert($text, $type = "info", $isHtml = false)
{
    $body = $isHtml ? $text : FNCC_UI_Escape($text);
    $role = ($type === "danger") ? " role=\"alert\"" : "";
    return "<div class=\"fncc-alert fncc-alert--" . FNCC_UI_Escape($type) . "\"$role>$body</div>";
}

/**
 * A titled panel.
 *
 * @param string $title already translated; empty for a card with no header
 * @param string $bodyHtml
 * @param string $headerExtraHtml buttons shown on the right of the title
 * @return string
 */
function FNCC_UI_Card($title, $bodyHtml, $headerExtraHtml = "")
{
    $html = "<section class=\"fncc-card\">";
    if ($title !== "" || $headerExtraHtml !== "")
    {
        $html .= "<header class=\"fncc-card__header\"><span>" . FNCC_UI_Escape($title) . "</span>";
        if ($headerExtraHtml !== "")
        {
            $html .= "<span class=\"fncc-toolbar__spacer\"></span>$headerExtraHtml";
        }
        $html .= "</header>";
    }
    return $html . "<div class=\"fncc-card__body\">$bodyHtml</div></section>";
}

/**
 * A table that becomes a list of cards on a phone.
 *
 * @param array $headers column titles, already translated
 * @param array $rows list of rows; each row is a list of cells of html
 * @param string $emptyText shown when there is no row
 * @return string
 */
function FNCC_UI_Table($headers, $rows, $emptyText = "")
{
    if (count($rows) == 0)
    {
        return FNCC_UI_Alert($emptyText === "" ? FN_Translate("no results") : $emptyText);
    }
    $html = "<div class=\"fncc-tablewrap\"><table class=\"fncc-table fncc-table--stack\"><thead><tr>";
    foreach ($headers as $header)
    {
        $html .= "<th scope=\"col\">" . FNCC_UI_Escape($header) . "</th>";
    }
    $html .= "</tr></thead><tbody>";
    foreach ($rows as $row)
    {
        $html .= "<tr>";
        $column = 0;
        foreach ($row as $cell)
        {
            // The label repeated on each cell is what the stacked layout shows.
            $label = isset($headers[$column]) ? $headers[$column] : "";
            $html .= "<td" . FNCC_UI_Attributes(array("data-label" => $label)) . ">$cell</td>";
            $column++;
        }
        $html .= "</tr>";
    }
    return $html . "</tbody></table></div>";
}

/**
 * The instructions of a section, read from its help/ folder.
 *
 * The file is looked up as help/section.<lang>.html, then in the default
 * language, then in english, in the application first and then in FINIS, so a
 * site can replace the text of a section it customised.
 *
 * @param string $opt path of the section, e.g. "contents/sitemap"
 * @param bool $open true to show the box already expanded
 * @return string empty when the section has no help
 */
function FNCC_UI_Help($opt, $open = false)
{
    global $_FN;
    if (empty($opt))
    {
        return "";
    }
    $languages = array($_FN['lang'], $_FN['lang_default'], "en");
    $roots = array("{$_FN['src_application']}/controlcenter/sections", "{$_FN['src_finis']}/controlcenter/sections");
    if (!empty($_FN['path_extensions']) && is_array($_FN['path_extensions']))
    {
        foreach ($_FN['path_extensions'] as $extension)
        {
            $roots[] = rtrim($extension, '/') . "/controlcenter/sections";
        }
    }
    $file = "";
    foreach ($roots as $root)
    {
        foreach ($languages as $language)
        {
            $candidate = "$root/$opt/help/section.$language.html";
            if ($language != "" && file_exists($candidate))
            {
                $file = $candidate;
                break 2;
            }
        }
    }
    if ($file === "")
    {
        return "";
    }
    $title = FN_Translate("how this page works");
    return "<details class=\"fncc-help\"" . ($open ? " open=\"open\"" : "") . ">"
        . "<summary><span aria-hidden=\"true\">&#x2139;</span> " . FNCC_UI_Escape($title) . "</summary>"
        . "<div class=\"fncc-help__body\">" . file_get_contents($file) . "</div>"
        . "</details>";
}

/**
 * Wrap the output of a section.
 *
 * Gives every section the same container (so the stylesheet can reach the
 * markup of the sections not migrated yet) and puts its instructions on top.
 *
 * @param string $html output of section.php
 * @param string $opt path of the section
 * @return string
 */
function FNCC_UI_Section($html, $opt)
{
    FNCC_UI_IncludeAssets();
    return "<div class=\"fncc-section\">" . FNCC_UI_Help($opt) . $html . "</div>";
}
