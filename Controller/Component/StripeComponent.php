<?php
class StripeComponent extends Component {

	public $mode = 'Test';
	public $currency = 'usd';
	public $fields = array('stripe_id' => 'id');

	public function startup(Controller $controller) {
		$this->Controller = $controller;

		// load the stripe vendor class
		App::import('Vendor', 'Stripe.Stripe', array(
			'file' => 'Stripe' . DS . 'lib' . DS . 'Stripe.php')
		);
		if (!class_exists('Stripe')) {
			throw new CakeException('Stripe API Libaray is missing or could not be loaded.');
		}

		// if mode is set in bootstrap.php, use it. otherwise, Test.
		$mode = Configure::read('Stripe.mode');
		if ($mode) {
			$this->mode = $mode;
		}

		// if currency is set in bootstrap.php, use it. otherwise, usd.
		$currency = Configure::read('Stripe.currency');
		if ($currency) {
			$this->currency = $currency;
		}

		// field map for charge response, or use default (set above)
		$fields = Configure::read('Stripe.fields');
		if ($fields) {
			$this->fields = $fields;
		}
	}

	public function charge($data) {
		// set the Stripe API key
		$key = Configure::read('Stripe.' . $this->mode . 'Secret');
		if (!$key) {
			throw new CakeException('Stripe API key is not set.');
		}

		// $data MUST contain 'amount' and 'stripeToken' to make a charge.
		if (!isset($data['amount']) || !isset($data['stripeToken'])) {
			throw new CakeException('The required amount or stripeToken fields are missing.');
		}

		// set the (optional) description field to null if not set in $data
		if (!isset($data['description'])) {
			$data['description'] = null;
		}

		// format the amount, in cents.
		$data['amount'] = number_format($data['amount'], 2) * 100;

		Stripe::setApiKey($key);
		$error = null;
		try {
			$charge = Stripe_Charge::create(array(
				'amount' => $data['amount'],
				'currency' => $this->currency,
				'card' => $data['stripeToken'],
				'description' => $data['description']
			));

		} catch(Stripe_CardError $e) {
			$body = $e->getJsonBody();
			$err  = $body['error'];
			CakeLog::warning($err['type'] . ': ' . $err['code'] . ': ' . $err['message']);
			$error = $err['message'];

		} catch (Stripe_InvalidRequestError $e) {
			$body = $e->getJsonBody();
			$err  = $body['error'];
			CakeLog::warning($err['type'] . ': ' . $err['message']);
			$error = $err['message'];

		} catch (Stripe_AuthenticationError $e) {
			CakeLog::warning('Stripe API key rejected!');
			$error = 'Payment processor API key error.';

		} catch (Stripe_Error $e) {
			CakeLog::warning('Stripe_Error: Stripe could be down.');
			$error = 'Payment processor error, try again later.';

		} catch (Exception $e) {
			CakeLog::warning('Unknown error.');
			$error = 'There was an error, try again later.';
		}

		if ($error !== null) {
			// make SURE return value is a string if the charge failed.
			return (string) $error;
		}

		return $this->_formatResult($charge);
	}

	// returns an array of fields we want from stripe's charge object
	protected function _formatResult($charge) {
		$result = array();
		foreach ($this->fields as $local => $stripe) {
			if (is_array($stripe)) {
				foreach ($stripe as $obj => $field) {
					$result[$local] = $charge->$obj->$field;
				}
			} else {
				$result[$local] = $charge->$stripe;
			}
		}
		return $result;
	}

}