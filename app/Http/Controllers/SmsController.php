<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use Services_Twilio;
use App\Http\Controllers\SmsSendValidator;
use App\Http\Helpers\WebCurl;
use Validator, Input, Redirect ; 
use Illuminate\Support\Facades\DB;


class SmsController extends Controller
{
	var $_tw_accId, $_tw_auth;
	var $_url, $_accId, $_pwd, $_ip, $_div, $_channel, $_sender;
	
	var $_tokens;
	var $_curl;
	
	public function __construct() {
		$this->_url			= env('SMS_URL');
		$this->_accId		= env('SMS_ID');
		$this->_pwd			= env('SMS_PWD');
		$this->_ip			= env('SMS_IP');
		$this->_div			= env('SMS_DIV');
		$this->_channel		= env('SMS_CHANNEL');
		$this->_sender		= env('SMS_SENDER');
		
		$this->_tw_accId	= env('TWILIO_ACCID_PROD'); //env('APP_DEBUG') == true ? env('TWILIO_ACCID_TEST') : env('TWILIO_ACCID_PROD');
		$this->_tw_auth		= env('TWILIO_AUTH_PROD'); //env('APP_DEBUG') == true ? env('TWILIO_AUTH_TEST') : env('TWILIO_AUTH_PROD');
		
		$this->_curl		= new WebCurl(['Content-Type: application/json']);
		
		$this->_tokens		= [];
		$this->_tokens[0]	= '0weWRasJL234wdf1URfwWxxXse304';//dashboard
		$this->_tokens[1]	= '2349oJhHJ20394j2LKJO034823423';//locker
		$this->_tokens[2]	= '3dkf9ekD2lDr103d98wjtR495wt32';//dimo pay
		$this->_tokens[3]	= '72472b3AW3WEG3249i239565Ddfg5';//cod
	}
	
	public function send(Request $req) {
		$notif		= $this->_checkToken($req->json('token'));
		
		if($notif->isOK()) {
			$phone	= $req->json('to');
			$msg	= $req->json('message');
			
			$to		= $phone;
			if(substr($phone, 0, 1) == '+') {
				$to	= substr($phone, 1);
			} else if(substr($phone, 0, 1) == '0') {
				$to = '62'.substr($phone, 1);
			} else if(substr($phone, 0, 1) == '8') {
			    $to = '62'.$phone;
			}
			
			$valid	= new SmsSendValidator();
			$valid->validateRequest($req);
			$notif	= $valid->getNotification();

			if($notif->isOK()) {
				$url			= $this->_url .'?userid='.$this->_accId.'&password='.$this->_pwd.'&msisdn='.$to.'&message='.urlencode($msg);
				$url			.= '&sender='.$this->_sender.'&division='.$this->_div.'&batchname='.$this->_sender.'&uploadby='.$this->_sender;
				$url			.= '&channel='.$this->_channel;
				
				$resp			= $this->_curl->get($url);
				$pass			= preg_match('/Status=([1|2|4|5]+)/', $resp);
				$message_id     = substr($resp, 19);
				
				if($pass == 1) {
					$pass = (string)$pass;
				    $resp=['message-count' => '1', 'messages' => [['to' => $to, 'message-id' => $message_id, 'status' => '0', 'remaining-balance' => '0', 'message-price' => '0', 'network'=>'0']]];
				    
					$notif->setOK($resp);
				} else {
					$notif->setInternalServerError();
				}
			}
		}
		
		return response()->json($notif->build());
	}
	
	public function delivery_report(Request $req) {
		$notif		= $this->_checkToken($req->json('token'));
		
		if($notif->isOK()) {
			$msgIds	= $req->json('message_ids');
			if(!is_array($msgIds) || empty($msgIds)) {
				$notif->addErrorMessage('message_ids', 'Message ID(s) required');
			}
			
			if($notif->isOK()) {
				$url			= 'https://sms-api.jatismobile.com/drreport.ashx';
				$params			= '<DRRequest><UserId>'.$this->_accId.'</UserId><Password>'.$this->_pwd.'</Password><Sender>'.$this->_sender.'</Sender>';
				foreach($msgIds as $id) {
					$params			.= '<MessageId>'.$id.'</MessageId>';
				}
				$params			.= '</DRRequest>';
				
				$notif->data	= $this->_curl->post($url, $params);
			}
		}
		
		return response()->json($notif->build());
	}
	
	public function send_twilio(Request $req) {
		$notif	= $this->_checkToken($req->json('token'));		
		if($notif->isOK()) {
			$to		= $req->json('to');
			$cc		= $req->json('cc');
			$msg	= $req->json('message');

			$valid	= new SmsSendValidator();
			$valid->validateRequest($req);
			$notif	= $valid->getNotification();

			if($notif->isOK()) {		
				$phone		= $this->_phoneNumber($cc, $to);
				
				$twilio		= new Services_Twilio($this->_tw_accId, $this->_tw_auth);
				$send		= $twilio->account->messages->sendMessage(
									env('TWILIO_NUMBER'),
									$phone,
									$msg
								);
				$notif->data = isset($send->sid) ? $send->sid : 'OK';
			}
		}
		$mid = $send->sid;
		$resp=['message-count' => '1', 'messages' => [['to' => $to, 'message-id' => $mid, 'status' => '0', 'remaining-balance' => '0', 'message-price' => '0', 'network'=>'0']]];
		
		$notif->setOK($resp);
		return response()->json($notif->build());
	}
	
	public function send_twilio_my(Request $req) {
		$notif	= $this->_checkToken($req->json('token'));
		
		$cc		= 'my';
		
		return $this->_sendViaTwilio($req->json('message'), $req->json('to'), $cc);
	}
	
	public function send_twilio_cn(Request $req) {
		$notif	= $this->_checkToken($req->json('token'));
		
		$cc		= 'cn';
		
		return $this->_sendViaTwilio($req->json('message'), $req->json('to'), $cc);
	}
		
	private function _sendViaTwilio($message, $to, $cc='id') {
		$notif	= new \App\Http\Helpers\NotificationHelper();
		
		$valid	= new SmsSendValidator();
		$valid->validateParams($to, $message);
		$notif	= $valid->getNotification();

		$mid="1";
		if($notif->isOK()) {		
			$phone		= $this->_phoneNumber($cc, $to);

			$twilio		= new Services_Twilio($this->_tw_accId, $this->_tw_auth);
			
			if ($cc == 'cn') {	
				$send			= $twilio->account->messages->sendMessage(
								env('TWILIO_NUMBER_CN'),
								$phone,
								$message
								);
				$mid = $send->sid;				
			}

			
			if ($cc == 'my') {
			
			$ckph = substr($phone, 0,4);
			
				if ($ckph == "+601") {
			
					$send		= $twilio->account->messages->sendMessage(
								env('TWILIO_NUMBER_MY'),
								$phone,
								$message
								);
				   $mid = $send->sid;
				}				
								
			} else {
			$send		= $twilio->account->messages->sendMessage(
								env('TWILIO_NUMBER'),
								$phone,
								$message
							);
			$mid = $send->sid;				
			}
			
							
			$notif->data = isset($send->sid) ? $send->sid : 'OK';
			
		
			
		}
		
		$resp=['message-count' => '1', 'messages' => [['to' => $to, 'message-id' => $mid, 'status' => '0', 'remaining-balance' => '0', 'message-price' => '0', 'network'=>'0']]];
		
		$notif->setOK($resp);
		return response()->json($notif->build());
	}
	
	public function infobip(Request $req) {
		$notif		= $this->_checkToken($req->json('token'));
		
		if($notif->isOK()) {
			$phone	= $req->json('to');
			$msg	= $req->json('message');
			
			$to		= $phone;
			if(substr($phone, 0, 1) == '+') {
				$to	= substr($phone, 1);
			} else if(substr($phone, 0, 1) == '0') {
				$to = '+62'.substr($phone, 1);
			} else if(substr($phone, 0, 1) == '8') {
			    $to = '+62'.$phone;
			}
			
			$frm = "CLIENT-NAME";
			
			$data				= [];
			$data['to']			= $to;
			$data['text']	= $msg;
			$data['from']		= 'CLIENT-NAME';
			
			$curl2				= new WebCurl(['Content-Type: application/json','authorization: Basic cG9wYm94LmFzaWE6UXdlcnR5ODk=']);
			$resnya = $curl2->post('https://api.infobip.com/sms/1/text/single', json_encode($data));
			
			//echo $resnya;
			$notif->setOK(json_decode($resnya));
			
		}
		
		return response()->json($notif->build());
	
	}

	public function nexmo(Request $req){		
		
		$notif		= $this->_checkToken($req->json('token'));		
		if($notif->isOK()) {
			$phone	= $req->json('to');
			$msg	= $req->json('message');
			
			$to		= $phone;
			if(substr($phone, 0, 1) == '+') {
				$to	= substr($phone, 1);
			} else if(substr($phone, 0, 1) == '0') {
				$to = '62'.substr($phone, 1);
			} else if(substr($phone, 0, 1) == '8') {
			    $to = '62'.$phone;
			} else if(substr($phone, 0, 2) == '01') { //Can also handle Malaysia Phone Number. [Wahyudi 09-09-17] for Malaysia Backup
			    $to = '6'.$phone;
			}
			
			$frm = "CLIENT-NAME";			
			$data = array();
			$data["api_key"] = env('NEXMO_API_KEY');
			$data["api_secret"] = env('NEXMO_API_SECRET');
			$data["to"] = $to;
			$data["from"] = $frm;
			$data["text"] = $msg;
		
			$url = 'https://rest.nexmo.com/sms/json?' . http_build_query($data);				
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  
			$response = curl_exec($ch);		
			$notif->setOK(json_decode($response));			
		}		
		return response()->json($notif->build());		

	}

	public function isentricmy (Request $req){

		$notif		= $this->_checkToken($req->json('token'));		
		if($notif->isOK()) {
			$phone	= $req->json('to');
			$msg	= $req->json('message');
			
			$to		= $phone;
			if(substr($phone, 0, 1) == '+') {
				$to	= substr($phone, 1);
			} else if(substr($phone, 0, 1) == '0') {
				$to = '60'.substr($phone, 1);
			} else if(substr($phone, 0, 1) == '1') {
			    $to = '60'.$phone;
			}
			
			$message = urlencode($msg);
			
			$mtid = "707".time()*1000;

			$accountName = 'popboxsunway'; //This is live account, please don't change..! [Wahyudi 09-09-17]

			// $server_ip = '203.223.130.118';
			$server_ip = '203.223.130.115';

			$runfile = 'http://'.$server_ip.'/ExtMTPush/extmtpush?shortcode=39398&custid='.$accountName.'&rmsisdn='.$to.'&smsisdn=62003&mtid='.$mtid.'&mtprice=000&productCode=&productType=4&keyword=&dataEncoding=0&dataStr='.$message.'&dataUrl=&dnRep=0&groupTag=10';

    		$ch = curl_init();
    		curl_setopt($ch, CURLOPT_URL, $runfile);
   		 	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

   		 	//add loop sending when no answer receive from isentric server, Wahyudi [01-12-17]
   		 	do {
   		 		$content = curl_exec ($ch);
   		 	} while (strpos($content, "returnCode = ") === false);
   		 	
   		 	$ret = strpos($content, "returnCode = ");
   		 	$start = $ret + 13;
   		 	$retcode = substr($content, $start, 1);

   		 	curl_close ($ch); 

   		 	$respa = ['response' => ['code' => 200, 'message' => 'OK'], 'data' => ['message-count' => '1', 'messages' => [['to' => $to, 'message-id' => $mtid, 'status' => $retcode, 'remaining-balance' => '0', 'message-price' => '0', 'network'=>'0']]]];

			$resp=['message-count' => '1', 'messages' => [['to' => $to, 'message-id' => $mtid, 'status' => $retcode, 'remaining-balance' => '0', 'message-price' => '0', 'network'=>'0']]];
			
		
			 // insert partner response to database
             DB::table('companies_response')
                    ->insert([
                        'api_url' => $runfile,
                        'api_send_data' => $message,
                        'api_response' => json_encode($respa),
                        'response_date' => date("Y-m-d H:i:s")
             ]);
			
			 
   	//		$notif->setOK(json_decode($resp));			
		}		
		return response()->json($respa);		

	}
	
	private function _checkToken($token) {
		$notif	= new \App\Http\Helpers\NotificationHelper();
		if($token == '' || !in_array($token, $this->_tokens)) {
			$notif->setUnauthorized();
		} else {
			$notif->setOK('');
		}
		
		return $notif;
	}
	
	private function _phoneNumber($domain, $phone) {
		$cc = '+62';
		
		switch($domain) {
			case 'my':
				$cc = '+60';
				break;
		}
		
		$ph = $phone;
		if(substr($phone, 0, 1) == '0') {
			$ph = $cc.substr($phone, 1);
		} else if(substr($phone, 0, 1) == '8') {
			    $ph = $cc.$phone;
		}
		
		return $ph;
	}
	
	
}
