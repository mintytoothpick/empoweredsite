<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/LookupTable.php';


class Brigade_Db_Table_FundraisingCampaign extends Zend_Db_Table_Abstract {

    protected $_name = 'fundraising_campaigns';
    protected $_primary = 'FundraisingCampaignId';

    public function loadInfo($FundraisingCampaignId) {
        return $this->fetchRow($this->select()
            ->from(array('fc' =>'fundraising_campaigns'), array('fc.*', 'g.GroupName', 'g.URLName as groupLink', 'g.GroupId', 'fc.URLName as campaignLink', 'fc.Description as cDescription', 'g.GoogleCheckoutAccountId', 'g.PaypalAccountId', 'g.Currency', 'g.isNonProfit'))
            ->joinInner(array('gc' => 'group_campaigns'), 'fc.FundraisingCampaignId=gc.FundraisingCampaignId')
            ->joinInner(array('g' => 'groups'), 'g.GroupId=gc.GroupId')
            ->where("fc.FundraisingCampaignId = ?", $FundraisingCampaignId)
            ->setIntegrityCheck(false))->toArray();
    }

    public function listByGroup($GroupId, $List = 'All') {
        if ($List == 'All') {
            return $this->fetchAll($this->select()->from(array('fc' => 'fundraising_campaigns'), array('fc.*', '(SELECT COUNT(*) FROM fundraisers f WHERE f.FundraisingCampaignId = fc.FundraisingCampaignId) as total_fundraisers'))
                ->joinInner(array('gc' => 'group_campaigns'), 'gc.FundraisingCampaignId=fc.FundraisingCampaignId')
                ->where("gc.GroupId = ?", $GroupId)
                ->setIntegrityCheck(false))->toArray();
        } else {
            $where = $List == 'active' ? "EndDate >= Now()" : "EndDate < Now()";
            return $this->fetchAll($this->select()->from(array('fc' => 'fundraising_campaigns'), array('fc.*', '(SELECT COUNT(*) FROM fundraisers f WHERE f.FundraisingCampaignId = fc.FundraisingCampaignId) as total_fundraisers', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId=fc.FundraisingCampaignId AND pd.OrderStatusId >= 1 AND pd.OrderStatusId <= 2) as total_donations'))
                ->joinInner(array('gc' => 'group_campaigns'), 'gc.FundraisingCampaignId=fc.FundraisingCampaignId')
                ->where("gc.GroupId = ?", $GroupId)
                ->where($where)
                ->setIntegrityCheck(false))->toArray();
        }
    }

    public function addFundraisingCampaign($data) {
        $data['FundraisingCampaignId'] = $this->createFundraisingCampaignId();
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = $_SESSION['UserId'];
        $this->insert($data);

        return $data['FundraisingCampaignId'];
    }

    public function editFundraisingCampaign($FundraisingCampaignId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $data['ModifiedBy'] = $_SESSION['UserId'];

        $where = $this->getAdapter()->quoteInto('FundraisingCampaignId = ?', $FundraisingCampaignId);
        $this->update($data, $where);
    }


    public function createFundraisingCampaignId() {
        $row = $this->fetchRow($this->select()->from("fundraising_campaigns", array('UUID() as FundraisingCampaignId')));
        return strtoupper($row['FundraisingCampaignId']);
    }

    public function searchCampaign($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "Title = '$search_text'" : "Title LIKE '%$search_text%' AND FundraisingCampaignId NOT IN (SELECT fc.FundraisingCampaignId FROM fundraising_campaigns fc WHERE fc.Title = '$search_text')";
        $select = $this->select()->where($where)->order("Title");
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function getCampaignFundraiserDonations($FundraisingCampaignId) {
        return $this->fetchAll($this->select()
            ->from(array('fc' => 'fundraising_campaigns'), array('u.FullName as Fundraiser', 'f.FundraiserId', '(SELECT SUM(DonationAmount) FROM project_donations pd WHERE pd.ProjectId=fc.FundraisingCampaignId AND pd.OrderStatusId = 2 AND pd.VolunteerId=u.UserId) as AmountRaised', 'f.UserDonationGoal'))
            ->joinInner(array('f' => 'fundraisers'), 'fc.FundraisingCampaignId=f.FundraisingCampaignId')
            ->joinInner(array('u' => 'users'), 'f.UserId=u.UserId')
            ->where('fc.FundraisingCampaignId = ?', $FundraisingCampaignId)
	    ->order('Fundraiser')
            ->setIntegrityCheck(false))->toArray();
    }

    public function getDetailDonationReport($FundraisingCampaignId, $StartDate='', $EndDate='') {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $Fundraisers = new Brigade_Db_Table_Fundraisers();
            if ($StartDate != '' && $EndDate != '') {
                $rows = $this->fetchAll($this->select()
                    ->from(array('pd' => 'project_donations'), array('FullName as Fundraiser', 'SUM(DonationAmount) as AmountRaised', 'p.DonationGoal', 'pd.OrderStatusId', 'pd.VolunteerId'))
                    ->joinInner(array('u' => 'users'), 'pd.VolunteerId = u.UserId')
                    ->joinInner(array('fc' => 'fundraising_campaigns'), 'fc.FundraisingCampaignId = pd.ProjectId')
                    ->where('pd.OrderStatusId >= 1')
                    ->where('pd.OrderStatusId <= 2')
                    ->where("pd.CreatedOn BETWEEN '$StartDate' AND '$EndDate'")
                    ->where("pd.ProjectId = ?", $FundraisingCampaignId)
                    ->order('Fundraiser')
                    ->setIntegrityCheck(false))->toArray();
            } else {
                $rows = $this->fetchAll($this->select()
                    ->from(array('pd' => 'project_donations'), array('FullName as Fundraiser', 'SUM(DonationAmount) as AmountRaised', 'p.DonationGoal', 'pd.OrderStatusId', 'pd.VolunteerId'))
                    ->joinInner(array('u' => 'users'), 'pd.VolunteerId = u.UserId')
                    ->joinInner(array('fc' => 'fundraising_campaigns'), 'fc.FundraisingCampaignId = pd.ProjectId')
                    ->where('pd.OrderStatusId >= 1')
                    ->where('pd.OrderStatusId <= 2')
                    ->where("pd.ProjectId = ?", $FundraisingCampaignId)
                    ->order('Fundraiser')
                    ->setIntegrityCheck(false))->toArray();
            }
            $result = array();
            if (count($rows) > 0) {
                foreach ($rows as $row) {
                    $row['BeingProcessed'] = $donations->getUserProjectDonationsByStatus($row['VolunteerId'], $FundraisingCampaignId, $StartDate, $EndDate, 'being processed');
                    $row['Processed'] = $donations->getUserProjectDonationsByStatus($row['VolunteerId'], $FundraisingCampaignId, $StartDate, $EndDate, 'processed');
                    $row['DonationGoal'] = $Fundraisers->getDonationGoal($FundraisingCampaignId, $row['VolunteerId'], true);
                    $result[] = $row;
                }
            }

            return $result;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function PopulateToProjectsTable() {
        $Projects = new Brigade_Db_Table_Brigades();
        $rows = $this->fetchAll($this->select())->toArray();
        foreach ($rows as $row) {
            $Projects->addProject(array(
                'ProjectId' => $row['FundraisingCampaignId'],
                'Type' => 1,
                'URLName' => $row['URLName'],
                'Name' => $row['Title'],
                'Description' => $row['Description'],
                'DonationGoal' => $row['DonationGoal'],
                'isRecurring' => $row['isRecurring'] == 'Yes' ? 1 : 0,
                'StartDate' => '0000-00-00 00:00:00',
                'EndDate' => date('Y-m-d H:i:s', strtotime($row['EndDate'])),
                'CreatedOn' => date('Y-m-d H:i:s', strtotime($row['CreatedOn'])),
                'CreatedBy' => $row['CreatedBy'],
                'ModifiedOn' => date('Y-m-d H:i:s', strtotime($row['ModifiedOn'])),
                'ModifiedBy' => $row['ModifiedBy']
            ), false);
        }
    }

}
?>
