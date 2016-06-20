<?php

// app/code/local/Appota/AppotaPay/Model/Bank.php
class Appota_AppotaPay_Model_Bank extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'bank';
    protected $_formBlockType = 'appotapay/form_bank';
    protected $_infoBlockType = 'appotapay/info_bank';

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
