<?php

require_once 'Zend/Db/Table/Abstract.php';
require_once 'Zend/Db/Adapter/Exception.php';
require_once 'Zend/Db/Exception.php';
require_once 'Brigade/Db/Table/GroupSurveyFeedbacks.php';

class Brigade_Db_Table_GroupSurveyFeedbacks extends Zend_Db_Table_Abstract {

    protected $_name = 'group_survey_feedbacks';
    protected $_primary = 'SurveyFeedbackId';

    public function AddSurveyFeedback($data) {
        $data['AnsweredBy'] = $_SESSION['UserId'];
        $data['AnsweredOn'] = date('Y-m-d H:i:s');
        $this->insert($data);
    }

    public function EditSurveyFeedback($SurveyFeedbackId, $data) {
        $where = $this->getAdapter()->quoteInto("SurveyFeedbackId = ?", $SurveyFeedbackId);
        $this->update($data, $where);
    }

    public function getSurveyFeedbacks($SurveyId, $detailed = false, $UserId = NULL) {
        if ($detailed) {
            $select = $this->select()
                ->from(array('f' => 'group_survey_feedbacks'), array('u.FullName', 'u.Email', 'u.UserId', 'q.Question', 'f.Answer', 's.Title', 'f.SurveyFeedbackId'))
                ->joinInner(array('u' => 'users'), 'f.AnsweredBy=u.UserId')
                ->joinInner(array('q' => 'group_survey_questions'), 'f.SurveyQuestionId=q.SurveyQuestionId')
                ->joinInner(array('s' => 'group_surveys'), 'q.SurveyId=s.SurveyId')
                ->group(array('u.FullName', 'u.Email', 'q.SurveyQuestionId'))
                ->where("s.SurveyId = ?", $SurveyId);
            if (!empty($UserId)) {
                $select = $select->where("u.UserId = ?", $UserId);
            }
            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        } else {
            $select = $this->select()
                ->from(array('f' => 'group_survey_feedbacks'), array('u.FullName', 'u.Email', 'u.UserId', 'q.Question', 'f.Answer', 's.Title'))
                ->joinInner(array('u' => 'users'), 'f.AnsweredBy=u.UserId')
                ->joinInner(array('q' => 'group_survey_questions'), 'f.SurveyQuestionId=q.SurveyQuestionId')
                ->joinInner(array('s' => 'group_surveys'), 'q.SurveyId=s.SurveyId')
                ->where("s.SurveyId = ?", $SurveyId)
                ->group(array('u.FullName', 'u.Email'));
            if (!empty($UserId)) {
                $select = $select->where("u.UserId = ?", $UserId);
            }
            $rows = $this->fetchAll($select->setIntegrityCheck(false))->toArray();
        }
        return $rows;
    }

}
?>
