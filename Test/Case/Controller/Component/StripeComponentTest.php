<?php
App::uses('Controller', 'Controller');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('StripeComponent', 'Stripe.Controller/Component');

class TestPaymentController extends Controller {
	// hi
}

class StripeComponentTest extends CakeTestCase {

	public $StripeComponent = null;
	public $Controller = null;

	public function setUp() {
		if (!Configure::read('Stripe.TestSecret')) {
			throw new CakeException('Stripe.TestSecret must be set in APP/Config/bootstrap.php');
		}
		parent::setUp();
		$Collection = new ComponentCollection();
		$this->StripeComponent = new StripeComponent($Collection);
		$CakeRequest = new CakeRequest();
		$CakeResponse = new CakeResponse();
		$this->Controller = new TestPaymentController($CakeRequest, $CakeResponse);

		Configure::write('Stripe.currency', null);
		Configure::write('Stripe.fields', null);
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

	public function testChargeDefaults() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Casi Robot',
			'address_zip' => '91361'
		)));
		$data = array('amount' => 7.45, 'stripeToken' => $token->id);
		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $charge->id);
		$data['amount'] = $data['amount'] * 100;
		$this->assertEquals($data['amount'], $charge->amount);
	}

	public function testChargeLargeAmount() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Large Amount',
			'address_zip' => '91361'
		)));
		$data = array('amount' => 1000, 'stripeToken' => $token->id);
		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $charge->id);
		$data['amount'] = $data['amount'] * 100;
		$this->assertEquals($data['amount'], $charge->amount);
	}

	/**
	 * @expectedException CakeException
	 * @expectedExceptionMessage Amount is required and must be numeric.
	 */
	public function testChargeMissingAmount() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Invalid Amount',
			'address_zip' => '91361'
		)));
		$data = array('stripeToken' => $token->id);
		$result = $this->StripeComponent->charge($data);
	}

	/**
	 * @expectedException CakeException
	 * @expectedExceptionMessage Amount is required and must be numeric.
	 */
	public function testChargeInvalidAmount() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Invalid Amount',
			'address_zip' => '91361'
		)));
		$data = array('amount' => 'casi', 'stripeToken' => $token->id);
		$result = $this->StripeComponent->charge($data);
	}

	public function testChargeWithDescriptionAndFields() {
		Configure::write('Stripe.fields', array(
			'stripe_id' => 'id',
			'stripe_last4' => array('card' => 'last4'),
			'stripe_cvc_check' => array('card' => 'cvc_check'),
			'stripe_amount' => 'amount'
		));

		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			"card" => array(
			"number" => "4242424242424242",
			"exp_month" => 12,
			"exp_year" => 2020,
			"cvc" => 777
		)));
		$data = array(
			'amount' => 5.45,
			'stripeToken' => $token->id,
			'description' => 'Casi Robot - casi@robot.com'
		);

		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve($result['stripe_id']);

		$data['amount'] = $data['amount'] * 100;
		$this->assertEquals($data['amount'], $charge->amount);

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

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			"card" => array(
			"number" => "4242424242424242",
			"exp_month" => 12,
			"exp_year" => 2020,
			"cvc" => 777
		)));
		$data = array(
			'amount' => 5.45,
			'stripeToken' => $token->id,
			'description' => 'Casi Robot - casi@robot.com'
		);

		$result = $this->StripeComponent->charge($data);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$charge = Stripe_Charge::retrieve($result['stripe_id']);

		$data['amount'] = $data['amount'] * 100;
		$this->assertEquals($data['amount'], $charge->amount);

		$this->assertEquals($result['stripe_id'], $charge->id);
		$this->assertArrayNotHasKey('beer_list_1', $result);
		$this->assertArrayNotHasKey('stripe_last4', $result);
		$this->assertArrayNotHasKey('stripe_cvc_check', $result);
		$this->assertArrayNotHasKey('beer_list_2', $result);
	}

	public function testChargeCardError() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			"card" => array(
			"number" => "4000000000000002",
			"exp_month" => 12,
			"exp_year" => 2020,
			"cvc" => 777
		)));
		$data = array('amount' => 1.77, 'stripeToken' => $token->id);
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertEquals('Your card was declined.', $result);
	}

	public function testChargeInvalidRequestError() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$data = array('amount' => 2.77, 'stripeToken' => 'tok_0MzJoNA8ZPrspx');
		$result = $this->StripeComponent->charge($data);
		$this->assertInternalType('string', $result);
		$this->assertContains('Invalid token id:', $result);
	}


	/**
	 * @expectedException STRIPE_AUTHENTICATIONERROR
	 * @expectedExceptionMessage Invalid API Key provided: *********
	 */
	public function testChargeAuthError() {
		Configure::write('Stripe.TestSecret', '123456789');
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			"card" => array(
			"number" => "4242424242424242",
			"exp_month" => 12,
			"exp_year" => 2020,
			"cvc" => 777
		)));
		$data = array('amount' => 3.77, 'stripeToken' => $token->id);
		$result = $this->StripeComponent->charge($data);
	}

	public function testCreateCustomerInvalidToken() {
		$this->StripeComponent->startup($this->Controller);
		$data = array('stripeToken' => '12345');
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertContains('Invalid token id:', $result);
	}

	public function testCreateCustomer() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Casi Robot',
			'address_zip' => '91361'
		)));
		$data = array(
			'stripeToken' => $token->id,
			'description' => 'casi@robot.com'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$customer = Stripe_Customer::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $customer->id);
		$customer->delete();
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

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Casi Robot',
			'address_zip' => '91361'
		)));
		$data = array(
			'stripeToken' => $token->id,
			'description' => 'A Test!',
			'email' => 'casi@robot.com'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['customer_id']);

		$customer = Stripe_Customer::retrieve($result['customer_id']);
		$this->assertEquals($result['customer_id'], $customer->id);
		$this->assertEquals($result['description'], $customer->description);
		$this->assertEquals($result['customer_email'], $customer->email);
		$customer->delete();
	}

	public function testCreateCustomerWithInvalidFields() {
		Configure::write('Stripe.fields', array(
			'another' => 'field',
			'something' => 'notusedhere'
		));
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Casi Robot',
			'address_zip' => '91361'
		)));
		$data = array(
			'stripeToken' => $token->id,
			'description' => 'A Test!',
			'email' => 'casi@robot.com'
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$customer = Stripe_Customer::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $customer->id);
		$this->assertArrayNotHasKey('another', $result);
		$this->assertArrayNotHasKey('something', $result);
		$customer->delete();
	}

	public function testCreateCustomerAndCharge() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Casi Robot',
			'address_zip' => '91361'
		)));
		$data = array(
			'stripeToken' => $token->id,
			'description' => 'Create Customer & Charge',
			'email' => 'casi@robot.com',
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$customer = Stripe_Customer::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $customer->id);

		$chargeData = array(
			'amount' => '14.69',
			'stripeCustomer' => $customer->id
		);
		$charge = $this->StripeComponent->charge($chargeData);
		$this->assertRegExp('/^ch\_[a-zA-Z0-9]+/', $charge['stripe_id']);

		$charge = Stripe_Charge::retrieve($charge['stripe_id']);

		$chargeData['amount'] = $chargeData['amount'] * 100;
		$this->assertEquals($chargeData['amount'], $charge->amount);

		$customer->delete();
	}

	public function testCreateCustomerAndSubscribeToPlan() {
		$this->StripeComponent->startup($this->Controller);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));

		// create a plan for this test
		Stripe_Plan::create(array(
			'amount' => 2000,
			'interval' => "month",
			'name' => "Test Plan",
			'currency' => 'usd',
			'id' => 'testplan')
		);

		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));
		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Casi Robot',
			'address_zip' => '91361'
		)));
		$data = array(
			'stripeToken' => $token->id,
			'plan' => 'testplan',
			'description' => 'Create Customer & Subscribe to Plan',
			'email' => 'casi@robot.com',
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$customer = Stripe_Customer::retrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $customer->id);
		$this->assertEquals($data['plan'], $customer->subscription->plan->id);

		// delete the plan
		$plan = Stripe_Plan::retrieve('testplan');
		$plan->delete();

		$customer->delete();
	}

	public function testCustomerRetrieveAndUpdate() {
		$this->StripeComponent->startup($this->Controller);
		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));

		$token = Stripe_Token::create(array(
			'card' => array(
			'number' => '4242424242424242',
			'exp_month' => 12,
			'exp_year' => 2020,
			'cvc' => 777,
			'name' => 'Casi Robot',
			'address_zip' => '91361'
		)));
		$data = array(
			'stripeToken' => $token->id,
			'description' => 'Original Description',
			'email' => 'casi@robot.com',
		);
		$result = $this->StripeComponent->customerCreate($data);
		$this->assertRegExp('/^cus\_[a-zA-Z0-9]+/', $result['stripe_id']);

		$customer = $this->StripeComponent->customerRetrieve($result['stripe_id']);
		$this->assertEquals($result['stripe_id'], $customer->id);

		$customer->description = 'An updated description';
		$customer->save();

		$customer = $this->StripeComponent->customerRetrieve($result['stripe_id']);
		$this->assertEquals('An updated description', $customer->description);

		$customer->delete();
	}

	public function testCustomerRetrieveNotFound() {
		$this->StripeComponent->startup($this->Controller);
		Stripe::setApiKey(Configure::read('Stripe.TestSecret'));

		$customer = $this->StripeComponent->customerRetrieve('invalid');
		$this->assertFalse($customer);
	}

}