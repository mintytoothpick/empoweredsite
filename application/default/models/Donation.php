<?php
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Group.php';
require_once 'Program.php';
require_once 'Organization.php';
require_once 'Project.php';
require_once 'User.php';
require_once 'DonationNote.php';

/**
 * Class Model Donation (project_donation).
 *
 * @author Matias Gonzalez
 */
class Donation extends Base {

    public $id;
    public $transactionId;
    public $projectId;
    public $userId;
    public $amount;
    public $createdById;
    public $createdOn;
    public $modifiedBy;
    public $modifiedOn;
    public $comments;
    public $donorUserId;
    public $status;
    public $supporterEmail;
    public $supporterName;
    public $xmlResponse;
    public $orderStatusId;
    public $transactionSource;
    public $isReceiptSent;
    public $isAnonymous;
    public $organizationId;
    public $programId;
    public $groupId;
    public $paidFees;

    // Lazy
    protected $_group        = null;
    protected $_project      = null;
    protected $_program      = null;
    protected $_organization = null;
    protected $_donor        = null; //the user who made the donation
    protected $_user         = null; //the user where the donation was destinated
    protected $_notes        = null;
    protected $_orderStatus  = null;
    protected $_destination  = null; //destination name

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
        if (property_exists('Donation', $attr)) {
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
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function getByTransactionId($transId) {
        $obj = new self;
        return $obj->loadByTransactionId($transId);
    }

    /**
     * Load information of the selected event.
     *
     * @param String $id Event Id.
     */
    public function load($id) {
        $PDonation = new Brigade_Db_Table_ProjectDonations();
        $data      = $PDonation->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Load information of the selected donation by trans id.
     *
     * @param String $id Transaction Id.
     */
    public function loadByTransactionId($id) {
        $PDonation = new Brigade_Db_Table_ProjectDonations();
        $data      = $PDonation->getDonationByTransaction($id);

        return self::_populateObject($data);
    }

    /**
     * Get events by object group.
     *
     * @param Group  $Group  Group object to filter events
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
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Donations        = $ProjectDonations->getListDonationsByOrganization(
            $Org->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($Donations as $donation) {
            // create objects project
            $list[] = self::_populateObject($donation);
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

        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Donations        = $ProjectDonations->getListDonationsByGroup(
            $Group->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($Donations as $donation) {
            // create objects project
            $list[] = self::_populateObject($donation);
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

        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Donations        = $ProjectDonations->getListDonationsByProject(
            $Project->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($Donations as $donation) {
            // create objects project
            $list[] = self::_populateObject($donation);
        }
        return $list;
    }

    /**
     * Return list of donations by Project and User (donations to volunteer)
     *
     * @param User    $User to get all donations.
     * @param Project $Project to get all donations.
     *
     * @return Array List of donations
     */
    static public function getListByUserAndProject(User $User, Project $Project) {
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Donations        = $ProjectDonations->getVolunteerProjectDonations(
            $User->id,
            $Project->id
        );

        $list = array();
        foreach($Donations as $donation) {
            // create objects project
            $list[] = self::_populateObject($donation);
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

        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Donations        = $ProjectDonations->getListDonationsByProgram(
            $Program->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($Donations as $donation) {
            // create objects project
            $list[] = self::_populateObject($donation);
        }
        return $list;
    }

    /**
     * Return list of donations by date.
     *
     * @todo: remove
     */
    static public function getListByDate($startDate = false, $endDate = false) {
        $ProjectDonations = new Brigade_Db_Table_ProjectDonations();
        $Donations        = $ProjectDonations->getDonationsByDate(
            $startDate,
            $endDate
        );

        $list = array();
        foreach($Donations as $donation) {
            // create objects project
            $list[] = self::_populateObject($donation);
        }
        return $list;
    }

    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object Donation.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['ProjectDonationId'];

            $obj->transactionId     = $data['TransactionId'];
            $obj->projectId         = $data['ProjectId'];
            $obj->userId            = $data['VolunteerId']; //is bad in the db
            $obj->amount            = $data['DonationAmount'];
            $obj->createdById       = $data['CreatedBy'];
            $obj->createdOn         = $data['CreatedOn'];
            $obj->modifiedBy        = $data['ModifiedBy'];
            $obj->modifiedOn        = $data['ModifiedOn'];
            $obj->comments          = $data['DonationComments'];
            $obj->donorUserId       = $data['DonorUserId'];
            $obj->status            = $data['Status'];
            $obj->supporterEmail    = $data['SupporterEmail'];
            $obj->supporterName     = $data['SupporterName'];
            $obj->xmlResponse       = $data['XmlResponse'];
            $obj->orderStatusId     = $data['OrderStatusId'];
            $obj->transactionSource = $data['TransactionSource'];
            $obj->isReceiptSent     = $data['isReceiptSent'];
            $obj->isAnonymous       = (bool)$data['isAnonymous'];
            $obj->organizationId    = $data['NetworkId'];
            $obj->programId         = $data['ProgramId'];
            $obj->groupId           = $data['GroupId'];
            $obj->paidFees          = $data['PaidFees'];

        }
        return $obj;
    }

    /**
     * Create/Update data
     */
    public function save() {
        if (!empty($this->project)) {
            if (empty($this->projectId)) {
                $this->projectId = $this->project->id;
            }
            if (!empty($this->project->groupId) && empty($this->groupId)) {
                $this->groupId = $this->project->groupId;
            }
            if (!empty($this->project->programId) && empty($this->programId)) {
                $this->programId = $this->project->programId;
            }
            if (!empty($this->project->organizationId) && empty($this->organizationId)) {
                $this->organizationId = $this->project->organizationId;
            }
        }

        $data = array(
            'TransactionId'     => $this->transactionId,
            'ProjectId'         => $this->projectId,
            'VolunteerId'       => $this->userId,
            'DonationAmount'    => $this->amount,
            'CreatedBy'         => $this->createdById,
            'CreatedOn'         => $this->createdOn,
            'ModifiedBy'        => $this->modifiedBy,
            'ModifiedOn'        => $this->modifiedOn,
            'DonationComments'  => $this->comments,
            'DonorUserId'       => $this->donorUserId,
            'Status'            => $this->status,
            'SupporterEmail'    => $this->supporterEmail,
            'SupporterName'     => $this->supporterName,
            'XmlResponse'       => $this->xmlResponse,
            'OrderStatusId'     => $this->orderStatusId,
            'TransactionSource' => $this->transactionSource,
            'isReceiptSent'     => $this->isReceiptSent,
            'isAnonymous'       => $this->isAnonymous,
            'NetworkId'         => $this->organizationId,
            'ProgramId'         => $this->programId,
            'GroupId'           => $this->groupId,
            'PaidFees'          => $this->paidFees
        );
        $pdonations = new Brigade_Db_Table_ProjectDonations();
        if (!empty($this->id)) {
            $pdonations->edit($this->id, $data);
        } else {
            $this->id = $pdonations->addProjectDonation($data);
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
     * Gets user donor.
     *
     * @return void
     */
    protected function _getDonor() {
        if (!empty($this->donorUserId)) {
            $this->_donor = User::get($this->donorUserId);
        } else {
            $this->_donor = false;
        }
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
     * Gets user volunteer. Donation behalf
     *
     * @return void
     */
    protected function _getNotes() {
        $this->_notes = DonationNote::getListByDonation($this);
    }

    /**
     * Get order status name
     *
     * @return void
     */
    protected function _getOrderStatus() {
        $orderStatus = '';
        if ($this->orderStatusId == 1) {
            $orderStatus = 'Pending';
        } elseif ($this->orderStatusId == 2) {
            $orderStatus = 'Processed';
        } elseif ($this->orderStatusId == 3) {
            $orderStatus = 'Cancelled';
        } elseif ($this->orderStatusId == 3) {
            $orderStatus = 'Payment Declined';
        }
        $this->_orderStatus = $orderStatus;
    }

    /**
     * Get destination name
     *
     * @return void
     */
    protected function _getDestination() {
        $Destination = '';
        if(!empty($this->userId) && $this->user) {
            $Destination = stripslashes($this->user->fullName);
        } elseif (!empty($this->projectId) && $this->project) {
            $Destination = stripslashes($this->project->name);
        } elseif (!empty($this->groupId) && $this->group) {
            $Destination = stripslashes($this->group->name);
        } elseif (!empty($this->programId) && $this->program) {
            $Destination = stripslashes($this->program->name);
        } else {
            $Destination = 'General Fund';
        }
        $this->_destination = $Destination;
    }
}
