<?php
// app/code/local/Appota/AppotaPay/Block/Form/AppotaPay.php
class Appota_AppotaPay_Block_Form_Credit extends Mage_Payment_Block_Form
{
  protected function _construct()
  {
    parent::_construct();
    $this->setTemplate('appotapay/form/credit.phtml');
  }
}