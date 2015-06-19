<?php

defined('_JEXEC') or die('Restricted access');

if (!class_exists('vmPSPlugin')) {
  require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmpaymentPaymentwall extends vmPSPlugin
{

  function __construct (& $subject, $config)
  {
    parent::__construct($subject, $config);

    $this->_loggable = TRUE;
    $this->_tablepkey = 'id';
    $this->_tableId = 'id';
    $this->tableFields = array_keys($this->getTableSQLFields());
    $varsToPush = array(
      'app_key' => array('', 'char'),
      'secret_key' => array('', 'char'),
      'widget_code' => array('', 'char'),
      'currency_code' => array('', 'char'),
      'test_mode' => array('', int)
    );

    $this->setConfigParameterable($this->_configTableFieldName, $varsToPush);
  }

  function getVmPluginCreateTableSQL ()
  {
    return $this->createTableSQL('Payment Paymentwall Table');
  }

  function getTableSQLFields ()
  {
    $SQLfields = array(
      'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
      'order_number'                => 'char(64)',
      'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
      'payment_name'                => 'varchar(5000)',
      'payment_currency'            => 'char(3)',
    );

    return $SQLfields;
  }

  /**
   * Create the table for this plugin if it does not yet exist.
   * This functions checks if the called plugin is active one.
   * When yes it is calling the standard method to create the tables
   */
  function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id)
  {
      if(!defined('VM_VERSION') or VM_VERSION < 3){
          // for older vm version
          return $this->onStoreInstallPaymentPluginTable($jplugin_id);
      }else{
          return $this->onStoreInstallPluginTable($jplugin_id);
      }
  }

  /**
   * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
   * The plugin must check first if it is the correct type
   *
   * @param VirtueMartCart cart: the cart object
   * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
   */
  function plgVmOnCheckAutomaticSelectedPayment(VirtueMartCart $cart, array $cart_prices = array())
  {
    return $this->onCheckAutomaticSelected($cart, $cart_prices);
  }

  /**
   * Process after buyer set confirm purchase in check out< it loads a new page with widget
   */
  function plgVmConfirmedOrder ($cart, $order)
  {
    if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
      return NULL; // Another method was selected, do nothing
    }

    if (!$this->selectedThisElement ($method->payment_element)) {
      return FALSE;
    }

    VmConfig::loadJLang('com_virtuemart', true);

    if (!class_exists ('VirtueMartModelOrders')) {
      require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
    }

    require(JPATH_SITE . DS . 'plugins' . DS . 'vmpayment' . DS . 'paymentwall' . DS . 'paymentwall_api' . DS . 'lib' . DS . 'paymentwall.php');

    Paymentwall_Base::setApiType(Paymentwall_Base::API_GOODS);
    Paymentwall_Base::setAppKey($method->app_key);
    Paymentwall_Base::setSecretKey($method->secret_key);

    $products_names = array();
    foreach ($order['items'] as $value) {
      if(!in_array($value->order_item_name, $products_names))
        array_push($products_names, $value->order_item_name);
    }

    $widget = new Paymentwall_Widget(
      $order['details']['BT']->email,                         // id of the end-user who's making the payment
      $method->widget_code,                                   // widget code, e.g. p1; can be picked inside of your merchant account
      array(                                                  // product details for Flexible Widget Call. To let users select the product on Paymentwall's end, leave this array empty
        new Paymentwall_Product(
          $order['details']['BT']->virtuemart_order_id,       // id of the product in your system
          $order['details']['BT']->order_total,               // price
          $method->currency_code,                             // currency code
          implode(', ', $products_names),                     // product name
          Paymentwall_Product::TYPE_FIXED                     // this is a time-based product;
        )
      ),
      array(
        'email' => $order['details']['BT']->email,
        'test_mode' => $method->test_mode,
        'success_url' => JURI::base() . 'index.php?option=com_virtuemart&amp;view=orders&amp;layout=details&amp;order_number=' . $order['details']['BT']->order_number . '&amp;order_pass=' . $order['details']['BT']->order_pass
      )        // additional parameters
    );

    $html =  $widget->getHtmlCode();

    vRequest::setVar('html', $html);
    $cart->emptyCart();    
    return true;
  }

  /**
   * This event is fired after the payment method has been selected. It can be used to store
   * additional payment info in the cart.
   *
   * @param VirtueMartCart $cart: the actual cart
   * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
   */
  function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg)
  {
    return $this->OnSelectCheck ($cart);
  }

  /**
   * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
   *
   * @param object  $cart Cart object
   * @param integer $selected ID of the method selected
   * @return boolean True on succes, false on failures, null when this plugin was not selected.
   * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
   */
  function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn)
  {
    //ToDo add image logo
    return $this->displayListFE ($cart, $selected, $htmlIn);
  }

  /**
   * Check if the payment conditions are fulfilled for this payment method
   *
   * @param $cart_prices: cart prices
   * @param $payment
   * @return true: if the conditions are fulfilled, false otherwise
   *
   */
  protected function checkConditions ($cart, $method, $cart_prices)
  {
    return true;
  }

  /**
   * This method is fired when showing the order details in the frontend.
   * It displays the method-specific data.
   *
   * @param integer $order_id The order ID
   * @return mixed Null for methods that aren't active, text (HTML) otherwise
   */
  function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
  {
    $this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
  }

  /**
    * Calculate the price (value, tax_id) of the selected method
    * It is called by the calculator
    * This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
    *
    * @param VirtueMartCart $cart the current cart
    * @param array cart_prices the new cart prices
    * @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
    *
    *
    */
  function plgVmonSelectedCalculatePricePayment(VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
  {
    return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
  }

  function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
  {
    if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
      return null; // Another method was selected, do nothing
    }

    if (!$this->selectedThisElement($method->payment_element)) {
      return false;
    }

    $this->getPaymentCurrency($method);

    $paymentCurrencyId = $method->payment_currency;
  }

  function plgVmDeclarePluginParamsPayment ($name, $id, &$data)
  {
    return $this->declarePluginParams('payment', $name, $id, $data); 
  }

  function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table)
  {
    return $this->setOnTablePluginParams($name, $id, $table);
  }

    /**
     * Addition triggers for VM3
     * @param $data
     * @return bool
     */
    function plgVmDeclarePluginParamsPaymentVM3(&$data) {
        return $this->declarePluginParams('payment', $data);
    }

  /**
   * This method is fired when showing when priting an Order
   * It displays the the payment method-specific data.
   *
   * @param integer $_virtuemart_order_id The order ID
   * @param integer $method_id  method used for this order
   * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
   */
  function plgVmOnShowOrderPrintPayment ($order_number, $method_id)
  {
    return $this->onShowOrderPrint($order_number, $method_id);
  }

  function getArrayOfPaymentwallSettings()
  {
    $db = JFactory::getDBO();

    $query = $db->getQuery(true);

    $query->select($db->quoteName(array('virtuemart_paymentmethod_id')));
    $query->from($db->quoteName('#__virtuemart_paymentmethods'));
    $query->where($db->quoteName('payment_element') . ' LIKE '. $db->quote('paymentwall'));
    $db->setQuery($query);

    $results = $db->loadObjectList();


    if (!($method = $this->getVmPluginMethod ($results[0]->virtuemart_paymentmethod_id))) {
      return NULL; // Another method was selected, do nothing
    }

    return array(
      'app_key' => $method->app_key,
      'secret_key' => $method->secret_key
    );
  }
}