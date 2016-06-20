<?php

class Appota_AppotaPay_Helper_Bank extends Appota_AppotaPay_Helper_Data {

    public function __construct($config) {
        parent::__construct($config);
    }
    
    public function test() {
        echo "Bla";
    }

    /*
     * function get payment bank url
     */

    public function getPaymentBankUrl($amount, $order_id, $client_ip, $state = '', $target = '', $success_url = '', $error_url = '', $bank_id = 0) {
        // build api url
        if($this->TEST_MODE) {
            $api_url = $this->API_URL . $this->VERSION . '/sandbox/services/ibanking?api_key=' . $this->API_KEY . '&lang=' . $this->LANG;
        }else{
            $api_url = $this->API_URL . $this->VERSION . '/services/ibanking?api_key=' . $this->API_KEY . '&lang=' . $this->LANG;
        }
        // build params
        $params = array(
            'amount' => $amount,
            'state' => $state, // Optional param
            'target' => $target, // Optional param
            'success_url' => $success_url, // Optional param
            'error_url' => $error_url, // Optional param
            'bank_id' => $bank_id, // Optional param
            'client_ip' => $client_ip,
            'developer_trans_id' => $order_id
        );

        // request get payment url
        $result = $this->makeRequest($api_url, $params, $this->METHOD);
        // decode result
        $result = json_decode($result);

        // check result 
        if (isset($result->error_code) && $result->error_code === 0) { // charging success
            $transaction_id = $result->data->transaction_id;
            $bank_options = $result->data->bank_options;
            return $bank_options[0]->url;
        }
    }


}
