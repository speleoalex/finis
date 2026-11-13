<?php

/**
 * Menu hook of the module (FN_MenuSectionIsVisible()): a menu group whose
 * pages are all hidden to the user is hidden too.
 *
 * @param array $section values of the section (FN_GetSections())
 * @return bool
 */
function block_menu_MenuSectionIsVisible($section)
{
    $children = FN_GetSections($section['id']);
    if (empty($children))
        return true;
    foreach ($children as $child)
    {
        if (FN_MenuSectionIsVisible($child))
            return true;
    }
    return false;
}
