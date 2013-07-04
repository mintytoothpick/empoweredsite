<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Volunteers.php';

class Brigade_Db_Table_Fundraisers extends Zend_Db_Table_Abstract {

    protected $_name = 'fundraisers';
    protected $_primary = 'FundraiserId';

    public function addFundraiser($data) {
        $data['FundraiserId'] = $this->createFundraiserId();
        $this->insert($data);
    }

    public function createFundraiserId() {
        $row = $this->fetchRow($this->select()->from("fundraisers", array('UUID() as FundraiserId')));
        return strtoupper($row['FundraiserId']);
    }

    /*
     * This method is used to get the fundraisers list for a specific fundraising campaign
     * @return array
     */
    public function getCampaignFundraisers($FundraisingCampaignId) {
        return $this->fetchAll($this->select()
            ->from(array('f' => 'fundraisers'), array('f.*', 'u.FullName', 'u.URLName'))
            ->joinInner(array('u' => 'users'), 'f.UserId=u.UserId')
            ->where('FundraisingCampaignId = ?', $FundraisingCampaignId)
            ->setIntegrityCheck(false))->toArray();
    }

    /*
     * This method is used to check if user has already joined a specific fundraising campaign
     * @return boolean
     */
    public function isFundraiserExists($FundraisingCampaignId, $UserId) {
        $row = $this->fetchRow($this->select()->where("UserId = ?", $UserId)->where("FundraisingCampaignId = ?", $FundraisingCampaignId));
        if (!empty($row)) {
            return true;
        } else {
            return false;
        }
    }

    /*
     * This method is used to get the list of fundraising campaigns supported by a specific user/fundraiser
     * @return array
     */
    public function getUserSupportedCampaigns($UserId, $List = 'All') {
        $select = $this->select()
            ->from(array('fc' => 'fundraising_campaigns'), array('fc.*', 'fc.URLName as campaignURLName', 'g.Currency'))
            ->joinInner(array('f' => 'fundraisers'), 'fc.FundraisingCampaignId = f.FundraisingCampaignId')
            ->joinInner(array('gc' => 'group_campaigns'), 'gc.FundraisingCampaignId = f.FundraisingCampaignId')
            ->joinInner(array('g' => 'groups'), 'g.GroupId = gc.GroupId')
            ->where("f.UserId = ?", $UserId);
        if ($List != 'All') {
            $where = ($List == "active") ? "(fc.EndDate >= Now() OR date_format(fc.EndDate, '%Y') = '1969') AND f.isActive = 1" : "(fc.EndDate < Now() AND date_format(fc.EndDate, '%Y') != '1969') OR f.isActive = 0";
            $select = $select->where($where);
        }
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    /*
     * This method is used to end a fundraising campaign when user clicks the "End Fundraising Campaign" link from profile dashboard page
     * @return null
     */
    public function EndCampaign($FundraiserId) {
        $where = $this->getAdapter()->quoteInto('FundraiserId = ?', $FundraiserId);
        $this->update(array('isActive' => 0, 'DateEnded' => date('Y-m-d H:i:s')), $where);
    }

    public function deleteCampaignFundraisers($FundraisingCampaignId) {
        $where = $this->getAdapter()->quoteInto('FundraisingCampaignId = ?', $FundraisingCampaignId);
        $this->delete($where);
    }

    public function getDonationGoal($FundraisingCampaignId, $UserId, $donationOnly = false) {
        $row = $this->fetchRow($this->select()->where("FundraisingCampaignId = ?", $FundraisingCampaignId)->where("UserId = ?", $UserId));
        if (count($row)) {
            $row = $row->toArray();
        }
        if ($donationOnly) {
            return $row;
        } else {
            return isset($row['UserDonationGoal']) ? $row['UserDonationGoal'] : 0;
        }
    }

    public function setDonationGoal($FundraiserId, $NewGoal) {
        $where = $this->getAdapter()->quoteInto('FundraiserId = ?', $FundraiserId);
        echo $this->update(array('UserDonationGoal' => $NewGoal), $where);
    }

}