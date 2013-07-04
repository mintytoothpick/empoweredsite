<?php 

require_once 'Mailer.php';
require_once 'Brigade/Db/Table/RepEmails.php';

$RepEmails = new Brigade_Db_Table_RepEmails();
$Mailer = new Mailer();

$newGroups = $RepEmails->getUnsent();

foreach($newGroups as $group){
	$Mailer->sendRepEmail($group['email'], $group['name']);
}

?>