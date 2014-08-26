<?php
error_reporting(0);

define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);

$com_viruemart = 'com_virtuemart';
$path = dirname(__FILE__);
$path = explode(DS . 'plugins', $path);  
$path = $path[0];

if (file_exists($path . '/defines.php')) {
  include_once $path . '/defines.php';
}

if (!defined('_JDEFINES')) {
  define('JPATH_BASE', $path);
  require_once JPATH_BASE . '/includes/defines.php';
}

define('JPATH_COMPONENT', JPATH_BASE . '/components/' . $com_viruemart);
define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/' . $com_viruemart);
define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/' . $com_viruemart);

require_once JPATH_BASE . '/includes/framework.php';

$app = JFactory::getApplication('site');
$app->initialise();

if (!class_exists( 'VmConfig' )) {
  require(JPATH_ADMINISTRATOR . DS . 'components' . DS . 'com_virtuemart' . DS . 'helpers' . DS . 'config.php');
}

VmConfig::loadConfig();

if (!class_exists('VirtueMartModelOrders')) {
  require( JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php' );
}

if (!class_exists('plgVmpaymentPaymentwall')) {
  require(JPATH_SITE . DS . 'plugins' . DS . 'vmpayment' . DS . 'paymentwall' . DS . 'paymentwall.php');
}

$dispatcher = JDispatcher::getInstance();
$paymentwallPlugin = new plgVmpaymentPaymentwall($dispatcher, Array('type'=>'vmpayment', 'name'=>'paymentwall'));

$paymentwallData = $paymentwallPlugin->getArrayOfPaymentwallSettings();

require(JPATH_SITE . DS . 'plugins' . DS . 'vmpayment' . DS . 'paymentwall' . DS . 'paymentwall_api' . DS . 'lib' . DS . 'paymentwall.php');

Paymentwall_Base::setApiType(Paymentwall_Base::API_GOODS);
Paymentwall_Base::setAppKey($paymentwallData['app_key']);
Paymentwall_Base::setSecretKey($paymentwallData['secret_key']);

$modelOrder = new VirtueMartModelOrders();

$pingback = new Paymentwall_Pingback($_GET, $_SERVER['REMOTE_ADDR']);
if ($pingback->validate()) {

  $productId = $pingback->getProduct()->getId();

  $orderUpd = array(
    'customer_notified' => 0,
    'virtuemart_order_id' => $productId,
    'comments' => 'Paymentwall payment successful'
  );

  if ($pingback->isDeliverable()) {
    $orderUpd['order_status'] = 'C';
  } else if ($pingback->isCancelable()) {
    $orderUpd['order_status'] = 'R';
  }

  $modelOrder->updateStatusForOneOrder($productId, $orderUpd, true);
  echo 'OK'; // Paymentwall expects response to be OK, otherwise the pingback will be resent
} else {
  echo $pingback->getErrorSummary();
}
