<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Brigades.php';

class Brigade_Db_Table_ProjectDonations extends Zend_Db_Table_Abstract {

    protected $_name = 'project_donations';
    protected $_primary = 'ProjectDonationId';

    public function loadInfo($ProjectDonationId) {
        $row = $this->fetchRow(
                    $this->select()->where('ProjectDonationId = ?', $ProjectDonationId)
               );
        return !empty($row) ? $row->toArray() : false;
    }

    public function isProjectDonationIdExists($ProjectDonationId) {
        $row = $this->fetchRow($this->select()->where('ProjectDonationId = ?', $ProjectDonationId));
        return !empty($row) ? true : false;
    }

    public function isTransactionIdExists($TransactionId) {
        $row = $this->fetchRow($this->select()->where('TransactionId = ?', $TransactionId));
        return !empty($row) ? true : false;
    }

    public function getInfoByTransactionId($TransactionId) {
        return $this->fetchRow($this->select()->where('TransactionId = ?', $TransactionId))->toArray();
    }

    public function getUserDonations($UserId, $NetworkId = NULL, $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
        try {
            $select = $this->select()->from(array('pd' => 'project_donations'), array('sum(DonationAmount) as DonationAmount'));
            if (!empty($NetworkId)) {
                $select = $select->where("pd.NetworkId = ?", $NetworkId);
                if (!empty($ProgramId)) {
                    $select = $select->where("pd.ProgramId = ?", $ProgramId);
                }
                if (!empty($GroupId)) {
                    $select = $select->where("pd.GroupId = ?", $GroupId);
                }
                if (!empty($ProjectId)) {
                    $select = $select->where("pd.ProjectId = ?", $ProjectId);
                }
            }
            $select = $select->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where('pd.VolunteerId = ?', $UserId);
            $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
            return $row['DonationAmount'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    //should be renamed as countUPD or totalUPD
    public function getUserProjectDonations($UserId, $ProjectId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('pd' => 'project_donations'), array('sum(DonationAmount) as DonationAmount'))
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where('pd.ProjectId = ?', $ProjectId)
                ->where('pd.VolunteerId = ?', $UserId))->toArray();
            return $row['DonationAmount'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getUserGroupDonations($UserId, $GroupId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('pd' => 'project_donations'), array('sum(DonationAmount) as DonationAmount'))
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("pd.ProjectId IN (SELECT p.ProjectId FROM projects p WHERE p.GroupId='$GroupId')")
                ->where('pd.VolunteerId = ?', $UserId))->toArray();
            return $row['DonationAmount'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getUserNetworkDonations($UserId, $NetworkId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from(array('pd' => 'project_donations'), array('sum(DonationAmount) as DonationAmount'))
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("pd.NetworkId='$NetworkId'")
                ->where('pd.VolunteerId = ?', $UserId))->toArray();
            return $row['DonationAmount'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getVolunteerProjectDonations($UserId, $ProjectId) {
    try {
        $rows = $this->fetchAll($this->select()
        ->where('OrderStatusId >= 1')
        ->where('OrderStatusId <= 2')
        ->where('ProjectId = ?', $ProjectId)
        ->where('VolunteerId = ?', $UserId)
                ->order("CreatedOn DESC"))->toArray();
        return $rows;
    } catch (Zend_Db_Adapter_Exception $zdae) {
        throw $zdae;
    } catch (Zend_Db_Exception $e) {
        throw $e;
    }
    }

    public function getVolunteerDonors($UserId, $ProjectId) {
    try {
        $rows = $this->fetchAll($this->select()
                ->from('project_donations', array('SupporterName', 'SupporterEmail', 'SUM(DonationAmount) as Donation'))
        ->where('OrderStatusId >= 1')
        ->where('OrderStatusId <= 2')
                ->where('isAnonymous = 0')
        ->where('ProjectId = ?', $ProjectId)
        ->where('VolunteerId = ?', $UserId)
                ->group(array('SupporterName', 'SupporterEmail')))->toArray();
        return $rows;
    } catch (Zend_Db_Adapter_Exception $zdae) {
        throw $zdae;
    } catch (Zend_Db_Exception $e) {
        throw $e;
    }
    }

    public function getProjectDonations($ProjectId, $detailed = false, $search_text = NULL, $FromDate = NULL, $ToDate = NULL) {
        try {
            if (!$detailed) {
                $list = 'sum(DonationAmount) as DonationAmount';
            } else {
                $list = "*";
            }
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array("$list", 'pd.CreatedOn as DonationDate'))
                ->joinLeft(array('u' => 'users'), 'pd.VolunteerId=u.UserId')
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("pd.ProjectId = ?", $ProjectId);
            if (!empty($search_text)) {
                $select = $select->where("TransactionId LIKE '%$search_text%' OR SupporterName LIKE '%$search_text%' OR SupporterEmail LIKE '%$search_text%' OR u.FullName LIKE '%$search_text%'");
            }
            if(!empty($FromDate) && !empty($ToDate)) {
                $FromDate = date('Y-m-d 00:00:00', strtotime($FromDate));
                $ToDate = date('Y-m-d 23:59:59', strtotime($ToDate));
                $select = $select->where("pd.CreatedOn BETWEEN '$FromDate' AND '$ToDate'");
            }
            if (!$detailed) {
                $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
                return $row['DonationAmount'];
            } else {
                return $this->fetchAll($select->order('pd.CreatedOn DESC')->setIntegrityCheck(false))->toArray();
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {

        } catch (Zend_Db_Exception $e) {

        }
    }

    public function getGeneralDonations($ProjectId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from('project_donations', array('sum(DonationAmount) as DonationAmount'))
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where('ProjectId = ?', $ProjectId)
                ->where('VolunteerId = "" OR VolunteerId = "none" OR VolunteerId = "00000000-0000-0000-0000-000000000000"'))
                ->toArray();
            return $row['DonationAmount'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getGroupDonations($GroupId, $Type = 'donations', $detailed = false, $search_text = NULL, $FromDate = NULL, $ToDate = NULL, $ProjectId = NULL) {
        try {
            if (!$detailed) {
                $list = $Type == 'donations' ? 'sum(DonationAmount) as total_donations' : 'COUNT(*) as total_donors';
            } else {
                $list = "*";
            }
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array("$list", 'pd.CreatedOn as DonationDate'))
                ->joinInner(array('p' => 'projects'), 'pd.ProjectId=p.ProjectId')
                ->joinLeft(array('u' => 'users'), 'pd.VolunteerId=u.UserId')
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("p.GroupId = ?", $GroupId);
            if (!empty($search_text)) {
                $select = $select->where("TransactionId LIKE '%$search_text%' OR SupporterEmail LIKE '%$search_text%' OR p.Name LIKE '%$search_text%' OR u.FullName LIKE '%$search_text%'");
            }
            if(!empty($FromDate) && !empty($ToDate)) {
                $FromDate = date('Y-m-d 00:00:00', strtotime($FromDate));
                $ToDate = date('Y-m-d 23:59:59', strtotime($ToDate));
                $select = $select->where("pd.CreatedOn BETWEEN '$FromDate' AND '$ToDate'");
            }
            if (!empty($ProjectId)) {
                $select = $select->where("p.ProjectId = ?", $ProjectId);
            }
            if (!$detailed) {
                $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
                return $Type == 'donations' ? $row['total_donations'] : $row['total_donors'];
            } else {
                return $this->fetchAll($select->order('pd.CreatedOn DESC')->setIntegrityCheck(false))->toArray();
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getGroupDonors($GroupId, $ProjectId = NULL, $search_text = NULL) {
        try {
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array("SupporterName", "SupporterEmail", "SUM(DonationAmount) as total_donation", "ProjectId", "VolunteerId"))
                ->joinInner(array('p' => 'projects'), 'p.ProjectId=pd.ProjectId')
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("SupporterEmail IS NOT NULL AND SupporterEmail != ''")
                ->where("p.GroupId= ?", $GroupId);
            if (!empty($ProjectId)) {
                $select = $select->where("p.ProjectId = ?", $ProjectId);
            }
            if (!empty($search_text)) {
                $select = $select->where("p.Name LIKE '%$search_text%' OR pd.SupporterName LIKE '%$search_text%' OR pd.SupporterEmail LIKE '%$search_text%'");
            }
            return $this->fetchAll($select->group(array('SupporterName', 'SupporterEmail'))->order('SupporterName')->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getProjectDonors($ProjectId, $search_text = NULL) {
        try {
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array("SupporterName", "SupporterEmail", "SUM(DonationAmount) as total_donation", "ProjectId", "VolunteerId"))
                ->joinInner(array('p' => 'projects'), 'p.ProjectId=pd.ProjectId')
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("SupporterEmail IS NOT NULL AND SupporterEmail != ''")
                ->where("p.ProjectId= ?", $ProjectId);
            if (!empty($search_text)) {
                $select = $select->where("p.Name LIKE '%$search_text%' OR pd.SupporterName LIKE '%$search_text%' OR pd.SupporterEmail LIKE '%$search_text%'");
            }
            return $this->fetchAll($select->group(array('SupporterName', 'SupporterEmail'))->order('SupporterName')->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getSiteDonors($SiteId, $Level, $ProgramId = NULL, $GroupId = NULL, $search_text = NULL, $ProjectId = NULL) {
        try {
            if ($Level == 'activity') {
                $where = "ProjectId = '$SiteId'";
            } else if ($Level == 'group') {
                $where = "GroupId='$SiteId'";
            } else if ($Level == 'program') {
                $where = "ProgramId='$SiteId'";
            } else if ($Level == 'nonprofit') {
                $where = "NetworkId='$SiteId'";
                if (!empty($ProgramId)) {
                    $where .= " AND ProgramId = '$ProgramId'";
                }
                if (!empty($GroupId)) {
                    $where .= " AND GroupId = '$GroupId'";
                }
                if (!empty($ProjectId)) {
                    $where .= " AND ProjectId = '$ProjectId'";
                }
            }
            $select = $this->select()
                ->from('project_donations', array("SupporterName", "SupporterEmail", "SUM(DonationAmount) as total_donation", "ProjectId", "VolunteerId"))
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where($where);
            if (!empty($search_text)) {
                $select = $select->where("SupporterName LIKE '%$search_text%' OR SupporterEmail LIKE '%$search_text%'");
            }
            return $this->fetchAll($select->group(array('SupporterName', 'SupporterEmail'))->order('SupporterName')->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getDonorDonations($SupporterEmail, $SiteId, $Level = 'group', $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
    try {
            if ($Level == 'activity') {
                $where = "ProjectId = '$SiteId'";
            } else if ($Level == 'group') {
                $where = "pd.GroupId='$SiteId'";
            } else if ($Level == 'program') {
                $where = "pd.ProgramId='$SiteId'";
            } else if ($Level == 'nonprofit') {
                $where = "pd.NetworkId='$SiteId'";
                if (!empty($ProgramId)) {
                    $where .= " AND pd.ProgramId = '$ProgramId'";
                }
                if (!empty($GroupId)) {
                    $where .= " AND pd.GroupId = '$GroupId'";
                }
                if (!empty($ProjectId)) {
                    $where .= " AND pd.ProjectId = '$ProjectId'";
                }
            }
        $select = $this->select()
                ->from(array('pd' => 'project_donations'), array('*'))
        ->where('OrderStatusId >= 1')
        ->where('OrderStatusId <= 2')
        ->where('SupporterEmail = ?', $SupporterEmail)
                ->where($where);
            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        return $rows;
    } catch (Zend_Db_Adapter_Exception $zdae) {
        throw $zdae;
    } catch (Zend_Db_Exception $e) {
        throw $e;
    }
    }

    public function getUserDonationsBySite($User, $SiteId, $Level = 'group', $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
    try {
            if ($Level == 'group') {
                $where = "GroupId='$SiteId'";
            } else if ($Level == 'nonprofit') {
                $where = "NetworkId='$SiteId'";
                if (!empty($ProgramId)) {
                    $where .= " AND ProgramId = '$ProgramId'";
                }
                if (!empty($GroupId)) {
                    $where .= " AND GroupId = '$GroupId'";
                }
                if (!empty($ProjectId)) {
                    $where .= " AND ProjectId = '$ProjectId'";
                }
            } else if ($Level == 'project') {
                $where = "ProjectId = '$SiteId'";
            }
        $rows = $this->fetchAll($this->select()
        ->where('OrderStatusId >= 1')
        ->where('OrderStatusId <= 2')
        ->where('VolunteerId = ?', $User)
                ->where($where)
                ->setIntegrityCheck(false))->toArray();
        return $rows;
    } catch (Zend_Db_Adapter_Exception $zdae) {
        throw $zdae;
    } catch (Zend_Db_Exception $e) {
        throw $e;
    }
    }

    public function getProgramDonations($ProgramId) {
        try {
            $row = $this->fetchRow($this->select()
                ->from('project_donations', array('sum(DonationAmount) as DonationAmount'))
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("ProjectId IN (SELECT p.ProjectId FROM projects p WHERE p.ProgramId='$ProgramId')")
                ->setIntegrityCheck(false))->toArray();
            return $row['DonationAmount'];
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getNetworkDonations($NetworkId, $Type = 'donations', $detailed = false, $search_text = NULL, $limit = NULL, $FromDate = NULL, $ToDate = NULL, $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
        try {
            if (!$detailed) {
                $list = $Type == 'donations' ? array('sum(DonationAmount) as total_donations') : array('COUNT(*) as total_donors');
            } else {
                $list = array("*", "pd.CreatedOn as DonationDate", 'p.Name', 'u.FullName');
            }
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), $list)
                ->joinLeft(array('u' => 'users'), 'pd.VolunteerId=u.UserId')
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where("pd.NetworkId = ?",$NetworkId);
            if ($detailed) {
                $select = $select->joinInner(array('p' => 'projects'), 'pd.ProjectId=p.ProjectId');
            }
            if (!empty($ProgramId)) {
                $select = $select->where("pd.ProgramId = ?", $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->where("pd.GroupId = ?", $GroupId);
            }
            if (!empty($ProjectId)) {
                $select = $select->where("pd.ProjectId = ?", $ProjectId);
            }
            if (!empty($search_text)) {
                $select = $select->where("TransactionId LIKE '%$search_text%' OR SupporterEmail LIKE '%$search_text%' OR p.Name LIKE '%$search_text%' OR u.FullName LIKE '%$search_text%'");
            }
            if(!empty($FromDate) && !empty($ToDate)) {
                $select = $select->where("date_format(pd.CreatedOn, '%m/%d/%Y') BETWEEN '$FromDate' AND '$ToDate'");
            }
            if (!$detailed) {
                $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
                return $Type == 'donations' ? $row['total_donations'] : $row['total_donors'];
            } else {

                $select = $select->order('pd.CreatedOn DESC')->setIntegrityCheck(false);

                /** nuevo **/
                $data = array('COUNT(pd.ProjectDonationId) as CountRows', 'u.FullName');
                if (!empty($search_text)) {
                    $data[] = 'p.Name';
                }
                $selectCount = $this->select()
                    ->from(array('pd' => 'project_donations'), $data)
                    ->joinLeft(array('u' => 'users'), 'pd.VolunteerId=u.UserId')
                    ->where('OrderStatusId >= 1')
                    ->where('OrderStatusId <= 2')
                    ->where("pd.NetworkId = ?",$NetworkId);
                if (!empty($ProgramId)) {
                    $selectCount = $selectCount->where("pd.ProgramId = ?", $ProgramId);
                }
                if (!empty($GroupId)) {
                    $selectCount = $selectCount->where("pd.GroupId = ?", $GroupId);
                }
                if (!empty($ProjectId)) {
                    $selectCount = $selectCount->where("pd.ProjectId = ?", $ProjectId);
                }
                if (!empty($search_text)) {
                    $selectCount = $selectCount->joinInner(array('p' => 'projects'), 'pd.ProjectId=p.ProjectId');
                    $selectCount = $selectCount->where("TransactionId LIKE '%$search_text%' OR SupporterEmail LIKE '%$search_text%' OR p.Name LIKE '%$search_text%' OR u.FullName LIKE '%$search_text%'");
                }
                if(!empty($FromDate) && !empty($ToDate)) {
                    $selectCount = $selectCount->where("date_format(pd.CreatedOn, '%m/%d/%Y') BETWEEN '$FromDate' AND '$ToDate'");
                }
                $selectCount->setIntegrityCheck(false);
                $count = $this->fetchRow($selectCount)->toArray();
                $adapter = new Zend_Paginator_Adapter_DbSelect($select);
                $adapter->setRowCount((int)$count['CountRows']);
                /** nuevo **/
                return $adapter;
            }
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getProjectDonationList($ProjectId) {
        try {
            return $this->fetchAll($this->select()
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where('ProjectId = ?', $ProjectId))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function addProjectDonation($values) {
        $values['ProjectDonationId'] = $this->createProjectDonationId();
        $this->insert($values);

        return $values['ProjectDonationId'];
    }

    public function createProjectDonationId() {
        $row = $this->fetchRow($this->select()->from("project_donations", array('UUID() as ProjectDonationId')));
        return strtoupper($row['ProjectDonationId']);
    }

    public function editProjectDonationInfo($ProjectDonationId, $values) {
        $projectdonationinfoRowset = $this->find($ProjectDonationId);
        $projectdonationinfo = $projectdonationinfoRowset->current();
        if (!$projectdonationinfo) {
            throw new Zend_Db_Table_Exception('Project donation with id '.$ProjectDonationId.' is not present in the database');
        }

        foreach ($values as $k => $v) {
            if (in_array($k, $this->_cols)) {
                if ($k == $this->_primary) {
                    throw new Zend_Db_Table_Exception('Id of project donation cannot be changed');
                }
                $projectdonationinfo->{$k} = $v;
            }
        }
        $projectdonationinfo->save();

        return $this;
    }

    public function updateOrderStatus($TransactionId, $data) {
        $where = $this->getAdapter()->quoteInto('TransactionId = ?', $TransactionId);
        $this->update($data, $where);
    }

    public function getUserDonors($UserId, $ProjectId = NULL) {
        try {
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array('pd.DonationAmount', 'pd.SupporterName', 'pd.SupporterEmail', 'pd.ModifiedOn', 'p.Name as VolunteerActivity', 'pd.TransactionSource', 'pd.DonationComments', 'pd.isAnonymous'))
                ->joinInner(array('p' => 'projects'), "pd.ProjectId = p.ProjectId")
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->where('pd.VolunteerId = ?', $UserId);
                //->group(array("p.Name", "SupporterName", "SupporterEmail"))
            if (!empty($ProjectId)) {
                $select = $select->where('p.ProjectId = ?', $ProjectId);
            }
            $select = $select->order('p.Name');
            return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getUserDonorsRefunded($UserId) {
        try {
            return $this->fetchAll($this->select()
                ->from(array('pd' => 'project_donations'), array('DonationAmount', 'SupporterName', 'SupporterEmail', 'p.Name as VolunteerActivity', 'pd.TransactionSource', 'pd.DonationComments', 'pd.isAnonymous'))
                ->joinInner(array('p' => 'projects'), "pd.ProjectId = p.ProjectId")
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
        ->where('DonationAmount < 0')
                ->where('pd.VolunteerId = ?', $UserId)
                //->group(array("p.Name", "SupporterName", "SupporterEmail"))
                ->order('p.Name')
                ->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getUserProjectDonationsByStatus($VolunteerId, $ProjectId, $StartDate, $EndDate, $status = 'processed') {
    try {
            if ($status == 'processed') { $where = 'OrderStatusId = 2'; }
            if ($status == 'being processed') { $where = 'OrderStatusId = 1'; }
            if ($status == 'all') { $where = 'OrderStatusId > 0 AND OrderStatusId < 3'; }
            if ($VolunteerId == '') { $where1 = "VolunteerId == '' OR VolunteerId = ''"; }
            if ($StartDate != '' && $EndDate != '') {
                $row = $this->fetchRow($this->select()
                    ->from(array('pd' => 'project_donations'), array('sum(DonationAmount) as DonationAmount'))
                    ->where($where)
                    ->where('pd.ProjectId = ?', $ProjectId)
                    ->where("pd.CreatedOn BETWEEN '$StartDate' AND '$EndDate'")
                    ->where('pd.VolunteerId = ?', $VolunteerId))->toArray();
            } else {
                $row = $this->fetchRow($this->select()
                    ->from(array('pd' => 'project_donations'), array('sum(DonationAmount) as DonationAmount'))
                    ->where($where)
                    ->where('pd.ProjectId = ?', $ProjectId)
                    ->where('pd.VolunteerId = ?', $VolunteerId))->toArray();
            }
            return !empty($row['DonationAmount']) ? $row['DonationAmount'] : 0;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /* this method is only used in getting the activity list for user's donations and store it in the site_activities table */
    public function storeUserDonationActivities() {
        $SiteActivities = new Brigade_Db_Table_SiteActivities();
        $rows = $this->fetchAll($this->select()->where("DonorUserId != '00000000-0000-0000-0000-000000000000' AND DonorUserId != '' AND DonorUserId IS NOT NULL")->where("VolunteerId != '00000000-0000-0000-0000-000000000000' AND VolunteerId != '' AND VolunteerId IS NOT NULL")->where("DonationAmount != '' AND DonationAmount IS NOT NULL")->where("CreatedOn != '' AND CreatedOn IS NOT NULL AND CreatedOn != '0000-00-00 00:00:00'")->where("ProjectId != '' AND ProjectId IS NOT NULL"));
        foreach ($rows as $row) {
            $SiteActivities->addSiteActivity(array(
                'SiteId' => $row['ProjectId'],
                'ActivityType' => 'User Donation',
                'CreatedBy' => $row['VolunteerId'],
                'ActivityDate' => $row['CreatedOn'],
                'Details' => $row['DonationAmount'],
                'Recipient' => $row['DonorUserId']
            ));
        }
    }

    public function getDonationLivefeed() {
        return $this->fetchAll($this->select()
            ->from(array('pd' => 'project_donations'), array('DonationAmount', 'SupporterName', 'SupporterEmail', 'p.Name', 'pd.isAnonymous', 'p.URLName', 'pd.CreatedOn as DonationDate'))
            ->joinInner(array('p' => 'projects'), "pd.ProjectId = p.ProjectId")
            ->where('OrderStatusId >= 1')
            ->where('OrderStatusId <= 2')
            ->order('pd.CreatedOn DESC')
            ->limit(4)
            ->setIntegrityCheck(false))->toArray();
    }

    public function getTotalDonations() {
        $row = $this->fetchRow($this->select()
            ->from(array('pd' => 'project_donations'), array('SUM(DonationAmount) as total_donations'))
            //->where('OrderStatusId >= 1')
            //->where('OrderStatusId <= 2')
            ->where('OrderStatusId = 2')
            ->setIntegrityCheck(false))->toArray();
        return $row['total_donations'];
    }

    public function getTotalDonors() {
        $row = $this->fetchRow($this->select()
            ->from(array('pd' => 'project_donations'), array('COUNT(SupporterEmail) as total_donors'))
            ->where('OrderStatusId >= 1')
            ->where('OrderStatusId <= 2')
            ->distinct()
            ->setIntegrityCheck(false))->toArray();
        return $row['total_donors'];
    }

    public function getDonationsList() {
        return $this->fetchAll($this->select()
            ->from(array('pd' => 'project_donations'), array('pd.TransactionId', 'pd.SupporterName', 'pd.SupporterEmail', 'pd.VolunteerId', 'pd.ProjectId', 'pd.DonationAmount', 'p.Name', 'g.Currency', 'g.GoogleCheckoutAccountId', 'pd.CreatedON as DateDonated', 'pd.OrderStatusId', 'p.URLName as pURLName'))
            ->join(array('p' => 'projects'), 'pd.ProjectId=p.ProjectId')
            ->join(array('g' => 'groups'), 'p.GroupId=g.GroupId')
            ->where("g.GoogleCheckoutAccountId = 1")
            ->where('pd.OrderStatusId = 2')
            ->where("pd.CreatedOn >= ?", date('Y-m-d H:i:s', strtotime('2011-04-05 10:00:00 PM')))
            ->order("pd.CreatedOn")
            ->setIntegrityCheck(false))->toArray();
    }

    //daily group fundraised
    public function getdailyGroupDonations($groupId, $date_from, $date_to, $projectId, $sortby) {
        try {
            $timezone = "GMT";
            $now = strtotime($date_from);
            $group = array('day');
            $dateFrom = date('Y-m-d H:i:s',strtotime($date_from . " 00:00:00"));
            $dateTo = date('Y-m-d H:i:s',strtotime($date_to . " 23:59:59"));
            $gmnow = strtotime($date_from);
            $seconds = $now-$gmnow;
            $day = "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%b %d, %Y')";
            $timestamp = "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%Y-%m-%d')";
            $select= $this->select()
                ->from(array('pd'=>'project_donations'),
                    array(
                        "timestamp"=>$timestamp,
                        "day"=>$day,
                        "weekday"=>"date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%W')",
                        "donation"=>"sum(DonationAmount)"
                    ))
                ->where("pd.CreatedOn between '$date_from' and '$date_to'")
                ->where("pd.OrderStatusId = 2");

            if (!empty($projectId)){
                $select->where('ProjectId = ?', $projectId);
                $group[] = 'ProjectId';
            } else {
                $select->where("pd.GroupId='$groupId'");
            }

            if ($row = $this->fetchAll($select->order($sortby)->group($group))) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    //daily group unique donor
    public function getdailyGroupDonor($groupId, $date_from, $date_to, $projectId, $sortby) {
        try{
            $select= $this->select()
                ->from(array('p'=>'project_donations'),
                    array(
                        "count"=>"count(ProjectDonationId)",
                        "timestamp"=>"date_format(CreatedOn, '%Y-%m-%d')",
                        "date"=>"date_format(CreatedOn, '%m/%d/%y')"
                    ))
                ->where('OrderStatusId = 2')
                ->where("CreatedOn between '$date_from' and '$date_to'")
                ->group("date")
                ->order($sortby)
                ->setIntegrityCheck(false);

            if (!empty($projectId)){
                $select->where('ProjectId = ?', $projectId);
            } else {
                $select->where("GroupId='$groupId'");
            }
            $row = $this->fetchAll($select);
            return $row ? $row->toArray() : array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getDailyNetworkDonations($SiteId, $date_from, $date_to, $sortby, $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
        try {
            $timezone = "GMT";
            $now = strtotime($date_from);
            $dateFrom = date('Y-m-d H:i:s', strtotime($date_from . " 00:00:00"));
            $dateTo = date('Y-m-d H:i:s', strtotime($date_to . " 23:59:59"));
            $gmnow = strtotime($date_from);
            $seconds = $now - $gmnow;
            $day = "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%b %d, %Y')";
            $timestamp = "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%Y-%m-%d')";

            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array("timestamp" => $timestamp, "day" => $day, "weekday" => "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%W')", "donation" => "sum(DonationAmount)"))
                ->where("pd.CreatedOn between '$date_from' and '$date_to'")
                ->where("pd.OrderStatusId = 2");

            $where = "pd.NetworkId='$SiteId'";
            if (!empty($ProgramId)) {
                $where .= " AND pd.ProgramId = '$ProgramId'";
            }
            if (!empty($GroupId)) {
                $where .= " AND pd.GroupId = '$GroupId'";
            }
            if (!empty($ProjectId)) {
                $where .= " AND pd.ProjectId = '$ProjectId'";
            }
            $select = $select->where($where)->group(array("day"))->order($sortby)->setIntegrityCheck(false);

            if ($row = $this->fetchAll($select)) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getDailyNetworkDonor($NetworkId, $date_from, $date_to, $sortby, $ProgramId = NULL, $GroupId = NULL, $ProjectId = NULL) {
        try {
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array("count" => "count(ProjectDonationId)", "timestamp" => "date_format(CreatedOn, '%Y-%m-%d')", "date" => "date_format(CreatedOn, '%m/%d/%y')"))
                ->where('OrderStatusId = 2')
                ->where("CreatedOn between '$date_from' and '$date_to'");

            $where = "pd.NetworkId='$NetworkId'";
            if (!empty($ProgramId)) {
                $where .= " AND pd.ProgramId = '$ProgramId'";
            }
            if (!empty($GroupId)) {
                $where .= " AND pd.GroupId = '$GroupId'";
            }
            if (!empty($ProjectId)) {
                $where .= " AND pd.ProjectId = '$ProjectId'";
            }
            $select = $select->where($where)->group(array("date"))->order($sortby)->setIntegrityCheck(false);

            if ($row = $this->fetchAll($select)) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getDailyProjectDonations($ProjectId, $date_from, $date_to, $sortby) {
        try {
            $timezone = "GMT";
            $now = strtotime($date_from);
            $dateFrom = date('Y-m-d H:i:s', strtotime($date_from . " 00:00:00"));
            $dateTo = date('Y-m-d H:i:s', strtotime($date_to . " 23:59:59"));
            $gmnow = strtotime($date_from);
            $seconds = $now - $gmnow;
            $day = "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%b %d, %Y')";
            $timestamp = "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%Y-%m-%d')";

            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array("timestamp" => $timestamp, "day" => $day, "weekday" => "date_format(date_add(pd.CreatedOn, INTERVAL $seconds SECOND), '%W')", "donation" => "sum(DonationAmount)"))
                ->where("pd.CreatedOn between '$date_from' and '$date_to'")
                ->where("pd.OrderStatusId = 2")
                ->where("ProjectId = '$ProjectId'");

            $select = $select->group(array("day"))->order($sortby)->setIntegrityCheck(false);

            if ($row = $this->fetchAll($select)) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getDailyProjectDonor($ProjectId, $date_from, $date_to, $sortby) {
        try {
            $select = $this->select()
                ->from(array('p' => 'project_donations'), array("count" => "count(ProjectDonationId)", "timestamp" => "date_format(CreatedOn, '%Y-%m-%d')", "date" => "date_format(CreatedOn, '%m/%d/%y')"))
                ->where('OrderStatusId = 2')
                ->where("CreatedOn between '$date_from' and '$date_to'")
                ->where("ProjectId = '$ProjectId'");

            $select = $select->group(array("date"))->order($sortby)->setIntegrityCheck(false);

            if ($row = $this->fetchAll($select)) {
                return $row->toArray();
            }
            return array();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessages();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessages();
        }
    }

    public function getSiteDonorDonationsReport($SiteId, $Level = 'group', $ProjectId = NULL, $ProgramId = NULL, $GroupId = NULL) {
        try {
            $project_supported = $Level != 'project' ? 'COUNT(DISTINCT pd.ProjectId) AS ProjectSupported' : 'p.Name AS ProjectSupported';
            $select = $this->select()
                ->from(array('pd' => 'project_donations'), array('*', $project_supported, 'SUM(pd.DonationAmount) AS TotalDonation'))
                ->where('OrderStatusId >= 1')
                ->where('OrderStatusId <= 2')
                ->group(array('SupporterName', 'SupporterEmail'))
                ->order(array('SupporterName'));
            if ($Level == 'project') {
                $select = $select->joinInner(array('p' => 'projects'), 'pd.ProjectId=p.ProjectId');
            }
            if ($Level == 'nonprofit') {
                $select = $select->where('pd.NetworkId = ?', $SiteId);
            } else if ($Level == 'group') {
                $select = $select->where('pd.GroupId = ?', $SiteId);
            } else if ($Level == 'project') {
                $select = $select->where('pd.ProjectId = ?', $SiteId);
            }
            if (!empty($ProgramId)) {
                $select = $select->where('pd.ProgramId = ?', $ProgramId);
            }
            if (!empty($GroupId)) {
                $select = $select->where('pd.GroupId = ?', $GroupId);
            }
            if (!empty($ProjectId)) {
                $select = $select->where('pd.ProjectId = ?', $ProjectId);
            }
            return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function sumProjectDonations($ProjectId) {
    $row = $this->fetchRow($this->select()
        ->from(array('pd' => 'project_donations'), array('SUM(pd.DonationAmount) as TotalDonations'))
        ->where("pd.ProjectId = ?", $ProjectId))->toArray();
            return $row['TotalDonations'];
    }

    public function populateSiteIds() {
        $Projects = new Brigade_Db_Table_Brigades();
        $rows = $this->fetchAll($this->select()->group(array('ProjectId')))->toArray();
        foreach ($rows as $row) {
            if (!empty($row['ProjectId'])) {
                $projInfo = $Projects->loadInfoBasic($row['ProjectId']);
                if (!empty($projInfo)) {
                    $where = $this->getAdapter()->quoteInto("ProjectId = ?", $row['ProjectId']);
                    $this->update(array(
                        'NetworkId' => $projInfo['NetworkId'],
                        'ProgramId' => $projInfo['ProgramId'],
                        'GroupId' => $projInfo['GroupId']
                    ), $where);
                    echo 'updated '.$row['ProjectId'].'\n\n';
                }
            }
        }
    }

    /** Start Refactor SQL **/

    /**
     * Return list of donations for an specific project
     *
     * @param String $ProjectId Id project
     *
     * @return Array
     */
    public function getListDonationsByProject($ProjectId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('pd' => 'project_donations'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = pd.ProjectId',
                                    array())
                             ->join(array('u' => 'users'),
                                    'u.UserId = pd.VolunteerId',
                                    array())
                             ->where("TransactionId LIKE '%$search%' OR
                                      SupporterEmail LIKE '%$search%' OR
                                      p.Name LIKE '%$search%' OR
                                      u.FullName LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("pd.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("pd.CreatedOn <= '$endDate'");
        }
        $select = $select->where("pd.ProjectId = ?", $ProjectId)
                         ->where("pd.OrderStatusId > 0")
                         ->order(array('pd.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }

    /**
     * Return list of donations for an specific Program
     *
     * @param String $ProgramId Id project
     *
     * @return Array
     */
    public function getListDonationsByProgram($ProgramId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('pd' => 'project_donations'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = pd.ProjectId',
                                    array())
                             ->join(array('u' => 'users'),
                                    'u.UserId = pd.VolunteerId',
                                    array())
                             ->join(array('pr' => 'programs'),
                                    'pr.ProgramId = pd.ProgramId',
                                    array())
                             ->where("TransactionId LIKE '%$search%' OR
                                      SupporterEmail LIKE '%$search%' OR
                                      p.Name LIKE '%$search%' OR
                                      pr.ProgramName LIKE '%$search%' OR
                                      u.FullName LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("pd.CreatedOn > '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("pd.CreatedOn < '$endDate'");
        }
        $select = $select->where("pd.ProgramId = ?", $ProgramId)
                         ->where("pd.OrderStatusId > 0")
                         ->order(array('pd.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }

    /**
     * Return list of donations for an specific group
     *
     * @param String $GroupId Id group
     *
     * @return Array
     */
    public function getListDonationsByGroup($GroupId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('pd' => 'project_donations'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = pd.ProjectId',
                                    array())
                             ->join(array('u' => 'users'),
                                    'u.UserId = pd.VolunteerId',
                                    array())
                             ->join(array('g' => 'groups'),
                                    'g.GroupId = pd.GroupId',
                                    array())
                             ->where("TransactionId LIKE '%$search%' OR
                                      SupporterEmail LIKE '%$search%' OR
                                      p.Name LIKE '%$search%' OR
                                      g.GroupName LIKE '%$search%' OR
                                      u.FullName LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("pd.CreatedOn > '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("pd.CreatedOn < '$endDate'");
        }
        $select = $select->where("pd.GroupId = ?", $GroupId)
                         ->where("pd.OrderStatusId > 0")
                         ->order(array('pd.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }


    /**
     * Return list of donations for an specific Organization
     *
     * @param String $NetworkId Id group
     *
     * @return Array
     */
    public function getListDonationsByOrganization($NetworkId, $search = false,
        $startDate = false, $endDate = false
    ) {
        $select = $this->select()
                       ->from(array('pd' => 'project_donations'));
        if ($search) {
            $select = $select->join(array('p' => 'projects'),
                                    'p.ProjectId = pd.ProjectId',
                                    array())
                             ->join(array('u' => 'users'),
                                    'u.UserId = pd.VolunteerId',
                                    array())
                             ->join(array('n' => 'networks'),
                                    'n.NetworkId = pd.NetworkId',
                                    array())
                             ->where("TransactionId LIKE '%$search%' OR
                                      SupporterEmail LIKE '%$search%' OR
                                      p.Name LIKE '%$search%' OR
                                      n.NetworkName LIKE '%$search%' OR
                                      u.FullName LIKE '%$search%'");
        }
        if ($startDate) {
            $select = $select->where("pd.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("pd.CreatedOn <= '$endDate'");
        }
        $select = $select->where("pd.NetworkId = ?", $NetworkId)
                         ->where("pd.OrderStatusId > 0")
                         ->where("pd.OrderStatusId < 4")
                         ->order(array('pd.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }

    public function getDonationsByDate($startDate = false, $endDate = false) {
        $select = $this->select()
                       ->from(array('pd' => 'project_donations'));
        if ($startDate) {
            $select = $select->where("pd.CreatedOn >= '$startDate'");
        }
        if ($endDate) {
            $select = $select->where("pd.CreatedOn <= '$endDate'");
        }
        $select = $select->where("pd.OrderStatusId > 0")
                         ->where("pd.OrderStatusId < 3")
                         ->order(array('pd.CreatedOn DESC'));
        $res    = $this->fetchAll($select->setIntegrityCheck(false));
        if ($res) {
            $res = $res->toArray();
        } else {
            $res = array();
        }
        return $res;
    }

    public function getDonationByTransaction($TransactionId) {
        return $this->fetchRow($this->select()->where('TransactionId = ?', $TransactionId));
    }

    /**
     * Return raised value for an specific project
     *
     * @param String $ProjectId   Id project
     *
     * @return Float Raised value.
     */
    public function getDonationsByProject($ProjectId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('pd' => 'project_donations'),
                   array('SUM(pd.DonationAmount) as Amount'))
            ->where("pd.ProjectId = ?", $ProjectId)
            ->where("pd.OrderStatusId = 2")
            ->group('pd.ProjectId'));
    }

    /**
     * Return raised value for an specific project+volunteer
     *
     * @param String $ProjectId   Id project
     * @param String $VolunteerId Id volunteer.
     *
     * @return Float Raised value.
     */
    public function getDonationsByVolunteer($ProjectId, $VolunteerId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('pd' => 'project_donations'),
                   array('SUM(pd.DonationAmount) as Amount'))
            ->where("pd.ProjectId = ?", $ProjectId)
            ->where("pd.VolunteerId = ?", $VolunteerId)
            ->where("pd.OrderStatusId = 2")
            ->group('pd.ProjectId'));
    }

    /**
     * Return raised value for an specific group
     *
     * @param String $GroupId   Id group
     *
     * @return Float Raised value.
     */
    public function getDonationsByGroup($GroupId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('pd' => 'project_donations'),
                   array('SUM(pd.DonationAmount) as Amount'))
            ->where("pd.GroupId = ?", $GroupId)
            ->where("pd.OrderStatusId = 2")
            ->group('pd.GroupId'));
    }

    /**
     * Return raised value for an specific organization
     *
     * @param String $organizationId   Id of organization
     *
     * @return Float Raised value.
     */
    public function getDonationsByOrganization($organizationId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('pd' => 'project_donations'),
                   array('SUM(pd.DonationAmount) as Amount'))
            ->where("pd.NetworkId = ?", $organizationId)
            ->where("pd.OrderStatusId = 2")
            ->group('pd.NetworkId'));
    }

    /**
     * Return raised value for an specific program
     *
     * @param String $programId   Id of program
     *
     * @return Float Raised value.
     */
    public function getDonationsByProgram($programId) {
        return $this->fetchRow(
            $this->select()
            ->from(array('pd' => 'project_donations'),
                   array('SUM(pd.DonationAmount) as Amount'))
            ->where("pd.ProgramId = ?", $programId)
            ->where("pd.OrderStatusId = 2")
            ->group('pd.ProgramId'));
    }

    /**
     * Return raised value for an specific user
     *
     * @param String $userId   Id of user
     *
     * @return Float Raised value.
     */
    public function getDonationsByUser($userId, $organizationId = false) {
        $select = $this->select()
            ->from(array('pd' => 'project_donations'),
                   array('SUM(pd.DonationAmount) as Amount'))
            ->where("pd.VolunteerId = ?", $userId)
            ->where("pd.OrderStatusId = 2");
        if ($organizationId) {
            $select = $select->where("pd.NetworkId = ?", $organizationId);
        }
        return $this->fetchRow($select->group('pd.VolunteerId'));
    }

    /**
     * Edit project donation data
     *
     * @param String $ProjectDonationId ProjectDonation Id
     * @param Array  $data              Data to update into project
     *
     * @return void.
     */
    public function edit($ProjectDonationId, $data) {
        $where = $this->getAdapter()->quoteInto('ProjectDonationId = ?', $ProjectDonationId);
        $this->update($data, $where);
    }

}
