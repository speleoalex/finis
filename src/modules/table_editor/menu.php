<?php

/**
 * Menu hook of the module (FN_MenuSectionIsVisible()): the table editor is
 * for the administrators only, who are let through before this is called.
 *
 * @param array $section values of the section (FN_GetSections())
 * @return bool
 */
function table_editor_MenuSectionIsVisible($section)
{
    return false;
}

/**
 * Sitemap hook (FN_GetModuleMenuFunction()): only the administrators.
 *
 * @param array $section values of the section (FN_GetSections())
 * @return string
 */
function table_editor_SectionViewRestriction($section)
{
    return FN_Translate("administrators");
}
