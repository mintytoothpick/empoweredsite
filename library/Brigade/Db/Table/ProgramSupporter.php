<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_ProgramSupporter extends Zend_Db_Table_Abstract {

    protected $_name = 'program_supporters';
    protected $_primary = 'id';

    public function loadInfo($id) {
        $row = $this->fetchRow($this->select()->where("id = ?", $id));

        if ($row) {
            return $row->toArray();
        } else {
            return null;
        }
    }

    public function addSupporter($data) {
        return $this->insert($data);
    }

    public function editSupporter($SupporterId, $data) {
        $where = $this->getAdapter()->quoteInto("id = ?", $SupporterId);
        $this->update($data, $where);
    }

    /**
     * Get user members
     */
    public function loadInfoByUserProgramId($ProgramId, $UserId) {
        $select = $this->select()
            ->where('UserId = ?', $UserId)
            ->where('ProgramId = ?', $ProgramId);

        $all = $this->fetchRow($select);
        if ($all)
            return $all->toArray();
        else
            return false;
    }


    /**
     * List of payments filtered by group
     */
    public function getListByOrganization($OrgId, $search = false, $from = false,
        $to = false
    ) {
        $select = $this->select()
                       ->from(array('ps' => 'program_supporters'), array('ps.*'))
                       ->where('ps.OrganizationId = ?', $OrgId);
        if ($search) {
            $db = $this->getAdapter();
            $select
                ->joinLeft(array('g' => 'groups'), 'g.GroupId = ps.GroupId', array())
                ->joinLeft(array('u' => 'users'), 'u.UserId = ps.UserId', array())
                ->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('ps.TransactionId') . " LIKE ?",
                        "%$search%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('ps.Amount') . " LIKE ?",
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
            $select = $select->where("ps.JoinedOn >= '$from'");
        }
        if ($to) {
            $select = $select->where("ps.JoinedOn <= '$to'");
        }
        $rows = $this->fetchAll($select);
        if (count($rows)){
            return $rows->toArray();
        }else {
            return array();
        }
    }
}
