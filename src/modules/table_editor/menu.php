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
