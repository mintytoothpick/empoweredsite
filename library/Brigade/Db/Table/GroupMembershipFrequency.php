<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';



class Brigade_Db_Table_GroupMembershipFrequency extends Zend_Db_Table_Abstract {

    protected $_name = 'group_membership_frequency';

    public function loadList($GroupId) {
        $select = $this->select()
                       ->where("GroupId = ?", $GroupId);
        $list   = $this->fetchAll($select);
        if (!empty($list)) {
            return $list->toArray();
        } else {
            return null;
        }
    }

    public function load($id, $groupId) {
        $select = $this->select()
                       ->where('FrequencyId = ?', $id)
                       ->where("GroupId = ?", $groupId);
        $list   = $this->fetchRow($select);
        if (!empty($list)) {
            return $list->toArray();
        } else {
            return null;
        }
    }

    public function addMembershipFrequency($data) {
        $this->insert($data);
    }

    public function cleanMembershipFrequency($groupId) {
        $where = $this->getAdapter()->quoteInto("GroupId = ?", $groupId);
        return $this->delete($where);
    }

    //used under misc controller
    public function changeFrequencyId($groupId,$frequencyId,$newFrequencyId) {
        $where = $this->getAdapter()->quoteInto("GroupId = '$groupId' AND FrequencyId = $frequencyId");
        $this->update(array('FrequencyId' => $newFrequencyId), $where);
    }

    //used under misc controller
    public function deleteFrequencyId($groupId,$frequencyId) {
        $where = $this->getAdapter()->quoteInto("GroupId = '$groupId' AND FrequencyId = $frequencyId");
        $this->delete($where);
    }
}
