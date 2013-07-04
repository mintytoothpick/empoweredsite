<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_DonorNotes extends Zend_Db_Table_Abstract {

    protected $_name = 'project_donation_notes';
    protected $_primary = 'DonorNoteId';

    public function addDonDonorNote($data) {
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($data);
    }

    public function editDonorNote($DonorNoteId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $data['ModifiedBy'] = $_SESSION['UserId'];
        $where = $this->getAdapter()->quoteInto('DonorNoteId = ?', $DonorNoteId);
        $this->update($data, $where);
    }

    public function deleteDonorNote($DonorNoteId) {
        $where = $this->getAdapter()->quoteInto('DonorNoteId = ?', $DonorNoteId);
        $this->delete($where);
    }

    public function deleteDonorNotes($DonorId) {
        $where = $this->getAdapter()->quoteInto('DonorId = ?', $DonorId);
        $this->delete($where);
    }

    public function getDonorNotes($ProjectDonorId) {
        $rows = $this->fetchAll($this->select()->where('ProjectDonorId = ?', $ProjectDonorId));
	return $rows ? $rows->toArray() : NULL;
    }

    public function getDonorNotesReport($ProjectId) {
        return $this->fetchAll($this->select()
            ->from(array('dn' => 'donor_notes'), array('dn.*', 'u.FullName as DonorName', 'u.UserId', "date_format(dn.CreatedOn, '%m/%d/%Y') as DateAdded", 'dn.CreatedBy as AddedBy'))
            ->joinInner(array('v' => 'Donors'), 'v.DonorId=dn.DonorId')
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where('ProjectId = ?', $ProjectId)
            ->setIntegrityCheck(false))->toArray();
    }
}
?>
