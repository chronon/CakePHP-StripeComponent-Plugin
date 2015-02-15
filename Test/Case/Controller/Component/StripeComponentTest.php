<?php
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('StripeComponent', 'Stripe.Controller/Component');


/**
 * The following `Stripe` classes mock static function calls used in the
 * StripeComponent.
 *
 */
class Stripe {
	public static function setApiKey($key) {
		return true;
	}
}

class Stripe_Charge {
	public static function path() {
		return APP . 'Plugin' . DS . 'Stripe' . DS . 'Test' . DS . 'Fixture' . DS;
	}

	public static function create($data) {
		$mockFile = 'ChargeCreate';

		if ($data['card'] == 'cardError') {
			throw new Stripe_CardError;
		}
		if ($data['card'] == 'invalidRequestError') {
			throw new Stripe_InvalidRequestError;
		}
		if ($data['card'] == 'authenticationError') {
			throw new Stripe_AuthenticationError;
		}
		if ($data['card'] == 'apiConnectionError') {
			throw new Stripe_ApiConnectionError;
		}
		if ($data['card'] == 'stripeError') {
			throw new Stripe_Error;
		}
		if ($data['card'] == 'exception') {
			throw new Exception;
		}
		if (isset($data['statement_descriptor']) && $data['statement_descriptor'] == 'chargeParams') {
			$mockFile = 'ChargeCreateParams';
		}
		$response = self::response($mockFile);
		$response->amount = $data['amount'];
		return $response;
	}

	public static function retrieve($data) {
		$mockFile = 'ChargeRetrieve';
		if ($data == 'chargeParams') {
			$mockFile = 'ChargeRetrieveParams';
		}
		return self::response($mockFile);
	}

	public static function response($type) {
		$response = self::path() . $type . '.txt';
		$response = file_get_contents($response);
		if ($response) {
			return json_decode($response);
		}
		return false;
	}
}

class Stripe_Customer {
	public static function path() {
		return APP . 'Plugin' . DS . 'Stripe' . DS . 'Test' . DS . 'Fixture' . DS;
	}

	public static function create($data) {
		if ($data['card'] == 'cardError') {
			throw new Stripe_CardError;
		}
		if ($data['card'] == 'invalidRequestError') {
			throw new Stripe_InvalidRequestError;
		}
		if ($data['card'] == 'authenticationError') {
			throw new Stripe_AuthenticationError;
		}
		if ($data['card'] == 'apiConnectionError') {
			throw new Stripe_ApiConnectionError;
		}
		if ($data['card'] == 'stripeError') {
			throw new Stripe_Error;
		}
		if ($data['card'] == 'exception') {
			throw new Exception;
		}
		$response = self::response('CustomerCreate');
		return $response;
	}

	public static function retrieve($data) {
		if ($data == 'invalid') {
			throw new Exception;
		}
		return self::response('CustomerRetrieve');
	}

	public static function response($type) {
		$response = self::path() . $type . '.txt';
		$response = file_get_contents($response);
		if ($response) {
			return json_decode($response);
		}
		return false;
	}
}

class Stripe_CardError extends Exception {
    public function getJsonBody() {
		$error = array(
			'error' => array(
				'type' => 'test',
				'code' => '000',
				'message' => 'Your card was declined.',
			)
		);
		return $error;
    }
}

class Stripe_InvalidRequestError extends Exception {
    public function getJsonBody() {
		$error = array(
			'error' => array(
				'type' => 'test',
				'message' => 'Invalid token id:',
			)
		);
		return $error;
    }
}

class Stripe_AuthenticationError extends Exception {
}

class Stripe_ApiConnectionError extends Exception {
}

class Stripe_Error extends Exception {
}

class TestController extends Controller {
}

class StripeComponentTest extends CakeTestCase {

	public $StripeComponent = null;

	public $Controller = null;

	public function setUp() {
		parent::setUp();

		Configure::write('Stripe.TestSecret', 'foobar');
		Configure::write('Stripe.LiveSecret', 'foobar');
		Configure::write('Stripe.currency', null);
		Configure::write('Stripe.fields', null);

		$this->StripeComponent = new StripeComponent(new ComponentCollection());
		$this->Controller = new TestController(new CakeRequest(), new CakeResponse());
		$this->StripeComponent->startup($this->Controller);

	}

	public function tearDown() {
		parent::tearDown();
		unset($this->StripeComponent);
		unset($this->Controller);
	}

	public function testStartupDefaults() {
		$this->StripeComponent->startup($this->Controller);
		$this->assertTrue(class_exists('Stripe'));
		$this->assertEquals('usd', $this->StripeComponent->currency);
		$this->assertEquals('Test', $this->StripeComponent->mode);
		$expected = array('stripe_id' => 'id');
		$this->assertEquals($expected, $this->StripeComponent->fields);
	}

	public function testStartupWithSettings() {
		Configure::write('Stripe.mode', 'Live');
		Configure::write('Stripe.currency', 'xxx');
		Configure::write('Stripe.fields', array(
			'stripe_id' => 'id',
			'stripe_last4' => array('card' => 'last4'),
			'stripe_address_zip_check' => array('card' => 'address_zip_check'),
			'stripe_cvc_check' => array('card' => 'cvc_check'),
			'stripe_amount' => 'amount'
		));

		$this->StripeComponent->startup($this->Controller);
		$this->assertTrue(class_exists('Stripe'));
		$this->assertEquals('xxx', $this->StripeComponent->currency);
		$this->assertEquals('Live', $this->StripeComponent->mode);
		$expected = array(
			'stripe_id' => 'id',
			'stripe_last4' => array(
				'card' => 'last4'
			),
			'stripe_address_zip_check' => array(
				'card' => 'address_zip_check'
			),
			'stripe_cvc_check' => array(
				'card' => 'cvc_check'
			),
			'stripe_amount' => 'amount'
		);
		$this->assertEquals($expected, $this->StripeComponent->fields);
	}

/**
 * @expectedException CakeException
 * @expectedExceptionMessage Stripe API key is not set.
 */
	public function testStartupWithNoApiKey() {
		Configure::write('Stripe.TestSecret', null);
		$this->StripeComponent->startup($this->Controller);
	}

/**
 * @expectedException CakeException
 * @expectedExceptionMessage The required stripeToken or stripeCustomer fields are missing.
 */
	public function testChargeInvalidData() {
		$data = array();
		$this->StripeComponent->charge($data);

		$data = array('amount' => 7, 'something' => 'wrong');
		$this->StripeComponent->charge($data);
	}

	/**
	 * @expectedException CakeException
	 * @expectedExceptionMessage Amount is required and must be numeric.
	 */
	public function testChargeMissingAmount() {
		$data = array('stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z');
		$result = $this->StripeComponent->charge($data);
	}

	/**
	 * @expectedException CakeException
	 * @expectedExceptionMessage Amount is required and must be numeric.
	 */
	public function testChargeInvalidAmount() {
		$data = array('amount' => 'casi', 'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z');
		$result = $this->StripeComponent->charge($data);
	}

	public function testChargeDefaults() {
		$data = array(
			'amount' => 7.45,
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $charge->id);
	}

	public function testChargeWithDescriptionAndFields() {
		Configure::write('Stripe.fields', array(
			'stripe_id' => 'id',
			'stripe_last4' => array('card' => 'last4'),
			'stripe_cvc_check' => array('card' => 'cvc_check'),
			'stripe_amount' => 'amount'
		));

		$this->StripeComponent->startup($this->Controller);

		$data = array(
			'amount' => 1005.45,
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z',
			'description' => 'Casi Robot - casi@robot.com'
		);

		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $charge->id);
		$this->assertEquals($result['stripe_last4'], $charge->card->last4);
		$this->assertEquals($result['stripe_cvc_check'], $charge->card->cvc_check);
	}

	public function testChargeWithInvalidFields() {
		Configure::write('Stripe.fields', array(
			'beer_list_1' => 'hops',
			'stripe_last4' => array('card' => 'casi'),
			'stripe_cvc_check' => array('card' => 'robot'),
			'beer_list_2' => 'malts'
		));

		$this->StripeComponent->startup($this->Controller);

		$data = array(
			'amount' => 5.45,
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z',
			'description' => 'Casi Robot - casi@robot.com'
		);

		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $charge->id);
		$this->assertArrayNotHasKey('beer_list_1', $result);
		$this->assertArrayNotHasKey('stripe_last4', $result);
		$this->assertArrayNotHasKey('stripe_cvc_check', $result);
		$this->assertArrayNotHasKey('beer_list_2', $result);
	}

	public function testChargeCardError() {
		$data = array(
			'amount' => 1.77,
			'stripeToken' => 'cardError'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertEquals('Your card was declined.', $result);
	}

	public function testChargeInvalidRequestError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'invalidRequestError'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Invalid token id:', $result);
	}

	public function testChargeAuthenticationError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'authenticationError'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Payment processor API key error.', $result);
	}

	public function testChargeApiConnectionError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'apiConnectionError'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Network communication with payment processor failed, try again later', $result);
	}

	public function testChargeStripeError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'stripeError'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Payment processor error, try again later.', $result);
	}

	public function testChargeException() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'exception'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('There was an error, try again later.', $result);
	}

	public function testCreateCustomer() {
		$data = array(
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z',
			'description' => 'casi@robot.com'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$customer = Stripe_Customer::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $customer->id);
	}

	public function testCreateCustomerWithFields() {
		Configure::write('Stripe.fields', array(
			'customer_id' => 'id',
			'description' => 'description',
			'customer_email' => 'email',
			'another' => 'field',
			'something' => 'notusedhere'
		));
		$this->StripeComponent->startup($this->Controller);

		$data = array(
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z',
			'description' => 'A Test!',
			'email' => 'casi@robot.com'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['customer_id']);

		$customer = Stripe_Customer::retrieve($result['customer_id']);
		$this->assertEquals($result['customer_id'], $customer->id);
		$this->assertEquals($result['description'], $customer->description);
	}

	public function testCreateCustomerWithInvalidFields() {
		Configure::write('Stripe.fields', array(
			'another' => 'field',
			'something' => 'notusedhere'
		));
		$this->StripeComponent->startup($this->Controller);

		$data = array(
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z',
			'description' => 'A Test!',
			'email' => 'casi@robot.com'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$customer = Stripe_Customer::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $customer->id);
		$this->assertArrayNotHasKey('another', $result);
		$this->assertArrayNotHasKey('something', $result);
	}

	public function testCustomerCardError() {
		$data = array(
			'amount' => 1.77,
			'stripeToken' => 'cardError'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertInternalType('string', $result);
		$this->assertEquals('Your card was declined.', $result);
	}

	public function testCustomerInvalidRequestError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'invalidRequestError'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Invalid token id:', $result);
	}

	public function testCustomerAuthenticationError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'authenticationError'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Payment processor API key error.', $result);
	}

	public function testCustomerApiConnectionError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'apiConnectionError'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Network communication with payment processor failed, try again later', $result);
	}

	public function testCustomerStripeError() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'stripeError'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Payment processor error, try again later.', $result);
	}

	public function testCustomerException() {
		$data = array(
			'amount' => 2.77,
			'stripeToken' => 'exception'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('There was an error, try again later.', $result);
	}

	public function testCustomerRetrieve() {
		$id = 'cus_5hr9R9860uchZg';
		$customer = $this->StripeComponent->customerRetrieve($id);
		$this->assertEquals($id, $customer->id);
	}

	public function testCustomerRetrieveNotFound() {
		$customer = $this->StripeComponent->customerRetrieve('invalid');
		$this->assertFalse($customer);
	}

	public function testChargeWithAdditionalChargeDataFields() {
		$data = array(
			'amount' => 7.45,
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z',
			'statement_descriptor' => 'some chargeParams',
			'receipt_email' => 'some@email.com'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve('chargeParams');
		$this->assertEquals($result['stripe_id'], $charge->id);
		$this->assertEquals($data['statement_descriptor'], $charge->statement_descriptor);
		$this->assertEquals($data['receipt_email'], $charge->receipt_email);
	}

	public function testChargeWithAdditionalInvalidChargeDataFields() {
		$data = array(
			'amount' => 7.45,
			'stripeToken' => 'tok_65Vl7Y7eZvKYlo2CurIZVU1z',
			'statement_descriptor' => 'some chargeParams',
			'invalid' => 'foobar'
		);
		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve('chargeParams');
		$this->assertEquals($result['stripe_id'], $charge->id);
		$this->assertEquals($data['statement_descriptor'], $charge->statement_descriptor);
		$this->assertObjectNotHasAttribute('invalid', $charge);
	}

}
