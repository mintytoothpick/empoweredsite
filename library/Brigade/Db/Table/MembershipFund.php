<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_MembershipFund extends Zend_Db_Table_Abstract {

    protected $_name = 'membership_funds';
    protected $_primary = 'id';

    public function loadByProject($ProjectId) {
        $row = $this->fetchRow(
            $this->select()->where('ProjectId = ?', $ProjectId)
        );
        if ($row) {
            return $row->toArray();
        } else {
            return false;
        }
    }

    /**
     * Get only the group record to manage that funds
     * Used for new donations. Sum amount
     */
    public function loadByGroup($GroupId) {
        $row = $this->fetchRow(
            $this->select()->where('GroupId = ?', $GroupId)
                           ->where('ProjectId IS NULL')
        );
        if ($row) {
            return $row->toArray();
        } else {
            return false;
        }
    }

    /**
     * Get the total amount raised by group
     */
    public function getRaisedByGroup($GroupId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('mf' => 'membership_funds'),
                   array('SUM(mf.Amount) as Amount'))
            ->where("mf.GroupId = ?", $GroupId)
            ->where("mf.ProjectId IS NULL")
            ->group('mf.GroupId'));
    }

    public function getTotalTransferedByGroup($GroupId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('mf' => 'membership_funds'),
                   array('SUM(mf.Amount) as Amount'))
            ->where("mf.GroupId = ?", $GroupId)
            ->where("mf.ProjectId IS NOT NULL")
            ->group('mf.GroupId'));
    }

    /**
     * List of funds filtered by group
     */
    public function getListByGroup($GroupId) {
        $select = $this->select()
                       ->from(array('mf' => 'membership_funds'), array('mf.*'))
                       ->where('mf.GroupId = ?',$GroupId)
                       ->where('mf.ProjectId IS NOT NULL');
        $rows = $this->fetchAll($select);
        if (count($rows)){
            return $rows->toArray();
        }else {
            return array();
        }
    }

    /**
     * List of funds filtered by group
     */
    public function getListByOrganization($OrganizationId) {
        $select = $this->select()
                       ->from(array('mf' => 'membership_funds'), array('mf.*'))
                       ->where('mf.OrganizationId = ?',$OrganizationId)
                       ->where('mf.ProjectId IS NOT NULL');
        $rows = $this->fetchAll($select);
        if (count($rows)){
            return $rows->toArray();
        }else {
            return array();
        }
    }


    /**
     * Get project transfered funds
     */
    public function getProjectFunds($ProjectId) {
        $select = $this->select()
                       ->from(array('mf' => 'membership_funds'), array('mf.Amount'))
                       ->where('mf.ProjectId = ?', $ProjectId);
        $row = $this->fetchRow($select);
        if (count($row)){
            return $row->toArray();
        }else {
            return array();
        }
    }

    /**
     * Insert new funds
     *
     * @params Array $values
     *
     * @return parent::insert
     */
    public function addFund($values) {
        return $this->insert($values);
    }

    /**
     * Edit fund data
     *
     * @param String $id   Fund id
     * @param Array  $data Data to update
     *
     * @return void.
     */
    public function editFund($id, $data) {
        $where = $this->getAdapter()->quoteInto('id = ?', $id);
        $this->update($data, $where);
    }

}
