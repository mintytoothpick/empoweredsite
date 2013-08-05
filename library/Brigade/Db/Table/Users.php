<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';
require_once 'Zend/Config.php';
require_once 'Mailer.php';
require_once 'Brigade/Db/Table/Brigades.php';
require_once 'Brigade/Db/Table/Volunteers.php';
require_once 'Brigade/Db/Table/ProjectDonations.php';
require_once 'Brigade/Db/Table/LookupTable.php';

class Brigade_Db_Table_Users extends Zend_Db_Table_Abstract {

    // table name
    protected $_name = 'users';
    protected $salt1 = '9lob4l';
    protected $salt2 = 'b12!94d3s';

    public function listAll() {
        return $this->fetchAll($this->select())->toArray();
    }

    public function loadInfo($UserId) {
        return $this->fetchRow($this->select()->where('UserId = ?', $UserId));
    }

    public function loadUser($email, $password) {
        // the passwords in test are not encrypted so let's comment this out for teh meantime
        // $row = $this->fetchRow($this->select()->where('Email = ?', $email)->where('Password = ?', $this->encryptPassword($password)));
        $row = $this->fetchRow($this->select()->where('Email = ?', $email)->where('Password = ?', $password)->where('isDeleted = 0'));
        if ($row) {
            // user has logged in successfully, so update the LastLogin field in the users table
            $this->edit($row['UserId'], array('LastLogin' => date('Y-m-d H:i:s')));
            return array('zend_auth_credential_match' => 1, $row);
        } else {
            return null;
        }
    }

    public function unique_key($id) {
        if (strpos($id, '@') !== false) {
            return "Email";
        }
        return "UserId";
    }

    public function findBy($id) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $row = $this->fetchRow($this->select()->where($this->unique_key($id).' = ?', $id));
            if ($row) {
                $row = $row->toArray();
                $row['brigades_participated'] = $this->getBrigadesParticipated($row['UserId']);
                $row['total_donations'] = $donations->getUserDonations($row['UserId']);
            }
            return $row ? $row : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessage();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessage();
        }
    }

    public function getBrigadesParticipated($UserId) {
        $volunteer = new Brigade_Db_Table_Volunteers();
        return $volunteer->getBrigadesParticipated($UserId);
    }

    public function getBrigadedWith($UserId, $count = false, $limit = 0) {
        try {
            $volunteer = new Brigade_Db_Table_Volunteers();
            return $volunteer->getBrigadedWith($UserId, $count, $limit);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessage();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessage();
        }
    }

    public function getBrigadesJoined($UserId, $Type = 'All', $limit = NULL) {
        try {
            $volunteer = new Brigade_Db_Table_Volunteers();
            return $volunteer->getBrigadesJoined($UserId, $Type, $limit);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessage();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessage();
        }
    }

    public function getFundraisingBrigadesJoined($UserId, $Type = 'All', $limit = NULL) {
        try {
            $volunteer = new Brigade_Db_Table_Volunteers();
            return $volunteer->getFundraisingBrigadesJoined($UserId, $Type, $limit);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessage();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessage();
        }
    }

    public function getGoingOnBrigade($UserId, $ProjectId) {
        try {
            $volunteer = new Brigade_Db_Table_Volunteers();
            return $volunteer->getGoingOnBrigade($UserId, $ProjectId);
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae->getMessage();
        } catch (Zend_Db_Exception $e) {
            throw $e->getMessage();
        }
    }

    public function addUser($values, $sendActivation = true) {
        $activation_code = sha1(uniqid('xyz', true));
        $values['UserId'] = $this->createUserId();
        $values['CreatedOn'] = date('Y-m-d H:i:s');
        if (!isset($values['FullName'])) {
                    $values['FullName'] = $values['FirstName']." ".$values['LastName'];
        }
        // don't encrypt the passwords for the meantime
        // $values['Password'] = $this->encryptPassword($values['Password']);
        $values['activation_code'] = $activation_code;
        $UserId = $this->insert($values);

        // add a record in lookup_table if the URLName does not exists
        $LookupTable = new Brigade_Db_Table_LookupTable();
        if (!$LookupTable->isSiteNameExists($values['URLName'], $UserId)) {
            $LookupTable->addSiteURL(array(
                'SiteName' => $values['URLName'],
                'SiteId' => $UserId,
                'Controller' => 'profile',
                'FieldId' => 'UserId'
                ));
        }

        if($sendActivation) {
            Zend_Registry::get('eventDispatcher')->dispatchEvent(EventDispatcher::$USER_REGISTERED,
                     array(
                        $values['Email'],
                        $values['FirstName'],
                        "http://{$_SERVER['HTTP_HOST']}/profile/activate/$UserId/$activation_code"
                     ));
        }

        return $UserId;
    }

    /**
     * Return users for a specific project.
     *
     * @param String $ProjectId   Id project
     *
     * @author Daniel Valverde
     */
    public function getUsersVolunteersForProject($ProjectId) {
        $select = $this->select()
            ->from(array('v' => 'volunteers'), array('u.*'))
            ->joinInner(array('u' => 'users'), 'v.UserId=u.UserId')
            ->where("v.ProjectId = ?", $ProjectId)
            ->where('v.isActive = 1')
            ->where('u.Active = 1')
            ->where('v.IsDeleted = 0 AND v.IsDenied = 0')
            ->order('u.FullName');
        return $this->fetchAll($select->setIntegrityCheck(false))->toArray();
    }

    public function edit($UserId, $values) {
        $userRowset = $this->find($UserId);
        $user = $userRowset->current();
        if (!$user) {
            throw new Zend_Db_Table_Exception('User with id '.$UserId.' is not present in the database');
        }

        foreach ($values as $k => $v) {
            if (in_array($k, $this->_cols)) {
                if ($k == $this->_primary) {
                    throw new Zend_Db_Table_Exception('Id of user cannot be changed');
                }
                // special case - hash have to be computed for password
                /*
                if ($k == 'password') {
                    $user->password = $this->encryptPassword($v);
                }
                else {
                    $user->{$k} = $v;
                }
                */
                $user->{$k} = $v;
            }
        }
        $user->save();

        return $this;
    }

    public function activateUser($UserId, $activation_code) {
        $row = $this->fetchRow($this->select()->where('UserId = ?', $UserId)->where('activation_code = ?', $activation_code));
        if ($row) {
            // $this->edit($UserId, array('Active' => 1));
            $data = array('Active' => 1, 'activation_code' => "");
            $where = $this->getAdapter()->quoteInto('UserId = ?', $UserId);
            $this->update($data, $where);
            return true;
        } else {
            return false;
        }
    }

    public function encryptPassword($password) {
        return sha1($this->salt1. $password . $this->salt2);
    }

    public function createUserId() {
        $row = $this->fetchRow($this->select()->from("users", array('UUID() as UserId')));
        return strtoupper($row['UserId']);
    }

    public function getNonProfitSupported($UserId) {
        $volunteer = new Brigade_Db_Table_Volunteers();
        $activities = $volunteer->getBrigadesJoined($UserId, "All", NULL, true);
        $projects = array();
        foreach($activities as $activity) {
            $projects[] = "'".$activity['ProjectId']."'";
        }
        $projects = implode(",", $projects);

        if (count($activities)) {
            return $this->fetchAll($this->select()
                ->from(array('n' => 'networks'), array('n.NetworkId', 'n.NetworkName', 'n.URLName'))
                ->joinInner(array('p' => 'projects'), 'p.NetworkId = n.NetworkId')
                ->where("p.ProjectId IN ($projects)")
                ->group(array('n.NetworkId', 'n.NetworkName', 'n.URLName'))
                ->order("NetworkName")
                ->setIntegrityCheck(false))->toArray();
        } else {
            return NULL;
        }
    }

    public function getGroupsSupported($UserId) {
        $volunteer = new Brigade_Db_Table_Volunteers();
        $activities = $volunteer->getBrigadesJoined($UserId, "All", NULL);
        $projects = array();
        foreach($activities as $activity) {
            $projects[] = "'".$activity['ProjectId']."'";
        }
        $projects = implode(",", $projects);

        if (count($activities)) {
            return $this->fetchAll($this->select()
                ->from(array('g' => 'groups'), array('g.GroupId', 'g.GroupName', 'g.URLName'))
                ->joinInner(array('p' => 'projects'), 'p.GroupId = g.GroupId')
                ->where("p.ProjectId IN ($projects)")
                ->group(array('g.GroupId', 'g.GroupName', 'g.URLName'))
                ->order("GroupName")
                ->setIntegrityCheck(false))->toArray();
        } else {
            return NULL;
        }
    }

    public function getFacebookUser($FacebookId) {
        $row = $this->fetchRow($this->select()->where("FacebookId = ?", $FacebookId));
        return $row ? $row : false;
    }

    /* The Top Nav was originally supposed to be First Name but was changed to Full Name.
       This function actually pulls Full Name and should be renamed when time permits */
    public function getUserFirstName($UserId) {
        $row = $this->fetchRow($this->select()->where("UserId = ?", $UserId));
        return $row['FirstName'];
    }

    public function getUserIdByURLName($URLName) {
        $row = $this->fetchRow($this->select()->where("URLName = ?", $URLName))->toArray();
        return $row['UserId'];
    }

    public function getFullNameById($UserId) {
    $row = $this->fetchRow($this->select()->where('UserId = ?', $UserId))->toArray();
    return $row['FullName'];
    }

    public function getURLNameById($UserId) {
        $row = $this->fetchRow($this->select()->where('UserId = ?', $UserId))->toArray();
        return $row['URLName'];
    }

    public function getUserId($FullName, $ProjectId) {
    $row = $this->fetchRow($this->select()
            ->from('users', array('users.UserId'))
            ->joinInner('volunteers', 'volunteers.UserId=users.UserId')
            ->where('volunteers.ProjectId = ?', $ProjectId)
            ->where("FullName = ?", $FullName)
            ->setIntegrityCheck(false));
    return $row['UserId'];
    }

    /**
     * TODO: Migrate to refactored SQL
     * Using in search controller
     */
    public function searchUser($search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "FirstName = '$search_text' OR LastName = '$search_text' OR FullName = '$search_text' OR Location = '$search_text' OR Email = '$search_text'" : "FirstName = '%$search_text%' OR LastName = '%$search_text%' OR FullName = '%$search_text%' OR Location = '%$search_text%' OR Email = '%$search_text%'";
        $select = $this->select()->where($where)->where('Active = 1 and isDeleted = 0')->order(array("FirstName", "LastName", "Location"));
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function getDonationReport($UserId, $StartDate, $EndDate) {
        try {
            ini_set("memory_limit","256M");
            set_time_limit(0);
            $rows = $this->fetchAll($this->select()
                ->from('project_donations', array('ProjectId', 'VolunteerId', 'TransactionId', 'DonationAmount', 'SupporterEmail', 'SupporterName', 'DonationComments', 'CreatedOn', 'ModifiedOn', 'orderstatus.OrderStatusName'))
                ->joinInner('orderstatus', 'project_donations.OrderStatusId=orderstatus.OrderStatusId')
                ->where('project_donations.OrderStatusId >= 1')
                ->where('project_donations.OrderStatusId <= 2')
                ->where("CreatedOn BETWEEN '$StartDate' AND '$EndDate'")
                ->where("v.VolunteerId = '$UserId'")
                ->setIntegrityCheck(false))->toArray();
            $Brigades = new Brigade_Db_Table_Brigades();
            $result = array();
            foreach($rows as $row) {
                if ($row['VolunteerId'] != '' || !empty($row['VolunteerId'])) {
                    $userInfo = $this->findBy($row['VolunteerId']);
                }
                $brigadeInfo = $Brigades->loadBrigadeTreeInfo($row['ProjectId']);
                $row['Name'] = $brigadeInfo['Name'];
                $row['GroupName'] = $brigadeInfo['GroupName'];
                $row['ProgramName'] = $brigadeInfo['ProgramName'];
                $row['BrigadeDate'] = date('m/d/Y', strtotime($brigadeInfo['StartDate']))." - ".date('m/d/Y', strtotime($brigadeInfo['EndDate']));
                $row['Volunteer'] = stripslashes($userInfo['FullName']);
                $result[] = $row;
            }
            return $result;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getGoogleCheckoutAccount($UserId) {
        return $this->fetchRow($this->select()
            ->from(array('gc' => 'googlecheckoutaccounts'), array('gc.*'))
            ->joinInner(array('u' => 'users'), 'u.GoogleCheckoutAccountId = gc.GoogleCheckoutAccountId')
            ->where('u.UserId = ?', $UserId)
            ->setIntegrityCheck(false))->toArray();
    }

    public function updateLogoMediaNames() {
        $rows = $this->fetchAll($this->select())->toArray();
        $user_image_location = realpath(dirname(__FILE__) . '/../../../')."/public/images/users/";
        foreach($rows as $row) {
            $logo_name_upper_case = $row['UserId'].".jpg";
            $logo_name_lower_case = strtolower($row['UserId']).".jpg";
            if (file_exists($user_image_location.$logo_name_lower_case) && !empty($row['UserId'])) {
                rename($user_image_location.$logo_name_lower_case, $user_image_location.$row['URLName']."-logo.jpg");
                unlink($user_image_location.$logo_name_lower_case);
            } else if (file_exists($user_image_location.$logo_name_upper_case) && !empty($row['UserId'])) {
                rename($user_image_location.$logo_name_upper_case, $user_image_location.$row['URLName']."-logo.jpg");
                unlink($user_image_location.$logo_name_upper_case);
            }
        }
    }

    public function updateAllowPercentageFee() {
        $where = $this->getAdapter()->quoteInto("allowPercentageFee = ?", "no");
        $this->update(array('allowPercentageFee' => 'optional'), $where);
    }

    /** Start Refactor SQL **/

    /**
     * Return user data by email.
     *
     * @param String $email Email to find the user.
     *
     * @return Array Data
     */
    public function getUserDataByEmail($email) {
        $res = $this->fetchRow($this->select()->where('Email = ?', $email));

        return ($res) ? $res : null;
    }

    /**
     * Return user data by url name.
     *
     * @param String $email Email to find the user.
     *
     * @return Array Data
     */
    public function getUserDataByUrlName($url) {
        $res = $this->fetchRow($this->select()->where('UrlName = ?', $url));

        return ($res) ? $res : null;
    }

    /**
     * Return user data by facebook id.
     *
     * @param String $fbid facebookId to find the user.
     *
     * @return Array Data
     */
    public function getUserDataByFaceBookId($fbid) {
        $res = $this->fetchRow($this->select()->where('FaceBookId = ?', $fbid));

        return ($res) ? $res : null;
    }

    /**
     * Save data.
     *
     * @param Array $data Data.
     *
     * @return Array Data
     */
    public function save($data) {
        $where = $this->getAdapter()->quoteInto('UserId = ?', $data['UserId']);
        $this->update($data, $where);
    }

    /**
     * Delete user from empowered - just put deleted true in database flag.
     *
     * @return void
     */
    public function delete($id) {
        $data = array('isDeleted' => 1);
        $where = $this->getAdapter()->quoteInto('UserId = ?', $id);
        $this->update($data, $where);
    }
}
