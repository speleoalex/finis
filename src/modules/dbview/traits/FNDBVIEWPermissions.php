<?php
/**
 * FNDBVIEW Permissions Trait
 * User permissions management for dbview module
 *
 * @package Finis_module_dbview
 * @author Alessandro Vernassa <speleoalex@gmail.com>
 */

trait FNDBVIEWPermissions
{
    /**
     * Check if current user is admin
     * @return bool
     */
    function IsAdmin()
    {
        if (FN_IsAdmin())
            return true;
        global $_FN;
        $config = $this->config;
        if (!empty($config['groupadmin']) && FN_UserInGroup($_FN['user'], $config['groupadmin']))
            return true;
        return false;
    }

    /**
     * Get the user associated with a field/record
     * @param array $row
     * @param string $tablename
     * @param string $databasename
     * @param string $pathdatabase
     * @return string
     */
    function GetFieldUser($row, $tablename, $databasename, $pathdatabase)
    {
        global $_FN;
        $listusers = FN_XMDBTable("fieldusers");
        $t = FN_XMDBTable($tablename);
        $restr = array();
        $field['username'] = '-';
        $restr['table_unirecid'] = $row[$t->primarykey];
        $restr['tablename'] = $tablename;
        $listusers = FN_XMDBTable("fieldusers");
        $field = $listusers->GetRecord($restr);
        return $field['username'];
    }

    /**
     * Get list of users allowed to edit a record
     * @param array $row
     * @param string $tablename
     * @param bool $usecache
     * @return array
     */
    function GetFieldUserList($row, $tablename, $usecache = true)
    {
        static $userPerm = false;
        $t = FN_XMDBTable($tablename);
        if (!$userPerm || !$usecache) {
            $listusers = FN_XMDBTable("fieldusers");
            $userPerm = $listusers->GetRecords();
        }
        $ret = array();
        foreach ($userPerm as $row_perm) {
            if ($row[$t->primarykey] == $row_perm['table_unirecid'] && $tablename == $row_perm['tablename']) {
                $ret[] = $row_perm;
            }
        }
        return $ret;
    }

    /**
     * Check if current user can edit the record
     * @param array $row
     * @return bool
     */
    function IsAdminRecord($row)
    {
        global $_FN;
        $config = $this->config;
        $tablename = $config['tables'];

        if (FN_IsAdmin())
            return true;
        $user = $_FN['user'];
        if ($_FN['user'] == "")
            return false;
        if (isset($row['username']) && $row['username'] == $_FN['user'])
            return true;
        if (isset($row['user']) && $row['user'] == $user)
            return true;
        if ($_FN['user'] != "" && $config['groupadmin'] != "" && FN_UserInGroup($_FN['user'], $config['groupadmin'])) {
            return true;
        }

        // Per-record permissions
        if (empty($config['viewonlycreator'])) {
            if (!empty($config['enable_permissions_edit_each_records']) && $config['enable_permissions_edit_each_records'] == 1) {
                $record = $row;
                if (empty($record['groupinsert'])) {
                    return true;
                } else {
                    $groups_can_insert = explode(",", $record['groupinsert'] . "," . $config['groupadmin']);
                    foreach ($groups_can_insert as $gr_can_insert) {
                        if ($gr_can_insert != "" && FN_UserInGroup($_FN['user'], $gr_can_insert)) {
                            return true;
                        }
                    }
                    return false;
                }
            }
        }

        if ($this->UserCanEditField($user, $row)) {
            return true;
        }

        return false;
    }

    /**
     * Check if user can add records
     * @return bool
     */
    function CanAddRecord()
    {
        global $_FN;
        if (FN_IsAdmin())
            return true;

        $config = $this->config;
        if ($_FN['user'] != "" && $config['groupadmin'] != "" && FN_UserInGroup($_FN['user'], $config['groupadmin']))
            return true;
        if ($_FN['user'] != "" && $config['groupinsert'] != "" && FN_UserInGroup($_FN['user'], $config['groupinsert']))
            return true;
        if ($_FN['user'] != "" && $config['groupinsert'] == "")
            return true;
        return false;
    }

    /**
     * Check if user can view records
     * @param array $config
     * @return bool
     */
    function CanViewRecords($config = "")
    {
        global $_FN;
        if (FN_IsAdmin())
            return true;
        if (!$config)
            $config = $this->config;
        if ($_FN['user'] != "" && $config['groupadmin'] != "" && FN_UserInGroup($_FN['user'], $config['groupadmin']))
            return true;
        if ($_FN['user'] != "" && $config['groupview'] != "" && FN_UserInGroup($_FN['user'], $config['groupview']))
            return true;
        if ($_FN['user'] != "" && $config['groupinsert'] != "" && FN_UserInGroup($_FN['user'], $config['groupinsert']))
            return true;
        if ($config['groupview'] == "")
            return true;
        return false;
    }

    /**
     * Check if user can edit a field
     * @param string $user
     * @param array $row
     * @return bool
     */
    function UserCanEditField($user, $row)
    {
        global $_FN;
        $config = $this->config;
        $tables = explode(",", $config['tables']);
        $tablename = $tables[0];

        if ($user == "")
            return false;
        $t = FN_XMDBTable($tablename);
        $restr = array();
        $restr['table_unirecid'] = $row[$t->primarykey];
        $restr['tablename'] = $tablename;
        $restr['username'] = $user;
        $list_field = $this->GetFieldUserList($row, $tablename, $_FN['database']);
        $id_record = $row[$t->primarykey];
        if (is_array($list_field))
            foreach ($list_field as $field) {
                if ($field['username'] == $user && $field['table_unirecid'] == $row[$t->primarykey] && $field['tablename'] == $tablename)
                    return true;
            }
        return false;
    }

    /**
     * Check if user can edit a specific record
     * @param mixed $id
     * @param string $tablename
     * @return bool
     */
    function CanEditRecord($id, $tablename)
    {
        global $_FN;
        if (FN_IsAdmin())
            return true;

        $config = $this->config;

        // If table is in another section, load correct config
        if ($config['tables'] != $tablename) {
            foreach ($_FN['sections'] as $section) {
                if ($section['type'] == "navigator" || $section['type'] == "dbview") {
                    $configTmp = FN_LoadConfig("", $section['id']);
                    if ($configTmp['tables'] == $tablename) {
                        $config = $configTmp;
                        if (!FN_UserCanViewSection($section['id'])) {
                            return false;
                        }
                        break;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Check if user can view a specific record
     * @param mixed $id
     * @param string $tablename
     * @param array $config
     * @return bool
     */
    function CanViewRecord($id, $tablename = "", $config = "")
    {
        global $_FN;
        if (FN_IsAdmin())
            return true;

        if (!$config) {
            $config = $this->config;
        }
        if (!$tablename) {
            $tablename = $config['tables'];
        }

        // If table is in another section, load correct config
        if ($config['tables'] != $tablename) {
            foreach ($_FN['sections'] as $section) {
                if ($section['type'] == "navigator" || $section['type'] == "dbview") {
                    $configTmp = FN_LoadConfig("", $section['id']);
                    if ($configTmp['tables'] == $tablename) {
                        $config = $configTmp;
                        if (!FN_UserCanViewSection($section['id'])) {
                            return false;
                        }
                        break;
                    }
                }
            }
        }

        $table = FN_XMDBTable($tablename);
        $record = $table->GetRecordByPrimaryKey($id);

        // View only for creator
        if (!empty($config['viewonlycreator'])) {
            if ($_FN['user'] == "" && $record['username'] != "") {
                return false;
            } elseif ($_FN['user'] == $record['username']) {
                return true;
            }

            $list_field = $this->GetFieldUserList($record, $tablename);
            if (is_array($list_field)) {
                foreach ($list_field as $field) {
                    if ($field['username'] == $_FN['user'] &&
                        $field['table_unirecid'] == $record[$table->primarykey] &&
                        $field['tablename'] == $tablename) {
                        return true;
                    }
                }
            }
        } else {
            // Per-record permissions
            if (!empty($config['enable_permissions_each_records']) && $config['enable_permissions_each_records'] == 1) {
                if (empty($record['groupview'])) {
                    return true;
                } else {
                    if ($_FN['user'] == "")
                        return false;
                    $uservalues = FN_GetUser($_FN['user']);
                    $usergroups = explode(",", $uservalues['group']);
                    $groupsview = explode(",", $record['groupview']);
                    $groupinsert = explode(",", $config['groupinsert']);
                    $groupadmin = explode(",", $config['groupadmin']);
                    foreach ($usergroups as $group) {
                        if (in_array($group, $groupsview) || in_array($group, $groupinsert) || in_array($group, $groupadmin)) {
                            return true;
                        }
                    }
                    return false;
                }
            } else {
                if (empty($record['groupview']) && empty($config['viewonlycreator'])) {
                    return true;
                }
            }
        }

        return true;
    }
}
