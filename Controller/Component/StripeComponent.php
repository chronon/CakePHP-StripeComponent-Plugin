<?php
/**
 * StripeComponent
 *
 * A component that handles payment processing using Stripe.
 *
 * PHP version 5
 *
 * @package		StripeComponent
 * @author		Gregory Gaskill <gregory@chronon.com>
 * @license		MIT License (http://www.opensource.org/licenses/mit-license.php)
 * @link		https://github.com/chronon/CakePHP-StripeComponent-Plugin
 */

App::uses('Component', 'Controller');

/**
 * StripeComponent
 *
 * @package		StripeComponent
 */
class StripeComponent extends Component {

/**
 * Default Stripe mode to use: Test or Live
 *
 * @var string
 * @access public
 */
	public $mode = 'Test';

/**
 * Default currency to use for the transaction
 *
 * @var string
 * @access public
 */
	public $currency = 'usd';

/**
 * Default mapping of fields to be returned: local_field => stripe_field
 *
 * @var array
 * @access public
 */
	public $fields = array('stripe_id' => 'id');

/**
 * Controller startup. Loads the Stripe API library and sets options from
 * APP/Config/bootstrap.php.
 *
 * @param Controller $controller Instantiating controller
 * @return void
 * @throws CakeException
 */
	public function startup(Controller $controller) {
		$this->Controller = $controller;

		// load the stripe vendor class IF it hasn't been autoloaded (composer)
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

/**
 * The charge method prepares data for Stripe_Charge::create and attempts a
 * transaction.
 *
 * @param array	$data Must contain 'amount' and 'stripeToken'.
 * @return array $charge if success, string $error if failure.
 * @throws CakeException
 * @throws CakeException
 * @throws CakeException
 */
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

		// if supplied amount is not numeric, abort.
		if (!is_numeric($data['amount'])) {
			throw new CakeException('Amount must be numeric.');
		}

		// set the (optional) description field to null if not set in $data
		if (!isset($data['description'])) {
			$data['description'] = null;
		}

		// format the amount, in cents.
		$data['amount'] = $data['amount'] * 100;

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
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['code'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_InvalidRequestError $e) {
			$body = $e->getJsonBody();
			$err = $body['error'];
			CakeLog::error('Stripe: ' . $err['type'] . ': ' . $err['message'], 'stripe');
			$error = $err['message'];

		} catch (Stripe_AuthenticationError $e) {
			CakeLog::error('Stripe: API key rejected!', 'stripe');
			$error = 'Payment processor API key error.';

		} catch (Stripe_Error $e) {
			CakeLog::error('Stripe: Stripe_Error - Stripe could be down.', 'stripe');
			$error = 'Payment processor error, try again later.';

		} catch (Exception $e) {
			CakeLog::error('Stripe: Unknown error.', 'stripe');
			$error = 'There was an error, try again later.';
		}

		if ($error !== null) {
			// an error is always a string
			return (string)$error;
		}

		CakeLog::info('Stripe: charge id ' . $charge->id, 'stripe');

		return $this->_formatResult($charge);
	}

/**
 * Returns an array of fields we want from Stripe's charge object
 *
 *
 * @param object $charge A successful charge object.
 * @return array The desired fields from the charge object as an array.
 */
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