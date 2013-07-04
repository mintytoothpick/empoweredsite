<?php
require_once 'Brigade/Db/Table/GiftAid.php';
require_once 'Project.php';
require_once 'User.php';
require_once 'Donation.php';

/**
 * Class Model Gift Aid.
 *
 * @author Matias Gonzalez
 */
class GiftAid extends Base {

    public $id;
    public $salutation;
    public $firstName;
    public $lastName;
    public $email;
    public $phone;
    public $address;
    public $familyMember;
    public $donationId;
    public $projectId;
    public $userId;
    public $programId;
    public $groupId;
    public $organizationId;

    // Lazy
    protected $_project      = null;
    protected $_user         = null;
    protected $_donation     = null;
    protected $_group        = null;
    protected $_program      = null;
    protected $_organization = null;

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
        if (property_exists('GiftAid', $attr)) {
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
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $GiftAid = new Brigade_Db_Table_GiftAid();
        $data    = $GiftAid->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Get gift aids data by object organization.
     *
     * @param Organization  $Org    Organization object to filter results
     * @param String $search search text to filter donations list
     *
     * @return List of donations objects.
     */
    static public function getListByOrganization(Organization $Org,
        $search = false, $startDate = false, $endDate = false
    ) {
        if ($startDate) {
            $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        }
        if ($endDate) {
            $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
        }
        $GiftAid = new Brigade_Db_Table_GiftAid();
        $res     = $GiftAid->getListByOrganization(
            $Org->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($res as $gift) {
            // create objects project
            $list[] = self::_populateObject($gift);
        }
        return $list;
    }


    /**
     * Get events by object group.
     *
     * @param Group  $Group  Group object to filter events
     * @param String $search search text to filter donations list
     *
     * @return List of donations objects.
     */
    static public function getListByGroup(Group $Group,
        $search = false, $startDate = false, $endDate = false
    ) {
        if ($startDate) {
            $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        }
        if ($endDate) {
            $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
        }

        $GiftAid = new Brigade_Db_Table_GiftAid();
        $res     = $GiftAid->getListByGroup(
            $Group->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($res as $gift) {
            // create objects project
            $list[] = self::_populateObject($gift);
        }
        return $list;
    }

    /**
     * Return list of donations by Project
     *
     * @param Project $Project to get all donations.
     *
     * @return Array List of donations
     */
    static public function getListByProject(Project $Project,
        $search = false, $startDate = false, $endDate = false
    ) {
        if ($startDate) {
            $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        }
        if ($endDate) {
            $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
        }

        $GiftAid = new Brigade_Db_Table_GiftAid();
        $res     = $GiftAid->getListByProject(
            $Project->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($res as $gift) {
            // create objects project
            $list[] = self::_populateObject($gift);
        }
        return $list;
    }

    /**
     * Return list of donations by Program
     *
     * @param Program $Program to get all donations.
     *
     * @return Array List of donations
     */
    static public function getListByProgram(Program $Program,
        $search = false, $startDate = false, $endDate = false
    ) {
        if ($startDate) {
            $startDate = date('Y-m-d 00:00:00', strtotime($startDate));
        }
        if ($endDate) {
            $endDate = date('Y-m-d 23:59:59', strtotime($endDate));
        }

        $GiftAid = new Brigade_Db_Table_GiftAid();
        $res     = $GiftAid->getListByProgram(
            $Program->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($res as $gift) {
            // create objects project
            $list[] = self::_populateObject($gift);
        }
        return $list;
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object GiftAid.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['id'];

            $obj->salutation     = $data['salutation'];
            $obj->firstName      = $data['first_name'];
            $obj->lastName       = $data['last_name'];
            $obj->email          = $data['email'];
            $obj->phone          = $data['phone'];
            $obj->address        = $data['address'];
            $obj->familyMember   = $data['family_member'];
            $obj->donationId     = $data['project_donation_id'];
            $obj->createdOn      = $data['CreatedOn'];
            $obj->projectId      = $data['ProjectId'];
            $obj->organizationId = $data['NetworkId'];
            $obj->programId      = $data['ProgramId'];
            $obj->groupId        = $data['GroupId'];
        }
        return $obj;
    }

    /**
     * Create/Update data
     */
    public function save() {
        $data = array(
            'salutation'          => $this->salutation,
            'first_name'          => $this->firstName,
            'last_name'           => $this->lastName,
            'email'               => $this->email,
            'phone'               => $this->phone,
            'address'             => $this->address,
            'family_member'       => $this->familyMember,
            'project_donation_id' => $this->donationId,
            'CreatedOn'           => $this->createdOn,
            'NetworkId'           => $this->organizationId,
            'ProgramId'           => $this->programId,
            'GroupId'             => $this->groupId,
            'UserId'              => $this->userId,
            'ProjectId'           => $this->projectId,
        );
        $giftaid = new Brigade_Db_Table_GiftAid();
        if (!empty($this->id)) {
            $giftaid->edit($this->id, $data);
        } else {
            $this->id = $giftaid->addDeclaration($data);
        }
    }

    /**
     * Gets the group of the donation.
     *
     * @return void
     */
    protected function _getGroup() {
        $this->_group = Group::get($this->groupId);
    }

    /**
     * Gets the program of the donation.
     *
     * @return void
     */
    protected function _getProgram() {
        $this->_program = Program::get($this->programId);
    }

    /**
     * Gets the organization of the donation.
     *
     * @return void
     */
    protected function _getOrganization() {
        $this->_organization = Organization::get($this->organizationId);
    }

    /**
     * Gets the project/initiative of the donation.
     *
     * @return void
     */
    protected function _getProject() {
        $this->_project = Project::get($this->projectId);
    }

    /**
     * Gets user volunteer. Donation behalf
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

    /**
     * Gets the donation of giftaid
     *
     * @return void
     */
    protected function _getDonation() {
        $this->_donation = Donation::get($this->donationId);
    }
}
