<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/Groups.php';
require_once 'Brigade/Db/Table/GroupSurveyQuestions.php';
require_once 'Brigade/Db/Table/GroupSurveyFeedbacks.php';
require_once 'Brigade/Db/Table/GroupSurveyRespondents.php';


class Brigade_Db_Table_GroupSurveys extends Zend_Db_Table_Abstract {

    protected $_name = 'group_surveys';
    protected $_primary = 'SurveyId';

    public function loadInfo($SurveyId) {
        return $this->fetchRow($this->select()->where("SurveyId = ?", $SurveyId))->toArray();
    }

    public function AddSurvey($data) {
        $data['CreatedOn'] = date('Y-m-d H:i:s');
        $data['CreatedBy'] = $_SESSION['UserId'];
        return $this->insert($data);
    }

    public function EditSurvey($SurveyId, $data) {
        $data['ModifiedOn'] = date('Y-m-d H:i:s');
        $data['ModifiedBy'] = $_SESSION['UserId'];
        $where = $this->getAdapter()->quoteInto("SurveyId = ?", $SurveyId);
        $this->update($data, $where);
    }

    public function getSurveys($GroupId, $detailed = false, $Type = NULL) {
		try {
			$Groups = new Brigade_Db_Table_Groups();
			$networkInfo = $Groups->loadProgOrg($GroupId);
			if ($detailed) {
				$surveys = array();
				$SurveyFeedbacks = new Brigade_Db_Table_GroupSurveyFeedbacks();
				$SurveyQuestions = new Brigade_Db_Table_GroupSurveyQuestions();
				$SurveyRespondents = new Brigade_Db_Table_GroupSurveyRespondents();
				$rows = $this->fetchAll($this->select()->where("GroupId = '$GroupId'")->where("isDeleted = 0"))->toArray();
				foreach($rows as $row) {
					$row['feedbacks'] = $SurveyFeedbacks->getSurveyFeedbacks($row['SurveyId']);
					$row['questions'] = $SurveyQuestions->getSurveyQuestions($row['SurveyId']);
					$row['respondents'] = $SurveyRespondents->getRespondents($row['SurveyId']);
					$surveys[] = $row;
				}
				return $surveys;
			} else {
				$select = $this->select()->where("GroupId = '$GroupId'")->where("isDeleted = 0");
				if (!empty($Type)) {
					$select = $select->where("Type = ?", $Type)->limit(1);
				}
				$rows = $this->fetchAll($select);
				if($rows) {
					return $rows->toArray();
				}
			}
		} catch (Zend_Db_Adapter_Exception $zdae) {
            throw $zdae;
        } catch (Zend_Db_Exception $e) {
            throw $e;
        }
    }

    public function getSurveysByNetwork($NetworkId, $detailed = false) {
        $where = "GroupId IN (SELECT g.GroupId FROM groups g WHERE g.NetworkId='$NetworkId')";
        $rows = $this->fetchAll($this->select()->where($where)->where("isDeleted = 0")->setIntegrityCheck(false))->toArray();
        $rows1 = $this->fetchAll($this->select()->where("GroupId = ?", $NetworkId)->where("isDeleted = 0")->group("Title")->setIntegrityCheck(false))->toArray();
        foreach($rows1 as $row) {
            $rows[] = $row;
        }
        if ($detailed) {
            $surveys = array();
            $SurveyFeedbacks = new Brigade_Db_Table_GroupSurveyFeedbacks();
            $SurveyQuestions = new Brigade_Db_Table_GroupSurveyQuestions();
            $SurveyRespondents = new Brigade_Db_Table_GroupSurveyRespondents();
            foreach($rows as $row) {
                $row['feedbacks'] = $SurveyFeedbacks->getSurveyFeedbacks($row['SurveyId']);
                $row['questions'] = $SurveyQuestions->getSurveyQuestions($row['SurveyId']);
                $row['respondents'] = $SurveyRespondents->getRespondents($row['SurveyId']);
                $surveys[] = $row;
            }
            return $surveys;
        } else {
            return $this->fetchAll($select)->toArray();
        }
    }

    public function getSurveyByActivity($ProjectId) {
        $row = $this->fetchRow($this->select()->where("ProjectId = ?", $ProjectId)->where("isDeleted = 0"));
        return $row ? $row->toArray() : NULL;
    }

    public function getSurveyReport($SurveyId) {
        return $this->fetchAll($this->select()
            ->from(array('f' => 'group_survey_feedbacks'), array('s.Title', 'u.FullName as CompletedBy', 'q.Question', 'f.Answer', 'date_format(f.AnsweredOn, "%m/%d/%Y") as AnsweredOn'))
            ->joinInner(array('u' => 'users'), 'f.AnsweredBy=u.UserId')
            ->joinInner(array('q' => 'group_survey_questions'), 'f.SurveyQuestionId=q.SurveyQuestionId')
            ->joinInner(array('s' => 'group_surveys'), 'q.SurveyId=s.SurveyId')
            ->where("s.SurveyId = ?", $SurveyId)
            ->order(array('u.FullName', 'q.SurveyQuestionId'))
            ->setIntegrityCheck(false))->toArray();
    }

    public function deleteSurvey($SurveyId) {
        $where = $this->getAdapter()->quoteInto("SurveyId = ?", $SurveyId);
        $this->update(array('isDeleted' => 1), $where);
    }
    
    public function getCompletedSurveys($UserId) {
        return $this->fetchAll($this->select()
            ->from(array('f' => 'group_survey_feedbacks'), array('s.Title', 's.SurveyId'))
            ->joinInner(array('q' => 'group_survey_questions'), 'f.SurveyQuestionId=q.SurveyQuestionId')
            ->joinInner(array('s' => 'group_surveys'), 'q.SurveyId=s.SurveyId')
            ->where("f.AnsweredBy = ?", $UserId)
            ->group(array("s.Title", "s.SurveyId"))
            ->order(array('s.Title'))
            ->setIntegrityCheck(false))->toArray();
    }

}
?>
