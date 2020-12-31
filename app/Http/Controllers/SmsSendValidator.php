<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Helpers\NotificationHelper;

class SmsSendValidator {
	
	var $notif;
	
	public function __construct() {
		$this->notif	= new NotificationHelper();
	}
	
	public function getNotification() {
		return $this->notif;
	}
	
	public function validateRequest(Request $req) {
		$to		= $req->json('to');
		$msg	= $req->json('message');
		
		if($to == '')
			$this->notif->addErrorMessage ('to', 'Destination number is required');
		if($msg == '')
			$this->notif->addErrorMessage ('message', 'Text message is required');
	}
	
	public function validateParams($to='', $msg='') {
		if($to == '')
			$this->notif->addErrorMessage ('to', 'Destination number is required');
		if($msg == '')
			$this->notif->addErrorMessage ('message', 'Text message is required');
	}
	
}
