<?php

class Appota_AppotaPay_Helper_Data extends Mage_Core_Helper_Abstract {

    protected $API_URL = 'https://api.appotapay.com/';
    protected $API_KEY;
    protected $SECRET_KEY;
    protected $LANG;
    protected $VERSION = 'v1';
    protected $METHOD = 'POST';
    protected $SUCCESS_STATUS = 1;
    protected $TEST_MODE;

    public function __construct($config) {
        // set params
        $this->API_KEY = $config['api_key'];
        $this->LANG = $config['lang'];
        $this->SECRET_KEY = $config['secret_key'];
        $this->TEST_MODE = $config['test_mode'];
    }

    /*
     * function verify signature
     */

    protected function verifySignature($data, $signature, $public_key) {
        if (openssl_verify($data, base64_decode($signature), $public_key) === 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Kiểm tra transaction có hợp lệ hay không
     * @param mix $data
     * @param string $hash
     * @return boolean
     */
    public function checkTransaction($data, $hash) {
        $data_hash = md5($data['amount'] . $data['country_code'] . $data['currency'] . $data['developer_trans_id'] . $data['sandbox'] . $data['state'] . $data['status'] . $data['target'] . $data['transaction_id'] . $data['transaction_type'] . $data['type'] . $this->SECRET_KEY);
        Mage::log("Data hash: {$data_hash} - Receive Hash: {$hash} \n", null, "appota-pay-".date('Y-m-d').".log");
		if ($data_hash === $hash) {
            return array(
                'error_code' => 0,
                'message' => 'Giao dịch hợp lệ'
            );
        } else {
            return array(
                'error_code' => 101,
                'message' => 'Giao dịch không hợp lệ'
            );
        }
    }

    public function checkValidOrder($data) {
        if ($data['status'] == $this->SUCCESS_STATUS) {
            $order_id = (int) $data['developer_trans_id'];
            if ($order_id) {
                $order = Mage::getModel('sales/order')->load($order_id);
                $order_data = $order->getData();
                if (!empty($order_data)) {
                    $get_grand_total = $order->getGrandTotal();
                    $get_grand_total_arr = explode(".", $get_grand_total);
                    $total = $get_grand_total_arr[0];
                    if ($data['amount'] >= $total) {
					Mage::log("Error code: 0 \n", null, "appota-pay-".date('Y-m-d').".log");
                        return array(
                            'error_code' => 0,
                            'message' => 'Giao dịch thành công.'
                        );
                    } else {
					Mage::log("Error code: 105 \n", null, "appota-pay-".date('Y-m-d').".log");
                        return array(
                            'error_code' => 105,
                            'message' => 'Số tiền thanh toán nhỏ hơn số tiền đơn hàng.'
                        );
                    }
                } else {
				Mage::log("Error code: 104 \n", null, "appota-pay-".date('Y-m-d').".log");
                    return array(
                        'error_code' => 104,
                        'message' => 'Order không tồn tại'
                    );
                }
            } else {
				Mage::log("Error code: 103 \n", null, "appota-pay-".date('Y-m-d').".log");
                return array(
                    'error_code' => 103,
                    'message' => 'Order ID không hợp lệ'
                );
            }
        } else {
		Mage::log("Error code: 102 \n", null, "appota-pay-".date('Y-m-d').".log");
            return array(
                'error_code' => 102,
                'message' => 'Giao dịch không thành công'
            );
        }
    }

    /*
     * function get public key
     */

    protected function getPublicKey() {
        // set your public key
        return '-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDNk9Fo5g54Wjsbx60jTPx9/13Q
3DgSx8KgrxplDrUGXCusaI4HG4/qiycR9DQQ8P5iH361NPvwbNJRskQtcySYTh54
Weft58ekVdLtw3ljCFM5AjVaGwPNr4G5J7kR4eo88wEkLZ5tgktwhDu8cH741dkG
M1lQGWg1Ezua7THoyQIDAQAB
-----END PUBLIC KEY-----';
    }

    /*
     * function make request
     * url : string | url request
     * params : array | params request
     * method : string(POST,GET) | method request
     */

    protected function makeRequest($url, $params, $method = 'POST') {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); // Time out 60s
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // connect time out 5s

        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_error($ch)) {
            return false;
        }

        if ($status != 200) {
            curl_close($ch);
            return false;
        }
        // close curl
        curl_close($ch);

        return $result;
    }

    /**
     * Restore last active quote based on checkout session
     *
     * @return bool True if quote restored successfully, false otherwise
     */
    public function restoreQuote() {
        $order = $this->_getCheckoutSession()->getLastRealOrder();
        if ($order->getId()) {
            $quote = $this->_getQuote($order->getQuoteId());
            if ($quote->getId()) {
                $quote->setIsActive(1)
                        ->setReservedOrderId(null)
                        ->save();
                $this->_getCheckoutSession()
                        ->replaceQuote($quote)
                        ->unsLastRealOrderId();
                return true;
            }
        }
        return false;
    }

    /**
     * Return checkout session instance
     *
     * @return Mage_Checkout_Model_Session
     */
    protected function _getCheckoutSession() {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return sales quote instance for specified ID
     *
     * @param int $quoteId Quote identifier
     * @return Mage_Sales_Model_Quote
     */
    protected function _getQuote($quoteId) {
        return Mage::getModel('sales/quote')->load($quoteId);
    }

}
