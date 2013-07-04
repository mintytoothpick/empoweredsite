<?php

require_once 'Zend/Mail.php';
require_once 'Zend/Mail/Transport/Smtp.php';
require_once 'Zend/Mail/Transport/Sendmail.php';
require_once 'Zend/Registry.php';

class Mailer {

    protected $templatesDir = '';

    public function __construct($templatesDir = 'templates') {
        $this->templatesDir = $templatesDir;

        $mailConfig = Zend_Registry::get('configuration')->mail;
        if ($mailConfig->smtp) {
            $transport = new Zend_Mail_Transport_Smtp($mailConfig->host, $mailConfig->smtpconfig->toArray());
        } else {
            $transport = new Zend_Mail_Transport_Sendmail();
        }

        Zend_Mail::setDefaultTransport($transport);
    }

    public function sendRegistrationMail($Email, $FirstName, $activationlink) {
        $templateTxt = "Hey $FirstName,<br /><br />
            Congratulations! You're almost ready to mobilize and start changing the world at Empowered.org.<br /><br />
        Click the following link to activate your Empowered.org account. You will need to follow this link before you are able to log in to the site: $activationlink<br /><br />
        Questions? Comments? Suggestions? Contact us at admin@empowered.org.<br /><br />
        We're here to help you create positive social change!<br /><br />
        Regards,<br />The Empowered.org Team<br/ ><a href='http://www.empowered.org/?utm_campaign=Registration&utm_medium=Email&utm_source=UserAction'>http://www.empowered.org/</a><br />http://www.facebook.com/EmpoweredOrg/<br />http://www.twitter.com/EmpoweredOrg";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject('Just one more step to get started on Empowered.org');
        $mailer->setBodyHtml(stripslashes($templateTxt), 'utf8');
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    public function sendForgotPasswordMail($Email, $FirstName, $LastName, $Password) {
        $templateTxt = "Hey ".stripslashes(trim($FirstName)).",<br /><br />
            Here is a reminder about your login information on Empowered.org:<br />
            Email: $Email<br />
            Password: ".stripslashes($Password)."<br /><br />
            Need anything else? Suggestions on ways we can better help you change the world? We always love to hear from you at admin@empowered.org.<br /><br />
            Enjoy using Empowered.org and make a difference today!<br /><br />
            Regards,<br />
            The Empowered.org Team<br />
            <a href='http://www.empowered.org/?utm_campaign=forgotPW&utm_medium=email&utm_source=userAction'>http://www.empowered.org/</a><br />http://www.facebook.com/EmpoweredOrg/<br />http://www.twitter.com/EmpoweredOrg";

        $mailer = new Zend_Mail('utf-8');

        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject('Login Information');
        $mailer->setBodyHtml(stripslashes($templateTxt), 'utf8');
        $mailer->setFrom("Empowered.org <admin@empowred.org>");
        $mailer->send();
    }

    // REACTIVATE_USER
    public function sendReactivateUserMail($fullName, $email, $password, $id) {

        $hashReactivate = sha1($password.'-reactivate-'.$email);

        $txt = "Hey ".stripslashes(trim($fullName)).",<br /><br />
            To reactivate your user on Empowered.org, click the following link:<br />
            http://".$_SERVER['HTTP_HOST']."/profile/reactivateuser?i={$id}&h={$hashReactivate}<br /><br />
            Need anything else? Suggestions on ways we can better help you change the world? We always love to hear from you at admin@empowered.org.<br /><br />
            Enjoy using Empowered.org and make a difference today!<br /><br />
            Regards,<br />
            The Empowered.org Team";
        $mailer = new Zend_Mail('utf-8');

        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject('Login Information');
        $mailer->setBodyHtml(stripslashes($txt), 'utf8');
        $mailer->setFrom("Empowered.org <admin@empowred.org>");
        $mailer->send();
    }

    /**
     * VOLUNTEER_REQUEST
     */
    public function sendProjectVolunteerRequest($Email, $VolunteerName,
        $VolunteerEmail, $ProjectName, $GroupURL
    ) {
        $txt  = "Great news from Empowered! ".stripslashes($VolunteerName);
        $txt .= " wants to be a part of your social movement.<br /><br />";
        $txt .= "Welcome him or her to $ProjectName and team up to change the";
        $txt .= " world together! Simply log in and go to your chapter page to";
        $txt .= " accept them: http://www.empowered.org/$GroupURL.<br /><br />";
        $txt .= "The more people we have, the more of a change we can create - ";
        $txt .= "and the better the world can become! Make it happen today!<br />";
        $txt .= "<br />Regards,<br />The Empowered.org Team<br />";
        $txt .= "<a href='http://www.empowered.org/?utm_campaign=forgotPW&utm_medium=email&utm_source=userAction'>";
        $txt .= "http://www.empowered.org/</a><br />http://www.facebook.com/EmpoweredOrg/";
        $txt .= "<br />http://www.twitter.com/EmpoweredOrg";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("$ProjectName Volunteer Request");
        $mailer->setBodyHtml(stripslashes($txt), 'utf8');
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    /**
     * AWAITING_REQUEST
     * Send email to volunteer explaining for actual status waiting to be accepted
     */
    public function sendAwaitingProjectAcceptance($Email, $ProjectName, $FirstName, $Contact) {
    $templateTxt = "Dear ".stripslashes($FirstName).",<br /><br />
        Thank you for your interest in joining ".stripslashes($ProjectName).". Your request to volunteer for this activity has been submitted and is awaiting approval. The chapter administrator will review your request shortly. If you have any questions about this, please contact the chapter at <a href='mailto:$Contact'>$Contact</a><br /><br />Regards,<br />The Empowered.org Team<br /><a href='http://www.empowered.org/?utm_campaign=projectSignUp&utm_medium=email&utm_source=userAction'>http://www.empowered.org/</a><br />http://www.facebook.com/EmpoweredOrg/<br />http://www.twitter.com/EmpoweredOrg";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Awaiting Approval for $ProjectName");
        $mailer->setBodyHtml(stripslashes($templateTxt), 'utf-8');
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    // VOLUNTEER_ACCEPTED
    public function sendProjectVolunteerAccepted($Email, $FirstName, $ProjectName, $Contact, $include) {
        if($include) {
            $packet_path = realpath(dirname(__FILE__) . '/../../../').'/public/docs/StudentIntroductionPacket.pdf';
        }
        $templateTxt = "Hey ".stripslashes($FirstName)."!<br /><br />
            Welcome to ".stripslashes($ProjectName)." on Empowered.org. You're ready to get started!<br /><br />
            Start customizing your own fundraising page, add your passions, pictures and points of interest to share your efforts with families and friends today. Facebook, Twitter or email, choose any way you like to spread the word and change the world!<br /><br />
            Sign into your account <a href='http://www.empowered.org/profile/login/'>here</a> to get started.<br /><br />
            If you need any further assistance, please contact us at admin@empowered.org";
            if($Contact != "") { $templateTxt .= " or the chapter admin at $Contact"; }
            $templateTxt .= ".<br /><br />
            Thanks and enjoy!<br /><br />
            Regards,<br />The Empowered.org Team<br /><br /><a href='http://www.empowered.org/?utm_campaign=projectSignUp&utm_medium=email&utm_source=userAction'>http://www.empowered.org/</a><br />http://www.facebook.com/EmpoweredOrg/<br />http://www.twitter.com/EmpoweredOrg";
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Welcome to $ProjectName");
        $mailer->setBodyHtml(stripslashes($templateTxt), 'utf-8');
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        if($include) {
            $Attachment = $mailer->createAttachment(file_get_contents($packet_path));
            $Attachment->filename = "StudentIntroductionPacket.pdf";
        }
        $mailer->send();
    }

    //DONATION_RECEIPT
    public function sendDonationReceipt($Email, $Message, $skipValidation = false){
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org' || $skipValidation) {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->SetSubject('Donation Receipt - Thanks for changing the world!');
        $mailer->setBodyHtml(stripslashes($Message), 'utf-8');
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    public function sendAnnouncement($Subject, $To, $Message) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($To);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject($Subject);
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    public function sendPostVolunteerActivity($Subject, $To, $Message, $Attachment1, $Attachment2) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($To);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject($Subject);
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $Attachment = $mailer->createAttachment($Attachment1[1]);
        $filename = explode('.',$Attachment1[0]);
        $Attachment->filename = "Volunteer Opportunity Logo.".$filename[count($filename)-1];
        if (!empty($Attachment2)) {
            $Attachment = $mailer->createAttachment($Attachment2[1]);
            $filename = explode('.',$Attachment2[0]);
            $Attachment->filename = "Chapter Logo.".$filename[count($filename)-1];
        }
        $mailer->send();
    }

    public function sendPostGroupNotification($Subject, $Message, $To = "iamjackross@gmail.com") {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($To);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject($Subject);
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    public function sendNonProfitSignUpNotification($Message, $From) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo("Empowered.org <nonprofits@empowered.org>");
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("New Non-Profit Request");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($From);
        $mailer->send();
    }

    public function sendGroupInvites($Email, $Name, $GroupName, $Link, $recipient = "new user") {
        if ($recipient == "new user") {
            $Message = "
                Hi,<br /><br />
                $Name has invited you to join the chapter $GroupName on <a href='empowered.org'>Empowered.org</a>. Click <a href='$Link'>here</a> to join.<br /><br />
                Regards,<br />The Empowered.org Team
            ";
        } else {
            $Message = "
                Hi,<br /><br />
                $GroupName has invited you to become a member. To join this chapter please follow this link: <a href='$Link'>$Link</a> <br /><br />
                Regards,<br />The Empowered.org Team
            ";
        }
        $mailer = new Zend_Mail('utf-8');
    if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
    } else {
        $mailer->addTo('empoweredqa@gmail.com');
    }
        $mailer->setSubject("$GroupName Chapter Invitation");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    public function sendGroupEmailVerification($Email, $GroupName, $Link, $From) {
        $Message = "
            Hi,<br /><br />
            To add the e-mail address $Email to $GroupName chapter follow this link: <a href='$Link'>$Link</a><br /><br />
            If you did not request this from Empowered.org, please disregard this message.<br />
            Contact admin@empowered.org if you have any questions.<br /><br />
            Thanks,<br />
            The Empowered.org Team
        ";
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Email Verification");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($From, $GroupName);
        $mailer->send();
    }

    public function sendGroupNotifications($Email, $Subject, $Message, $From, $GroupName) {

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setFrom($From, $GroupName);
        $mailer->setSubject($Subject);
        $mailer->setBodyHtml($Message);
        $mailer->send();
    }

    public function sendGroupMemberNotification($Email, $Group, $Name, $Link) {
        $login_url = "www.empowered.org/profile/login";
        $Message = "
            Hi,<br /><br />
            $Name has sent a membership request to join the $Group chapter.<br /><br />
            In order to accept/deny the membership request:<br />
                - Login <a href='$login_url'>here</a><br />
                - Visit your chapter's page then select the Members tab and click on Pending Requests.<br />
                - Select which member(s) to accept or deny<br /><br />
            Thanks,<br />
            The Empowered.org Team
        ";
        $mailer = new Zend_Mail('utf-8');
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
    if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
    } else {
        $mailer->addTo('empoweredqa@gmail.com');
    }
        $mailer->setSubject("Membership Request Notification");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->send();
    }

    public function sendPostGroupIntroNotification($GroupURL, $Email, $GroupName, $fundraising) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'dev.empowered.org') {
            $server = "dev";
        } else {
            $server = "www";
        }
        $GroupURL = "$server.empowered.org/$GroupURL";
        $Subject = "$GroupName on Empowered.org";
        $Message = "
            Congratulations! ".(isset($_SESSION['PostGroup']) ? "Your chapter on Empowered.org has been successfully created" : "You have activated fundraising on your chapter").".<br><br>
            What would you like to do next?<br><br>
                - ".(isset($_SESSION['PostGroup']) && !$fundraising ? "<a href='$GroupURL'>Go to My Chapter Page</a><br>" : ($fundraising && !isset($_SESSION['PostGroup']) ? "<a href='$GroupURL/create-fundraisingcampaign'>Start Fundraising Campaign</a><br>" : ""))."
                ".(isset($_SESSION['PostGroup']) && $fundraising ? "- <a href='$GroupURL/create-fundraisingcampaign'>Start Fundraising Campaign</a><br>" : (!isset($_SESSION['PostGroup']) && $fundraising ? "- <a href='$GroupURL'>Go to My Chapter Page</a><br>" : ""))."
                - <a href='$GroupURL/create-activity'>Start a Volunteer Opportunity</a><br><br>
            Have more questions? Need a hand with something? Let us know! We're here for you.<br><br>
            You can talk to an Empowered team member any time. Send us an email at admin@empowered.org — we're ready to give you all the help you need.<br><br>
            Thank you and enjoy Empowered.org! Let's keep changing the world together!<br><br>
            Best,<br>
            Oisin
        ";
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($Email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject($Subject);
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->send();
    }

    public function sendMailToFrom($To, $Subject, $Message, $From, $Attachments=null) {
        $mailer = new Zend_Mail('utf-8');
        $mailer->setFrom($From);
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            if (is_array($To)) {
               foreach ($To as $recipient) {
                    $mailer->addTo($recipient);
               }
            } else {
                $mailer->addTo($To);
            }
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }

        if ($Attachments && !empty($Attachments)) {
            foreach ($Attachments as $Attachment) {
                $at = $mailer->createAttachment(file_get_contents($Attachment["filePath"]));
                $at->disposition = Zend_Mime::DISPOSITION_INLINE;
                $at->encoding = Zend_Mime::ENCODING_BASE64;
                $at->filename = $Attachment["fileName"];
            }
        }

        $mailer->setSubject($Subject);
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->send();
    }

    public function sendMail($To, $Subject, $Message, $Attachments=null) {
        $this->sendMailToFrom($To, $Subject, $Message, "Empowered.org <admin@empowered.org>", $Attachments);
    }

    public function sendRepEmail($toEmail, $toName) {
        $utc_time = time();
        if($utc_time % 10 == 1){
            $fromEmail = "Jack Ross <jack@empowered.org>";
            $fromName = "Jack";
        } else if($utc_time % 2 == 0){
            $fromEmail = "Daniel Truong <daniel@empowered.org>";
            $fromName = "Daniel";
        } else {
            $fromEmail = "Jack Ross <jack@empowered.org>";
            $fromName = "Jack";
        }
        $toName = stripslashes($toName);

        $Message = "Hi $toName,<br /><br />
            Just wanted to say hey and welcome you to the Empowered.org community! I'm your chapter rep and can answer any questions you have - I'd be happy to walk you through the site or set up a call anytime.<br /><br />
            Looking forward to working with you.<br /><br />
            Best,<br />
            $fromName";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }       $mailer->setSubject("Greetings, I'm Your Empowered.org Rep");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($fromEmail);
        $mailer->send();
    }

    public function sendUploadedMemberAlert($toUser, $chapter, $fromUser, $messageFromcreator) {
        $Message = "Greetings {$toUser->firstName},<br /><br />
                    <p>I just added you as a member of our home page for {$chapter->name} using Empowered.org (our new mobilization tool for fundraising, events and volunteer coordination).
                    We think Empowered can really advance our mission, but we’ll need all our members on the platform to take advantage of it. Just click on the link below, login, and use your account to engage with our organization and help us grow (only takes about 30 seconds)</p>

                    <p>http://www.empowered.org/profile/emaillogin?e=".urlencode($toUser->email)."&p=".urlencode($toUser->password)."</p>

                    <p>If you have any questions (or if you received this message in error), please contact me or our Empowered Advocate at support@empowered.org. Thanks and see you on Empowered!</p>

                    All the best,<br />
                    {$fromUser->fullName}<br />".(isset($chapter->organizationId) ? $chapter->name.'<br />'.$chapter->organization->name : $chapter->name);

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toUser->email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been identified as a {$chapter->name} member on Empowered.org");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($fromUser->fullName." <{$fromUser->email}>");
        $mailer->send();
    }
    //MEMBERSHIP_REMOVE
    public function membershipRemove($toEmail, $toName, $chapterName) {

        $Message  = "Hi $toName,<br /><br />";
        $Message .= "Your membership of $chapterName was successfully deactivated.<br />
            <br />
            All the best,<br />
            The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Membership of $chapterName - Deactivated");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }

    // ADDED_NEW_ADMIN
    public function sendUploadedNewAdminAlert($toEmail, $toName, $GroupName, $Creator,
        $Password, $messageFromcreator, $from, $orgId = null
    ) {
        if (!is_null($orgId) && $orgId == "8CDCC196-B117-11E2-8BB8-0025904EACF0") {
            return $this->sendCustomTxtNewAdmin($toEmail, $toName, $GroupName, $Password, $from);
        }
        $Message = "Hey $toName,<br /><br />";
        if(isset($Creator)) {
                    $Message .= "Just wanted to let you know that $Creator just created $GroupName's home page on our site, Empowered.org, and listed you as an administrator. <br />";
        } else {
                    $Message .= "Just wanted to let you know that $GroupName just signed up on our site, Empowered.org, and listed you as an administrator.<br />";
        }
        if (!empty($messageFromCreator)) {
            $Message .= "<br />Message from $Creator: $messageFromcreator<br />";
        }
        $Message .= "<br />
            (In case you were wondering, Empowered.org is a free online platform that provides tools for groups to help them fundraise, organize and reach their full potential --- and we're really looking forward to serving you and $GroupName as best we can.)<br />
            <br />
            Feel free to log-in to your new account and check out $GroupName's page when you get the chance.<br />
            <br />
            Log-in email: $toEmail<br />
            Temporary Password: $Password<br />
            <br />
            If you have any questions (or if you received this message in error), please contact us at admin@empowered.org. We're always here to help! <br />
            <br />
            Look forward to helping you going forward, and enjoy Empowered!<br />
            <br />
            All the best,<br />
            The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been identified as a $GroupName administrator on Empowered.org");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }

    /**
     * For: United Planet
     * Custom message to send email.
     */
    public function sendCustomTxtNewAdmin($toEmail, $toName, $GroupName, $Password, $from) {
        $Message  = "United Planet has listed you as an administrator on their new Empowered web pages.<br /><br />";
        $Message .= "Empowered enables organizations to mobilize, fund raise and share initiatives of your organization while providing the flexibility to customize the pages to look like the organizations current website.  Please log in to begin exploring the system:<br /><br />";
        $Message .= "http://www.empowered.org/United-Planet<br />";
        $Message .= "Log-in email: $toEmail<br />";
        if (!is_null($Password)) {

            $Message .= "Temporary Password: $Password<br /><br />";
            $Message .= "If you have any questions (or received this message accidentally), we're here to support at admin@empowered.org.<br />";
        }
        $Message .= "<br />Kind regards,<br />";
        $Message .= "Nikhil Seth<br />";
        $Message .= "Empowered Consultant";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been identified as a $GroupName administrator on Empowered.org");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }
    //ADDED_EXISTING_ADMIN
    public function sendUploadedExistingAdminAlert($toEmail, $toName, $GroupName,
        $Creator, $messageFromcreator, $from, $orgId = null
    ) {
        if (!is_null($orgId) && $orgId == "8CDCC196-B117-11E2-8BB8-0025904EACF0") {
            return $this->sendCustomTxtNewAdmin($toEmail, $toName, $GroupName, null, $from);
        }
        $Message = "Hey $toName,<br /><br />";
        if(isset($Creator)) {
            $Message .= "Hope all's well! Just wanted to let you know that $Creator just listed you as an administrator of $GroupName on Empowered.<br />";
        } else {
            $Message .= "Hope all's well! Just wanted to let you know that $GroupName just listed you as an administrator on Empowered.<br />";
        }
        if (!empty($messageFromCreator)) {
            $Message .= "<br />Message from $Creator: $messageFromcreator<br />";
        }
        $Message .= "<br />
        Feel free to poke around their page when you get the chance --- and if you'd rather not be a part of the chapter, simply go to your dashboard to remove yourself.<br />
        <br />
        If you have any questions (or received this message accidentally), we're always here to help at admin@empowered.org. Look forward to serving you, and enjoy Empowered.org!<br />
        <br />
        All the best,<br />
        The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been added as a $GroupName administrator");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }

    /**
     * UPLOAD_NEW_VOLUNTEER
     */
    public function sendUploadedNewVolunteerAlert($toEmail, $toName, $GroupName,
        $ActivityName, $Creator, $Password, $messageFromcreator, $from, $loginUrl
    ) {
        $Message = "Hey {$toName},<br /><br />";
        if(isset($Creator)) {
            $Message .= "Just wanted to let you know that {$Creator} just created $ActivityName for {$GroupName} on our site, Empowered.org, and listed you as a volunteer for the opportunity. <br />";
            if (!empty($messageFromCreator)) {
                $Message .= "<br />Message from {$Creator}: {$messageFromcreator}<br />";
            }
        } else {
            $Message .= "Just wanted to let you know that {$GoupName} just created {$ActivityName} on our site, Empowered.org, and listed you as a volunteer for the opportunity. <br />";
            if (!empty($messageFromCreator)) {
                $Message .= "<br />Message from {$GoupName}: {$messageFromcreator}<br />";
            }
        }
        $Message .= "<br />
        (In case you were wondering, Empowered.org is a free online platform that provides tools for groups to help them fundraise, organize and reach their full potential --- and we're really looking forward to serving you and $GroupName as best we can.)<br />
        <br />
        Feel free to log-in to your new account <a href=$loginUrl>here</a> and check out $GroupName's page when you get the chance.<br />
        <br />
        Log-in email: $toEmail<br />
        Temporary Password: $Password<br />
        <br />
        If you have any questions (or if you received this message in error), please contact us at admin@empowered.org. We're always here to help! <br />
        <br />
        Look forward to helping you going forward, and enjoy Empowered!<br />
        <br />
        All the best,<br />
        The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been identified as an $ActivityName volunteer on Empowered.org");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }

    /**
     * UPLOAD_EXISTING_VOLUNTEER
     */
    public function sendUploadedExistingVolunteerAlert($toEmail, $toName, $GroupName,
        $ActivityName, $Creator, $messageFromcreator, $from
    ) {
        $Message = "Hey $toName,<br /><br />";
        if(isset($Creator)) {
            $Message .= "Hope all's well! Just wanted to let you know that {$Creator} just listed you as a volunteer for {$ActivityName} with {$GroupName} on Empowered.<br />";
            if (!empty($messageFromcreator)) {
                $Message .= "<br />Message from {$Creator}: {$messageFromcreator}<br />";
            }
        } else {
            $Message .= "Hope all's well! Just wanted to let you know that {$GroupName} just listed you as a volunteer for {$ActivityName} on Empowered.<br />";
            if (!empty($messageFromcreator)) {
                $Message .= "<br />Message from {$GroupName}: {$messageFromcreator}<br />";
            }
        }
        $Message .= "<br />
        Feel free to poke around their page when you get the chance --- and if you'd rather not be a part of the opportunity, simply go to your dashboard to remove yourself.<br />
        <br />
        If you have any questions (or received this message accidentally), we're always here to help at admin@empowered.org. Look forward to serving you, and enjoy Empowered.org!<br />
        <br />
        All the best,<br />
        The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been added as an $ActivityName volunteer");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }

    public function sendUploadedNewFundraiserAlert($toEmail, $toName, $GroupName, $ActivityName, $Creator, $Password, $messageFromcreator, $from) {
        $Message = "Hey $toName,<br /><br />";
        if(isset($Creator)) {
            $Message .= "Just wanted to let you know that $Creator just created $ActivityName for $GroupName on our site, Empowered.org, and listed you as a fundraiser for the campaign. <br />";
        } else {
            $Message .= "Just wanted to let you know that $GoupName just created $ActivityName on our site, Empowered.org, and listed you as a fundraiser for the campaign. <br />";
        }
                if (!empty($messageFromCreator)) {
                    $Message .= "<br />Message from $Creator: $messageFromcreator<br />";
                }
        $Message .= "<br />
            (In case you were wondering, Empowered.org is a free online platform that provides tools for groups to help them fundraise, organize and reach their full potential --- and we're really looking forward to serving you and $GroupName as best we can.)<br />
            <br />
            Feel free to log-in to your new account and check out $GroupName's page when you get the chance.<br />
            <br />
            Log-in email: $toEmail<br />
            Temporary Password: $Password<br />
            <br />
            If you have any questions (or if you received this message in error), please contact us at admin@empowered.org. We're always here to help! <br />
            <br />
            Look forward to helping you going forward, and enjoy Empowered!<br />
            <br />
            All the best,<br />
            The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been identified as a $ActivityName fundraiser on Empowered.org");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }

    public function sendUploadedExistingFundraiserAlert($toEmail, $toName, $GroupName, $ActivityName, $Creator, $messageFromcreator, $from) {
        $Message = "Hey $toName,<br /><br />";
        if(isset($Creator)) {
            $Message .= "Hope all's well! Just wanted to let you know that $Creator just listed you as a fundraiser for $ActivityName with $GroupName on Empowered.<br />";
        } else {
            $Message .= "Hope all's well! Just wanted to let you know that $GroupName just listed you as a fundraiser for $ActivityName on Empowered.<br />";
        }
                if (!empty($messageFromCreator)) {
                    $Message .= "<br />Message from $Creator: $messageFromcreator<br />";
                }
            $Message .= "<br />
            Feel free to poke around their page when you get the chance --- and if you'd rather not be a part of the campaign, simply go to your dashboard to remove yourself.<br />
            <br />
            If you have any questions (or received this message accidentally), we're always here to help at admin@empowered.org. Look forward to serving you, and enjoy Empowered.org!<br />
            <br />
            All the best,<br />
            The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($toEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("You have been added as a $ActivityName fundraiser");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($from->fullName." <{$from->email}>");
        $mailer->send();
    }

    public function sendCancellationAlert($email, $message) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Cancelled Donation");
        $mailer->setBodyHtml(stripslashes($message));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    // CHAPTER_CREATED_BY_USER
    public function onChapterCreatedByUser($to, $creator, $chapter, $isOpen) {
        if ($isOpen) {
            $this->chapterCreated($to, $creator, $chapter);
        } else {
            $this->chapterApprovalRequest($to, $creator, $chapter);
        }
    }

    public function chapterApprovalRequest($to, $creator, $chapter) {
      $message = "Dear ".$to['FirstName'].",<br />
        <br />
        Just wanted to let you know that the {$chapter->name} chapter was formed by {$creator->fullName} under {$chapter->organization->name} - please follow the link below to check out the chapter's home page for yourself and approve it: http://{$_SERVER['HTTP_HOST']}/{$creator->urlName}<br />
        <br />
        Currently, your org settings only allow new chapters to be created after you approve them - if you would like to make it so your approval is not required (and chapters can be created automatically), simply change the settings with your admin Toolbar (click on 'Edit Details') on your org homepage.<br />
        <br />
        And if there's anything else we can do to help, please let us know. Hope your having a great day!<br />
        <br />
        Thanks,<br />
        The Empowered Team";
      $this->sendMail($to['Email'], "Chapter of {$chapter->organization->name} Created on Empowered.org - Awaiting Your Approval", $message);
    }

    public function chapterCreated($to, $creator, $chapter) {
      $message = "Dear ".$to['FirstName'].",<br />
        <br />
        Just wanted to let you know that the {$chapter->name} chapter was formed by {$creator->fullName} under {$chapter->organization->name}. Here's a link to the chapter home page: http://{$_SERVER['HTTP_HOST']}/{$creator->urlName}<br />
        <br />
        Currently, your org settings allow chapters to form without your approval - if you would like to make it mandatory for new chapters to be approved by you, simply change the settings with your admin Toolbar (click on 'Edit Details') on your org homepage.<br />
        <br />
        And if there's anything else we can do to help, please let us know. Hope your having a great day!<br />
        <br />
        Thanks,<br />
        The Empowered Team";
      $this->sendMail($to['Email'], "Chapter of {$chapter->organization->name} Created on Empowered.org - Check it out! ", $message);
    }

    /**
     * Send a message from a logged in user to a group administrator
     *
     * @param User $from From the user
     * @param User $to   To the user administrator group
     *
     * @return Boolean
     */
    static public function sendMessageToAdminGroup($from, $to, $message) {
        $Msg = "Hi {$to->name},<br /><br />
            You have received a message from {$from->fullName}:<br />
            ====<br />
            {$message}
            <br /><br />
            Best,<br />
            The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($to->email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Empowered.org - User Message");
        $mailer->setBodyHtml(stripslashes($Msg));
        $mailer->setFrom("{$from->fullName} <{$from->email}>");

        return $mailer->send();
    }

    /**
     * Send a message from a logged in user to a list of tickets created
     *
     * @param User   $from      From the user
     * @param String $toMail    To destination email
     * @param String $toName    To destination full name
     * @param String $eventName Event name
     *
     * @return Boolean
     */
    static public function sendTicketsEventRSVP($from, $toMail, $toName, $eventName) {
        $Message = "Hi {$toName},<br /><br />
            You have received a ticket from {$from->fullName} for {$eventName}<br />
            Best,<br />
            The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($to->email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Empowered.org - User Message");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom("{$from->fullName} <{$from->email}>");

        return $mailer->send();
    }

    static public function sendVolunteerQuitAlert($user, $project) {
        $Message = "Dear Admin,<br /><br />
            This email is to let you know that {$user->fullName} has un-volunteered from {$project->name}. Please determine if the volunteer has any funds and contact accounting@globalbrigades.org to have them transferred appropriately.<br /><br />
            Best,<br />
            The Empowered.org Team";

        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($project->group->contact->email);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Empowered.org - Volunteer Quit");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");

        $mailer->send();
    }

    public function onOrganizationCreated($user, $organization) {
        $Message = "Dear Admin,<br /><br />
            <p>This email is to let you know that the user {$user->fullName} has created the {$organization->name} organization.<p/>
            <p>Organization Home: <a href='http://{$_SERVER['HTTP_HOST']}/{$organization->urlName}' >http://{$_SERVER['HTTP_HOST']}/{$organization->urlName}</a> </p>
            Best,<br />
            The Empowered.org Team";
        $To = Zend_Registry::get('configuration')->email->notifications->toArray();
        $this->sendMail($To, "Organization Created: {$organization->name}", $Message);
    }

    public function onUserCreateAction($user, $target, $type) {

        $title = ($type=="Event") ? $target->title : $target->name ;

        $Message = "Dear Admin,<br /><br />
            <p>This email is to let you know that the user {$user->fullName} has created the {$title} {$type}.<p/>";
        if ($type=="Event") {
            $Message = $Message. "<p>{$type} Home: <a href='http://{$_SERVER['HTTP_HOST']}/{$user->urlName}/events?EventId={$target->id}' >http://{$_SERVER['HTTP_HOST']}/{$user->urlName}/events?EventId={$target->id}</a> </p>
            Best,<br />
            The Empowered.org Team";
        } else {
            $Message = $Message. "<p>{$type} Home: <a href='http://{$_SERVER['HTTP_HOST']}/{$target->urlName}' >http://{$_SERVER['HTTP_HOST']}/{$target->urlName}</a> </p>
            Best,<br />
            The Empowered.org Team";
        }
        $To = Zend_Registry::get('configuration')->email->notifications->toArray();

        $this->sendMail($To, "{$type} Created: {$title}", $Message);
    }

    public function onSharedFileUpload($To, $attachedFiles, $target) {
        $title = $target->name;
        $url = $_SERVER["HTTP_HOST"] ."/". $target->urlName;
        $Message = "Dear User,<br /><br />
            <p>This email is to let you know that the attached files were uploaded to <a href='{$url}'>{$title}</a> <p/>
            Best,<br />
            The Empowered.org Team";
        $this->sendMail($To, "Shared files in $title", $Message, $attachedFiles);
    }

    /**
     * ORGANIZATION_NOMINATION
     * Send email request to steve to add a new organization integration with
     * Fly for good. Called from external controller.
     *
     * @param String $Message Message to send to steve
     * @param String $From    Email requesting this action
     *
     * @return void
     */
    public function sendOrganizationNomination($Message, $From) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo("Empowered.org <steveatamian@gmail.com>");
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("New FlyForGood Request");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($From);
        $mailer->send();
    }

    /**
     * MEMBERSHIP_TURN_OFF
     * Send email request to steve to explain why turned off membership
     *
     * @param String $Message Message to send to steve
     * @param String $From    Email requesting this action
     *
     * @return void
     */
    public function sendMembershipTurnOff($Message, $From) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo("Empowered.org <membership@globalbrigades.org>");
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Membership Turn Off - Comment");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($From);
        $mailer->send();
    }

    public function sendMembershipNotification($Text, $To) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($To);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("UGRENT NOTIFICATION FROM EMPOWERED.ORG ABOUT THE NEW MEMBERSHIP OPTION FROM GLOBAL BRIGADES");
        $mailer->setBodyHtml(stripslashes($Text));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();
    }

    /**
     * Send a notification to the donors and admins about a donation canceled or
     * refunded from bluepay.
     *
     * @param Donation $donation
     * @param String   $operationMade Label the operation made: refund|canceled|etc
     */
    public function sendProjectDonationUpdated($donation, $operationMade, $refund = false) {
        $mailer = new Zend_Mail('utf-8');

        if ($donation->donor) {
            $donor      = $donation->donor->fullName;
            $donorEmail = $donation->donor->email;
        } else {
            $donor      = $donation->supporterName;
            $donorEmail = $donation->supporterEmail;
        }

        $paymentAmount = $donation->amount;
        if ($donation->paidFees) {
            $paymentAmount = $donation->amount * (1 + ($donation->project->percentageFee/100));
        }

        $text       = "Dear ".$donor.",<br />";
        $doneBy     = ($donation->donor) ? $donation->donor->fullName : $donation->supporterName;
        $textAdmin  = "The donation made to the initiative <b>".$donation->project->name;
        $textAdmin .= "</b> on ".$donation->createdOn." done by ".$doneBy." on ";
        $textAdmin .= "behalf of <b>".$donation->destination."</b>";

        switch($operationMade) {
            case 'refund':
                $subject  = "Donation Refunded";
                $text    .= "The donation made to the initiative <b>".$donation->project->name;
                $text    .= "</b> on ".$donation->createdOn." was refunded.<br /><br />";
                $text    .= "<u>Transaction details:</u><br />";
                $text    .= "- Transaction #: ".$donation->transactionId."<br />";
                $text    .= "- Transaction Amount: ".$donation->project->currency;
                $text    .= sprintf("%01.2f", $paymentAmount)."<br />";
                $text    .= "- Destination: ".$donation->destination."<br />";
                $text    .= "<br />Please note:  Your previously received receipt ";
                $text    .= "for this donation is no longer valid, as this donation ";
                $text    .= "has been deemed invalid.  Using that receipt without ";
                $text    .= "correcting the validity of your donation is against the law.";
                $text    .= "<br /><br />Sincerely,<br />The Empowered.org Team";

                $textAdmin .= " was refunded.<br /><br />";
                $textAdmin .= "<u>Transaction details:</u><br />";
                $textAdmin .= "- Transaction #: ".$donation->transactionId."<br />";
                $textAdmin .= "- Transaction Amount: ".$donation->project->currency;
                $textAdmin .= sprintf("%01.2f", $paymentAmount)."<br />";
                $textAdmin .= "- Destination: ".$donation->destination."<br />";
                break;
            case 'partial_refund':
                $subject  = "Donation Refunded";
                $text    .= "The donation made to the initiative <b>".$donation->project->name;
                $text    .= "</b> on ".$donation->createdOn." was partial refunded.<br /><br />";
                $text    .= "<u>Transaction details:</u><br />";
                $text    .= "- Transaction #: ".$donation->transactionId."<br />";
                $text    .= "- Destination: ".$donation->destination."<br />";
                $text    .= "- Transaction Refund #: ".$refund->transactionId."<br />";
                $text    .= "- Refund Amount: ".$donation->project->currency;
                $text    .= sprintf("%01.2f", ($refund->amount*-1))."<br />";
                $text    .= "<br />Please note:  Your previously received receipt ";
                $text    .= "for this donation is no longer valid, as this donation ";
                $text    .= "has been deemed invalid.  Using that receipt without ";
                $text    .= "correcting the validity of your donation is against the law.";
                $text    .= "<br /><br />Sincerely,<br />The Empowered.org Team";

                $textAdmin .= " was partial refunded.<br /><br />";
                $textAdmin .= "<u>Transaction details:</u><br />";
                $textAdmin .= "- Transaction #: ".$donation->transactionId."<br />";
                $textAdmin .= "- Transaction Refund #: ".$refund->transactionId."<br />";
                $textAdmin .= "- Refund Amount: ".$donation->project->currency;
                $textAdmin .= sprintf("%01.2f", ($refund->amount*-1))."<br />";
                break;
            case 'declined':
                $subject  = "Donation Declined";
                $text    .= "The donation made to the initiative <b>".$donation->project->name;
                $text    .= "</b> on ".$donation->createdOn." was declined.<br /><br />";
                $text    .= "<u>Transaction details:</u><br />";
                $text    .= "- Transaction #: ".$donation->transactionId."<br />";
                $text    .= "- Transaction Amount: ".$donation->project->currency;
                $text    .= sprintf("%01.2f", $paymentAmount)."<br />";
                $text    .= "- Destination: ".$donation->destination."<br />";
                $text    .= "<br />Please note:  Your previously received receipt ";
                $text    .= "for this donation is no longer valid, as this donation ";
                $text    .= "has been deemed invalid. Using that receipt without ";
                $text    .= "correcting the validity of your donation is against the law.";
                $text    .= "<br /><br />Sincerely,<br />The Empowered.org Team";

                $textAdmin .= " was declined.<br /><br />";
                $textAdmin .= "<u>Transaction details:</u><br />";
                $textAdmin .= "- Transaction #: ".$donation->transactionId."<br />";
                $textAdmin .= "- Transaction Amount: ".$donation->project->currency;
                $textAdmin .= sprintf("%01.2f", $paymentAmount)."<br />";
                $textAdmin .= "- Destination: ".$donation->destination."<br />";
                break;
            case 'approved':
                $subject  = "Donation Approved";
                $text    .= "The donation made to the initiative <b>".$donation->project->name;
                $text    .= "</b> on ".$donation->createdOn." was approved.<br /><br />";
                $text    .= "<u>Transaction details:</u><br />";
                $text    .= "- Transaction #: ".$donation->transactionId."<br />";
                $text    .= "- Transaction Amount: ".$donation->project->currency;
                $text    .= sprintf("%01.2f", $paymentAmount)."<br />";
                $text    .= "- Destination: ".$donation->destination."<br />";
                $text    .= "<br />Please note:  Your previously received receipt ";
                $text    .= "for this donation is no longer valid, as this donation ";
                $text    .= "has been deemed invalid. Using that receipt without ";
                $text    .= "correcting the validity of your donation is against the law.";
                $text    .= "<br /><br />Sincerely,<br />The Empowered.org Team";

                $textAdmin .= " was approved.<br /><br />";
                $textAdmin .= "<u>Transaction details:</u><br />";
                $textAdmin .= "- Transaction #: ".$donation->transactionId."<br />";
                $textAdmin .= "- Transaction Amount: ".$donation->project->currency;
                $textAdmin .= sprintf("%01.2f", $paymentAmount)."<br />";
                $textAdmin .= "- Destination: ".$donation->destination."<br />";
                break;
        }
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo($donorEmail);
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject($subject);
        $mailer->setBodyHtml(stripslashes($text));
        $mailer->setFrom("Empowered.org <admin@empowered.org>");
        $mailer->send();

        // Send notification to admins
        if ($donation->project->group) {
            foreach($donation->project->group->getAdminsRoles() as $admin) {
                if ($admin->email) {
                    $this->sendMailToFrom(
                        $admin->email,
                        $subject,
                        "Dear ".$admin->fullName.",<br />".$textAdmin,
                        "Empowered.org <admin@empowered.org>"
                    );
                }
            }
        }
    }

    /**
     * ContactUs get started
     *
     * @return void
     */
    public function contactUsGetStarted($Message, $From) {
        $mailer = new Zend_Mail('utf-8');
        if($_SERVER['HTTP_HOST'] == 'www.empowered.org') {
            $mailer->addTo("Empowered.org <steveatamian@gmail.com>");
        } else {
            $mailer->addTo('empoweredqa@gmail.com');
        }
        $mailer->setSubject("Get Started Today");
        $mailer->setBodyHtml(stripslashes($Message));
        $mailer->setFrom($From);
        $mailer->send();
    }
}
