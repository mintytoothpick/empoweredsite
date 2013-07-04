<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_MembershipStatChapters extends Zend_Db_Table_Abstract {

    protected $_name = 'membership_stats_chapters';

    /**
     * Get complete list of membership stats
     */
    public function getGroups($statId) {
        $select = $this->select()
                ->from(array('mc' => 'membership_stats_chapters'), array('mc.*', 'g.*'))
                ->joinInner(array('g' => 'groups'), 'g.GroupId = mc.idGroup')
                ->where("mc.idMembershipStat = '$statId'")
                ->setIntegrityCheck(false);
        $all = $this->fetchAll($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }

    /**
     * Get notes for a chapter in a membership stat
     */
    public function getNotes($statId, $groupId) {
        $select = $this->select()
                ->from(array('mc' => 'membership_stats_chapters'), array('mc.Notes'))
                ->where('mc.idGroup = ?', $groupId)
                ->where('mc.idMembershipStat = ?', $statId);
        $all = $this->fetchRow($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }

    /**
     * Save notes for membership chapter stat
     */
    public function saveNote($groupId, $statId, $notes) {
        $where = $this->getAdapter()->quoteInto("idGroup = '$groupId' AND idMembershipStat = ?", $statId);
        $this->update(array('Notes' => $notes), $where);
    }
}
