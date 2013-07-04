<?php
require_once 'Brigade/Db/Table/MembershipTransfer.php';
require_once 'User.php';

/**
 * Class MembershipTransfer to see details of transfer done.
 *
 * @author Matias Gonzalez
 */
class MembershipTransfer extends Base {

    public $id;
    public $membershipFundId;
    public $amount;
    public $createdOn;
    public $createdById;

    // Lazy

    protected $_createdBy = null;

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
        if (property_exists('MembershipTransfer', $attr)) {
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
     * Return list of funds by group
     *
     * @param Group  $group  Group
     * @param String $search String text to filter
     *
     * @return Array MembershipFunds
     */
    static public function getListByMembershipFund($membershipFundId) {
        $MembershipFund = new Brigade_Db_Table_MembershipTransfer();
        $fundsList      = $MembershipFund->getListByMembershipFund($membershipFundId);
        $list           = array();
        foreach($fundsList as $fund) {
            // create objects project
            $list[] = self::_populateObject($fund);
        }
        return $list;
    }

    /**
     * Load information of the selected funds.
     *
     * @param String $id Fund Id.
     */
    public function load($id) {
        $MembershipFund = new Brigade_Db_Table_MembershipTransfer();
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

            $obj->amount           = $data['Amount'];
            $obj->membershipFundId = $data['MembershipFundId'];
            $obj->createdOn        = $data['CreatedOn'];
            $obj->createdById      = $data['CreatedBy'];
        }
        return $obj;
    }

    /**
     * Create/Update data
     */
    public function save() {
        $data = array(
            'Amount'           => $this->amount,
            'MembershipFundId' => $this->membershipFundId,
            'CreatedOn'        => $this->createdOn,
            'CreatedBy'        => $this->createdById,
        );

        $membFund = new Brigade_Db_Table_MembershipTransfer();
        if (!empty($this->id)) {
            $membFund->editTransfer($this->id, $data);
        } else {
            $this->id = $membFund->addTransfer($data);
        }
    }

    /**
     * Set contact lazy attr CreatedBy
     *
     * @return void.
     */
    protected function _getCreatedBy() {
        $this->_createdBy = User::get($this->createdById);
    }
}
