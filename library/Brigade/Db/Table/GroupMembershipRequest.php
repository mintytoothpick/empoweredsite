<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Blogs.php';
require_once 'Brigade/Db/Table/Groups.php';

class Brigade_Db_Table_GroupMembershipRequest extends Zend_Db_Table_Abstract {

    protected $_name = 'group_membership_requests';
    protected $_primary = 'MembershipRequestId';

    public function AddMembershipRequest($data) {
        $this->insert($data);
    }

    public function validateInvite($GroupId, $ActivationCode) {
        $row = $this->fetchRow($this->select()->where("GroupId = ?", $GroupId)->where("ActivationCode = ?", $ActivationCode)->where("RequestAccepted = 0"));
        if ($row) {
            $where = $this->getAdapter()->quoteInto("GroupId = '$GroupId' AND ActivationCode = '$ActivationCode'");
            $this->update(array('RequestAccepted' => 1), $where);
            
            return $row->toArray();
        } else {
            return false;
        }
    }

    public function getMembershipRequests($GroupId, $isDenied = 0, $count = false) {
        $columns = !$count ? array('u.*', 'm.*') : array('COUNT(*) as total_count');
        $select = $this->select()
            ->from(array('m' => 'group_membership_requests'), $columns)
            ->joinInner(array('u' => 'users'), 'u.UserId=m.UserId')
            ->where("m.isDenied = $isDenied")
            ->where("RequestAccepted = 0")
            ->where("m.GroupId = ?", $GroupId)
            ->order("u.FullName");
        if ($count) {
            $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
            return $row['total_count'];
        } else {
            return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        }
    }

    public function loadInfo($MembershipRequestId) {
        return $this->fetchRow($this->select()->where("MembershipRequestId = ?", $MembershipRequestId));
    }

    public function hasMembershipRequest($SiteId, $UserId, $Level = 'group') {
        $where = $Level == 'group' ? "GroupId = '$SiteId'" : "NetworkId = '$SiteId'";
        $row = $this->fetchRow($this->select()->where("$where AND UserId = '$UserId' AND isDenied = 0 AND RequestAccepted = 0"));
        if ($row) {
            return $row->toArray();
        } else {
            return false;
        }
    }

    public function acceptMembershipRequest($MembershipRequestId) {
        $where = $this->getAdapter()->quoteInto('MembershipRequestId = ?', $MembershipRequestId);
        $this->update(array('RequestAccepted' => 1), $where);
    }
    
    public function denyMembershipRequest($MembershipRequestId, $deny = true) {
        $where = $this->getAdapter()->quoteInto('MembershipRequestId = ?', $MembershipRequestId);
        $this->update(array('isDenied' => $deny ? 1 : 0), $where);
    }

    public function populateNetworkId() {
        $Groups = new Brigade_Db_Table_Groups();
        $rows = $this->fetchAll($this->select()->group(array('GroupId')))->toArray();
        foreach($rows as $row) {
            $networkInfo = $Groups->loadProgOrg($row['GroupId']);
            $where = $this->getAdapter()->quoteInto("GroupId = ?", $row['GroupId']);
            $this->update(array('NetworkId' => $networkInfo['NetworkId']), $where);
        }
    }
    
    
    /** sql refactor **/
    
    public function acceptMembershipUserGroup($userId, $groupId) {
        $where[] = $this->getAdapter()->quoteInto('UserId = ?', $userId);
        $where[] = $this->getAdapter()->quoteInto('GroupId = ?', $groupId);
        $this->update(array('RequestAccepted' => 1), $where);
    }
    
    public function denyMembershipUserGroup($userId, $groupId) {
        $where[] = $this->getAdapter()->quoteInto('UserId = ?', $userId);
        $where[] = $this->getAdapter()->quoteInto('GroupId = ?', $groupId);
        $this->update(array('isDenied' => 1), $where);
    }
}
?>
