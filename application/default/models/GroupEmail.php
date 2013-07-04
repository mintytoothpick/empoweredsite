<?php
require_once 'Brigade/Db/Table/GroupEmailAccounts.php';

/**
 * Class Model Group Email Account.
 *
 * @author Matias Gonzalez
 */
class GroupEmail extends Base {

    public $id;
    public $groupId;
    public $email;
    public $verificationCode;
    public $isVerified = 0;

    protected $_group;

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
        if (property_exists('GroupEmail', $attr)) {
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

    /**
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function get($id) {
        $obj = new self;
        return $obj->load($id);
    }

    /**
     * Get group emails accounts by group
     *
     * @param Group $group
     *
     * @return array GroupEmail
     */
    static public function getByGroup($group) {
        $gea   = new Brigade_Db_Table_GroupEmailAccounts();
        $accts = $gea->getGroupEmailAccounts($group->id);
        $list  = array();
        foreach($accts as $account) {
            // create objects project
            $list[] = self::_populateObject($account);
        }
        return $list;
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $BluePay = new Brigade_Db_Table_GroupEmailAccounts();
        $data    = $BluePay->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Verify email account.
     *
     * @param String $groupId
     * @param String $verificationCode
     *
     * @return void
     */
    static public function verify($groupId, $verificationCode) {
        $gea = new Brigade_Db_Table_GroupEmailAccounts();
        $gea->verifyEmail($groupId, $verificationCode);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object BluePay.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['EmailAccountId'];

            $obj->groupId          = $data['GroupId'];
            $obj->email            = $data['Email'];
            $obj->verificationCode = $data['VerificationCode'];
            $obj->isVerified       = $data['isVerified'];
        }
        return $obj;
    }

    /**
     * Create/Update data
     */
    public function save() {
        $data = array(
            'GroupId'    => $this->groupId,
            'Email'      => $this->email,
            'isVerified' => $this->isVerified
        );

        $gea = new Brigade_Db_Table_GroupEmailAccounts();
        if ($this->id != '') {
            //TODO edit
        } else {
            $this->verificationCode = $gea->AddEmailAccount($data);
        }
    }

    /**
     * Gets the group of the project.
     *
     * @return void
     */
    protected function _getGroup() {
        $this->_group = Group::get($this->groupId);
    }
}
