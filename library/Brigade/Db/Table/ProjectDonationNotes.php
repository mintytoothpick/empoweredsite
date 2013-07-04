<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_ProjectDonationNotes extends Zend_Db_Table_Abstract {

    protected $_name = 'project_donation_notes';
    protected $_primary = 'DonationNoteId';

    public function addDonationNote($data) {
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($data);
    }

    public function editDonationNote($DonationNoteId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $data['ModifiedBy'] = $_SESSION['UserId'];
        $where = $this->getAdapter()->quoteInto('DonationNoteId = ?', $DonationNoteId);
        $this->update($data, $where);
    }

    public function deleteDonationNote($DonationNoteId) {
        $where = $this->getAdapter()->quoteInto('DonationNoteId = ?', $DonationNoteId);
        $this->delete($where);
    }

    public function deleteDonationNotes($DonationId) {
        $where = $this->getAdapter()->quoteInto('DonationId = ?', $DonationId);
        $this->delete($where);
    }

    public function getDonationNotes($ProjectDonationId) {
        $rows = $this->fetchAll($this->select()->where('ProjectDonationId = ?', $ProjectDonationId));
    return $rows ? $rows->toArray() : NULL;
    }

    public function getDonorNotes($DonorEmail) {
        $rows = $this->fetchAll($this->select()->where('DonorEmail = ?', $DonorEmail));
        return $rows ? $rows->toArray() : NULL;
    }

    public function getDonationNotesReport($ProjectId) {
        return $this->fetchAll($this->select()
            ->from(array('n' => 'Donation_notes'), array('n.*', 'u.FullName as DonationName', 'u.UserId', "date_format(n.CreatedOn, '%m/%d/%Y') as DateAdded", 'n.CreatedBy as AddedBy'))
            ->joinInner(array('v' => 'Donations'), 'v.DonationId=n.DonationId')
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where('ProjectId = ?', $ProjectId)
            ->setIntegrityCheck(false))->toArray();
    }

    /** Start SQL Refactor **/

    /**
     * Return the list of notes for a donation
     *
     * @param String $DonationId Donation id.
     *
     * @return Array
     */
    public function getListByDonation($DonationId) {
        $rows = $this->fetchAll($this->select()
            ->where('ProjectDonationId = ?', $DonationId)
            ->setIntegrityCheck(false));
        return $rows ? $rows->toArray() : NULL;
    }
}
