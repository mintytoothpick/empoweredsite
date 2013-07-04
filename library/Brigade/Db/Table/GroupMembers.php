<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/Blogs.php';
require_once 'Brigade/Db/Table/UserRoles.php';

class Brigade_Db_Table_GroupMembers extends Zend_Db_Table_Abstract {

    protected $_name = 'group_members';
    protected $_primary = 'MemberId';

    public function AddGroupMember($data) {
        if (!isset($data['JoinedOn'])) {
            $data['JoinedOn'] = date('Y-m-d H:i:s');
        }
        return $this->insert($data);
    }

    public function EditGroupMember($MemberId, $data) {
        $where = $this->getAdapter()->quoteInto("MemberId = ?", $MemberId);
        $this->update($data, $where);
    }

    public function isMemberExists($SiteId, $UserId, $Level = 'group') {
        $where = $Level == 'group' ? "GroupId = '$SiteId'" : "NetworkId = '$SiteId'";
        $row = $this->fetchRow($this->select()->where("$where AND UserId = '$UserId'"));
        if ($row) {
            return $row->toArray();
        } else {
            return false;
        }
    }

    public function getGroupMembers($GroupId, $ActivateEmail = array(0,1), $isDeleted = 0, $sortbyImage = false, $search = NULL) {
        $select = $this->select()
            ->from(array('u' => 'users'), array('u.*', 'm.*'))
            ->joinInner(array('m' => 'group_members'), "m.UserId=u.UserId")
            ->where("m.GroupId = ?", $GroupId)
            ->where("m.isDeleted = $isDeleted")
            ->where("m.ActivateEmail IN (?)", $ActivateEmail)
            ->where("u.Active = 1")
            ->group("u.UserId")
            ->order("u.FullName");
        if (!empty($search)) {
            $select = $select->where("u.FullName LIKE '%$search%' OR u.Email LIKE '%$search%'");
        }
        $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();

        if ($sortbyImage) {
            // get profile images md5
            $Users = new Brigade_Db_Table_Users();
            $members_list = array();
            foreach($rows as $member) {
                $image = @imagecreatefromstring($member['ProfileImage']);
                if ($image) {
                    imagejpeg($image, realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg", 100);
                    $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg"));
                    if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg")) {
                        unlink(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg");
                    }
                } else {
                    if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg")) {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg"));
                    } else if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg")) {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg"));
                    } else {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/Pictures/002.jpg"));
                    }
                }
                $members_list[] = $member;
            }

            $groups = array();
            foreach ($members_list as $item) {
                $key = $item['md5_hash'];
                if (!isset($groups[$key])) {
                    $groups[$key] = array(
                        'items' => array($item),
                        'count' => ($key != '7e242d8d63c318c90d46a42bf33efa24' && $key != '1d79627b3d7fa28d89db9ee88066ad83') ? 1 : 2
                    );
                } else {
                    $groups[$key]['items'][] = $item;
                    $groups[$key]['count'] += 1;
                }
            }
            $group_prof_images_1 = array();
            $group_prof_images_2 = array();
            foreach($groups as $group => $items) {
                if(isset($items['count']) && $items['count'] == 1) {
                    $group_prof_images_1[] = $items['items'][0];
                } else {
                    foreach($items['items'] as $item) {
                        $group_prof_images_2[] = $item;
                    }
                }
            }
            return array_merge($group_prof_images_1, $group_prof_images_2);
        } else {
            return $rows;
        }
    }

    public function getProgramMembers($ProgramId, $ActivateEmail = array(0,1), $isDeleted = 0, $sortbyImage = false, $search = NULL) {
        $select = $this->select()
            ->from(array('u' => 'users'), array('u.*', 'm.*'))
            ->joinInner(array('m' => 'group_members'), "m.UserId=u.UserId")
            ->where("GroupId IN (SELECT sg.GroupId FROM groups sg WHERE sg.ProgramId='$ProgramId')")
            ->where("m.isDeleted = $isDeleted")
            ->where("ActivateEmail IN (?)", $ActivateEmail)
            ->order("u.FullName");
        if (!empty($search)) {
            $select = $select->where("FullName LIKE '%$search%' OR Email LIKE '%$search%'");
        }
        $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();

        if ($sortbyImage) {
            // get profile images md5
            $Users = new Brigade_Db_Table_Users();
            $members_list = array();
            foreach($rows as $member) {
                $image = @imagecreatefromstring($member['ProfileImage']);
                if ($image) {
                    imagejpeg($image, realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg", 100);
                    $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg"));
                    if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg")) {
                        unlink(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg");
                    }
                } else {
                    if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg")) {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg"));
                    } else if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg")) {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg"));
                    } else {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/Pictures/002.jpg"));
                    }
                }
                $members_list[] = $member;
            }

            $groups = array();
            foreach ($members_list as $item) {
                $key = $item['md5_hash'];
                if (!isset($groups[$key])) {
                    $groups[$key] = array(
                        'items' => array($item),
                        'count' => ($key != '7e242d8d63c318c90d46a42bf33efa24' && $key != '1d79627b3d7fa28d89db9ee88066ad83') ? 1 : 2
                    );
                } else {
                    $groups[$key]['items'][] = $item;
                    $groups[$key]['count'] += 1;
                }
            }
            $group_prof_images_1 = array();
            $group_prof_images_2 = array();
            foreach($groups as $group => $items) {
                if(isset($items['count']) && $items['count'] == 1) {
                    $group_prof_images_1[] = $items['items'][0];
                } else {
                    foreach($items['items'] as $item) {
                        $group_prof_images_2[] = $item;
                    }
                }
            }
            return array_merge($group_prof_images_1, $group_prof_images_2);
        } else {
            return $rows;
        }
    }

    public function getOrganizationMembers($NetworkId, $ActivateEmail = array(0,1), $isDeleted = 0, $sortbyImage = false, $search = NULL, $count = false, $limit = NULL) {
        if ($count) {
            $columns = array('COUNT(*) as total_members');
        } else {
            $columns = array('u.*', 'm.*');
        }
        $select = $this->select()
            ->from(array('u' => 'users'), $columns)
            ->joinInner(array('m' => 'group_members'), "m.UserId=u.UserId")
            ->where("m.isDeleted = $isDeleted")
            ->where("m.ActivateEmail IN (?)", $ActivateEmail)
            ->where("m.NetworkId = ?", $NetworkId)
            ->where("u.Active = 1");
        if (!empty($search)) {
            $select = $select->where("u.FullName LIKE '%$search%' OR u.Email LIKE '%$search%'");
        }
        if (!empty($limit)) {
            $select = $select->limit($limit);
        }
        if (!$count) {
            $rows = $this->fetchAll($select->group(array("u.Email"))->order("u.FullName")->setIntegrityCheck(false))->toArray();

            if ($sortbyImage) {
                // get profile images md5
                $Users = new Brigade_Db_Table_Users();
                $members_list = array();
                foreach($rows as $member) {
                    $image = @imagecreatefromstring($member['ProfileImage']);
                    if ($image) {
                        imagejpeg($image, realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg", 100);
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg"));
                        if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg")) {
                            unlink(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg");
                        }
                    } else {
                        if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg")) {
                            $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg"));
                        } else if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg")) {
                            $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg"));
                        } else {
                            $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/Pictures/002.jpg"));
                        }
                    }
                    $members_list[] = $member;
                }

                $groups = array();
                foreach ($members_list as $item) {
                    $key = $item['md5_hash'];
                    if (!isset($groups[$key])) {
                        $groups[$key] = array(
                            'items' => array($item),
                            'count' => ($key != '7e242d8d63c318c90d46a42bf33efa24' && $key != '1d79627b3d7fa28d89db9ee88066ad83') ? 1 : 2
                        );
                    } else {
                        $groups[$key]['items'][] = $item;
                        $groups[$key]['count'] += 1;
                    }
                }
                $group_prof_images_1 = array();
                $group_prof_images_2 = array();
                foreach($groups as $group => $items) {
                    if(isset($items['count']) && $items['count'] == 1) {
                        $group_prof_images_1[] = $items['items'][0];
                    } else {
                        foreach($items['items'] as $item) {
                            $group_prof_images_2[] = $item;
                        }
                    }
                }
                return array_merge($group_prof_images_1, $group_prof_images_2);
            } else {
                return $rows;
            }
        } else {
            $row = $this->fetchRow($select->setIntegrityCheck(false))->toArray();
            return $row['total_members'];
        }
    }


    public function getGroupLeaders($GroupId, $sortbyImage = false, $search = NULL) {
        $select = $this->select()
            ->from(array('u' => 'users'), array('u.*', 'm.*'))
            ->joinInner(array('m' => 'group_members'), "m.UserId=u.UserId")
            ->where("GroupId = ?", $GroupId)
            ->where("m.isDeleted = 0")
            ->where("m.isAdmin = 1 OR Title IS NOT NULL OR Title != ''")
            ->order("u.FullName");
        if (!empty($search)) {
            $select = $select->where("FullName LIKE '%$search%' OR Email LIKE '%$search%'");
        }
        $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        if ($sortbyImage) {
            // get profile images md5
            $Users = new Brigade_Db_Table_Users();
            $members_list = array();
            foreach($rows as $member) {
                $image = @imagecreatefromstring($member['ProfileImage']);
                if ($image) {
                    imagejpeg($image, realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg", 100);
                    $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg"));
                    if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg")) {
                        unlink(realpath(dirname(__FILE__) . '/../../../../')."/public/tmp/".$member['UserId'].".jpg");
                    }
                } else {
                    if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg")) {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".strtolower($member['UserId']).".jpg"));
                    } else if (file_exists(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg")) {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/users/".$member['UserId'].".jpg"));
                    } else {
                        $member['md5_hash'] = md5(file_get_contents(realpath(dirname(__FILE__) . '/../../../../')."/public/images/Pictures/002.jpg"));
                    }
                }
                $members_list[] = $member;
            }

            $groups = array();
            foreach ($members_list as $item) {
                $key = $item['md5_hash'];
                if (!isset($groups[$key])) {
                    $groups[$key] = array(
                        'items' => array($item),
                        'count' => ($key != '7e242d8d63c318c90d46a42bf33efa24' && $key != '1d79627b3d7fa28d89db9ee88066ad83') ? 1 : 2
                    );
                } else {
                    $groups[$key]['items'][] = $item;
                    $groups[$key]['count'] += 1;
                }
            }
            $group_prof_images_1 = array();
            $group_prof_images_2 = array();
            foreach($groups as $group => $items) {
                if(isset($items['count']) && $items['count'] == 1) {
                    $group_prof_images_1[] = $items['items'][0];
                } else {
                    foreach($items['items'] as $item) {
                        $group_prof_images_2[] = $item;
                    }
                }
            }
            return array_merge($group_prof_images_1, $group_prof_images_2);
        } else {
            return $rows;
        }
    }

    public function getGroupAdmins($GroupId) {
        return $this->fetchAll($this->select()
            ->from(array('u' => 'users'), array('u.*', 'm.*'))
            ->joinInner(array('m' => 'group_members'), "m.UserId=u.UserId")
            ->where("GroupId = ?", $GroupId)
            ->where("m.isDeleted = 0")
            ->where("m.isAdmin = 1")
            ->order("u.FullName")
            ->setIntegrityCheck(false))->toArray();
    }

    public function getMemberGroups($UserId) {
        return $this->fetchAll($this->select()
            ->from(array('g' => 'groups'), array('g.*', 'm.*'))
            ->joinInner(array('m' => 'group_members'), "m.GroupId=g.GroupId")
            ->where("m.UserId = ?", $UserId)
            ->where("m.isDeleted = 0")
            ->order("g.GroupName")
            ->setIntegrityCheck(false))->toArray();
    }

    public function ActivateEmail($GroupId, $UserId, $ActivateEmail) {
        $where = $this->getAdapter()->quoteInto("GroupId = '$GroupId' AND UserId = ?", $UserId);
        $this->update(array('ActivateEmail' => $ActivateEmail), $where);
    }

    public function leaveGroup($GroupId, $UserId) {
        $memberInfo = $this->loadInfoByUserGroupId($GroupId, $UserId);
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRole($memberInfo['UserId'], $memberInfo['GroupId']);

        $where = $this->getAdapter()->quoteInto("GroupId = '$GroupId' AND UserId = ?", $UserId);
        $this->update(array('isDeleted' => 1), $where);
    }

    public function deleteMember($MemberId) {
        // delete records to user_roles and user_role_sites tables
        $memberInfo = $this->loadInfo($MemberId);
        $UserRoles = new Brigade_Db_Table_UserRoles();
        $UserRoles->deleteUserRole($memberInfo['UserId'], $memberInfo['GroupId']);

        $where = $this->getAdapter()->quoteInto("MemberId = ?", $MemberId);
        $this->update(array('isDeleted' => 1), $where);
    }

    public function removeAdmin($GroupId, $UserId) {
        $where = $this->getAdapter()->quoteInto("GroupId = '$GroupId' AND UserId = ?", $UserId);
        $this->update(array('isAdmin' => 0), $where);
    }

    public function setAdminStatus($MemberId, $isAdmin, $GroupId = NULL) {
        if (!empty($GroupId)) {
            $where = $this->getAdapter()->quoteInto("GroupId = ?", $GroupId);
            $rows = $this->fetchAll($this->select()->where("GroupId = ?", $GroupId))->toArray();
            $UserRoles = new Brigade_Db_Table_UserRoles();
            foreach($rows as $member) {
                $UserRoles->deleteUserRole($member['UserId'], $member['GroupId']);
            }
        } else {
            $where = $this->getAdapter()->quoteInto("MemberId = ?", $MemberId);

            // add records to user_roles and user_role_sites tables
            $memberInfo = $this->loadInfo($MemberId);
            $UserRoles = new Brigade_Db_Table_UserRoles();
            $UserRoleId = $UserRoles->addUserRole(array(
                'UserId' => $memberInfo['UserId'],
                'RoleId' => 'ADMIN',
                'Level' => 'Group',
                'SiteId' => $memberInfo['GroupId']
            ));
        }
        $this->update(array('isAdmin' => $isAdmin), $where);
    }

    public function setMemberTitle($MemberId, $Title) {
        $where = $this->getAdapter()->quoteInto("MemberId = ?", $MemberId);
        $this->update(array('Title' => $Title), $where);
    }

    public function loadInfo($MemberId) {
        $row = $this->fetchRow($this->select()->where("MemberId = ?", $MemberId));

        if ($row) {
            return $row->toArray();
        } else {
            return null;
        }
    }

    public function loadInfoByUserGroupId($GroupId, $UserId) {
        $row = $this->fetchRow($this->select()->where("GroupId = '$GroupId' AND UserId = ?", $UserId));
        if ($row) {
            return $row->toArray();
        } else {
            return null;
        }
    }

    //use to get daily new members of the group
    public function getDailyGroupMembers($groupId, $date_from, $date_to, $sortby) {
        try{
            $select= $this->select()
                ->from(array('gm'=>'group_members'),
                    array(
                        "count"=>"count(UserId)",
                        "timestamp"=>"date_format(JoinedOn, '%Y-%m-%d')",
                        "date"=>"date_format(JoinedOn, '%m/%d/%y')"
                    ))
                ->where("JoinedOn between '$date_from' and '$date_to'")
                ->where("isDeleted = 0")
                ->where("GroupId = ?", $groupId)
                ->group("date")
                ->order($sortby)
                ->setIntegrityCheck(false);

            //echo $select;
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

    public function searchGroupMembers($GroupId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "u.FirstName = '$search_text' OR LastName = '$search_text' OR FullName = '$search_text' OR Location = '$search_text' OR Email = '$search_text'" : "FirstName = '%$search_text%' OR LastName = '%$search_text%' OR FullName = '%$search_text%' OR Location = '%$search_text%' OR Email = '%$search_text%'";
        $select = $this->select()
            ->from(array('g' => 'group_members'), array('u.FullName', 'u.URLName', 'u.UserId', 'u.Location'))
            ->joinInner(array('u' => 'users'), 'u.UserId = g.UserId')
            ->where($where)
            ->where('g.GroupId = ?', $GroupId)
            ->where('u.Active = 1')
            ->order("FullName")
            ->setIntegrityCheck(false);
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    public function searchOrganizationMembers($NetworkId, $search_text, $perfect_match = true, $limit = NULL, $offset = NULL) {
        $where = $perfect_match ? "u.FirstName = '$search_text' OR LastName = '$search_text' OR FullName = '$search_text' OR Location = '$search_text' OR Email = '$search_text'" : "FirstName = '%$search_text%' OR LastName = '%$search_text%' OR FullName = '%$search_text%' OR Location = '%$search_text%' OR Email = '%$search_text%'";
        $select = $this->select()
            ->from(array('g' => 'group_members'), array('u.FullName', 'u.URLName', 'u.UserId', 'u.Location'))
            ->joinInner(array('u' => 'users'), 'u.UserId = g.UserId')
            ->where($where)
            ->where('u.Active = 1')
            ->where('g.NetworkId = ?', $NetworkId)
            ->order("FullName")
            ->setIntegrityCheck(false);
        if (!empty($offset)) {
            $select->limit($limit, $offset);
        } else {
            $select->limit($limit);
        }
        return $this->fetchAll($select)->toArray();
    }

    /* Refactor */

    /*
     * get groups that a user is a member of
     *
     * @param $userId Id for user whose affilations are being listed
     *
     * @return $groups List of groups where the user has an affiliation
     */

    public function getUserGroupAffiliations($userId) {
        $rows = $this->fetchAll(
          $this->select()
            ->from(array('gm' => 'group_members'), array('g.*'))
            ->joinInner(array('g' => 'groups'), 'gm.GroupId = g.GroupId')
            ->where('gm.UserId = ?', $userId)
            ->where('gm.ActivateEmail = 1')
            ->where('gm.isDeleted = 0')
            ->group('g.GroupId')
            ->order('g.GroupName')
            ->setIntegrityCheck(false)
        );
        return $rows ? $rows->toArray() : NULL;
    }


    /*
     * get organizations that a user is a member of
     *
     * @param $userId Id for user whose affilations are being listed
     *
     * @return $organzations List of organzations where the user has an affiliation
     */

    public function getUserOrganizationAffiliations($userId) {
        $rows = $this->fetchAll(
          $this->select()
            ->from(array('gm' => 'group_members'), array('n.*'))
            ->joinInner(array('n' => 'networks'), 'gm.NetworkId = n.NetworkId')
            ->where('gm.UserId = ?', $userId)
            ->where('gm.isDeleted = 0')
            ->where('n.isDeleted = 0')
            ->group('n.NetworkId')
            ->order('n.NetworkName')
            ->setIntegrityCheck(false)
        );
        return $rows ? $rows->toArray() : NULL;
    }

    /**
     * Remove member of a organization
     *
     * @param String $userId
     * @param String $orgId
     */
    public function deleteOrganizationMember($userId, $orgId) {
        $where = $this->getAdapter()->quoteInto("UserId = '$userId' AND NetworkId = '$orgId'");
        $this->update(array('isDeleted' => 1), $where);
    }

    /**
     * Delete memberships of the user in groups and organizations.
     * Used for user deletion.
     */
    public function deleteAffiliations($userId) {
        $where = $this->getAdapter()->quoteInto('UserId = ?', $userId);
        $this->update(array('isDeleted' => 1), $where);
    }

    /**
     * Get organization members
     */
    public function getMembersByOrganization($OrgId, $ActivateEmail, $search = NULL) {
        $select = $this->select()
            ->from(array('m' => 'group_members'), array('m.*'))
            ->joinInner(array('u' => 'users'), 'm.UserId=u.UserId', array())
            ->where('m.NetworkId = ?', $OrgId)
            ->where('m.ActivateEmail IN (?)', $ActivateEmail)
            ->where('m.isDeleted = 0')
            ->where('u.Active = 1');
        if (!empty($search)) {
            $select->where("u.FullName LIKE '%$search%' OR u.Email LIKE '%$search%'");
        }
        $select->setIntegrityCheck(false);

        $all = $this->fetchAll($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }

    /**
     * Get group members
     */
    public function getMembersByGroup($GroupId, $ActivateEmail, $search = NULL) {
        $select = $this->select()
            ->from(array('m' => 'group_members'), array('m.*'))
            ->joinInner(array('u' => 'users'), 'm.UserId=u.UserId', array())
            ->where('m.GroupId = ?', $GroupId)
            ->where('m.ActivateEmail IN (?)', $ActivateEmail)
            ->where('m.isDeleted = 0')
            ->where('u.Active = 1');
        if (!empty($search)) {
            $select->where("u.FullName LIKE '%$search%' OR u.Email LIKE '%$search%'");
        }
        $select->setIntegrityCheck(false);

        $all = $this->fetchAll($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }

    /**
     * Count group members
     */
    public function countMembersByGroup($GroupId, $ActivateEmail, $search = NULL) {
        $select = $this->select()
            ->from(array('m' => 'group_members'), array('COUNT(*) as total_members'))
            ->joinInner(array('u' => 'users'), 'm.UserId=u.UserId', array())
            ->where('m.GroupId = ?', $GroupId)
            ->where('m.ActivateEmail IN (?)', $ActivateEmail)
            ->where('m.isDeleted = 0')
            ->where('u.Active = 1');
        if (!empty($search)) {
            $select->where("u.FullName LIKE '%$search%' OR u.Email LIKE '%$search%'");
        }
        $select->setIntegrityCheck(false);

        $all = $this->fetchRow($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }

    /**
     * Count org members
     */
    public function countMembersByOrg($OrgId, $ActivateEmail, $search = NULL) {
        $select = $this->select()
            ->from(array('m' => 'group_members'), array('COUNT(*) as total_members'))
            ->joinInner(array('u' => 'users'), 'm.UserId=u.UserId', array())
            ->where('m.NetworkId = ?', $OrgId)
            ->where('m.ActivateEmail IN (?)', $ActivateEmail)
            ->where('m.isDeleted = 0')
            ->where('u.Active = 1');
        if (!empty($search)) {
            $select->where("u.FullName LIKE '%$search%' OR u.Email LIKE '%$search%'");
        }
        $select->setIntegrityCheck(false);

        $all = $this->fetchRow($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }

    /**
     * Get user members
     */
    public function getMembersByUser($UserId, $Paid) {
        $select = $this->select()
            ->from(array('m' => 'group_members'), array('m.*'))
            ->where('m.UserId = ?', $UserId)
            ->where('m.isDeleted = 0')
            ->where('m.ActivateEmail = 1')
            ->where('m.paid = ?', $Paid);

        $all = $this->fetchAll($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }

    /**
     * Get count members with specific member title
     */
    public function getMembersByTitle($titleId) {
        $select = $this->select()
            ->from(array('m' => 'group_members'), array('m.*'))
            ->where('m.isDeleted = 0')
            ->where('m.ActivateEmail = 1')
            ->where('m.MemberTitleId = ?', $titleId);

        $all = $this->fetchAll($select);
        if ($all)
            return $all->toArray();
        else
            return array();
    }
}
