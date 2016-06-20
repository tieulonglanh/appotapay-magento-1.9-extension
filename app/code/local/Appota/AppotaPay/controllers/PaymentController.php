<?php

class Appota_AppotaPay_PaymentController extends Mage_Core_Controller_Front_Action {
    
    protected $language = array(
        'vi_VN' => 'vi',
        'en_US' => 'en'
    );
    
    protected $method_code = array(
        'bank',
        'credit'
    );


    public function redirectAction() {
        
        /*$refer = Mage::app()->getRequest()->getServer('HTTP_REFERER');
        
        $refer_compare = Mage::getUrl('checkout/onepage');
        if($refer != $refer_compare) {
			$log_message = "Referer không đúng: Link refer: ". $refer . " - Link so sánh: " . $refer_compare;
            Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
            $url = Mage::getUrl('checkout/cart');
            return $this->_redirect('checkout/cart');
        }*/
        if ($this->getRequest()->getParam('appotapay') != 1) {
            $order = $this->getOrder();
            if(!$order) {
				$log_message = "Không tồn tại order: ". $state . "\n";
				Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
                $url = Mage::getUrl('checkout/cart');
                return $this->_redirect('checkout/cart');
            }
            $method_code = $order->getPayment()->getMethodInstance()->getCode();
            $total_amount = $this->getOrderTotalAmount($order);
            $order_customer_info = $this->getOrderCustomerInfo($order);
			$order_id = $order->getId();
            $customer_ip = Mage::helper('core/http')->getRemoteAddr();
            $state = "Customer Info: " .  $order_customer_info['email']. " - " . $order_customer_info['phone'];
            $target = $method_code;
            $success_url = Mage::getUrl('appotapay/payment/success');
            $cancel_url = Mage::getUrl('appotapay/payment/cancel');
            $config = $this->getConfig($method_code);
            if(!$config) {
                $lang_code = $this->getLanguageCode();
                if($lang_code == 'vi')
                    $message = "Bọn không thể thanh toán được do lỗi hệ thống. Xin liên hệ người quản lý website!";
                else
                    $message = "You cannot payment because system error. Please contact website's admin!";
                $log_message = "Lỗi thiếu api key hoặc secret key: ". $state . "\n";
                Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
                Mage::getSingleton('core/session')->addWarning($message);
                $url = Mage::getUrl('checkout/cart');
                return $this->_redirect('checkout/cart');
            }
            $helper = $this->getHelper($method_code, $config);    
				
            $url = $helper->getPaymentBankUrl($total_amount, $order_id, $customer_ip, $state, $target, $success_url, $cancel_url);
            if(!$url) {
                $url = Mage::getUrl('checkout/cart');
                $log_message = "Không nhận được link thanh toán.\n";
                
            }else{
                $order->setData('state', Mage_Sales_Model_Order::STATE_PROCESSING)
                      ->setData('status', Mage_Sales_Model_Order::STATE_PROCESSING);
                $order->save();
                $log_message = "Đã nhận được link thanh toán.\n";
            }
            $log_message .= " Chuyển hướng url: {$url}  \n";
            Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
            $this->_redirectUrl($url);
        }
    }
    
    /**
     * Lấy thông tin config để truyền vào helper
     * @return array $config
     */
    public function getConfig($method_code) {
        $lang_code = $this->getLanguageCode();
        $config_info = Mage::getStoreConfig("payment/{$method_code}");
        if(!$config_info['api_key']) {
            return false;
        }
        if(!$config_info['api_secret']) {
            return false;
        }
        $config = array(
            'api_key' => Mage::helper('core')->decrypt($config_info['api_key']),
            'lang' => $lang_code,
            'secret_key' => Mage::helper('core')->decrypt($config_info['api_secret']),
            'test_mode' => $config_info['test_mode'],
        );
        return $config;
    }
    
    public function getMethodConfig($method_code) {
        $config_info = Mage::getStoreConfig("payment/{$method_code}");
        $config_info['api_key'] = Mage::helper('core')->decrypt($config_info['api_key']);
        $config_info['secret_key'] = Mage::helper('core')->decrypt($config_info['secret_key']);
        return $config_info;
    }

    public function getOrder() {
        $session = Mage::getSingleton('checkout/session');
        $order_id = $session->getLastRealOrderId();
        if($order_id) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($order_id);
            return $order;
        }else{
            return false;
        }
    }
    
    /*
     * Lấy thông tin ngôn ngữ hiện tại của người dùng thanh toán
     * @return string
     */
    public function getLanguageCode() {
        $locale = Mage::app()->getLocale()->getLocaleCode();
        if(isset($this->language[$locale])) {
            $lang = $this->language[$locale];
        }else {
            $lang = 'en';
        }
        return $lang;
    }
    
    /**
     * Lấy thông tin tổng tiền cần thanh toán của đơn hàng
     * @param object $order
     * @return float
     */
    public function getOrderTotalAmount($order) {
        $get_grand_total = $order->getGrandTotal();
        $get_grand_total_arr = explode(".", $get_grand_total);
        $total = $get_grand_total_arr[0];
        return $total;
    }
    
    /**
     * Lấy thông tin chi tiết của người thanh toán: Họ tên, Email, Số điện thoại
     * @param object $order
     * @return array
     */
    public function getOrderCustomerInfo($order) {
        $customer_info = array();
        $first_name = $order->getBillingAddress()->getFirstname();
        $middle_name = $order->getBillingAddress()->getMiddlename();
        $last_name = $order->getBillingAddress()->getLastname();
        $full_name = $last_name . " " . $middle_name . " " . $first_name;
        $email = $order->getBillingAddress()->getEmail();
        $phone = $order->getBillingAddress()->getTelephone();
        $customer_info['full_name'] = $full_name;
        $customer_info['email'] = $email;
        $customer_info['phone'] = $phone;
        
        return $customer_info;
    }
    
    public function checkIsLoggedIn() {
        
    }
    
    /**
     * Khởi tạo helper cần sử dụng dựa trên phương thức thanh toán
     * @param string $method_code
     * @param array $config
     * @return \Appota_AppotaPay_Helper_Credit | \Appota_AppotaPay_Helper_Bank
     */
    public function getHelper($method_code, $config) {
        if($method_code == 'bank') {
            $helpers = new Appota_AppotaPay_Helper_Bank($config);
        }else{
            $helpers = new Appota_AppotaPay_Helper_Credit($config);
        }
        return $helpers;
    }

    /**
     * When a customer success payment from Baokim.
     */
    public function successAction() {
        $order = $this->getOrder();
        $lang_code = $this->getLanguageCode();
        if(!$order) {
            return $this->_redirect('checkout/cart', array('_secure' => true));
        }
        $refer = Mage::app()->getRequest()->getServer('HTTP_REFERER');
        if(!$refer) {
            return $this->_redirect('checkout/cart', array('_secure' => true));
        }
        $order_status = $order->getStatus();
        if($order_status == 'complete') {
            if($lang_code == 'vi') {
                $message = "Thanh toán và đặt hàng thành công!";
            }else{
                $message = "Your order payment has completed successfully!";
            }
            Mage::getSingleton('core/session')->addSuccess($message);
            return $this->_redirect('checkout/onepage/success', array('_secure' => true));
        }else if($order_status == 'holded') {
            if($lang_code == 'vi') {
                $message = "Số tiền bạn thanh toán nhỏ hơn số tiền cần để mua hàng. Chúng tôi sẽ tạm giữ lại, xin hãy liên lạc với chủ cửa hàng!";
            }else{
                $message = "The payment amount is less than the order total amount. The money wil be holded, please contact to website's administrator!";
            }
            Mage::getSingleton('core/session')->addWarning($message);
            return $this->_redirect('checkout/onepage/success', array('_secure' => true));
        }else {
            if($lang_code == 'vi') {
                $message = "Thanh toán chưa thành công!";
            }else{
                $message = "The payment did not complete successfully!";
            }
            Mage::getSingleton('core/session')->addError($message);
            return $this->_redirect('checkout/onepage/failure', array('_secure' => true));
        }
    }
    
    public function receiveInfoAfterPaymentAction() {
        $data = $this->getRequest()->getPost();
		$data_json = json_encode($data);
		Mage::log($data_json . "\n", null, "appota-pay-".date('Y-m-d').".log");
        $transaction_id = $data['transaction_id'];
        if($transaction_id) {
            $method_code = $data['target'];
            if(in_array($method_code, $this->method_code)){
                $config = $this->getConfig($method_code);
                if($config) {
                    $helper = $this->getHelper($method_code, $config);
                    $check_transaction = $helper->checkTransaction($data, $data['hash']);
                    if($check_transaction['error_code'] == 0) {
                        $check_valid_order = $helper->checkValidOrder($data);
                        if($check_valid_order['error_code'] == 0) {
							$order_id = (int) $data['developer_trans_id'];
                            $order = Mage::getModel('sales/order')->load($order_id);
							Mage::log("Show hrerere valid Order ID: {$order->getId()}\n", null, "appota-pay-".date('Y-m-d').".log");
                            $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE)
                                  ->setData('status', Mage_Sales_Model_Order::STATE_COMPLETE);
                            $order->save();
                            $log_message = "Transaction ID: {$transaction_id} - Order ID: {$order->getId()} - Thanh toán thành công đơn hàng." . PHP_EOL;
                            Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
                        }else if($check_valid_order['error_code'] == 105){
							$order_id = (int) $data['developer_trans_id'];
                            $order = Mage::getModel('sales/order')->load($order_id);
                            $order->setData('state',Mage_Sales_Model_Order::STATE_HOLDED)
                                  ->setData('status',Mage_Sales_Model_Order::STATE_HOLDED);
                            $order->save();
                            $log_message = "Transaction ID: {$transaction_id} - Order ID: {$order->getId()} - Mã lỗi: {$check_valid_order['error_code']} -  {$check_valid_order['message']}" . PHP_EOL;
                            Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
                        }else{
                            $log_message = "Transaction ID: {$transaction_id} - Mã lỗi: {$check_valid_order['error_code']} -  {$check_valid_order['message']}" . PHP_EOL;
                            Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
                        }

                    }else{
                        $order->setData('state', Mage_Sales_Model_Order::STATE_CANCELED)
                              ->setData('status', Mage_Sales_Model_Order::STATE_CANCELED);
                        $order->save();
                        $log_message = "Transaction ID: {$transaction_id} - Mã lỗi: {$check_transaction['error_code']} -  {$check_transaction['message']}" . PHP_EOL;
                        Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
                    }
                }else{
                    $log_message = "Chưa cấu hình api key hoặc sercret key!" . PHP_EOL;
                    Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
                }
            }else{
                $log_message = "Method Code gửi lên không tôn tại" . PHP_EOL;
                Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
            }
        }else{
            $log_message = "Không tồn tại Transaction ID" . PHP_EOL;
            Mage::log($log_message, null, "appota-pay-".date('Y-m-d').".log");
        }
    }

    /**
     * Nhận thông tin trả về từ Appota Pay nếu thanh toán bị lỗi hoặc cancel
     */
    public function cancelAction() {
        $session = Mage::getSingleton('checkout/session');
        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }
            Mage::helper('appotapay/data')->restoreQuote();
        }
        $this->_redirect('checkout/cart');
    }

}
