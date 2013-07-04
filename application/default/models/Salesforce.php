<?php
require_once "Salesforce/soapclient/SforcePartnerClient.php";
require_once "Salesforce/soapclient/SforceHeaderOptions.php";
require_once 'Brigade/Db/Table/Salesforce.php';

/**
 * Singleton instance of salesforce integration platform
 *
 * @author Matias
 */
class Salesforce {

    private static $instance = null;

    private $_client = null;

    private function __construct() {
        $this->_client = new SforcePartnerClient();
        $this->_client->createConnection(dirname(__FILE__)."/../../../library/Salesforce/soapclient/partner.wsdl.xml");
    }

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function login($organization) {
        $salesforce = new Brigade_Db_Table_Salesforce();
        $info       = $salesforce->loadByOrganization($organization->id);
        if ($info) {
            $this->_loginAccount($info['user'], $info['password'].$info['token']);
            return true;
        }
        return false;
    }

    /**
     * Add volunteer into salesforce
     * Try to setup hierarchy Account and then Opportunity.
     *
     * @param Volunteer $volunteer
     */
    public function addVolunteer($volunteer) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $contactId = $this->_getContactId($volunteer->user);

        //add info
        $accountId     = $this->_getVolunteerAccountId($volunteer);
        $opportunityId = $this->_getOpportunityId($volunteer->project, $accountId);

        $chapter = ($volunteer->project->group) ? $volunteer->project->group : null;
        $this->_updateUserContact($volunteer->user, $contactId, $accountId, $chapter);

        $volunteerId = $this->getOpportunityContactRelationSalesforceId(
            $contactId,
            $opportunityId
        );
        if (!$volunteerId) {
            $volunteerId = $this->_createOpportunityContactRole(
                $volunteer,
                $contactId,
                $opportunityId
            );
        } else {
            $result = $this->_client->undelete(array($volunteerId));
            if ($result && $result[0] && $result[0]->success) {
                $volunteerId = $result[0]->id;
                Zend_Registry::get('logger')->info(__METHOD__."::[UnDeleted:{$volunteerId}]");
            }
        }

        return $volunteerId;
    }

    /**
     * Update membership status for a contact user
     */
    public function updateMember($member) {
        $contactId = $this->_getContactId($member->user);
        $accountId = $this->_getAccountId($member->group);

        $this->_updateUserContact($member->user, $contactId, $accountId, $member->group);
    }

    /**
     * Remove volunteer from salesforce
     *
     * @param Volunteer $volunteer
     */
    public function removeVolunteer($volunteer) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $contactId     = $this->_getContactId($volunteer->user);
        $opportunityId = $this->_getOpportunityId($volunteer->project);
        $volunteerId   = $this->getOpportunityContactRelationSalesforceId(
            $contactId,
            $opportunityId
        );
        if ($volunteerId) {
            $result = $this->_client->delete(array($volunteerId));
            if ($result && $result[0] && $result[0]->success) {
                Zend_Registry::get('logger')->info(__METHOD__."::[Deleted:".$volunteerId."]");
                return true;
            } else {
                return false;
            }
        }
        Zend_Registry::get('logger')->info(__METHOD__."::[NotDeleted]");
        return false;
    }

    /**
     * Account salesforce data update. could be organization, chapter, program obj
     */
    public function updateAccountInfo($account, $oldName = '') {
        Zend_Registry::get('logger')->info(__METHOD__);
        if ($oldName != '') {
            $id = $this->getAccountSalesforceId($oldName);
        } else {
            $id = $this->getAccountSalesforceId($account->name);
        }
        if ($id) {
            $this->_updateAccount($account, $id);
        }
    }

    public function updateOpportunityInfo($project, $oldName = '') {
        Zend_Registry::get('logger')->info(__METHOD__);
        if ($oldName != '') {
            $id = $this->getOpportunitySalesforceId($oldName);
        } else {
            $id = $this->getOpportunitySalesforceId($project->name);
        }
        if ($id) {
            $this->_updateOpportunity($project, $id);
        }
    }

    public function deleteOpportunity($project) {
        Zend_Registry::get('logger')->info(__METHOD__);

        $id = $this->getOpportunitySalesforceId($project->name);
        if ($id) {
            $result = $this->_client->delete(array($id));
            if ($result && $result[0] && $result[0]->success) {
                Zend_Registry::get('logger')->info(__METHOD__."::[Deleted:".$volunteerId."]");
                return true;
            } else {
                return false;
            }
        }
        Zend_Registry::get('logger')->info(__METHOD__."::[NotDeleted]");
        return false;
    }

    public function deleteAccount($account) {
        Zend_Registry::get('logger')->info(__METHOD__);

        $id = $this->getAccountSalesforceId($account->name);
        if ($id) {
            $result = $this->_client->delete(array($id));
            if ($result && $result[0] && $result[0]->success) {
                Zend_Registry::get('logger')->info(__METHOD__."::[Deleted:".$volunteerId."]");
                return true;
            } else {
                return false;
            }
        }
        Zend_Registry::get('logger')->info(__METHOD__."::[NotDeleted]");
        return false;
    }
    /**
     * Get the account id by name
     *
     * @param String $name
     *
     * @return $id or false
     */
    public function getAccountSalesforceId($name) {
        Zend_Registry::get('logger')->info(__METHOD__.'::[name:'.$name.']');
        $result = $this->_client->query(
            "SELECT Id FROM Account WHERE Name = '".addslashes($name)."'"
        );
        if ($result && $result->size > 0) {
            Zend_Registry::get('logger')->info(__METHOD__."::[Found Id:".$result->records[0]->Id[0]."]");
            return $result->records[0]->Id[0];
        } else {
            return false;
        }
    }

    /**
     * Get the contact id by email
     *
     * @param String $name
     *
     * @return $id or false
     */
    public function getContactSalesforceId($email) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $result = $this->_client->query(
            "SELECT Id FROM Contact WHERE Email = '".$email."'"
        );
        if ($result && $result->size > 0) {
            Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$result->records[0]->Id[0]."]");
            return $result->records[0]->Id[0];
        } else {
            return false;
        }
    }

    /**
     * Get the opportunity id by name
     *
     * @param String $name
     *
     * @return $id or false
     */
    public function getOpportunitySalesforceId($name, $accountId = '') {
        Zend_Registry::get('logger')->info(__METHOD__.'::[Name:'.addslashes($name).']');
        $qry = "SELECT Id FROM Opportunity WHERE Name = '".addslashes($name)."'";
        if ($accountId != '') {
            $qry .= " AND AccountId = '".$accountId."'";
        }
        $result = $this->_client->query($qry);
        if ($result && $result->size > 0) {
            Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$result->records[0]->Id[0]."]");
            return $result->records[0]->Id[0];
        } else {
            return false;
        }
    }

    /**
     * Get the opportunity id by name
     *
     * @param String $name
     *
     * @return $id or false
     */
    public function getOpportunityContactRelationSalesforceId($contactId, $opportunityId) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $qry  = "SELECT Id FROM OpportunityContactRole WHERE ContactId = '".$contactId."'";
        $qry .= " and OpportunityId = '".$opportunityId."'";
        $result = $this->_client->query($qry);
        if ($result && $result->size > 0) {
            Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$result->records[0]->Id[0]."]");
            return $result->records[0]->Id[0];
        } else {
            return false;
        }
    }


    public function logout() {
        Zend_Registry::get('logger')->info(__METHOD__);
        $this->_client->logout();
    }

    protected function _loginAccount($username, $password) {
        Zend_Registry::get('logger')->info(__METHOD__);
        return $this->_client->login($username, $password);
    }

    protected function _getAccountId($instance, $parent = null) {
        Zend_Registry::get('logger')->info(__METHOD__.'::[name:'.$instance->name.']');
        $id = $this->getAccountSalesforceId($instance->name);
        if (empty($id)) {
            if (isset($instance->groupId) && !empty($instance->groupId)
                && $instance->group
            ) {
                $parentId = $this->_getAccountId($instance->group);
            } else if (isset($instance->programId) && !empty($instance->programId)
                       && $instance->program
            ) {
                $parentId = $this->_getAccountId($instance->program);
            } else if (isset($instance->organizationId)
                       && !empty($instance->organizationId) && $instance->organization
            ) {
                $parentId = $this->_getAccountId($instance->organization);
            }
            Zend_Registry::get('logger')->info(
                'Create::[name:'.$instance->name.'][parentId:'.$parentId.']'
            );
            return $this->_createAccount($instance, $parentId);
        }
        return $id;
    }

    /**
     * Get the account id of salesforce to setup the new volunteer
     * If something is missing, it creates the account object.
     * @TODO: remove this and use _getAccountId
     *
     * @param Volunteer $volunteer
     */
    protected function _getVolunteerAccountId($volunteer) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $accountId = false;

        //get group id
        if ($volunteer->project->group) {
            Zend_Registry::get('logger')->info(__METHOD__."::group");
            $groupId = $this->getAccountSalesforceId($volunteer->project->group->name);
            if (!empty($groupId)) {
                $accountId = $groupId;
            }
        }

        //get program id
        if ($volunteer->project->program && empty($groupId)) {
            Zend_Registry::get('logger')->info(__METHOD__."::program");
            $parentId = $this->getAccountSalesforceId($volunteer->project->program->name);
            if (!empty($parentId)) {
                if ($volunteer->project->group) {
                    $accountId = $this->_createAccount($volunteer->project->group, $parentId);
                }
            }
        }

        //get organization id
        if ($volunteer->project->organization && empty($accountId)) {
            Zend_Registry::get('logger')->info(__METHOD__."::organization");
            $parentId = $this->getAccountSalesforceId($volunteer->project->organization->name);
            if (empty($parentId)) {
                Zend_Registry::get('logger')->info(__METHOD__."::create organization");
                $parentId = $this->_createAccount($volunteer->project->organization);
                Zend_Registry::get('logger')->info(__METHOD__."::new organization[id:{$parentId}]");
            }
            if ($volunteer->project->program) {
                Zend_Registry::get('logger')->info(__METHOD__."::create program[pid:{$parentId}]");
                $parentId = $this->_createAccount($volunteer->project->program, $parentId);
                Zend_Registry::get('logger')->info(__METHOD__."::new program[id:{$parentId}]");
            }
            if ($volunteer->project->group) {
                Zend_Registry::get('logger')->info(__METHOD__."::create group[pid:{$parentId}]");
                $parentId = $this->_createAccount($volunteer->project->group, $parentId);
                Zend_Registry::get('logger')->info(__METHOD__."::new group[id:{$parentId}]");
            }
            $accountId = $parentId;
        }

        return $accountId;
    }

    /**
     * Get the account id of salesforce to setup the new volunteer
     * If something is missing, it creates the account object.
     *
     * @param Volunteer $volunteer
     */
    protected function _getOpportunityId($project, $accountId = '') {
        $opportunityId = false;

        $opportunityId = $this->getOpportunitySalesforceId($project->name, $accountId);
        if (!$opportunityId) {
            $opportunityId = $this->_createOpportunity($project, $accountId);
        }
        return $opportunityId;
    }

    /**
     * Get the contact id of salesforce
     * If something is missing, it creates the contact object.
     *
     * @param Volunteer $volunteer
     */
    protected function _getContactId($user) {
        $contactId = $this->getContactSalesforceId($user->email);
        if (!$contactId) {
            $contactId = $this->_createUserContact($user);
        }
        return $contactId;
    }

    /**
     * Create the account into salesforce to setup the new volunteer
     *
     * @param Volunteer $volunteer
     */
    protected function _createAccount($account, $salesforceParentId = '') {
        $records            = array();
        $records[0]         = new SObject();
        $records[0]->fields = array(
            'Name' => $account->name
        );
        if ($salesforceParentId != '') {
            $records[0]->fields['ParentId'] = $salesforceParentId;
        }
        $records[0]->type = 'Account';

        $response = $this->_client->create($records);
        if ($response && isset($response[0]) && $response[0]->success) {
            Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$response[0]->id."]");
            return $response[0]->id;
        }
        return false;
    }

    /**
     * Update account info into salesforce
     *
     * @param Volunteer $volunteer
     */
    protected function _updateAccount($account, $accountId) {
        Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$accountId."]");
        $records            = array();
        $records[0]         = new SObject();
        $records[0]->fields = array(
            'Id'   => $accountId,
            'Name' => $account->name
        );
        $records[0]->type = 'Account';

        $response = $this->_client->update($records);
        if ($response && isset($response[0]) && $response[0]->success) {
            return true;
        }
        return $response;
    }

    /**
     * Create the account into salesforce to setup the new volunteer
     *
     * @param Volunteer $volunteer
     */
    protected function _createOpportunity($opportunity, $accountId = '') {
        Zend_Registry::get('logger')->info(__METHOD__);
        $records            = array();
        $records[0]         = new SObject();
        $records[0]->fields = array(
            'Name'      => $opportunity->name,
            'StageName' => 'Prospecting'
        );
        if ($opportunity->endDate != '0000-00-00 00:00:00') {
            $records[0]->fields['CloseDate'] = date('Y-m-d', strtotime($opportunity->endDate));
        } else {
            $records[0]->fields['CloseDate'] = date('Y-m-d');
        }
        if ($accountId != '') {
            $records[0]->fields['AccountId'] = $accountId;
        }
        $records[0]->type = 'Opportunity';
        $response = $this->_client->create($records);
        if ($response && isset($response[0]) && $response[0]->success) {
            Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$response[0]->id."]");
            return $response[0]->id;
        }
        return false;
    }

    /**
     * Update opportunity info into salesforce
     *
     * @param Volunteer $volunteer
     */
    protected function _updateOpportunity($project, $opportunityId) {
        Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$opportunityId."]");
        $records            = array();
        $records[0]         = new SObject();
        $records[0]->fields = array(
            'Id'   => $opportunityId,
            'Name' => $project->name
        );
        if ($project->endDate != '0000-00-00 00:00:00') {
            $records[0]->fields['CloseDate'] = date('Y-m-d', strtotime($project->endDate));
        } else {
            $records[0]->fields['CloseDate'] = date('Y-m-d');
        }
        $records[0]->type = 'Opportunity';

        $response = $this->_client->update($records);
        if ($response && isset($response[0]) && $response[0]->success) {
            return true;
        }
        return $response;
    }

    /**
     * Create the OpportunityContactRole (volunteer) into salesforce
     *
     * @param Volunteer $volunteer
     * @param String    $contactId
     * @param String    $opportunityId
     */
    protected function _createOpportunityContactRole($volunteer, $contactId,
        $opportunityId
    ) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $records            = array();
        $records[0]         = new SObject();
        $records[0]->fields = array(
            'ContactId'     => $contactId,
            'OpportunityId' => $opportunityId,
        );
        $records[0]->type = 'OpportunityContactRole';

        $response = $this->_client->create($records);
        if ($response && isset($response[0]) && $response[0]->success) {
            Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$response[0]->id."]");
            return $response[0]->id;
        }
        return $response;
    }

    /**
     * Create the contact into salesforce
     *
     * @param User $user
     */
    protected function _createUserContact($user) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $records            = array();
        $records[0]         = new SObject();
        $records[0]->fields = array(
            'FirstName' => $user->firstName,
            'LastName'  => $user->lastName,
            'Email'     => $user->email,
            'BirthDate' => date('Y-m-d', strtotime($user->dateOfBirth))
        );
        $records[0]->type = 'Contact';

        $response = $this->_client->create($records);
        if ($response && isset($response[0]) && $response[0]->success) {
            Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$response[0]->id."]");
            return $response[0]->id;
        }
        return $response;
    }

    /**
     * Update the contact into salesforce
     *
     * @param User   $user
     * @param String $accountId
     */
    protected function _updateUserContact($user, $contactId, $accountId = '', $chapter = null) {
        Zend_Registry::get('logger')->info(__METHOD__."::[ID:".$contactId."]");
        $records            = array();
        $records[0]         = new SObject();
        $records[0]->fields = array(
            'Id'        => $contactId,
            'FirstName' => $user->firstName,
            'LastName'  => $user->lastName,
            'Email'     => $user->email,
            'Phone'     => $user->phone,
            'BirthDate' => date('Y-m-d', strtotime($user->dateOfBirth))
        );
        if ($accountId != '') {
            $records[0]->fields['AccountId'] = $accountId;
        }
        if ($chapter) {
            $records[0]->fields = $this->_getMemberInformation(
                $user,
                $chapter,
                $records[0]->fields
            );
        }
        $records[0]->type   = 'Contact';

        $response = $this->_client->update($records);
        if ($response && isset($response[0]) && $response[0]->success) {
            Zend_Registry::get('logger')->info(__METHOD__."::Updated::[ID:".
                               $contactId."][".print_r($records[0]->fields, true)."]");
            return true;
        }
        return $response;
    }

    protected function _getMemberInformation($user, $chapter, $fields) {
        $memberOK = $chapter->getMember($user);
        $fields['Membership__c']       = 'No';
        $fields['Membership_Title__c'] = '';
        if ($memberOK) {
            $isDonationMember = 'No';
            $isMember         = 'No';
            if ($chapter->hasMembershipFee) {
                if ($memberOK->paid && $memberOK->activateEmail && $memberOK->payment) {
                    $fields['Membership__c'] = 'Yes';
                    if ($memberOK->memberTitle) {
                        $fields['Membership_Title__c'] = $memberOK->memberTitle->title;
                    }
                }
            } else if ($memberOK->activateEmail) {
                $fields['Membership__c'] = 'Yes';
                if ($memberOK->memberTitle) {
                    $fields['Membership_Title__c'] = $memberOK->memberTitle->title;
                }
            }
        }

        return $fields;
    }
}
