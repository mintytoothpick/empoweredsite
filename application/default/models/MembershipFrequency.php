<?php
require_once 'Brigade/Db/Table/GroupMembershipFrequency.php';
require_once 'Payment.php';

/**
 * Class Model MembershipFrequency.
 * For different ways of memberhisp payment.
 *
 * @author Matias Gonzalez
 */
class MembershipFrequency extends Base {

    public $id;
    public $groupId;
    public $amount;
    public $frequency   = '';
    public $paidUntil   = null;
    public $bluePayFreq = '';

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
            $obj          = new self;
            $obj->id      = $data['FrequencyId'];
            $obj->groupId = $data['GroupId'];
            $obj->amount  = $data['Amount'];

            $obj->bluePayFreq = BluePay::getFrequency($obj->id);
            $obj->frequency   = Payment::getFrequency($obj->id);
            $obj->paidUntil   = Payment::getPaidUntil($obj->id);
        }
        return $obj;
    }

    static public function getList($group) {
        $gmf  = new Brigade_Db_Table_GroupMembershipFrequency();
        $freq = $gmf->loadList($group->id);
        $list = array();
        foreach($freq as $data) {
            // create objects project
            $list[] = self::_populateObject($data);
        }

        return $list;
    }

    /**
     * Create/Update frequency data
     */
    public function save() {
        $GMemF = new Brigade_Db_Table_GroupMembershipFrequency();
        $data  = array(
            'GroupId'       => $this->groupId,
            'FrequencyId'   => $this->id,
            'Amount'        => $this->amount
        );
        $GMemF->addMembershipFrequency($data);
    }

    static public function clean($group) {
        $GMemF = new Brigade_Db_Table_GroupMembershipFrequency();
        $GMemF->cleanMembershipFrequency($group->id);
    }

    /**
     * Get particullar frequency for group.
     */
    static public function get($id, $group) {
        $gmf  = new Brigade_Db_Table_GroupMembershipFrequency();
        $data = $gmf->load($id, $group->id);
        $obj  = self::_populateObject($data);

        if (!$obj && $id != '') {
            $data['FrequencyId'] = $id;
            $data['GroupId']     = $group->id;
            $data['Amount']      = 0;
            $obj  = self::_populateObject($data);
        }

        return $obj;
    }
}
