<?php
require_once 'Brigade/Db/Table/FlyForGood.php';

/**
 * Class Fly For Good.
 *
 * @author Matias Gonzalez
 */
class FlyForGood extends Base {

    public $id;
    public $userId;
    public $organizationId;
    public $amount;
    public $fee;
    public $currency;
    public $flyForGoodId;
    public $description = '';
    public $status      = 2;
    public $createdOn;

    protected $_user = null;


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
        if (property_exists('FlyForGood', $attr)) {
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
    static public function getByFlyForGoodId($ffgId) {
        $obj = new self;
        return $obj->loadByFlyForGoodId($ffgId);
    }

    /**
     * Return total user spent in fly for good site.
     *
     * @param User         $user         User instance to get total spent
     * @param Organization $organization Organization to get funds
     *
     * return float Total amount spent.
     */
    static public function getUserSpent($user, $organization) {
        $FFG  = new Brigade_Db_Table_FlyForGood();
        $res = $FFG->getMoneySpent($user->id, $organization->id);
        if (!$res['TotalSpent']) {
            return 0;
        } else {
            return $res['TotalSpent']+$res['TotalFee'];
        }
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
        $FFG   = new Brigade_Db_Table_FlyForGood();
        $trans = $FFG->getListByOrganization(
            $Org->id,
            $search,
            $startDate,
            $endDate
        );

        $list = array();
        foreach($trans as $ffgTrans) {
            // create objects project
            $list[] = self::_populateObject($ffgTrans);
        }
        return $list;
    }

    /**
     * Load information of the selected ffg by ffg internal id.
     *
     * @param String $id Fly For Good Id.
     */
    public function loadByFlyForGoodId($id) {
        $FFG  = new Brigade_Db_Table_FlyForGood();
        $data = $FFG->getByFlyForGoodId($id);

        return self::_populateObject($data);
    }

    /**
     * Add/edit payment into database
     *
     * return void
     */
    public function save() {
        $data                   = array();
        $data['UserId']         = $this->userId;
        $data['OrganizationId'] = $this->organizationId;
        $data['Amount']         = $this->amount;
        $data['Fee']            = $this->fee;
        $data['Currency']       = $this->currency;
        $data['FlyForGoodId']   = $this->flyForGoodId;
        $data['Description']    = $this->description;
        $data['Status']         = $this->status;
        $data['CreatedOn']      = $this->createdOn;

        $ffg = new Brigade_Db_Table_FlyForGood();
        if ($this->id) {
            $ffg->edit($this->id, $data);
        } else {
            $ffg->insert($data);
        }
    }


    /**
     * Create a object with the database array data.
     *
     * @param Array $data Data in array format of the database
     *
     * @return Object FlyForGood.
     */
    static protected function _populateObject($data) {
        $obj = false;
        if ($data) {
            $obj     = new self;
            $obj->id = $data['id'];

            $obj->userId         = $data['UserId'];
            $obj->organizationId = $data['OrganizationId'];
            $obj->amount         = $data['Amount'];
            $obj->fee            = $data['Fee'];
            $obj->currency       = $data['Currency'];
            $obj->flyForGoodId   = $data['FlyForGoodId'];
            $obj->description    = $data['Description'];
            $obj->status         = $data['Status'];
            $obj->createdOn      = $data['CreatedOn'];

        }
        return $obj;
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
