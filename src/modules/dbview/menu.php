<?php

/**
 * Menu hook of the module (FN_MenuSectionIsVisible()): the section is listed
 * only to the users who may view its records (groupview, groupadmin,
 * groupinsert of the section settings).
 *
 * @param array $section values of the section (FN_GetSections())
 * @return bool
 */
function dbview_MenuSectionIsVisible($section)
{
    if (!class_exists("FNDBVIEW"))
        require_once __DIR__ . "/FNDBVIEW.php";
    $dbview = new FNDBVIEW(FN_LoadConfig("modules/dbview/config.php", $section['id']));
    return $dbview->CanViewRecords();
}

/**
 * Sitemap hook (FN_GetModuleMenuFunction()): the groups of the section
 * settings that may view the records, "" when everyone may.
 *
 * @param array $section values of the section (FN_GetSections())
 * @return string
 */
function dbview_SectionViewRestriction($section)
{
    $config = FN_LoadConfig("modules/dbview/config.php", $section['id']);
    return isset($config['groupview']) ? $config['groupview'] : "";
}
