<?php
require_once 'Brigade/Db/Table/UserRoles.php';

/**
 * Class Model Role
 * Avoid table Role because is a static information. We use Role for
 * table UserRoles.
 *
 * @author Matias Gonzalez
 */
class Role extends Base {

    public $id;
    public $siteId;
    public $userId;
    public $type;
    public $level;
    public $modifiedById;
    public $modifiedOn;
    public $createdById;
    public $createdOn;

    protected $_user = null;

    const GLOBADMIN = 'GLOB-ADMIN';
    const ADMIN     = 'ADMIN';

    /**
     * Magic getter for relationship objects.
     * Lazy load.
     *
     * @param String $name Name attr.
     */
    public function __get($name) {
        $data  = $this->_getLimits($name);
        $attr  = '_'.$data[0];
        $param = $data[1];
        if (property_exists('Role', $attr)) {
            if (is_null($this->$attr)) {
                $method = '_get'.ucfirst($data[0]);
                if ($param != '') {
                    $this->$method($param);
                } else {
                    $this->$method();
                }
            }
            return $this->$attr;
        }
    }

    public function loadByUserAndSite($userId, $siteId) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $data      = $UserRoles->getByUserAndSite($userId, $siteId);

        return self::_populateObject($data);
    }

    /**
     * Get list of role object by site id.
     *
     * @param String $siteId
     * @param String $search
     *
     * @return Array Role
     */
    public function loadBySite($siteId, $search = null) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $roles     = $UserRoles->getBySite($siteId, $search);
        $list      = array();
        foreach($roles as $role) {
            // create objects project
            $list[] = self::_populateObject($role);
        }
        return $list;
    }

    public function delete() {
        self::deleteRolesBySite($this->siteId, $this->userId);
    }


    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Project.
     */
    static protected function _populateObject($data) {
        $obj = null;
        if ($data) {
            $obj         = new self;
            $obj->id     = $data['UserRoleId'];
            $obj->siteId = $data['SiteId'];
            $obj->userId = $data['UserId'];
            $obj->type   = $data['RoleId'];
            $obj->level  = $data['Level'];

            $obj->modifiedOn   = $data['ModifiedOn'];
            $obj->modifiedById = $data['ModifiedBy'];
            $obj->createdById  = $data['CreatedBy'];
            $obj->createdOn    = $data['CreatedOn'];

        }
        return $obj;
    }

    public function save() {
        $data = array(
            'SiteId'     => $this->siteId,
            'UserId'     => $this->userId,
            'RoleId'     => $this->type,
            'Level'      => $this->level,
            'ModifiedOn' => $this->modifiedOn,
            'ModifiedBy' => $this->modifiedById,
            'CreatedBy'  => $this->createdById,
            'CreatedOn'  => $this->createdOn,
        );

        $UserRoles = new Brigade_Db_Table_UserRoles();
        if (empty($this->id)) {
            $UserRoles->addUserRole($data);
        } else {
            $UserRoles->editUserRole($this->id, $data);
        }
    }

    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function getByUserAndSite($userId, $siteId) {
        $obj = new self;
        return $obj->loadByUserAndSite($userId, $siteId);
    }

    /**
     * Return all admins for a site id.
     * TODO: Implement cache layer.
     *
     * @param String $siteId Site Id
     *
     * @return Array of Role
     */
    static public function getBySite($siteId, $search = null) {
        $obj = new self;
        return $obj->loadBySite($siteId, $search);
    }

    /**
     * Validate if the user is admin of a specific siteid
     *
     * @return bool
     */
    static public function isAdmin($userId, $siteId) {
        if (self::isGlobalAdmin($userId)) {
            return true;
        }
        $role = self::getByUserAndSite($userId, $siteId);
        return ($role && ($role->type == self::ADMIN || $role->type == self::GLOBADMIN));
    }

    /**
     * Validate if the user is globaladmin
     *
     * @return bool
     */
    static public function isGlobalAdmin($userId) {
        $UserRoles = new Brigade_Db_Table_UserRoles();
        if ($UserRoles->isGlobalAdmin($userId)) {
            return true;
        }
        return false;
    }

    static public function deleteRolesBySite($siteId, $userId = false){
        $UserRoles = new Brigade_Db_Table_UserRoles();
        if ($userId) {
            $UserRoles->deleteUserRole($userId, $siteId);
        } else {
            $UserRoles->deleteUserRolesBySiteId($siteId);
        }
    }

    /**
     * Gets user
     *
     * @return void
     */
    protected function _getUser() {
        if (!empty($this->userId)) {
            $this->_user = User::get($this->userId);
        } else {
            $this->_user = false;
        }
    }
}
