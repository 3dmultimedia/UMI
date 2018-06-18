<?php
/**
* Interkassa payment class
* @author www.gateon.net E-mail: www@smartbyte.pro
* @package UMI CMS version 2.x (testing for 2.14 version)
* @version 1.1
*/
class interkassaPayment extends payment {

	const STATUS_SUCCESS = "success";
	const STATUS_FAIL  = "fail";
	const STATUS_PROCESS  = "process";
	const STATUS_PENDING = "waitAccept";

	private $hash_mode;

	/**
	* Function validate amount order
	* In this case we make off this function
	* @param NULL
	* @return boolean
	*/
	public function validate() { return true; }

	/**
	* Function create payment form and create order
	* @param $template
	* @return NULL
	*/
	public function process($template = null) {

		//initialize order and first params
		$this->order->order();
		$currency = strtoupper( mainConfiguration::getInstance()->get('system', 'default-currency') );
		$amount = number_format($this->order->getActualPrice(), 2, '.', '');
		$param = array();
		$this->hash_mode = $this->object->hash_mode;

		//fix currency RUB
		if($currency == 'RUR'){
			$currency = 'RUB';
		}

		//initialize global params for interkassa form
		$param["ik_co_id"] = $this->object->merchant_id;
		$param["ik_pm_no"] = $this->order->id;
		$param["ik_am"] = $amount;
		$param["ik_cur"] = $currency;
		$param["ik_desc"] = '#'.$this->order->id;

		//detect web protocol
		$httpScheme = 'http://';
		if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) {
			$httpScheme = 'https://';
		}

		//Interaction Url
		$param['ik_ia_u'] = $httpScheme . $_SERVER['SERVER_NAME'] . "/emarket/gateway/" . $this->order->getId() . "/";

		ksort($param, SORT_STRING);
		$param["key"] = $this->object->secret_key;
		$sign_string = implode(":", $param);
		unset($param["key"]);

		//make sign hash
		if($this->hash_mode == 'md5'){
			$param["sign"] = base64_encode(md5($sign_string, true));
		} else {
			$param["sign"] = base64_encode(hash('sha256', $sign_string, true));
		}		

		//add order into system
		$this->order->setPaymentStatus('initialized');
		list($templateString) = def_module::loadTemplates("emarket/payment/interkassa/".$template, "form_block");
		return def_module::parseTemplate($templateString, $param);
	}

	/**
	* Function validate response from Interkassa
	* @param NULL
	* @return NULL
	*/
	public function poll() { //validate request from interkassa
		$this->wrlog("====================START=========================");
		$this->wrlog("Payment for order: ".getRequest("ik_pm_no")." Time: ".date("Y-m-d H:i:s"));
		$buffer = outputBuffer::current();
		$buffer->clear();
		$buffer->contentType("text/plain");
		$this->wrlog($_REQUEST);
		if($this->hash_validation()) {
			$status = getRequest("ik_inv_st");
			switch($status) {
				case interkassaPayment::STATUS_FAIL : {	//fail order				
					$this->order->setPaymentStatus('declined');
					$buffer->push("failed");
					break;
				}
				case interkassaPayment::STATUS_SUCCESS  : { //success order, create payment
					$this->order->setPaymentStatus('accepted');
					$this->order->payment_document_num = getRequest('ik_inv_id');
					$buffer->push("OK");
					break;
				}
				case interkassaPayment::STATUS_PENDING  : { //pedning payment, wait for payment
					$this->order->setPaymentStatus('initialized');
					$this->order->payment_document_num = getRequest('ik_inv_id');
					$buffer->push("waitAccept");
					break;
				}
			}
		} else {
			$buffer->push("failed");
		}
		$this->wrlog("=====================END=========================");
		$buffer->end();
	}

	/**
	* Function validation hash with change hash methods
	* @param NULL
	* @return boolean
	*/
	private function hash_validation() { //check hash
		
		$testMode = getRequest('ik_pw_via');
		$hash_mode = $this->object->hash_mode;

		if(isset($testMode) && $testMode == 'test_interkassa_test_xts'){
			$secretKey = $this->object->test_key;
		} else {
			$secretKey = $this->object->secret_key;
		}

		$data = array();
		
		foreach ($_REQUEST as $key => $value) {
            if (!preg_match('/ik_/', $key)) continue;
            $data[$key] = $value;
        }

        $ik_sign = $data['ik_sign'];
        unset($data['ik_sign']);
        ksort($data, SORT_STRING);
        array_push($data, $secretKey);
        $sign_string = implode(":", $data);

        if(empty($hash_mode)){
			$hash_mode == 'md5';
        } 

    	if($hash_mode == 'md5'){
    		$hash = base64_encode(md5($sign_string, true));
    	} else {
    		$hash = base64_encode(hash('sha256', $sign_string, true));
    	}
		$this->wrlog("hash: ".$hash);
		$this->wrlog("ik_sign: ".$ik_sign);
		return $hash == $ik_sign ? true : false;
	}

	/**
	* Function writen log messages, optional function
	* @param mixed $content
	* @return NULL || (boolean) false
	*/
	private function wrlog($content){
		$path = $_SERVER['DOCUMENT_ROOT'];
		$file = fopen($path."/errors/logs/log.txt", "a");
		if($file && $path){
			if(is_array($content)){
				foreach ($content as $line => $value) {
					if(is_array($value)){
						$this->wrlog($value);
					} else {
						fwrite($file, $line . "=>" . $value . "\r\n");
					}
				}
			} elseif(is_object($content)) {
				foreach (get_object_vars($content) as $obj_line => $value) {
					fwrite($file, $obj_line . "=>" . $value . "\r\n");
				}
			} else {
				fwrite($file, $content."\r\n");
			}
			fclose($file);
		} else {
			return false;
		}
	}

	private function checkIP()
	{
		$ip_stack = array(
			'ip_begin' => '151.80.190.97',
			'ip_end' => '151.80.190.104'
		);

		if (!ip2long($_SERVER['REMOTE_ADDR']) >= ip2long($ip_stack['ip_begin']) && !ip2long($_SERVER['REMOTE_ADDR']) <= ip2long($ip_stack['ip_end'])) {
			exit();
		}
		return true;
	}

};
?>
