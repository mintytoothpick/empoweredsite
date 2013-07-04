<?php
require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Table/Exception.php';

/**
 * Database acces to projects table. The refactor startup.
 *
 * @author Matias Gonzalez
 */
class Brigade_Db_Table_Projects extends Zend_Db_Table_Abstract {

    protected $_name    = 'projects';
    protected $_primary = 'ProjectId';

    /**
     * Load information of a specific project.
     *
     * @param String $ProjectId Id of the project to load information.
     *
     * @return Information of a project.
     */
    public function loadInfo($ProjectId) {
        try {
            $donations = new Brigade_Db_Table_ProjectDonations();
            $row = $this->fetchRow($this->select()
                ->from(array('p' => 'projects'), array('p.*'))
                ->where('p.ProjectId = ?', $ProjectId));
            return !empty($row) ? $row->toArray() : null;
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Get initiatives for a Group.
     * Is used inside Project controller, index method.
     *
     * @param String  $GroupId Id of the current group.
     * @param String  $status  Filter the inititatives by status (date).
     * @param Integer $type    Type of project (Fundraising Campaign or Volunteer Activity)
     *
     * @return List initiatives
     */
    public function getInitiatives($GroupId, $status = 'upcoming', $type = null, $searchText = false) {
        try {
            $qry = $this->select()
                        ->from(array('p' => 'projects'), array('p.*'))
                        ->where('p.GroupId = ?', $GroupId);
            if (!is_null($type) && "$type" <> 'all') {
                $qry->where('Type = ?' , $type);
            }
            if ($status == 'upcoming') {
                $qry->where('EndDate > Now() OR EndDate = "0000-00-00 00:00:00"');
            } else if ($status == 'completed' || $status == 'past') {
                $qry->where('EndDate < Now() AND EndDate <> "0000-00-00 00:00:00"');
            } else if ($status == 'in progress') {
                $qry->where('StartDate <= Now() AND EndDate > Now()');
            }
            if ($searchText) {
                $db = $this->getAdapter();
                $qry->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('Name') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('Description') . " LIKE ?",
                        "%$searchText%"
                    )
                );
            }
            $qry->where('isDeleted = 0');
            return $this->fetchAll($qry)->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }


    /**
     * Get initiatives for an organization.
     * Is used inside Organization controller, index method.
     *
     * @param String  $organizationId Id of the current group.
     * @param String  $status  Filter the inititatives by status (date).
     * @param Integer $type    Type of project (Fundraising Campaign or Volunteer Activity)
     *
     * @return List of initiatives
     */
    public function getOrganizationInitiatives( $organizationId,
        $status = 'upcoming', $type = null, $searchText = false, $fundraising = false
    ) {
        try {
            $qry = $this->select()
                        ->from(array('p' => 'projects'), array('p.*'))
                        ->joinLeft(array('c' => 'contactinformation'),
                            'c.SiteId = p.ProjectId',array())
                        ->where('p.NetworkId = ?', $organizationId);
            if (!is_null($type)) {
                $qry->where('Type = ?' , $type);
            }
            if ($fundraising) {
                $qry->where('isFundraising = 1 OR isFundraising = "Yes"');
            }
            if ($status == 'upcoming') {
                $qry->where('(EndDate > Now()) OR
                             (EndDate = "0000-00-00 00:00:00")
                             OR (StartDate <= Now() AND EndDate > Now())');
            } else if ($status == 'completed' || $status == 'past') {

                $qry->where('(StartDate <= Now() AND EndDate < Now() AND
                             EndDate <> "0000-00-00 00:00:00")
                             OR (StartDate IS NULL AND EndDate < Now())');
            } else if ($status == 'in progress') {
                $qry->where('(StartDate <= Now() AND EndDate > Now()) OR (EndDate = "0000-00-00 00:00:00")');
            }
            if ($searchText) {
                $db = $this->getAdapter();
                $qry->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('Name') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('Description') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.Region') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.City') . " LIKE ?",
                        "%$searchText%"
                    )
                );
            }
            $qry->where('isDeleted = 0')
                ->order('EndDate DESC');
            return $this->fetchAll($qry)->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Get initiatives for an organization.
     * Is used inside Organization controller, index method.
     *                Program controller, index method.
     *
     * @param String  $programId Id of the current program.
     * @param String  $status    Filter the inititatives by status (date).
     * @param Integer $type      Type of project (Fund Campaign or Vol Activity)
     *
     * @return List of initiatives
     */
    public function getProgramInitiatives($programId, $status = 'upcoming',
        $type = null, $searchText = false, $limit = false
    ) {
        try {
            $qry = $this->select()
                        ->from(array('p' => 'projects'), array('p.*'))
                        ->joinLeft(array('c' => 'contactinformation'),
                            'c.SiteId = p.ProjectId',array());
            if (is_array($programId)) {
                $qry->where('p.ProgramId IN (?)', $programId);
            } else {
                $qry->where('p.ProgramId = ?', $programId);
            }
            if (!is_null($type) && "$type" != 'all') {
                $qry->where('Type = ?' , $type);
            }
            if ($status == 'upcoming') {
                $qry->where('(EndDate > Now()) OR
                             (EndDate = "0000-00-00 00:00:00")
                             OR (StartDate <= Now() AND EndDate > Now())');
            } else if ($status == 'completed' || $status == 'past') {
                $qry->where('EndDate < Now()');
            } else if ($status == 'in progress') {
                $qry->where('StartDate <= Now() AND EndDate > Now()');
            }
            if ($searchText) {
                $db = $this->getAdapter();
                $qry->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('Name') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('Description') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.Region') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('c.City') . " LIKE ?",
                        "%$searchText%"
                    )
                );
            }
            $qry->where('isDeleted = 0');
            if ($limit) {
                $qry->limit($limit);
            }
            return $this->fetchAll($qry)->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getUpcomingGroupInitiative($GroupId) {
        $row = $this->fetchRow(
            $this->select()
                 ->where('GroupId = ?', $GroupId)
                 ->where('EndDate >= Now() OR EndDate = "0000-00-00 00:00:00"')
                 ->where('isDeleted = 0')
                 ->order('EndDate ASC')
        );
        return $row ? $row->toArray() : NULL;
    }

    public function getPastGroupInitiative($GroupId) {
        $row = $this->fetchRow(
            $this->select()
                 ->where('GroupId = ?', $GroupId)
                 ->where('EndDate < Now()')
                 ->where('isDeleted = 0')
                 ->order('EndDate DESC')
        );
        return $row ? $row->toArray() : NULL;
    }

    /**
     * Count number of projects by organization.
     *
     * @param String  $organizationId Organization Id
     * @param String  $status         Filter the inititatives by status (date).
     * @param Integer $type           Type of project (Fund Camp or Vol Activity)
     *
     * @return Integer Number of programs.
     */
    public function countByOrganization($organizationId, $status, $type) {
        $select = $this->select()->from(
                            array('p' => 'projects'), array('COUNT(*) as Total')
                        );
        if ($status == 'upcoming') {
            $select->where('(EndDate > Now()) OR (EndDate == "0000-00-00 00:00:00"
                            AND StartDate > Now())');
        } else if ($status == 'completed' || $status == 'past') {
            $select->where('(EndDate < Now()) OR (EndDate == "0000-00-00 00:00:00"
                           AND StartDate < Now())');
        } else if ($status == 'in progress') {
            $select->where('StartDate <= Now() AND EndDate > Now()');
        }
        $select->where("p.NetworkId = ?", $organizationId)
               ->where('Type = ?' , $type)
               ->where('isDeleted = 0');

        return $this->fetchRow($select);
    }

    /**
     * Count number of projects for a program or coalition programs.
     *
     * @param Object $programData Program id or list of ids to look up.
     *
     * @return Integer Number of programs.
     */
    public function countByPrograms($programData, $type) {
        $select = $this->select()->from(
                            array('p' => 'projects'), array('COUNT(*) as Total')
                        );
        if (is_array($programData)) {
            $select->where("p.ProgramId IN (?)", $programData);
        } else {
            $select->where("p.ProgramId = ?", $programData);
        }
        $select->where('Type = ?' , $type)
               ->where('isDeleted = 0');
        return $this->fetchRow($select);
    }

    /**
     * Get projects of programs coalition.
     *
     * @param Array   $programIds Ids of projects.
     * @param String  $searchText Search by text
     * @param Integer $limit      Limit of records
     *
     * @return List of groups
     */
    public function getByCoalitionProgram($programIds, $searchText = false, $limit = false) {
        try {
            $select = $this->select()
                        ->from(array('p' => 'projects'))
                        ->where('ProgramId IN (?)', $programIds);
            if ($searchText) {
                $db = $this->getAdapter();
                $select->where(
                    $db->quoteInto(
                        $db->quoteIdentifier('Name') . " LIKE ?",
                        "%$searchText%"
                    ). ' OR '.
                    $db->quoteInto(
                        $db->quoteIdentifier('Description') . " LIKE ?",
                        "%$searchText%"
                    )
                );
            }
            $select->order('p.CreatedOn DESC');
            if ($limit) {
                $select->limit($limit);
            }
            return $this->fetchAll($select)->toArray();
        } catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    /**
     * Edit project information
     *
     * @param String $ProjectId Project Id
     * @param Array  $data      Data to update into project
     *
     * @return void.
     */
    public function edit($ProjectId, $data) {
        $where = $this->getAdapter()->quoteInto('ProjectId = ?', $ProjectId);
        $this->update($data, $where);
    }

    /**
     * Delete project
     *
     * @param String $ProjectId Project Id
     *
     * @return void.
     */
    public function deleteProject($ProjectId) {
        $where = $this->getAdapter()->quoteInto('ProjectId = ?', $ProjectId);
        $this->update(array('isDeleted' => 1), $where);
    }

    /**
     * Add project information
     *
     * @param Array $values Data to insert into project
     *
     * @return ProjectId.
     */
    public function add($values) {
        $values['ProjectId'] = $this->createProjectId();
        $this->insert($values);

        return $values['ProjectId'];
    }

    public function createProjectId() {
        $row = $this->fetchRow($this->select()->from("projects", array('UUID() as ProjectId')));
        return strtoupper($row['ProjectId']);
    }
    
    /**
     *
     * @param Group Id
     *
     * @return Projects related to the given Group
     */
    public function getProjects($GroupId) {
        return $this->fetchAll($this->select()->from(array('p' => 'projects'), array('p.ProjectId', 'p.Name'))->where("p.GroupId = ?", $GroupId))->toArray();
    }
    
    /**
     *
     * @param Network Id
     *
     * @return Projects related to the given Network
     */
    public function getProjectsByNetwork($NetworkId) {
        return $this->fetchAll($this->select()->from(array('p' => 'projects'), array('p.ProjectId', 'p.Name'))->where("p.NetworkId = ?", $NetworkId))->toArray();
    }
}
