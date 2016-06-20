<?php

class Appota_AppotaPay_Helper_Credit extends Appota_AppotaPay_Helper_Data {
    /*
     * function get payment bank url
     */
    public function __construct($config) {
        parent::__construct($config);
    }
    
    public function test() {
        echo "test";
    }

    public function getPaymentBankUrl($amount, $order_id, $client_ip, $state = '', $target = '', $success_url = '', $error_url = '', $bank_id = 0) {
        $log_data = "Tổng tiền: {$amount} - Order ID: {$order_id} - Clien IP: {$client_ip} - State: {$state} - Target: {$target} \n";
		Mage::log($log_data, null, "appota-pay-".date('Y-m-d').".log");		
        // build api url
        if($this->TEST_MODE) {
            $api_url = $this->API_URL . $this->VERSION . '/sandbox/services/pay_visa?api_key=' . $this->API_KEY . '&lang=' . $this->LANG;
        }else{
            $api_url = $this->API_URL . $this->VERSION . '/services/pay_visa?api_key=' . $this->API_KEY . '&lang=' . $this->LANG;
        }
        // build params
        $params = array(
            'amount' => $amount,
            'state' => $state, // Optional param
            'target' => $target, // Optional param
            'success_url' => $success_url, // Optional param
            'error_url' => $error_url, // Optional param
            'client_ip' => $client_ip,
            'developer_trans_id' => $order_id
        );
//        echo $api_url; die;
        // request get payment url
        $result = $this->makeRequest($api_url, $params, $this->METHOD);
        // decode result
        $result_obj = json_decode($result);
        Mage::log($result . PHP_EOL, null, "appota-pay-".date('Y-m-d').".log");    
        // check result 
        if (isset($result_obj->error_code) && $result_obj->error_code === 0) { // charging success
            $bank_options = $result_obj->data->bank_options;
            return $bank_options[0]->url;
        }else{
            return false;
        }
    }

}
