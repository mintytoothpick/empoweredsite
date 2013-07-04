<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_MembershipTransfer extends Zend_Db_Table_Abstract {

    protected $_name = 'membership_transfer';
    protected $_primary = 'id';

    /**
     * List of funds filtered by MembershipFund
     */
    public function getListByMembershipFund($MembershipFundId) {
        $select = $this->select()
                       ->from(array('mf' => 'membership_transfer'), array('mf.*'))
                       ->where('mf.MembershipFundId = ?', $MembershipFundId);
        $rows = $this->fetchAll($select);
        if (count($rows)){
            return $rows->toArray();
        }else {
            return array();
        }
    }


    /**
     * Insert new transfer detail
     *
     * @params Array $values
     *
     * @return parent::insert
     */
    public function addTransfer($values) {
        return $this->insert($values);
    }

    /**
     * Edit transfer data
     *
     * @param String $id   Fund id
     * @param Array  $data Data to update
     *
     * @return void.
     */
    public function editTransfer($id, $data) {
        $where = $this->getAdapter()->quoteInto('id = ?', $id);
        $this->update($data, $where);
    }

}
