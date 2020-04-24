<?php
class Permissions {
    /**
     * Check to see if a user has the permission to perform an action
     * This is called by check_perms in util.php, for convenience.
     *
     * @param string PermissionName
     * @param int $MinClass Return false if the user's class level is below this.
     *
     * @return bool
     */
    public static function check_perms($PermissionName, $MinClass = 0) {
        $Override = self::has_override(G::$LoggedUser['EffectiveClass']);
        return ($PermissionName === null ||
            (isset(G::$LoggedUser['Permissions'][$PermissionName]) && G::$LoggedUser['Permissions'][$PermissionName]))
            && (G::$LoggedUser['Class'] >= $MinClass
                || G::$LoggedUser['EffectiveClass'] >= $MinClass
                || $Override);
    }

    /**
     * Gets the permissions associated with a certain permissionid
     *
     * @param int $PermissionID the kind of permissions to fetch
     * @return array permissions
     */
    public static function get_permissions($PermissionID) {
        $Permission = G::$Cache->get_value("perm_$PermissionID");
        if (empty($Permission)) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query("
                SELECT Level AS Class, `Values` AS Permissions, Secondary, PermittedForums
                FROM permissions
                WHERE ID = '$PermissionID'");
            $Permission = G::$DB->next_record(MYSQLI_ASSOC, ['Permissions']);
            G::$DB->set_query_id($QueryID);
            $Permission['Permissions'] = unserialize($Permission['Permissions']) ?: [];
            G::$Cache->cache_value("perm_$PermissionID", $Permission, 2592000);
        }
        return $Permission;
    }

    /**
     * Get a user's permissions.
     *
     * @param $UserID
     * @param array|false $CustomPermissions
     *    Pass in the user's custom permissions if you already have them.
     *    Leave false if you don't have their permissions. The function will fetch them.
     * @return array Mapping of PermissionName=>bool/int
     */
    public static function get_permissions_for_user($UserID, $CustomPermissions = false) {
        $UserInfo = Users::user_info($UserID);

        // Fetch custom permissions if they weren't passed in.
        if ($CustomPermissions === false) {
            $QueryID = G::$DB->get_query_id();
            G::$DB->query('
                SELECT CustomPermissions
                FROM users_main
                WHERE ID = ' . (int)$UserID);
            list($CustomPermissions) = G::$DB->next_record(MYSQLI_NUM, false);
            G::$DB->set_query_id($QueryID);
        }

        if (!empty($CustomPermissions) && !is_array($CustomPermissions)) {
            $CustomPermissions = unserialize($CustomPermissions);
        }

        $Permissions = self::get_permissions($UserInfo['PermissionID']);

        // TODO: WTF is this nonsense to calculate MaxCollages? - Spine

        // Manage 'special' inherited permissions
        $BonusPerms = [];
        $BonusCollages = 0;
        foreach ($UserInfo['ExtraClasses'] as $PermID => $Value) {
            $ClassPerms = self::get_permissions($PermID);
            $BonusCollages += $ClassPerms['Permissions']['MaxCollages'];
            unset($ClassPerms['Permissions']['MaxCollages']);
            $BonusPerms = array_merge($BonusPerms, $ClassPerms['Permissions']);
        }

        if (empty($CustomPermissions)) {
            $CustomPermissions = [];
        }

        $MaxCollages = $BonusCollages;
        if (is_numeric($Permissions['Permissions']['MaxCollages'])) {
            $MaxCollages += $Permissions['Permissions']['MaxCollages'];
        }
        if (isset($CustomPermissions['MaxCollages'])) {
            $MaxCollages += $CustomPermissions['MaxCollages'];
            unset($CustomPermissions['MaxCollages']);
        }
        $Permissions['Permissions']['MaxCollages'] = $MaxCollages;
        // Combine the permissions
        return array_merge(
            $Permissions['Permissions'],
            $BonusPerms,
            $CustomPermissions
        );
    }

    public static function is_mod($UserID) {
        return self::has_permission($UserID, 'users_mod');
    }

    public static function has_permission($UserID, $privilege) {
        $Permissions = self::get_permissions_for_user($UserID);
        return isset($Permissions[$privilege]) && $Permissions[$privilege];
    }

    public static function has_override($Level) {
        return $Level >= 1000;
    }
}
