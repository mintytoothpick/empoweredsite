<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Brigade/Db/Table/Programs.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Brigades.php';

class Brigade_Db_Table_UserRoles extends Zend_Db_Table_Abstract {

    protected $_name = 'user_roles';

    public function getUserRole($UserId) {
        $row = $this->fetchRow($this->select()
            ->where("UserId = ?", $UserId)
            ->order('RoleId DESC')
            ->limit(1));
        return $row ? $row->toArray() : null;
    }

    public function addUserRole($values) {
        $values['UserRoleId'] = $this->createUserRoleId();
        $values['CreatedOn']  = date('Y-m-d H:i:s');
        $this->insert($values);

        return $values['UserRoleId'];
    }

    public function editUserRole($UserRoleId, $data) {
        $where = $this->getAdapter()->quoteInto('UserRoleId = ?', $UserRoleId);
        $this->update($data, $where);
    }

    public function createUserRoleId() {
        $row = $this->fetchRow($this->select()->from("user_roles", array('UUID() as UserRoleId')));
        return strtoupper($row['UserRoleId']);
    }

    public function deleteUserRole($UserId, $SiteId) {
        $where = $this->getAdapter()->quoteInto("UserId = '$UserId' AND SiteId = ?", $SiteId);
        $this->delete($where);
    }

    public function deleteUserRoleByUserRoleId($UserRoleId) {
        $where = $this->getAdapter()->quoteInto('UserRoleId = ?', $rows['UserRoleId']);
        $this->delete($where);
    }

    public function deleteUserRolesBySiteId($SiteId) {
        $where = $this->getAdapter()->quoteInto('SiteId = ?', $SiteId);
        $this->delete($where);
    }

    public function nonProfitsManaged($UserId) {
        return $this->fetchAll($this->select()
            ->from(array('ur' => 'user_roles'), array('n.*',))
            ->joinInner(array('n' => 'networks'), 'n.NetworkId = ur.SiteId')
            ->where('ur.UserId = ?', $UserId)
            ->setIntegrityCheck(false))->toArray();
    }

    public function getGroupAdmins() {
        return $this->fetchAll($this->select()->distinct()
            ->from(array('ur' => 'user_roles'), array('ur.UserId', 'g.GroupId'))
            ->joinInner(array('g' => 'groups'), 'g.GroupId = ur.SiteId')
            ->setIntegrityCheck(false))->toArray();
    }

    public function UserHasAccess($SiteId, $UserId, $site = 'network') {
        // check first if user has direct access
        $hasAccess = $this->UserHasDirectAccess($SiteId, $UserId);
        if ($hasAccess) {
            return true;
        } else {
            // check if user has access to the site's parents
            if ($site == 'brigade') {
                $Brigades = new Brigade_Db_Table_Brigades();
                $siteInfo = $Brigades->loadInfoBasic($SiteId);
                if(!empty($siteInfo['GroupId'])) {
                    return $this->hasAccessOnGroup($siteInfo['GroupId'], $UserId);
                } else if(!empty($siteInfo['NetworkId'])) {
                    return $this->hasAccessOnNetwork($siteInfo['NetworkId'], $UserId);
                }
            } else if ($site == 'group') {
                $Groups = new Brigade_Db_Table_Groups();
                $siteInfo = $Groups->loadInfo($SiteId);
                if(!empty($siteInfo['ProgramId'])) {
                    return $this->hasAccessOnProgram($siteInfo['ProgramId'], $UserId);
                } else {
                    return $this->hasAccessOnNetwork($siteInfo['NetworkId'], $UserId);
                }
            } else if ($site == 'program') {
                $Programs = new Brigade_Db_Table_Programs();
                $siteInfo = $Programs->loadInfo($SiteId);
                return $this->hasAccessOnNetwork($siteInfo['NetworkId'], $UserId);
            }
        }
    }

    public function UserHasDirectAccess($SiteId, $UserId) {
        $row = $this->fetchRow($this->select()
            ->from(array('ur' => 'user_roles'), array('ur.*'))
            ->where("ur.SiteId = '$SiteId'")
            ->where("UserId = '$UserId'")
            ->setIntegrityCheck(false));
        return $row ? true : false;
    }

    public function hasAccessOnGroup($GroupId, $UserId) {
        $hasAccess = $this->UserHasDirectAccess($GroupId, $UserId);
        if ($hasAccess) {
            return true;
        } else {
            // check the groups's parent network (and program)
            $Groups = new Brigade_Db_Table_Groups();
            $groupInfo = $Groups->loadInfo($GroupId);
            if(!empty($groupInfo['ProgramId'])) {
                return $this->hasAccessOnProgram($groupInfo['ProgramId'], $UserId);
            } else {
                return $this->hasAccessOnNetwork($groupInfo['NetworkId'], $UserId);
            }
        }
    }

    public function hasAccessOnProgram($ProgramId, $UserId) {
        $hasAccess = $this->UserHasDirectAccess($ProgramId, $UserId);
        if ($hasAccess) {
            return true;
        } else {
            // check the program's parent network
            $Programs = new Brigade_Db_Table_Programs();
            $programInfo = $Programs->loadInfo($ProgramId);
            return $this->hasAccessOnNetwork($programInfo['NetworkId'], $UserId);
        }
    }

    public function hasAccessOnNetwork($NetworkId, $UserId) {
        return $this->UserHasDirectAccess($NetworkId, $UserId);
    }

    public function getSiteAdmin($SiteId, $count = false, $searchText = null) {
        if ($count) {
            $columns = array('COUNT(*) as total_admins');
        } else {
            $columns = array('u.UserId', 'u.FirstName', 'u.LastName', 'ur.UserRoleId', 'u.Email', 'u.URLName', 'u.AboutMe', 'ur.CreatedOn', 'u.*');
        }
        $select = $this->select()
            ->from(array('u' => 'users'), $columns)
            ->joinInner(array('ur' => 'user_roles'), 'u.UserId=ur.UserId')
            ->where('ur.SiteId = ?', $SiteId)
            ->where('u.isDeleted = 0')
            ->group('u.Email')
            ->order('u.FullName')
            ->setIntegrityCheck(false);
        if (!is_null($searchText)) {
            $select = $select->where("u.FullName LIKE '%$searchText%' OR u.Email LIKE '$searchText'");
        }

        if ($count) {
            $row = $this->fetchRow($select)->toArray();
            return $row['total_admins'];
        } else {
            return $this->fetchAll($select)->toArray();
        }
    }

    public function getAdminListBySite($SiteId) {
        return $this->fetchAll($this->select()->where('SiteId = ?', $SiteId));
    }

    public function isUserRoleExists($SiteId, $UserId) {
        $row = $this->fetchAll($this->select()
            ->where('SiteId = ?', $SiteId)
            ->where('UserId = ?', $UserId)
            ->setIntegrityCheck(false))->toArray();
        if (!empty($row)) {
            return true;
        } else {
            return false;
        }
    }

    public function isOrganizationAdmin($UserId) {
        $row = $this->fetchAll($this->select()
            ->from(array('ur' => 'user_roles'), array('ur.*', 'n.*'))
            ->joinInner(array('n' => 'networks'), 'ur.SiteId=n.NetworkId')
            ->where('ur.UserId = ?', $UserId)
            ->where("ur.Level = 'Organization'")
            ->order("n.NetworkName")
            ->setIntegrityCheck(false))->toArray();

        if ($row) {
            return $row;
        } else {
            return false;
        }
    }

    /** Refactor **/

    /**
     * Get data of the user by site.
     *
     * @param String $userId User id
     * @param String $siteId Site id to check role
     *
     * @return RoleId
     */
    public function getByUserAndSite($userId, $siteId) {
        $row = $this->fetchRow($this->select()
            ->where("UserId = ?", $userId)
            ->where("SiteId = ?", $siteId)
            ->order('RoleId DESC')
            ->limit(1));

        if ($row) {
            return $row->toArray();
        }
        return false;
    }

    /**
     * Get role of the user by site.
     *
     * @param String $userId User id
     * @param String $siteId Site id to check role
     *
     * @return RoleId
     */
    public function getRoleByUserAndSite($userId, $siteId) {
        $row = $this->fetchRow($this->select()
            ->where("UserId = ?", $userId)
            ->where("SiteId = ?", $siteId)
            ->order('RoleId DESC')
            ->limit(1));

        $role = false;
        if ($row) {
            $res  = $row->toArray();
            $role = $res['RoleId'];
        }
        return $role;
    }

    /**
     * Get roles by site.
     *
     * @param String $siteId Site id
     *
     * @return RoleId
     */
    public function getBySite($siteId, $search = null) {
        $select = $this->select();
        if (!is_null($search)) {
            $select = $select->setIntegrityCheck(false)
                             ->from(
                                array('ur' => 'user_roles'),
                                array('ur.*', 'u.FullName', 'u.Email')
                             )
                             ->joinInner(array('u' => 'users'), 'u.UserId = ur.UserId')
                             ->where("u.FullName like '%$search%' OR u.Email like '%$search%'");
        }
        $select = $select->where("SiteId = ?", $siteId)
                         ->order('RoleId DESC');
        $rows = $this->fetchAll($select);
        if ($rows) {
            $rows->toArray();
        }
        return $rows;
    }

    /**
     * Validate if the user is globadmin
     *
     * @return bool
     */
    public function isGlobalAdmin($UserId) {
        $row = $this->fetchRow($this->select()
            ->where("UserId = ?", $UserId)
            ->where('RoleId = "GLOB-ADMIN"')
            ->limit(1));

        $role = false;
        if ($row) {
            $role = true;
        }
        return $role;
    }
}
