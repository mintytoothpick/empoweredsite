<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';

class Brigade_Db_Table_FundraisingSuggestedDonations extends Zend_Db_Table_Abstract {

    protected $_name = 'fundraising_suggested_donations';
    protected $_primary = 'SuggestedDonationId';

    public function getSuggestedDonations($ProjectId) {
        return $this->fetchAll($this->select()->where("ProjectId = ?", $ProjectId)->order("SuggestedDonationId"))->toArray();
    }

    public function addSuggestedDonation($data) {
        $this->insert($data);
    }

    public function editSuggestedDonation($SuggestedDonationId, $data) {
        $where = $this->getAdapter()->quoteInto('SuggestedDonationId = ?', $SuggestedDonationId);
        $this->update($data, $where);
    }

    public function deleteSuggestedDonation($SuggestedDonationId, $data) {
        $where = $this->getAdapter()->quoteInto('SuggestedDonationId = ?', $SuggestedDonationId);
        $this->delete($where);
    }

    public function deleteCampaignSuggestedDonations($ProjectId) {
        $where = $this->getAdapter()->quoteInto('ProjectId = ?', $ProjectId);
        $this->delete($where);
    }

}
?>
