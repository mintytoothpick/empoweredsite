<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_Payments extends Zend_Db_Table_Abstract {

    protected $_name = 'payments';
    protected $_primary = 'PaymentId';

    public function loadInfo($PaymentId) {
        return $this->fetchRow(
            $this->select()->where('PaymentId = ?', $PaymentId)
        )->toArray();
    }

    public function getInfoByTransactionId($TransactionId) {
        $row = $this->fetchRow(
            $this->select()->where('TransactionId = ?', $TransactionId)
        );
        if ($row) {
            return $row->toArray();
        } else {
            return false;
        }
    }

    public function getInfoByRebillingId($RebillingId) {
        $row = $this->fetchRow(
            $this->select()->where('RebillingId = ?', $RebillingId)
        );
        if ($row) {
            return $row->toArray();
        } else {
            return false;
        }
    }


    public function loadLastPaymentByGroupAndUser($groupId, $userId) {
        $select = $this->select()
                       ->where('GroupId = ?',$groupId)
                       ->where('UserId = ?',$userId)
                       ->order('PaymentId DESC')
                       ->limit(1);
        $row    = $this->fetchRow($select);
        if ($row){
            return $row->toArray();
        }else {
            return array();
        }
    }

    public function loadLastPaymentByProgramAndUser($programId, $userId) {
        $select = $this->select()
                       ->where('ProgramId = ?',$programId)
                       ->where('UserId = ?',$userId)
                       ->order('PaymentId DESC')
                       ->limit(1);
        $row    = $this->fetchRow($select);
        if ($row){
            return $row->toArray();
        }else {
            return array();
        }
    }

    public function loadPaymentByGroupAndUser($groupId, $userId) {
        $select = $this->select()
                       ->where('GroupId = ?',$groupId)
                       ->where('UserId = ?',$userId)
                       ->order('PaymentId DESC');
        $row    = $this->fetchAll($select);
        if ($row){
            return $row->toArray();
        }else {
            return array();
        }
    }

    public function loadLastRebIdByGroupAndUser($groupId, $userId) {
        $select = $this->select()
                       ->where('GroupId = ?',$groupId)
                       ->where('UserId = ?',$userId)
                       ->where('RebillingId IS NOT NULL')
                       ->where('RebillingId != ""')
                       ->order('PaymentId DESC')
                       ->limit(1);
        $row    = $this->fetchRow($select);
        if ($row){
            return $row->toArray();
        }else {
            return array();
        }
    }

    /**
     * List of payments filtered by group
     */
    public function getListByGroup($GroupId, $search = false, $from = false,
        $to = false
    ) {
        $select = $this->select()
                       ->from(array('p' => 'payments'), array('p.*'))
                       ->where('p.GroupId = ?',$GroupId);
        if ($search) {
            $db = $this->getAdapter();
            $select
                ->joinLeft(array('g' => 'groups'), 'g.GroupId = p.GroupId', array())
                ->joinLeft(array('u' => 'users'), 'u.UserId = p.UserId', array())
                ->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('p.TransactionId') . " LIKE ?",
                        "%$search%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('p.Amount') . " LIKE ?",
                        "%$search%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('g.GroupName') . " LIKE ?",
                        "%$search%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('u.FullName') . " LIKE ?",
                        "%$search%"
                    )
                );
        }
        if ($from) {
            $select = $select->where("p.CreatedOn >= '$from'");
        }
        if ($to) {
            $select = $select->where("p.CreatedOn <= '$to'");
        }
        $rows = $this->fetchAll($select);
        if (count($rows)){
            return $rows->toArray();
        }else {
            return array();
        }
    }

    /**
     * List of payments filtered by group
     */
    public function getListByOrganization($OrgId, $search = false, $from = false,
        $to = false, $type = "Membership"
    ) {
        $select = $this->select()
                       ->from(array('p' => 'payments'), array('p.*'))
                       ->where('p.OrganizationId = ?', $OrgId)
                       ->where('p.Type = ?', $type);
        if ($search) {
            $db = $this->getAdapter();
            $select
                ->joinLeft(array('g' => 'groups'), 'g.GroupId = p.GroupId', array())
                ->joinLeft(array('u' => 'users'), 'u.UserId = p.UserId', array())
                ->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('p.TransactionId') . " LIKE ?",
                        "%$search%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('p.Amount') . " LIKE ?",
                        "%$search%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('g.GroupName') . " LIKE ?",
                        "%$search%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('u.FullName') . " LIKE ?",
                        "%$search%"
                    )
                );
        }
        if ($from) {
            $select = $select->where("p.CreatedOn >= '$from'");
        }
        if ($to) {
            $select = $select->where("p.CreatedOn <= '$to'");
        }
        $rows = $this->fetchAll($select);
        if (count($rows)){
            return $rows->toArray();
        }else {
            return array();
        }
    }

    /**
     * Get the total amount raised by group
     */
    public function getRaisedByGroup($GroupId, $type) {
        return $this->fetchRow(
            $this->select()
            ->from(array('p' => 'payments'),
                   array('SUM(p.Amount) as Amount'))
            ->where("p.GroupId = ?", $GroupId)
            ->where("p.OrderStatusId = 2")
            ->where("p.Type = ?", $type)
            ->group('p.GroupId'));
    }

    /**
     * Get the total amount raised by group
     */
    public function getRaisedByOrganization($OrganizationId, $type) {
        return $this->fetchRow(
            $this->select()
            ->from(array('p' => 'payments'),
                   array('SUM(p.Amount) as Amount'))
            ->where("p.OrganizationId = ?", $OrganizationId)
            ->where("p.OrderStatusId = 2")
            ->where("p.Type = ?", $type)
            ->group('p.OrganizationId'));
    }

    public function addPayment($values) {
        return $this->insert($values);
    }

    /**
     * Edit payment data
     *
     * @param String $PaymentId PaymentId Id
     * @param Array  $data      Data to update into project
     *
     * @return void.
     */
    public function edit($PaymentId, $data) {
        $where = $this->getAdapter()->quoteInto('PaymentId = ?', $PaymentId);
        $this->update($data, $where);
    }

}
