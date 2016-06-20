<?php

// app/code/local/Appota/AppotaPay/Model/Credit.php
class Appota_AppotaPay_Model_Credit extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'credit';
    protected $_formBlockType = 'appotapay/form_credit';
    protected $_infoBlockType = 'appotapay/info_credit';

    public function assignData($data) {
        $this->getInfoInstance();

        return $this;
    }

    public function validate() {
        parent::validate();
        $this->getInfoInstance();

        return $this;
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('appotapay/payment/redirect', array('_secure' => true));
    }

}
