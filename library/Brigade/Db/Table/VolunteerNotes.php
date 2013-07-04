<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_VolunteerNotes extends Zend_Db_Table_Abstract {

    protected $_name = 'volunteer_notes';
    protected $_primary = 'VolunteerNoteId';

    public function addVolunteerNote($data) {
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($data);
    }

    public function editVolunteerNote($VolunteerNoteId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $data['ModifiedBy'] = $_SESSION['UserId'];
        $where = $this->getAdapter()->quoteInto('VolunteerNoteId = ?', $VolunteerNoteId);
        $this->update($data, $where);
    }

    public function deleteVolunteerNote($VolunteerNoteId) {
        $where = $this->getAdapter()->quoteInto('VolunteerNoteId = ?', $VolunteerNoteId);
        $this->delete($where);
    }

    public function deleteVolunteerNotes($VolunteerId) {
        $where = $this->getAdapter()->quoteInto('VolunteerId = ?', $VolunteerId);
        $this->delete($where);
    }

    public function getVolunteerNotes($VolunteerId) {
        $rows = $this->fetchAll($this->select()->where('VolunteerId = ?', $VolunteerId));
	return $rows ? $rows->toArray() : NULL;
    }

    public function getVolunteerNotesReport($SiteId, $Level = 'project') {
        $select = $this->select()
            ->from(array('n' => 'volunteer_notes'), array('n.*', 'u.FullName as VolunteerName', 'u.UserId', "date_format(n.CreatedOn, '%m/%d/%Y') as DateAdded", 'n.CreatedBy as AddedBy', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId=v.ProjectId AND pd.VolunteerId=v.UserId AND pd.OrderStatusId >= 1 AND pd.OrderStatusId <= 2) as total_fundraised', '(SELECT COUNT(*) FROM volunteers vv WHERE vv.UserId=v.UserId) as activities_participated'))
            ->joinInner(array('v' => 'volunteers'), 'v.VolunteerId=n.VolunteerId')
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId');
        if ($Level == 'project') {
            $select = $select->where('v.ProjectId = ?', $SiteId);
        } else if ($Level == 'group') {
            $select = $select->where("v.GroupId = '$SiteId')");
        } else if ($Level == 'organization') {
            $select = $select->where("v.NetworkId='$SiteId')");
        }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function getVolunteerNotesBySite($UserId, $SiteId, $Level, $ProgramId = NULL, $GroupId = NULL) {
        $select = $this->select()
            ->from(array('n' => 'volunteer_notes'), array('n.Notes'))
            ->joinInner(array('v' => 'volunteers'), 'n.VolunteerId=v.VolunteerId')
            ->joinInner(array('p' => 'projects'), 'v.ProjectId = p.ProjectId')
            ->where("v.IsDenied = 0")
            ->where("v.IsDeleted = 0")
            ->where("v.DocumentsSigned = 1");
        if ($Level == 'project') {
            $select = $select->where('v.ProjectId = ?', $SiteId);
        } else if ($Level == 'group') {
            $select = $select->where("v.ProjectId IN (SELECT pr.ProjectId FROM projects pr WHERE pr.GroupId = '$SiteId')");
        } else if ($Level == 'organization') {
            $select = $select->where("v.ProjectId IN (SELECT pr.ProjectId FROM projects pr WHERE pr.NetworkId='$SiteId')");
        }
        if ($Level == 'project') {
            $select = $select->where("p.ProjectId = ?", $SiteId);
        } else if ($Level == 'group') {
            $select = $select->where("p.GroupId = ?", $SiteId);
        } else if ($Level == 'organization') {
            $select = $select->where("p.NetworkId = ?", $SiteId);
            if (!empty($ProgramId)) {
                $select = $select->where("p.ProgramId = ?", $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->where("p.GroupId = ?", $GroupId);
            }
        }
        $rows = $this->fetchAll($select->where("v.UserId = ?", $UserId)->setIntegrityCheck(false));
	return $rows ? $rows->toArray() : NULL;
    }
    
}
?>
