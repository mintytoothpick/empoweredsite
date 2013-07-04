<?php
require_once 'Brigade/Db/Table/MembershipFund.php';
require_once 'Group.php';
require_once 'Organization.php';
require_once 'User.php';
require_once 'MembershipTransfer.php';

/**
 * Class MembershipFund to manage funds from chapters memberships
 *
 * @author Matias Gonzalez
 */
class MembershipFund extends Base {

    public $id;
    public $amount;
    public $organizationId = null;
    public $groupId        = null;
    public $projectId      = null;

    // Lazy
    protected $_group        = null;
    protected $_organization = null;
    protected $_project      = null;
    protected $_transfers    = null;

    const transferLimit = 100; // percentage to transfer

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
        if (property_exists('MembershipFund', $attr)) {
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
     * Get the total amount raised by group
     *
     * @param Group $group
     */
    static public function getRaisedByGroup($group) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $raised         = $MembershipFund->getRaisedByGroup($group->id);

        if ($raised)
            return $raised['Amount'];
        else {
            $raised = Payment::getRaisedByGroup($group);

            $membershipFund                 = new self;
            $membershipFund->groupId        = $group->id;
            $membershipFund->organizationId = $group->organizationId;
            $membershipFund->amount         = $raised;
            $membershipFund->save();

            return $raised;
        }
    }

    /**
     * Return list of funds by group
     *
     * @param Group  $group  Group
     * @param String $search String text to filter
     *
     * @return Array MembershipFunds
     */
    static public function getListByGroup($group, $search = false) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $fundsList      = $MembershipFund->getListByGroup($group->id);
        $list           = array();
        foreach($fundsList as $fund) {
            // create objects project
            $list[] = self::_populateObject($fund);
        }
        return $list;
    }

    /**
     * Return list of funds by org
     *
     * @param Organization $organization Organization
     * @param String       $search       String text to filter
     *
     * @return Array MembershipFunds
     */
    static public function getListByOrg($organization, $search = false) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $fundsList      = $MembershipFund->getListByOrganization($organization->id);
        $list           = array();
        foreach($fundsList as $fund) {
            // create objects project
            $list[] = self::_populateObject($fund);
        }
        return $list;
    }

    /**
     * Return the total transfered from the group raised to other initiatives
     *
     * @param Group  $group  Group
     *
     * @return Double Transfered total amount.
     */
    static public function getTotalTransferedByGroup($group) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $raised         = $MembershipFund->getTotalTransferedByGroup($group->id);

        if ($raised) {
            return $raised['Amount'];
        } else {
            return 0;
        }
    }

    /**
     * Get transfered funds from chapter membership total raised.
     *
     * @param Project $project
     *
     * @return Double funds transfered from chapter membership
     */
    static public function getProjectFunds($project) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $raised         = $MembershipFund->getProjectFunds($project->id);

        if ($raised) {
            return $raised['Amount'];
        } else {
            return 0;
        }
    }

    /**
     * Load information of the selected funds.
     *
     * @param Project $project Project instance
     *
     * @return self instance
     */
    public function getByProject($project) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $data           = $MembershipFund->loadByProject($project->id);

        return self::_populateObject($data);
    }

    /**
     * Load information of the selected funds of the group.
     *
     * @param Group $group Group instance
     *
     * @return self instance
     */
    public function getByGroup($group) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $data           = $MembershipFund->loadByGroup($group->id);

        return self::_populateObject($data);
    }

    /**
     * Load information of the selected funds.
     *
     * @param String $id Fund Id.
     */
    public function load($id) {
        $MembershipFund = new Brigade_Db_Table_MembershipFund();
        $data           = $MembershipFund->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object MembershipFund.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['id'];

            $obj->amount         = $data['Amount'];
            $obj->organizationId = $data['OrganizationId'];
            $obj->groupId        = $data['GroupId'];
            $obj->projectId      = $data['ProjectId'];
        }
        return $obj;
    }

    /**
     * Create/Update data
     */
    public function save() {
        $data = array(
            'Amount'         => $this->amount,
            'OrganizationId' => $this->organizationId,
            'GroupId'        => $this->groupId,
            'ProjectId'      => $this->projectId,
        );

        $membFund = new Brigade_Db_Table_MembershipFund();
        if (!empty($this->id)) {
            $membFund->editFund($this->id, $data);
        } else {
            $this->id = $membFund->addFund($data);
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
     * Gets the organization of the donation.
     *
     * @return void
     */
    protected function _getOrganization() {
        $this->_organization = Organization::get($this->organizationId);
    }

    /**
     * Gets the project destination.
     *
     * @return void
     */
    protected function _getProject() {
        $this->_project = Project::get($this->projectId);
    }

    /**
     * Gets transfers list
     *
     * @return void
     */
    protected function _getTransfers() {
        $this->_transfers = MembershipTransfer::getListByMembershipFund($this->id);
    }
}
