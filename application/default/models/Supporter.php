<?php
require_once 'Brigade/Db/Table/ProgramSupporter.php';
require_once 'Base.php';
require_once 'Payment.php';

/**
 * Class Model Supporter for Programs.
 *
 * @author Matias Gonzalez
 */
class Supporter extends Base {

    public $id;
    public $programId;
    public $organizationId;
    public $groupId;
    public $joinedOn;
    public $userId            = null;
    public $isActive          = false;
    public $frequencyId       = null;
    public $paid              = false;
    public $paidUntil         = '0000-00-00';
    public $lastTransactionId = "";

    //Lazy
    protected $_user         = null;
    protected $_program      = null;
    protected $_organization = null;
    protected $_email        = null;
    protected $_fullName     = null;
    protected $_urlName      = null;
    protected $_payment      = null;
    protected $_rebillId     = null;
    protected $_frequency    = null; //SupporterFrequency

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
        if (property_exists('Supporter', $attr)) {
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
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Member.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj                    = new self;
            $obj->id                = $data['id'];
            $obj->organizationId    = $data['OrganizationId'];
            $obj->programId         = $data['ProgramId'];
            $obj->groupId           = $data['GroupId'];
            $obj->userId            = $data['UserId'];
            $obj->joinedOn          = $data['JoinedOn'];
            $obj->paidUntil         = $data['paidUntil'];
            $obj->paid              = $data['paid'];
            $obj->frequencyId       = $data['frequencyId'];
            $obj->isActive          = $data['isActive'];
            $obj->lastTransactionId = $data['lastTransactionId'];
        }
        return $obj;
    }

    /**
     * Stores data into db. If already exists, updates record.
     *
     * @return void
     */
    public function save() {
        $supporters = new Brigade_Db_Table_ProgramSupporter();
        $data       = array(
            'OrganizationId'    => $this->organizationId,
            'ProgramId'         => $this->programId,
            'GroupId'           => $this->groupId,
            'UserId'            => $this->userId,
            'JoinedOn'          => $this->joinedOn,
            'isActive'          => $this->isActive,
            'paidUntil'         => $this->paidUntil,
            'paid'              => $this->paid,
            'frequencyId'       => $this->frequencyId,
            'lastTransactionId' => $this->lastTransactionId
        );
        if ($this->id != '') {
            $supporters->editSupporter($this->id, $data);
        } else {
            $this->id = $supporters->addSupporter($data);
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
     * TODO: Implement cache layer.
     *
     * @param Group $group
     * @param User  $user
     *
     * @return Class Object
     */
    static public function getByProgramUser($program, $user) {
        $GMem = new Brigade_Db_Table_ProgramSupporter();
        $data = $GMem->loadInfoByUserProgramId($program->id, $user->id);

        return self::_populateObject($data);
    }

    /**
     * Get the list of supporters
     *
     * @return Array Supporter
     */
    static public function getByOrganization($organization) {
        $PS   = new Brigade_Db_Table_ProgramSupporter();
        $data = $PS->getListByOrganization($organization->id);
        $list = array();
        foreach($data as $supporter) {
            // create objects project
            $list[] = self::_populateObject($supporter);
        }
        return $list;
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $GMem = new Brigade_Db_Table_ProgramSupporter();
        $data = $GMem->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Return last payment of supporter
     */
    protected function _getPayment() {
        if (!is_null($this->userId)) {
            $this->_payment = Payment::getLastByUserAndProgram($this->user, $this->program);
        } else {
            $this->_payment = Payment::getByTransactionId($this->lastTransactionId);
        }
    }

    /**
     * Get user data by the email attr contact info.
     */
    protected function _getUser() {
        if (!is_null($this->userId)) {
            $this->_user = User::get($this->userId);
        }
    }

    /**
     * Get organization lazy attr
     */
    protected function _getOrganization() {
       $this->_organization = Organization::get($this->organizationId);
    }

    /**
     * Get group lazy attr
     */
    protected function _getProgram() {
       $this->_program = Program::get($this->programId);
    }

    /**
     * Get user email
     */
    protected function _getEmail() {
        if (!is_null($this->userId)) {
            $this->_email = $this->user->email;
        } else {
            $this->_email = '';
        }
    }

    /**
     * Get user email
     */
    protected function _getFullName() {
        if (!is_null($this->userId)) {
            $this->_fullName = $this->user->fullName;
        } else {
            $this->_fullName = '';
        }
    }

    /**
     * Get user email
     */
    protected function _getUrlName() {
        if (!is_null($this->userId)) {
            $this->_urlName = $this->user->urlName;
        } else {
            $this->_urlName = '';
        }
    }

    /**
     * Get frequency
     */
    protected function _getFrequency() {
        $this->_frequency = SupporterFrequency::getByProgram(
            $this->frequencyId,
            $this->programId
        );
    }
}
