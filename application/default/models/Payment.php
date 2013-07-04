<?php
require_once 'Brigade/Db/Table/Payments.php';
require_once 'Group.php';
require_once 'Organization.php';
require_once 'User.php';

/**
 * Class Payment for history of misc payments
 * Now: History of membership payments.
 *
 * @author Matias Gonzalez
 */
class Payment extends Base {

    public $id;
    public $transactionId;
    public $rebillingId;
    public $amount;
    public $createdById;
    public $comments;
    public $transactionSource;
    public $userId;
    public $orderStatusId  = 0;
    public $organizationId = null;
    public $groupId        = null;
    public $programId      = null;
    public $projectId      = null;
    public $type           = 'Membership';
    public $createdOn;
    public $modifiedOn;
    public $paidUntil;

    // Lazy
    protected $_group        = null;
    protected $_program      = null;
    protected $_organization = null;
    protected $_user         = null;

    // validate types of payment
    private $_types = array('Membership', 'Supporter');

    // payment gateways
    const BLUEPAY   = 'Blue Pay';
    const GCHECKOUT = 'Google Checkout';

    // Payments frequencies Ids
    const ONETIME    = 1;
    const TWICE_YEAR = 2;
    const ANNUAL     = 3;
    const MONTHLY    = 4;
    const ONEDAY     = 5; //for testing

    //status of transaction
    const PENDING   = 1;
    const PROCESSED = 2;
    const DECLINED  = 3;

    private $ordBPStatus = array(
        'Declined' => 4,
    );

    static public function getAllIds() {
        return array(
            array(
                'id'   => self::ONEDAY,
                'name' => 'One Day',
            ),
            array(
                'id'   => self::ONETIME,
                'name' => 'One Time',
            ),
            array(
                'id'   => self::TWICE_YEAR,
                'name' => 'Twice a Year',
            ),
            array(
                'id'   => self::ANNUAL,
                'name' => 'Annual',
            ),
            array(
                'id'   => self::MONTHLY,
                'name' => 'Monthly',
            )
        );
    }

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
        if (property_exists('Payment', $attr)) {
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
     * TODO: Implement cache layer.
     * @return Class Object
     */
    static public function getByRebillingId($rebId) {
        $Payment = new Brigade_Db_Table_Payments();
        $data    = $Payment->getInfoByRebillingId($rebId);

        return self::_populateObject($data);
    }


    /**
     * Return last payment for a specific member
     *
     * @param User  $user
     * @param Group $group
     *
     * @return Volunteer Instance
     */
    public static function getLastByUserAndGroup(User $user, Group $group) {
        $Payment = new Brigade_Db_Table_Payments();
        $data    = $Payment->loadLastPaymentByGroupAndUser(
                        $group->id,
                        $user->id
        );

        return self::_populateObject($data);
    }

    /**
     * Return last payment for a specific member
     *
     * @param User  $user
     * @param Group $group
     *
     * @return Volunteer Instance
     */
    public static function getLastByUserAndProgram(User $user, Program $program) {
        $Payment = new Brigade_Db_Table_Payments();
        $data    = $Payment->loadLastPaymentByProgramAndUser(
                        $program->id,
                        $user->id
        );

        return self::_populateObject($data);
    }

    /**
     * Return list of payments for a specific member
     *
     * @param User  $user
     * @param Group $group
     *
     * @return Volunteer Instance
     */
    public static function getByUserAndGroup(User $user, Group $group) {
        $Payment = new Brigade_Db_Table_Payments();
        $data    = $Payment->loadPaymentByGroupAndUser(
                        $group->id,
                        $user->id
        );
        $list    = array();
        foreach($data as $payment) {
            // create objects project
            $list[] = self::_populateObject($payment);
        }
        return $list;
    }

    /**
     * Return last rebilling id payment for a specific member
     *
     * @param User  $user
     * @param Group $group
     *
     * @return Volunteer Instance
     */
    public static function getLastRebIdByUserAndGroup(User $user, Group $group) {
        $Payment = new Brigade_Db_Table_Payments();
        $data    = $Payment->loadLastRebIdByGroupAndUser(
                        $group->id,
                        $user->id
        );

        return self::_populateObject($data);
    }

    /**
     * Return list of payments by group
     *
     * @param Group  $group  Group
     * @param String $search String text to filter
     * @param String $from   Date From to filter
     * @param String $to     Date To to filter
     *
     * @return Array Payments
     */
    static public function getListByGroup($group, $search = false, $from = false,
        $to = false
    ) {
        $Payments = new Brigade_Db_Table_Payments();
        $payList  = $Payments->getListByGroup($group->id, $search, $from, $to);
        $list       = array();
        foreach($payList as $payment) {
            // create objects project
            $list[] = self::_populateObject($payment);
        }
        return $list;
    }

    /**
     * Return list of payments by organization
     *
     * @param Organization $organization
     * @param String       $search       String text to filter
     * @param String       $from         Date From to filter
     * @param String       $to           Date To to filter
     *
     * @return Array Payments
     */
    static public function getListByOrganization($organization, $search = false,
        $from = false, $to = false, $type = 'Membership'
    ) {
        $Payments = new Brigade_Db_Table_Payments();
        $payList  = $Payments->getListByOrganization($organization->id, $search,
            $from, $to, $type
        );
        $list     = array();
        foreach($payList as $payment) {
            // create objects project
            $list[] = self::_populateObject($payment);
        }
        return $list;
    }

    /**
     * Get the total amount raised by group
     *
     * @param Group $group
     */
    static public function getRaisedByGroup($group, $type = "Membership") {
        $Payments = new Brigade_Db_Table_Payments();
        $raised   = $Payments->getRaisedByGroup($group->id, $type);
        if ($raised)
            return $raised['Amount'];
        else
            return 0;
    }

    /**
     * Get the total amount raised by organization
     *
     * @param Organization $organization
     */
    static public function getRaisedByOrganization($organization, $type = "Membership") {
        $Payments = new Brigade_Db_Table_Payments();
        $raised   = $Payments->getRaisedByOrganization($organization->id, $type);
        if ($raised)
            return $raised['Amount'];
        else
            return 0;
    }

    /**
     * Load information of the selected payment.
     *
     * @param String $id Payment Id.
     */
    public function load($id) {
        $PDonation = new Brigade_Db_Table_Payments();
        $data      = $PDonation->loadInfo($id);

        return self::_populateObject($data);
    }

    /**
     * Load information of the selected payment by trans id.
     *
     * @param String $id Transaction Id.
     */
    public function loadByTransactionId($id) {
        $Payment = new Brigade_Db_Table_Payments();
        $data    = $Payment->getInfoByTransactionId($id);

        return self::_populateObject($data);
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
            $obj->id = $data['PaymentId'];

            $obj->transactionId     = $data['TransactionId'];
            $obj->rebillingId       = $data['RebillingId'];
            $obj->userId            = $data['UserId'];
            $obj->amount            = $data['Amount'];
            $obj->createdById       = $data['CreatedBy'];
            $obj->createdOn         = $data['CreatedOn'];
            $obj->modifiedBy        = $data['ModifiedBy'];
            $obj->modifiedOn        = $data['ModifiedOn'];
            $obj->comments          = $data['Comments'];
            $obj->orderStatusId     = $data['OrderStatusId'];
            $obj->transactionSource = $data['TransactionSource'];
            $obj->organizationId    = $data['OrganizationId'];
            $obj->programId         = $data['ProgramId'];
            $obj->groupId           = $data['GroupId'];
            $obj->projectId         = $data['ProjectId'];
            $obj->paidUntil         = $data['PaidUntil'];
            $obj->type              = $data['Type'];
        }
        return $obj;
    }

    /**
     * Create/Update data
     */
    public function save() {
        if (!in_array($this->type, $this->_types))
            return false;

        $data = array(
            'TransactionId'     => $this->transactionId,
            'RebillingId'       => $this->rebillingId,
            'UserId'            => $this->userId,
            'Amount'            => $this->amount,
            'CreatedBy'         => $this->createdById,
            'CreatedOn'         => $this->createdOn,
            'ModifiedBy'        => $this->modifiedBy,
            'ModifiedOn'        => $this->modifiedOn,
            'Comments'          => $this->comments,
            'OrderStatusId'     => $this->orderStatusId,
            'TransactionSource' => $this->transactionSource,
            'OrganizationId'    => $this->organizationId,
            'ProgramId'         => $this->programId,
            'GroupId'           => $this->groupId,
            'ProjectId'         => $this->projectId,
            'PaidUntil'         => $this->paidUntil,
            'Type'              => $this->type
        );

        $payments = new Brigade_Db_Table_Payments();
        if (!empty($this->id)) {
            $payments->edit($this->id, $data);
        } else {
            $this->id = $payments->addPayment($data);
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
     * Get order status name
     *
     * @return void
     */
    protected function _getOrderStatus() {
        $orderStatus = '';
        if ($this->orderStatusId == '1') {
            $orderStatus = 'Pending';
        } elseif ($this->orderStatusId == '2') {
            $orderStatus = 'Processed';
        } elseif ($this->orderStatusId == '3') {
            $orderStatus = 'Cancelled';
        } elseif ($this->orderStatusId == '4') {
            $orderStatus = 'Payment Declined';
        }
        $this->_orderStatus = $orderStatus;
    }

    /**
     * Get frequency string constant.
     *
     * @param Payment constant id
     *
     * @return String Frequency name
     */
    static public function getFrequency($paymentId) {
        $freq = self::getAllIds();
        foreach ($freq as $val) {
            if ($val['id'] == $paymentId) {
                return $val['name'];
            }
        }
        return '';
    }

    /**
     * Get paid until date.
     *
     * @param FrequencyId constant id
     *
     * @return String Date for next bill
     */
    static public function getPaidUntil($freqId) {
        switch ($freqId) {
            case self::ONEDAY:
                $newDate = mktime(0,0,0,date("m"),date("d")+1,date("Y"));
                $val     = date("Y-m-d", $newDate);
                break;
            case self::ONETIME:
                $val = '0000-00-00';
                break;
            case self::TWICE_YEAR:
                $newDate = mktime(0,0,0,date("m")+6,date("d"),date("Y"));
                $val     = date("Y-m-d", $newDate);
                break;
            case self::ANNUAL:
                $newDate = mktime(0,0,0,date("m"),date("d"),date("Y")+1);
                $val     = date("Y-m-d", $newDate);
                break;
            case self::MONTHLY:
                $newDate = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
                $val     = date("Y-m-d", $newDate);
                break;
        }
        return $val;
    }
}
