<?php
/**
 * @license Copyright 2016-2017 Smartex.io Ltd., MIT License
 * 
 */
namespace Smartex\Core\Controller\Ipn;

use Magento\Framework\App\Action\Context;
/**
 * @route smartex/ipn/
 */
class Index extends \Magento\Framework\App\Action\Action {
	protected $_smartexHelper;

	protected $_smartexModel;

	protected $_orderModel;

	protected $_smartexInvoiceModel;
	protected $_smartexPaymentModel;

	public function __construct(\Magento\Framework\App\Action\Context $context, \Smartex\Core\Helper\Data $_smartexHelper, \Smartex\Core\Model\Ipn $_smartexModel, \Magento\Sales\Model\Order $_orderModel, \Smartex\Core\Model\Invoice $_smartexInvoiceModel,  \Smartex\Core\Model\Order\Payment $_smartexPaymentModel) {
		parent::__construct($context);
		$this -> _smartexHelper = $_smartexHelper;
		$this -> _smartexModel = $_smartexModel;
		$this -> _orderModel = $_orderModel;
		$this -> _smartexInvoiceModel = $_smartexInvoiceModel;
		$this -> _smartexPaymentModel = $_smartexPaymentModel;
	}

	/**
	 * Smartex's IPN lands here
	 *
	 * @route /smartex/ipn
	 * @route /smartex/ipn/index
	 */
	public function execute() {

		if (false === ini_get('allow_url_fopen')) {
			ini_set('allow_url_fopen', true);
		}

		$raw_post_data = file_get_contents('php://input');

		if (false === $raw_post_data) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Could not read from the php://input stream or invalid Smartex IPN received.');
			throw new \Exception('Could not read from the php://input stream or invalid Smartex IPN received.');
		}

		$this -> _smartexHelper -> registerAutoloader();

		$this -> _smartexHelper -> debugData(sprintf('[INFO] In \Smartex\Core\Controller\Ipn::indexAction(), Incoming IPN message from Smartex: ') . ' ' . json_encode($raw_post_data));

		// Magento doesn't seem to have a way to get the Request body
		$ipn = json_decode($raw_post_data);

		if (true === empty($ipn)) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Could not decode the JSON payload from Smartex.');
			throw new \Exception('Could not decode the JSON payload from Smartex.');
		}

		if (true === empty($ipn -> id) || false === isset($ipn -> posData)) {
			$this -> _smartexHelper -> debugData(sprintf('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Did not receive order ID in IPN: ', $ipn));
			throw new \Exception('Invalid Smartex payment notification message received - did not receive order ID.');
		}

		$ipn -> posData = is_string($ipn -> posData) ? json_decode($ipn -> posData) : $ipn -> posData;
		$ipn -> buyerFields = isset($ipn -> buyerFields) ? $ipn -> buyerFields : new stdClass();

		$this -> _smartexHelper -> debugData(json_encode($ipn));

		$invoice_id = isset($ipn -> id) ? $ipn -> id : '';
		$url = isset($ipn -> url) ? $ipn -> url : '';
		$pos_data = json_encode($ipn -> posData);
		$status = isset($ipn -> status) ? $ipn -> status : '';
		$eth_price = isset($ipn -> ethPrice) ? $ipn -> ethPrice : '';
		$price = isset($ipn -> price) ? $ipn -> price : '';
		$currency = isset($ipn -> currency) ? $ipn -> currency : '';
		$invoice_time = isset($ipn -> invoiceTime) ? intval($ipn -> invoiceTime / 1000) : '';
		$expiration_time = isset($ipn -> expirationTime) ? intval($ipn -> expirationTime / 1000) : '';
		$current_time = isset($ipn -> currentTime) ? intval($ipn -> currentTime / 1000) : '';
		$eth_paid = isset($ipn -> ethPaid) ? $ipn -> ethPaid : '';
		$rate = isset($ipn -> rate) ? $ipn -> rate : '';
		$exception_status = isset($ipn -> exceptionStatus) ? $ipn -> exceptionStatus : '';

		$resources = \Magento\Framework\App\ObjectManager::getInstance() -> get('Magento\Framework\App\ResourceConnection');
		$connection = $resources -> getConnection();
		$ipnTable = $resources -> getTableName('smartex_ipns');

		$sql = "Insert into " . $ipnTable . "(invoice_id,url,pos_data,status,eth_price,price,currency,invoice_time,expiration_time,eth_paid,rate,exception_status) Values ('" . $invoice_id . "','" . $url . "','" . $pos_data . "','" . $status . "','" . $eth_price . "','" . $price . "','" . $currency . "','" . $invoice_time . "','" . $expiration_time . "','" . $eth_paid . "','" . $rate . "','" . $exception_status . "')";
		$connection -> query($sql);

		// Order isn't being created for iframe...
		if (isset($ipn -> posData -> orderId)) {
			$order = $this -> _orderModel -> loadByIncrementId($ipn -> posData -> orderId);
		} else {
			$order = $this -> _orderModel -> load($ipn -> posData -> quoteId, 'quote_id');
		}

		if (false === isset($order) || true === empty($order)) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Invalid Smartex IPN received.');
			$this -> throwException('Invalid Smartex IPN received.');
		}

		$orderId = $order -> getId();
		if (false === isset($orderId) || true === empty($orderId)) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Invalid Smartex IPN received.');
			$this -> throwException('Invalid Smartex IPN received.');
		}

		/**
		 * Ask Smartex to retreive the invoice so we can make sure the invoices
		 * match up and no one is using an automated tool to post IPN's to merchants
		 * store.
		 */
		$invoice = \Magento\Framework\App\ObjectManager::getInstance() -> get('Smartex\Core\Model\Method\Ethereum') -> fetchInvoice($ipn -> id);

		if (false === isset($invoice) || true === empty($invoice)) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Could not retrieve the invoice details for the ipn ID of ' . $ipn -> id);
			$this -> throwException('Could not retrieve the invoice details for the ipn ID of ' . $ipn -> id);
		}

		// Does the status match?
		/*if ($invoice -> getStatus() != $ipn -> status) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), IPN status and status from Smartex are different. Rejecting this IPN!');
			$this -> throwException('There was an error processing the IPN - statuses are different. Rejecting this IPN!');
		}*/

		// Does the price match?
		if ($invoice -> getPrice() != $ipn -> price) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), IPN price and invoice price are different. Rejecting this IPN!');
			$this -> throwException('There was an error processing the IPN - invoice price does not match the IPN price. Rejecting this IPN!');
		}

		// Update the order to notifiy that it has been paid
		$transactionSpeed = \Magento\Framework\App\ObjectManager::getInstance() -> create('Magento\Framework\App\Config\ScopeConfigInterface') -> getValue('payment/smartex/speed');
		
		if ($ipn -> status === 'paid' || $ipn -> status === 'confirmed') {
			try{				
			$payment = $this -> _smartexPaymentModel-> setOrder($order);						 			
			}
			catch(\Exception $e){				
				$this -> _smartexHelper -> debugData("[ERROR] In \Smartex\Core\Controller\Ipn::indexAction():".$e->getMessage());				
			}			

			if (true === isset($payment) && false === empty($payment)) {
				if ($ipn -> status === 'confirmed') {
					// Create invoice for this order
					$order_invoice = $this -> _objectManager -> create('Magento\Sales\Model\Service\InvoiceService') -> prepareInvoice($order);

					// Make sure there is a qty on the invoice
					if (!$order_invoice -> getTotalQty()) {
						throw new \Magento\Framework\Exception\LocalizedException(__('You can\'t create an invoice without products.'));
					}

					// Register as invoice item
					$order_invoice -> setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
					$order_invoice -> register();

					// Save the invoice to the order
					$transaction = $this -> _objectManager -> create('Magento\Framework\DB\Transaction') -> addObject($order_invoice) -> addObject($order_invoice -> getOrder());

					$transaction -> save();

					$order -> addStatusHistoryComment(__('Notified customer about invoice #%1.', $order_invoice -> getId())) -> setIsCustomerNotified(true);
				}

				$order -> save();

			} else {
				$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Could not create a payment object in the Smartex IPN controller.');
				$this -> throwException('Could not create a payment object in the Smartex IPN controller.');
			}
		}

		// use state as defined by Merchant
		$state = \Magento\Framework\App\ObjectManager::getInstance() -> create('Magento\Framework\App\Config\ScopeConfigInterface') -> getValue(sprintf('payment/smartex/invoice_%s', $invoice -> getStatus()));

		if (false === isset($state) || true === empty($state)) {
			$this -> _smartexHelper -> debugData('[ERROR] In \Smartex\Core\Controller\Ipn::indexAction(), Could not retrieve the defined state parameter to update this order to in the Smartex IPN controller.');
			$this -> throwException('Could not retrieve the defined state parameter to update this order in the Smartex IPN controller.');
		}

		// Check if status should be updated
		switch ($order->getStatus()) {
			case \Magento\Sales\Model\Order::STATE_CANCELED :
			case \Magento\Sales\Model\Order::STATUS_FRAUD :
			case \Magento\Sales\Model\Order::STATE_CLOSED :
			case \Magento\Sales\Model\Order::STATE_COMPLETE :
			case \Magento\Sales\Model\Order::STATE_HOLDED :
				// Do not Update
				break;
			case \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT :
			case \Magento\Sales\Model\Order::STATE_PROCESSING :
			default :
				$order -> addStatusToHistory($state, sprintf('[INFO] In \Smartex\Core\Controller\Ipn::indexAction(), Incoming IPN status "%s" updated order state to "%s"', $invoice -> getStatus(), $state)) -> save();
				break;
		}
	}

}

