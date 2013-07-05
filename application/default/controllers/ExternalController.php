<?php

/**
 * External controller for other sites using empowered.
 * Now: Fly for good.
 *      BluePay Refunds
 *      Membership Rebill + Donation to project + Payment History
 *      Membership Funds
 *
 * @author  Matias Gonzalez
 * @version
 */

require_once 'BluePay/BluePayment.php';
require_once 'BaseController.php';
require_once 'FlyForGood.php';
require_once 'Payment.php';
require_once 'MembershipFrequency.php';
require_once 'MembershipFund.php';

class ExternalController extends BaseController {

    CONST ffgKey = '00fthflykk12for954good2#za';
    CONST ffgKeyReturn = '0resp0fthflykk12for954good182#za';

    /**
     * Return login form to validate user.
     *
     * @return Zend_Form form
     */
    private function _getLoginForm($from = '') {
        $_elementDecorators = array(
            'ViewHelper',
            'Errors',
            array('Label'),
            array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'left'))
        );

        $_chkboxDecorators = array(
            'ViewHelper',
            array('Label', array('placement' => 'append')),
            array(array('row' => 'HtmlTag'), array('tag' => 'div', 'class' => 'left')),
        );

        $_buttonDecorators = array(
            'ViewHelper',
            array(array('row' => 'HtmlTag'), array('tag' => 'div'))
        );

        $_hiddenElementDecorator = array(
            'ViewHelper',
        );

        $form = new Zend_Form('login');
        $form->setAction('/profile/dologin')
            ->setMethod('post')
            ->setAttrib('id', 'login')
            ->setName('login')
            ->setDecorators(array(
            'FormElements',
            array('HtmlTag', array('tag' => 'div', 'class' => '')),
            'Form',
        ));


        $username = new Zend_Form_Element_Text('login001', array(
            'label'      => 'Email:',
            'decorators' => $_elementDecorators,
            'class'      => 'textfield'
        ));
        $username->removeDecorator('Errors');

        $password = new Zend_Form_Element_Password('pwd001', array(
            'label'      => 'Password:',
            'decorators' => $_elementDecorators,
            'class'      => 'textfield'
        ));
        $password->removeDecorator('Errors');


        $remember = new Zend_Form_Element_Checkbox('remember', array(
            'label'      => 'Remember me?',
            'decorators' => $_chkboxDecorators,
            'class'      => 'ppcheck'
        ));

        $postfrom = new Zend_Form_Element_Hidden('redirect', 'formtype');
        $postfrom->setValue($from);
        $postfrom->removeDecorator('HtmlTag');
        $postfrom->removeDecorator('Label');

        $submit = new Zend_Form_Element_Submit('login', array(
            'label'      => 'Login',
            'decorators' => $_buttonDecorators,
            'class'      => 'btn'
        ));


        $form->addElements(array($username, $password, $remember, $postfrom, $submit));

        return $form;
    }

    /**
     * Validate security keys.
     *
     * @param $key      get from external site
     * @param $validate All values in sequence to generate the same security key
     *
     * @return bool
     */
    private function _validateSecurityCode($key, $validate) {
        $compareKey = '';
        foreach($validate as $value) {
            $compareKey .= $value;
        }

        return ($key == md5($compareKey));
    }

    private function _validateAmount() {
        $external   = new Zend_Session_Namespace('External');
        $raisedCall = "raised_{$external->organizationId}";
        $raised     = $this->view->userNew->$raisedCall;
        $org        = Organization::get($external->organizationId);
        $isOk       = false;
        $userSpent  = FlyForGood::getUserSpent($this->view->userNew, $org);
        $ticketAmnt = $external->ffg->params['amnt'] + $external->ffg->params['fee'];
        if (($userSpent + $ticketAmnt) <= ($raised+0)) {
            $isOk = true;
        }
        $this->view->raised    = $raised;
        $this->view->available = $raised - $userSpent;
        return $isOk;
    }

    private function _clearSessionData() {
        //clear session params
        $external      = new Zend_Session_Namespace('External');
        $external->ffg = null;
    }

    public function init() {
        parent::init();
    }

    /**
     * Fly for good request validation user raised to buy ticket
     *
     * Params:
     *  - url_rep: Url Reply/url to forward the user after amount review.
     *  - amnt: Amount of ticket.
     *  - fee: Fee charge
     *  - curr: Currency of the ticket.
     *  - idffg: Internal id of fly for good.
     *  - key: Security hash (idffg | amnt | fee | curr | ffgKey)
     *  - txt: [optional] Description of the ticket.
     *
     * @return void
     */
    public function ffgAction() {
        $params = $this->_getAllParams();

        //Session Handler
        $external = new Zend_Session_Namespace('External');
        if (!isset($external->ffg) && empty($external->ffg->params)) {
            // validate all params
            if (!isset($params['url_rep']) || !isset($params['amnt']) ||
                !isset($params['fee']) || !isset($params['curr']) ||
                !isset($params['idffg']) || !isset($params['key'])
            ) {
                $this->_helper->redirector('badparams', 'error');
            }
            $external->ffg->params = $params;
        } else {
            if (isset($_POST['key'])) {
                // validate all params
                if (!isset($params['url_rep']) || !isset($params['amnt']) ||
                    !isset($params['fee']) || !isset($params['curr']) ||
                    !isset($params['idffg']) || !isset($params['key'])
                ) {
                    $this->_helper->redirector('badparams', 'error');
                }
                $external->ffg->params = $params;
            } else {
                $params = $external->ffg->params;
            }
        }

        //validate security code
        $valid = $this->_validateSecurityCode($params['key'], array(
            $params['idffg'],
            $params['amnt'],
            $params['fee'],
            $params['curr'],
            self::ffgKey
        ));

        // security code invalid
        if (!$valid) {
            $this->_helper->redirector('badparams', 'error');
        }

        if (!$this->_helper->authUser->isLoggedIn()) {
            $this->view->loginForm = $this->_getLoginForm('/external/ffg');
            if (!empty($_SESSION['errorLogin'])) {
                $this->view->errorLogin = true;
            }
        }
        $this->view->amount      = number_format($params['amnt'], 2);
        $this->view->fee         = number_format($params['fee'], 2);
        $this->view->totalAmnt   = $params['amnt'] + $params['fee'];
        $this->view->currency    = $params['curr'];
        $this->view->description = '';
        if (isset($params['txt'])) {
            $this->view->description = $params['txt'];
        }

        $this->view->render('common/header_small.phtml');
        $this->view->render('nonprofit/footer.phtml');

        $this->_helper->layout->setLayout('newlayout');
    }

    /**
     * Fly for good ajax request validation amount
     *
     * Params:
     *  - organizationId Selected organization id.
     *
     * @return void
     */
    public function ffgvalidateAction() {
        $this->_helper->layout()->disableLayout();

        if (!isset($this->view->userNew->id)) {
            die('login');
        }

        $params   = $this->_getAllParams();
        $external = new Zend_Session_Namespace('External');
        $org      = Organization::get($params['organizationId']);

        $external->organizationId = $org->id;

        $this->view->status   = 'failure';
        $this->view->urlFFG   = $external->ffg->params['url_rep'];
        $this->view->idffg    = $external->ffg->params['idffg'];
        $this->view->currency = $external->ffg->params['curr'];
        $this->view->isOk     = false;
        if ($this->_validateAmount()) {
            $this->view->isOk   = true;
            $this->view->status = 'success';
        }
        $this->view->organization = $org;
        $this->view->key          = md5(
            $external->ffg->params['idffg'] .
            $this->view->status .
            self::ffgKey
        );
    }

    /**
     * Fly for good ajax request enter local payment
     *
     * @return void
     */
    public function ffgpayAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if (!isset($this->view->userNew->id)) {
            die('login');
        }

        $params   = $this->_getAllParams();
        $external = new Zend_Session_Namespace('External');

        $this->view->status = 'failure';
        if ($this->_validateAmount()) {
            $this->view->status = 'success';

            $ffg                 = new FlyForGood();
            $ffg->userId         = $this->view->userNew->id;
            $ffg->organizationId = $external->organizationId;
            $ffg->amount         = $external->ffg->params['amnt'];
            $ffg->fee            = $external->ffg->params['fee'];
            $ffg->currency       = $external->ffg->params['curr'];
            $ffg->flyForGoodId   = $external->ffg->params['idffg'];
            $ffg->createdOn      = date('Y-m-d H:i:s');
            if (isset($external->ffg->params['txt'])) {
                $ffg->description = $external->ffg->params['txt'];
            }
            $ffg->save();
        }
        $data = array(
            'key' => md5(
                $external->ffg->params['idffg'] .
                $this->view->status .
                self::ffgKeyReturn
            ),
            'status' => $this->view->status
        );

        //clear session params
        $this->_clearSessionData();

        echo json_encode($data);
    }

    /**
     * Fly for good ajax request cancel.
     * This method is to get the reply for FFG with the secret response key
     *
     * @return void
     */
    public function ffgcancelAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        if (!isset($this->view->userNew->id)) {
            die('login');
        }

        $params   = $this->_getAllParams();
        $external = new Zend_Session_Namespace('External');

        $data = array(
            'key' => md5(
                $external->ffg->params['idffg'] .
                'failure' .
                self::ffgKeyReturn
            ),
            'status' => 'failure'
        );

        //clear session params
        $this->_clearSessionData();

        echo json_encode($data);
    }

    /**
     * Ajax call. Request Organization Nomination for fly for good integration.
     * Send email to steve to add a new org with ffg.
     *
     * @return void
     */
    public function ffgorgnominationAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        $params   = $this->_getAllParams();

        $message  = "New organization request - Fly for Good.<br />";
        $message .= "Organization Name: ".$params['name'] ."<br />
        Contact Email: ".$params['email']."<br />
        Phone Number: ".$params['phone']."<br />
        --<br />
        Empowered.org";

        Zend_Registry::get('eventDispatcher')->dispatchEvent(
            EventDispatcher::$ORGANIZATION_NOMINATION,
            array(
                $message,
                $params['email']
            )
        );
    }

    /**
     * Capture notifications from bluepay payment gateway.
     * @TODO: add security validation
     *
     * TAMPER_PROOF_SEAL:
     * md5(SECRET KEY + ACCOUNT_ID + TRANS_TYPE + AMOUNT + MASTER_ID + NAME1 + PAYMENT_ACCOUNT)
     *
     * doc: https://secure.assurebuy.com/BluePay/BluePay_Trans_Notify_Post/trans_notify.txt
     */
    public function bpnotifyAction() {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender();
        $params = $this->_getAllParams();
        Zend_Registry::get('logger')->info(
            'BluePay::Notify - Params' . print_r($params, true)
        );

        if (!empty($params['order_id'])) {
            //Supporter
            if (strpos($params['order_id'], 'SUP') === 0) {
                $id   = substr($params['order_id'], 3, strlen($params['order_id']));
                $supp = Supporter::get($id);
                $this->_bpHandleSupporter($supp);
            } else {
                //Donation
                $donation = Donation::get($params['order_id']);
                if ($donation) {
                    $this->_bpHandleDonation($donation);
                } else {
                    //Membership
                    $member = Member::get($params['order_id']);
                    if ($member) {
                        $this->_bpHandleMembership($member);
                    }
                }
            }
        }
    }

    /**
     * Manage blue pay - donations
     * Now refunds or cancel donations.
     *
     * @param Donation $donation
     */
    protected function _bpHandleDonation($donation) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $params = $this->_getAllParams();

        //for refunds or cancels
        if ($params['trans_type'] == 'VOID' || $params['trans_type'] == 'REFUND' ||
            $params['trans_status'] == BluePayment::STATUS_ERROR ||
            $params['trans_status'] == BluePayment::STATUS_DECLINE
        ) {
            //check fee payment to cancell all donation
            $paymentAmount = $donation->amount;
            if ($donation->paidFees) {
                $paymentAmount = $donation->amount * (1 + ($donation->project->percentageFee/100));
            }

            Zend_Registry::get('logger')->info(
                'BluePay::_bpHandleDonation: ('.$params['trans_id'].') ea$ '.sprintf("%01.2f", $paymentAmount).
                '|bpa$ '.sprintf("%01.2f", $params['amount'])
            );

            if (sprintf("%01.2f", $paymentAmount) == sprintf("%01.2f", $params['amount'])) {
                //complete refund or error echeck
                //we cancell the complete donation
                $donation->orderStatusId = Payment::DECLINED;
                if ($params['trans_type'] == 'REFUND') {
                    $donation->comments = 'Donation refunded from bluepay.';
                    $mailData           = array($donation, 'refund');
                    Zend_Registry::get('logger')->info(
                        'BluePay::Refund - Project: '.$donation->projectId
                    );
                } else {
                    $donation->comments = 'Donation declined from bluepay';
                    $mailData           = array($donation, 'declined');
                    Zend_Registry::get('logger')->info(
                        'BluePay::Declined - Project: '.$donation->projectId
                    );
                }
                $donation->transactionId = $params['trans_id'];
                $donation->save();
            } else {
                //partial refund
                //we create a new manual donation with negative number
                $refund                    = new Donation();
                $refund->projectId         = $donation->projectId;
                $refund->userId            = $donation->userId;
                $refund->donorUserId       = $donation->donorUserId;
                $refund->programId         = $donation->programId;
                $refund->groupId           = $donation->groupId;
                $refund->organizationId    = $donation->organizationId;
                $refund->transactionSource = $donation->transactionSource;
                $refund->amount            = -1 * $params['amount'];
                $refund->transactionId     = $params['trans_id'];
                $refund->comments          = 'Donation partial refunded from bluepay.';
                $refund->isReceiptSent     = 1;
                $refund->isAnonymous       = 0;
                $refund->paidFees          = $donation->paidFees;
                $refund->createdOn         = date('Y-m-d H:i:s');
                if ($params['trans_status'] == BluePayment::STATUS_ERROR ||
                    $params['trans_status'] == BluePayment::STATUS_DECLINE
                ) {
                    $refund->orderStatusId = Payment::DECLINED;
                } else {
                    $refund->orderStatusId = Payment::PROCESSED;
                }
                $refund->save();

                Zend_Registry::get('logger')->info(
                    'BluePay::PartialRefund - Project: '.$donation->projectId
                );
                $mailData = array($donation, 'partial_refund', $refund);
            }
            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                EventDispatcher::$PROJECT_DONATION_UPDATED,
                $mailData
            );
        } else if ($params['trans_status'] == BluePayment::STATUS_APPROVED &&
                   $donation->orderStatusId == Payment::DECLINED
        ) {
            $donation->orderStatusId = Payment::PROCESSED;
            $donation->transactionId = $params['trans_id'];
            $donation->comments      = '';
            $donation->save();

            $mailData = array($donation, 'approved');
            Zend_Registry::get('eventDispatcher')->dispatchEvent(
                EventDispatcher::$PROJECT_DONATION_UPDATED,
                $mailData
            );
        }
    }

    /**
     * Manage blue pay - membership
     * Now for rebill.
     *
     * @param Member $member
     */
    protected function _bpHandleMembership($member) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $params = $this->_getAllParams();
        if ($params['trans_type'] != 'SALE') {
            return;
        }
        if (empty($member->frequencyId)) {
            $member->frequencyId = $this->_fixGroupMemberFrequency($member);
        }
        $member->paid          = true;
        $member->activateEmail = true;
        if ($params['fancy_status'] == BluePay::DECLINED ||
            $params['fancy_status'] == BluePay::ERROR
        ) {
            $member->paid          = false;
            $member->activateEmail = false;
        }
        $member->paidUntil = $member->frequency->paidUntil;
        $member->save();

        $this->_savePaymentMembership($member);
    }

    /**
     * Create history of membership payment
     *
     * @param Member $member
     *
     * @return Payment
     */
    protected function _savePaymentMembership($member) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $params           = $this->_getAllParams();
        if ($params['origin'] == 'bp20post') {
            Zend_Registry::get('logger')->info("Membership::[Post] Already done.");
            return;
        }
        if (Payment::getByTransactionId($params['trans_id'])) {
            Zend_Registry::get('logger')->info("Membership::Skip new payment. Already done.");
            return;
        }
        Zend_Registry::get('logger')->info(
            "Membership::REBILL - [Member:".$member->id."][TransId: ".$params['trans_id']."]"
        );
        $payment          = new Payment();
        $payment->groupId = $member->group->id;
        if (!empty($member->group->programId)) {
            $payment->programId = $member->group->programId;
        }
        if (!empty($member->group->organizationId)) {
            $payment->organizationId = $member->group->organizationId;
        }
        $payment->userId            = $member->userId;
        $payment->amount            = (isset($params['amount'])) ? $params['amount'] : $member->frequency->amount;
        $payment->createdById       = $member->userId;
        $payment->orderStatusId     = Payment::PROCESSED;
        $payment->transactionSource = Payment::BLUEPAY;
        $payment->transactionId     = $params['trans_id'];
        $payment->createdOn         = date('Y-m-d');
        $payment->paidUntil         = $member->paidUntil;
        if ($params['fancy_status'] == BluePay::DECLINED ||
            $params['fancy_status'] == BluePay::ERROR
        ) {
            $payment->orderStatusId = Payment::DECLINED;
        } else {
            $raisedProject = MembershipFund::getByGroup($member->group);
            if ($raisedProject) {
                $raisedProject->amount += $payment->amount;
                $raisedProject->save();
            }
        }
        $payment->save();

        return $payment;
    }

    /**
     * Manage blue pay - supporter
     * Now for rebill.
     *
     * @param Supporter $supporter
     */
    protected function _bpHandleSupporter($supporter) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $params = $this->_getAllParams();
        if ($params['trans_type'] != 'SALE') {
            return;
        }
        $supporter->paid = true;
        if ($params['fancy_status'] == BluePay::DECLINED) {
            $supporter->paid = false;
        }
        $supporter->paidUntil = $supporter->frequency->paidUntil;

        $this->_savePaymentSupporter($supporter);
    }

    /**
     * Create history of supporter payment
     *
     * @param Supporter $supporter
     *
     * @return Payment
     */
    protected function _savePaymentSupporter($supporter) {
        Zend_Registry::get('logger')->info(__METHOD__);
        $params = $this->_getAllParams();
        if ($params['origin'] == 'bp20post') {
            Zend_Registry::get('logger')->info("Supporter::[Post] Already done.");
            return;
        }
        if (Payment::getByTransactionId($params['trans_id'])) {
            Zend_Registry::get('logger')->info("Supporter::Skip new payment. Already done.");
            return;
        }
        Zend_Registry::get('logger')->info(
            "Supporter::REBILL - [Supporter:".$supporter->id."][TransId: ".
            $params['trans_id']."]"
        );
        $supporter->lastTransactionId = $params['trans_id'];
        $supporter->save();

        $payment            = new Payment();
        $payment->programId = $supporter->programId;
        if (!empty($supporter->organizationId)) {
            $payment->organizationId = $supporter->organizationId;
        }
        $payment->userId            = $supporter->userId;
        $payment->amount            = $supporter->frequency->amount;
        $payment->createdById       = $supporter->userId;
        $payment->orderStatusId     = Payment::PROCESSED;
        $payment->transactionSource = Payment::BLUEPAY;
        $payment->transactionId     = $params['trans_id'];
        $payment->createdOn         = date('Y-m-d');
        $payment->paidUntil         = $supporter->paidUntil;
        if ($params['fancy_status'] == BluePay::DECLINED) {
            $payment->orderStatusId = Payment::DECLINED;
        }
        $payment->type = 'Supporter';
        $payment->save();

        return $payment;
    }

    /**
     * Update missing information for groupmember table (the freq id field)
     *
     * @param Member
     */
    protected function _fixGroupMemberFrequency($member) {
        $error = false;
        if ($member->payment) {
            if (count($member->group->membershipDonationAmounts) > 0) {
                foreach ($member->group->membershipDonationAmounts as $freq) {
                    if ($freq->amount == $member->payment->amount) {
                        $mailer = new Zend_Mail('utf-8');
                        $mailer->addTo('matias@empowered.org');
                        $mailer->setSubject("FreqId updated");
                        $mailer->setBodyHtml("Member:".$member->id." Updated.");
                        $mailer->setFrom("Empowered.org <admin@empowered.org>");
                        $mailer->send();

                        return $freq->id;
                    }
                }
            } else {
                $error = true;
            }
        }

        if ($error) {
            $mailer = new Zend_Mail('utf-8');
            $mailer->addTo('matias@empowered.org');
            $mailer->setSubject("Error FreqId");
            $mailer->setBodyHtml("Error with member:".$member->id);
            $mailer->setFrom("Empowered.org <admin@empowered.org>");
            $mailer->send();
        }
        return 4;
    }
}
