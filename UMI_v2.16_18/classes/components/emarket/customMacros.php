<?php
	/** Класс пользовательских макросов */
	class EmarketCustomMacros {
		/** @var emarket $module */
		public $module;
		public function sendSign() {
			$request = $_POST;
			if (isset($request['paymentId']) && $request['paymentId']) {
				$paymentId = $request['paymentId'];
				$order = $this->module->getBasketOrder();
				$payment = payment::get($paymentId, $order);
				if (isset($request['ik_pm_no']) && $request['ik_pm_no']) {
					$secret_key = $payment->object->secret_key;
					if (!empty($request['ik_sign'])) unset($request['ik_sign']);

					$dataSet = array();
					foreach ($request as $key => $value) {
						if (!preg_match('/ik_/', $key)) continue;
						$dataSet[$key] = $value;
					}

					ksort($dataSet, SORT_STRING);
					array_push($dataSet, $secret_key);
					$signString = implode(':', $dataSet);
					$sign = base64_encode(md5($signString, true));
					header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
					header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
					header("Cache-Control: no-store, no-cache, must-revalidate");
					header("Cache-Control: post-check=0, pre-check=0", FALSE);
					header("Pragma: no-cache");
					echo $sign;
				}
			}
			exit;
		}

	}
