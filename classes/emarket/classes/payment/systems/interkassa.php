<?php
/**
* Interkassa payment class
* @author www.gateon.net E-mail: www@smartbyte.pro
* @package UMI CMS version 2.x (testing for 2.14 version)
* @version 1.0
*/
class interkassaPayment extends payment {

	const STATUS_SUCCESS = "success";
	const STATUS_FAIL  = "fail";
	const STATUS_PROCESS  = "process";

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
		
		$this->order->order();
		$currency = strtoupper( mainConfiguration::getInstance()->get('system', 'default-currency') );
		$amount = number_format($this->order->getActualPrice(), 2, '.', '');
		$param = array();
		$this->hash_mode = $this->object->hash_mode;

		//initialise global params for interkassa form
		$param["ik_co_id"] = $this->object->merchant_id;
		$param["ik_pm_no"] = $this->order->id;
		$param["ik_am"] = $amount;
		$param["ik_cur"] = $currency;
		$param["ik_desc"] = '#'.$this->order->id;

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
		$buffer = outputBuffer::current();
		$buffer->clear();
		$buffer->contentType("text/plain");
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
			}
		} else {
			$buffer->push("failed");
		}
		$buffer->end();
	}

	/**
	* Function validation hash with change hash methods
	* @param NULL
	* @return boolean
	*/
	private function hash_validation() { //check hash
		
		$testMode = getRequest('ik_pw_via');
		
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

        foreach ($_REQUEST as $key => $value) {
	 		$str = $key.' => '.$value;
	 	}

        $ik_sign = $data['ik_sign'];
        unset($data['ik_sign']);
        ksort($data, SORT_STRING);
        array_push($data, $secretKey);
        $sign_string = implode(":", $data);

    	if($this->hash_mode == 'md5'){
    		$hash = base64_encode(md5($sign_string, true));
    	} else {
    		$hash = base64_encode(hash('sha256', $sign_string, true));
    	}       
		
		return $hash == $ik_sign ? true : false;
	}

	/**
	* Function writen log messages, optional function
	* @param string $content
	* @return NULL
	*/
	private function wrlog($content){
		$file = fopen("log.log", "a");
        fwrite($file, $content."\r\n");
        fclose($file);
	}
};
?>
