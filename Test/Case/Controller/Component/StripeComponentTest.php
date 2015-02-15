<?php
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('StripeComponent', 'Stripe.Controller/Component');

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
		if ($data['card'] == 'cardError') {
			throw new Stripe_CardError;
		}
		if ($data['card'] == 'invalidRequestError') {
			throw new Stripe_InvalidRequestError;
		}
		$response = self::response('StripeChargeCreate');
		$response->amount = $data['amount'];
		return $response;
	}

	public static function retrieve($data) {
		return self::response('StripeChargeRetrieve');
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

    //
	// /**
	//  * @expectedException STRIPE_AUTHENTICATIONERROR
	//  * @expectedExceptionMessage Invalid API Key provided
	//  */
	// public function testChargeAuthError() {
	// 	Configure::write('Stripe.TestSecret', '123456789');
	// 	$this->StripeComponent->startup($this->Controller);
    //
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
	// 	$token = Stripe_Token::create(array(
	// 		"card" => array(
	// 		"number" => "4242424242424242",
	// 		"exp_month" => 12,
	// 		"exp_year" => 2020,
	// 		"cvc" => 777
	// 	)));
	// 	$data = array('amount' => 3.77, 'stripeToken' => $token->id);
	// 	$result = $this->StripeComponent->charge($data);
	// }
    //
	// public function testCreateCustomerInvalidToken() {
	// 	$this->StripeComponent->startup($this->Controller);
	// 	$data = array('stripeToken' => '12345');
	// 	$result = $this->StripeComponent->customerCreate($data);
	// 	$this->assertContains('Invalid token id:', $result);
	// }
    //
	// public function testCreateCustomer() {
	// 	$this->StripeComponent->startup($this->Controller);
    //
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
	// 	$token = Stripe_Token::create(array(
	// 		'card' => array(
	// 		'number' => '4242424242424242',
	// 		'exp_month' => 12,
	// 		'exp_year' => 2020,
	// 		'cvc' => 777,
	// 		'name' => 'Casi Robot',
	// 		'address_zip' => '91361'
	// 	)));
	// 	$data = array(
	// 		'stripeToken' => $token->id,
	// 		'description' => 'casi@robot.com'
	// 	);
	// 	$result = $this->StripeComponent->customerCreate($data);
	// 	$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);
    //
	// 	$customer = Stripe_Customer::retrieve($result['stripe_id']);
	// 	$this->assertEquals($result['stripe_id'], $customer->id);
	// 	$customer->delete();
	// }
    //
	// public function testCreateCustomerWithFields() {
	// 	Configure::write('Stripe.fields', array(
	// 		'customer_id' => 'id',
	// 		'description' => 'description',
	// 		'customer_email' => 'email',
	// 		'another' => 'field',
	// 		'something' => 'notusedhere'
	// 	));
	// 	$this->StripeComponent->startup($this->Controller);
    //
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
	// 	$token = Stripe_Token::create(array(
	// 		'card' => array(
	// 		'number' => '4242424242424242',
	// 		'exp_month' => 12,
	// 		'exp_year' => 2020,
	// 		'cvc' => 777,
	// 		'name' => 'Casi Robot',
	// 		'address_zip' => '91361'
	// 	)));
	// 	$data = array(
	// 		'stripeToken' => $token->id,
	// 		'description' => 'A Test!',
	// 		'email' => 'casi@robot.com'
	// 	);
	// 	$result = $this->StripeComponent->customerCreate($data);
	// 	$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['customer_id']);
    //
	// 	$customer = Stripe_Customer::retrieve($result['customer_id']);
	// 	$this->assertEquals($result['customer_id'], $customer->id);
	// 	$this->assertEquals($result['description'], $customer->description);
	// 	$this->assertEquals($result['customer_email'], $customer->email);
	// 	$customer->delete();
	// }
    //
	// public function testCreateCustomerWithInvalidFields() {
	// 	Configure::write('Stripe.fields', array(
	// 		'another' => 'field',
	// 		'something' => 'notusedhere'
	// 	));
	// 	$this->StripeComponent->startup($this->Controller);
    //
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
	// 	$token = Stripe_Token::create(array(
	// 		'card' => array(
	// 		'number' => '4242424242424242',
	// 		'exp_month' => 12,
	// 		'exp_year' => 2020,
	// 		'cvc' => 777,
	// 		'name' => 'Casi Robot',
	// 		'address_zip' => '91361'
	// 	)));
	// 	$data = array(
	// 		'stripeToken' => $token->id,
	// 		'description' => 'A Test!',
	// 		'email' => 'casi@robot.com'
	// 	);
	// 	$result = $this->StripeComponent->customerCreate($data);
	// 	$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);
    //
	// 	$customer = Stripe_Customer::retrieve($result['stripe_id']);
	// 	$this->assertEquals($result['stripe_id'], $customer->id);
	// 	$this->assertArrayNotHasKey('another', $result);
	// 	$this->assertArrayNotHasKey('something', $result);
	// 	$customer->delete();
	// }
    //
	// public function testCreateCustomerAndCharge() {
	// 	$this->StripeComponent->startup($this->Controller);
    //
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
	// 	$token = Stripe_Token::create(array(
	// 		'card' => array(
	// 		'number' => '4242424242424242',
	// 		'exp_month' => 12,
	// 		'exp_year' => 2020,
	// 		'cvc' => 777,
	// 		'name' => 'Casi Robot',
	// 		'address_zip' => '91361'
	// 	)));
	// 	$data = array(
	// 		'stripeToken' => $token->id,
	// 		'description' => 'Create Customer & Charge',
	// 		'email' => 'casi@robot.com',
	// 	);
	// 	$result = $this->StripeComponent->customerCreate($data);
	// 	$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);
    //
	// 	$customer = Stripe_Customer::retrieve($result['stripe_id']);
	// 	$this->assertEquals($result['stripe_id'], $customer->id);
    //
	// 	$chargeData = array(
	// 		'amount' => '14.69',
	// 		'stripeCustomer' => $customer->id
	// 	);
	// 	$charge = $this->StripeComponent->charge($chargeData);
	// 	$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $charge['stripe_id']);
    //
	// 	$charge = Stripe_Charge::retrieve($charge['stripe_id']);
    //
	// 	$chargeData['amount'] = $chargeData['amount'] * 100;
	// 	$this->assertEquals($chargeData['amount'], $charge->amount);
    //
	// 	$customer->delete();
	// }
    //
	// public function testCreateCustomerAndSubscribeToPlan() {
	// 	$this->StripeComponent->startup($this->Controller);
    //
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
    //
	// 	// create a plan for this test
	// 	Stripe_Plan::create(array(
	// 		'amount' => 2000,
	// 		'interval' => "month",
	// 		'name' => "Test Plan",
	// 		'currency' => 'usd',
	// 		'id' => 'testplan')
	// 	);
    //
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
	// 	$token = Stripe_Token::create(array(
	// 		'card' => array(
	// 		'number' => '4242424242424242',
	// 		'exp_month' => 12,
	// 		'exp_year' => 2020,
	// 		'cvc' => 777,
	// 		'name' => 'Casi Robot',
	// 		'address_zip' => '91361'
	// 	)));
	// 	$data = array(
	// 		'stripeToken' => $token->id,
	// 		'plan' => 'testplan',
	// 		'description' => 'Create Customer & Subscribe to Plan',
	// 		'email' => 'casi@robot.com',
	// 	);
	// 	$result = $this->StripeComponent->customerCreate($data);
	// 	$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);
    //
	// 	$customer = Stripe_Customer::retrieve($result['stripe_id']);
	// 	$this->assertEquals($result['stripe_id'], $customer->id);
	// 	$this->assertEquals($data['plan'], $customer->subscription->plan->id);
    //
	// 	// delete the plan
	// 	$plan = Stripe_Plan::retrieve('testplan');
	// 	$plan->delete();
    //
	// 	$customer->delete();
	// }
    //
	// public function testCustomerRetrieveAndUpdate() {
	// 	$this->StripeComponent->startup($this->Controller);
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
    //
	// 	$token = Stripe_Token::create(array(
	// 		'card' => array(
	// 		'number' => '4242424242424242',
	// 		'exp_month' => 12,
	// 		'exp_year' => 2020,
	// 		'cvc' => 777,
	// 		'name' => 'Casi Robot',
	// 		'address_zip' => '91361'
	// 	)));
	// 	$data = array(
	// 		'stripeToken' => $token->id,
	// 		'description' => 'Original Description',
	// 		'email' => 'casi@robot.com',
	// 	);
	// 	$result = $this->StripeComponent->customerCreate($data);
	// 	$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);
    //
	// 	$customer = $this->StripeComponent->customerRetrieve($result['stripe_id']);
	// 	$this->assertEquals($result['stripe_id'], $customer->id);
    //
	// 	$customer->description = 'An updated description';
	// 	$customer->save();
    //
	// 	$customer = $this->StripeComponent->customerRetrieve($result['stripe_id']);
	// 	$this->assertEquals('An updated description', $customer->description);
    //
	// 	$customer->delete();
	// }
    //
	// public function testCustomerRetrieveNotFound() {
	// 	$this->StripeComponent->startup($this->Controller);
	// 	Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
    //
	// 	$customer = $this->StripeComponent->customerRetrieve('invalid');
	// 	$this->assertFalse($customer);
	// }

}
