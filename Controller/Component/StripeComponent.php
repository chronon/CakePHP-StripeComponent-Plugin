<?php
class StripeComponent extends Component {

	public $currency = 'usd';

	public function startup(Controller $controller) {
		$this->Controller = $controller;
		// load the stripe vendor class
		App::import('Vendor', 'Stripe.Stripe', array(
			'file' => 'Stripe' . DS . 'lib' . DS . 'Stripe.php')
		);
		// if currency is set in bootstrap.php, use it. otherwise, usd.
		$currency = Configure::read('Stripe.currency');
		if ($currency) {
			$this->currency = $currency;
		}
	}

	public function charge($data, $mode) {
		// mock results for testing
		if (
			(isset($this->Controller->Order->useDbConfig) &&
			$this->Controller->Order->useDbConfig == 'test') ||
			$this->Controller->name == 'TestOrders'
			)
		{
			return $this->__testCharge($data, $mode);
		}

		$key = Configure::read($mode . '.secret');
		Stripe::setApiKey($key);

		try {
			$charge = Stripe_Charge::create(array(
				'amount' => $data['amount'] * 100, // amount in cents
				'currency' => $this->currency,
				'card' => $data['stripeToken'],
				'description' => $data['description']
			));
		} catch (Exception $e) {
			$error = array();
			CakeLog::write('transactions', $e->getCode() . ', '. $e->getMessage());
			$error['Error']['message'] = $e->getMessage();
			return $error;
		}

		return $charge;
	}

	// used only for testing
	private function __testCharge($data, $mode) {
		$result = null;
		if ($mode == 'success') {
			$result = array(
				'Success' => array(
					'id' => 'ch_tr3GjUPSCNDAIs'
				)
			);
		}
		if ($mode == 'error') {
			$result = array(
				'Error' => array(
					'message' => 'Invalid token id: tok_WIzWO5qKRmRXXX'
				)
			);
		}
		if ($mode == 'user') {
			$result = $data;
		}
		return $result;
	}

}