<?php
/**
 * Copyright (C) 2005, 2006, 2007, 2008  Brice Burgess <bhb@iceburg.net>
 * 
 * This file is part of poMMo (http://www.pommo.org)
 * 
 * poMMo is free software; you can redistribute it and/or modify 
 * it under the terms of the GNU General Public License as published 
 * by the Free Software Foundation; either version 2, or any later version.
 * 
 * poMMo is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty
 * of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See
 * the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with program; see the file docs/LICENSE. If not, write to the
 * Free Software Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA.
 */

/**********************************
	INITIALIZATION METHODS
*********************************/
require ('../bootstrap.php');
Pommo::init();
$logger = & Pommo::$_logger;
$dbo = & Pommo::$_dbo;

/**********************************
	SETUP TEMPLATE, PAGE
 *********************************/
require_once(Pommo::$_baseDir.'classes/Pommo_Template.php');
$smarty = new Pommo_Template();
$smarty->prepareForForm();

/**********************************
	JSON OUTPUT INITIALIZATION
 *********************************/
require_once(Pommo::$_baseDir.'classes/Pommo_Json.php');
$json = new Pommo_Json();

// Check if user requested to restore defaults
if (isset($_POST['restore'])) {
	require_once(Pommo::$_baseDir.'classes/Pommo_Helper_Messages.php');
	switch (key($_POST['restore'])) {
		case 'subscribe' : $messages = Pommo_Helper_Messages::ResetDefault('subscribe'); break;
		case 'activate' : $messages = Pommo_Helper_Messages::resetDefault('activate'); break;
		case 'unsubscribe' : $messages = Pommo_Helper_Messages::resetDefault('unsubscribe'); break;
		case 'confirm' : $messages = Pommo_Helper_Messages::resetDefault('confirm'); break;
		case 'update' : $messages = Pommo_Helper_Messages::resetDefault('update'); break;
	}
	// reset _POST.
	$_POST = array();
	
	$json->add('callbackFunction','redirect');
	$json->add('callbackParams',Pommo::$_baseUrl.'setup_configure.php?tab=Messages');
	$json->serve();
}

// ADD CUSTOM VALIDATOR FOR CHARSET
function check_notifyMails($value, $empty, & $params, & $formvars) {
	$mails = Pommo_Helper::trimArray(explode(',',$value));
	$ret = true;
	foreach($mails as $mail)
		if (!empty($mail) && !Pommo_Helper::isEmail($mail))
			$ret = false;
	return $ret;
}

SmartyValidate :: connect($smarty);
if (!SmartyValidate :: is_registered_form('messages') || empty ($_POST)) {
	// ___ USER HAS NOT SENT FORM ___
	SmartyValidate::register_form('messages', true);
	
	// register custom criteria
	SmartyValidate::register_criteria('isMails','check_notifyMails', 'messages');
	

	SmartyValidate :: register_validator('subscribe_sub', 'subscribe_sub', 'notEmpty', false, false, 'trim', 'messages');
	SmartyValidate :: register_validator('subscribe_msg', 'subscribe_msg', 'notEmpty', false, false, 'trim', 'messages');
	SmartyValidate :: register_validator('subscribe_web', 'subscribe_web', 'notEmpty', false, false, 'trim', 'messages');
	
	SmartyValidate :: register_validator('unsubscribe_sub', 'unsubscribe_sub', 'notEmpty', false, false, 'trim', 'messages');
	SmartyValidate :: register_validator('unsubscribe_msg', 'unsubscribe_msg', 'notEmpty', false, false, 'trim', 'messages');
	SmartyValidate :: register_validator('unsubscribe_web', 'unsubscribe_web', 'notEmpty', false, false, 'trim', 'messages');
	
	SmartyValidate :: register_validator('confirm_sub', 'confirm_sub', 'notEmpty', false, false, 'trim', 'messages');
	SmartyValidate :: register_validator('confirm_msg', 'confirm_msg:!\[\[URL\]\]!i', 'isRegExp', false, false, 'trim', 'messages');
	
	SmartyValidate :: register_validator('activate_sub', 'activate_sub', 'notEmpty', false, false, 'trim', 'messages');
	SmartyValidate :: register_validator('activate_msg', 'activate_msg:!\[\[URL\]\]!i', 'isRegExp', false, false, 'trim', 'messages');
	
	SmartyValidate :: register_validator('update_sub', 'update_sub', 'notEmpty', false, false, 'trim', 'messages');
	SmartyValidate :: register_validator('update_msg', 'update_msg:!\[\[URL\]\]!i', 'isRegExp', false, false, 'trim', 'messages');

	
	SmartyValidate :: register_validator('notify_email','notify_email','isMails', false, false, false, 'messages');   
	SmartyValidate :: register_validator('notify_subscribe','notify_subscribe:!^(on|off)$!','isRegExp', false, false, false, 'messages');   
	SmartyValidate :: register_validator('notify_unsubscribe','notify_unsubscribe:!^(on|off)$!','isRegExp', false, false, false, 'messages');   
	SmartyValidate :: register_validator('notify_update','notify_update:!^(on|off)$!','isRegExp', false, false, false, 'messages');   
	SmartyValidate :: register_validator('notify_pending','notify_pending:!^(on|off)$!','isRegExp', false, false, false, 'messages');   
	
	
	$vMsg = array();
	$vMsg['subscribe_sub'] =
	$vMsg['subscribe_msg'] =
	$vMsg['subscribe_web'] =
	$vMsg['unsubscribe_sub'] = 
	$vMsg['unsubscribe_msg'] =
	$vMsg['unsubscribe_web'] = 
	$vMsg['confirm_sub'] = 
	$vMsg['update_sub'] = 
	$vMsg['activate_sub'] = Pommo::_T('Cannot be empty.');
	
	$vMsg['confirm_msg'] = 
	$vMsg['update_msg'] = 
	$vMsg['activate_msg'] = Pommo::_T('You must include "[[URL]]" for the confirm link');
	
	$smarty->assign('vMsg', $vMsg);

	// populate _POST with info from database (fills in form values...)
	$dbvalues = Pommo_Api::configGet(array(
		'messages',
		'notices'));
		
	$notices = unserialize($dbvalues['notices']);
	$messages = unserialize($dbvalues['messages']);
		
	if (empty($messages)) 
		$messages = Pommo_Helper_Messages::resetDefault('all');
		
	if (empty($notices)) 
		$notices = array(
			'email' => Pommo::$_config['admin_email'],
			'subject' => Pommo::_T('[poMMo Notice]'),
			'subscribe' => 'off',
			'unsubscribe' => 'off',
			'update' => 'off',
			'pending' => 'off');
			
	$p = array();	
	$p['notify_email'] = $notices['email'];
	$p['notify_subject'] = $notices['subject'];
	$p['notify_subscribe'] = $notices['subscribe'];
	$p['notify_unsubscribe'] = $notices['unsubscribe'];
	$p['notify_update'] = $notices['update'];
	$p['notify_pending'] = $notices['pending'];
	
	$p['subscribe_sub'] = $messages['subscribe']['sub'];
	$p['subscribe_msg'] = $messages['subscribe']['msg'];
	$p['subscribe_web'] = $messages['subscribe']['web'];
	$p['subscribe_email'] = $messages['subscribe']['email'];
	
	$p['unsubscribe_sub'] = $messages['unsubscribe']['sub'];
	$p['unsubscribe_msg'] = $messages['unsubscribe']['msg'];
	$p['unsubscribe_web'] = $messages['unsubscribe']['web'];
	$p['unsubscribe_email'] = $messages['unsubscribe']['email'];
	
	$p['confirm_sub'] = $messages['confirm']['sub'];
	$p['confirm_msg'] = $messages['confirm']['msg'];
	
	$p['activate_sub'] = $messages['activate']['sub'];
	$p['activate_msg'] = $messages['activate']['msg'];
	
	$p['update_sub'] = $messages['update']['sub'];
	$p['update_msg'] = $messages['update']['msg'];
	
	$smarty->assign($p);
} 
else {
	// ___ USER HAS SENT FORM ___
	
	
	if (SmartyValidate :: is_valid($_POST,'messages')) {
	// __ FORM IS VALID
		$messages = array();
		
		$messages['subscribe'] = array();
		$messages['subscribe']['sub'] = $_POST['subscribe_sub'];
		$messages['subscribe']['msg'] = $_POST['subscribe_msg'];
		$messages['subscribe']['web'] = $_POST['subscribe_web'];
		$messages['subscribe']['email'] = (isset($_POST['subscribe_email'])) ? true : false;
		
		$messages['unsubscribe'] = array();
		$messages['unsubscribe']['sub'] = $_POST['unsubscribe_sub'];
		$messages['unsubscribe']['msg'] = $_POST['unsubscribe_msg'];
		$messages['unsubscribe']['web'] = $_POST['unsubscribe_web'];
		$messages['unsubscribe']['email'] = (isset($_POST['unsubscribe_email'])) ? true : false;
		
		$messages['confirm'] = array();
		$messages['confirm']['sub'] = $_POST['confirm_sub'];
		$messages['confirm']['msg'] = $_POST['confirm_msg'];
		
		$messages['activate'] = array();
		$messages['activate']['sub'] = $_POST['activate_sub'];
		$messages['activate']['msg'] = $_POST['activate_msg'];
		
		$messages['update'] = array();
		$messages['update']['sub'] = $_POST['update_sub'];
		$messages['update']['msg'] = $_POST['update_msg'];
		
		
		$notices = array();
		$notices['email'] = $_POST['notify_email'];
		$notices['subject'] = $_POST['notify_subject'];
		$notices['subscribe'] = $_POST['notify_subscribe'];
		$notices['unsubscribe'] = $_POST['notify_unsubscribe'];
		$notices['update'] = $_POST['notify_update'];
		$notices['pending'] = $_POST['notify_pending'];
		
		$input = array('messages' => serialize($messages),'notices' => serialize($notices));
		Pommo_Api::configUpdate( $input, TRUE);
		
		$json->success(Pommo::_T('Configuration Updated.'));
	} 
	else {
		// __ FORM NOT VALID
		
		$json->add('fieldErrors',$smarty->getInvalidFields('messages'));
		$json->fail(Pommo::_T('Please review and correct errors with your submission.'));
	}
}

$smarty->assign('t_subscribe',Pommo::_T('Subscription'));
$smarty->assign('t_unsubscribe',Pommo::_T('Unsubscription'));
$smarty->assign('t_pending',Pommo::_T('Pending'));
$smarty->assign('t_update',Pommo::_T('Update'));

$smarty->assign($_POST);
$smarty->display('admin/setup/config/messages.tpl');
Pommo::kill();
			
