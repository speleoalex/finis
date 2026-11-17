<?php
/**
 * index.php?fnapp=notifications: the unread notifications of the user (JSON).
 * display=<id> marks that notification as read; with go=1 the browser is sent to
 * its page instead (the link of the themes' notification menu).
 * @package Finis
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 * @copyright Copyright (c) 2021
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License
 */
global $_FN;
$notifications = array();
$target = $_FN['siteurl'];
if (!empty($_FN['user']))
{
    $id = FN_GetParam("display", $_GET, "int");
    $notifications = FN_GetNotificationsUndisplayed($_FN['user']);
    foreach ($notifications as $notification)
    {
        if ($id && $notification['id'] == $id)
        {
            FN_SetNotificationDisplayed($id);
            // FN_AddNotification stores the link html-escaped; only pages of this site
            $link = html_entity_decode($notification['link'], ENT_QUOTES, "UTF-8");
            if ($link !== "" && (strpos($link, $_FN['siteurl']) === 0 || !preg_match('#^([a-z][a-z0-9+.-]*:|//|\\\\)#i', $link)))
                $target = $link;
            break;
        }
    }
}
if (FN_GetParam("go", $_GET) !== "")
{
    header("Location: $target");
    exit;
}
header('Content-Type: application/json');
echo json_encode($notifications);
