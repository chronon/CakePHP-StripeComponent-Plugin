<?php
App::uses('OrdersController', 'Controller');
App::uses('ComponentCollection', 'Controller');
App::uses('StripeComponent', 'Controller/Component');

class TestOrdersController extends OrdersController {
	public $autoRender = false;
}

/**
 * StripeComponent Test Case
 *
 */
class StripeComponentTest extends CakeTestCase {

/**
 * Fixtures
 *
 * @var array
 */
	public $fixtures = array();

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		$Collection = new ComponentCollection();
		$this->Stripe = new StripeComponent($Collection);

		$this->Orders = new TestOrdersController();
		$this->Orders->constructClasses();
		$this->Stripe->startup($this->Orders);
	}

/**
 * tearDown method
 *
 * @return void
 */
	public function tearDown() {
		unset($this->Stripe);

		parent::tearDown();
	}

/**
 * testCharge method
 *
 * @return void
 */
	public function testCharge() {
		$data = array(
			'id' => '10',
			'cart_uuid' => '4ffb145c-3a78-4fb1-be68-00d9dd5460c2',
			'billing_email' => 'test@chronon.com',
			'billing_first_name' => 'Gregory',
			'billing_last_name' => 'Gaskill',
			'billing_company' => '',
			'billing_address_1' => '2189 Hillsbury Rd',
			'billing_address_2' => '',
			'billing_city' => 'Westlake Village',
			'billing_state' => 'CA',
			'billing_zip' => '91361',
			'billing_phone' => '805-500-6811',
			'shipping_first_name' => 'Gregory',
			'shipping_last_name' => 'Gaskill',
			'shipping_company' => '',
			'shipping_address_1' => '2189 Hillsbury Rd',
			'shipping_address_2' => '',
			'shipping_city' => 'Westlake Village',
			'shipping_state' => 'CA',
			'shipping_zip' => '91361',
			'created' => '2012-07-09 17:28:08',
			'message' => '',
			'order_total' => '13.45',
			'stripeToken' => 'tok_WIzWO5qKRmRXXX'
		);

		$result = $this->Stripe->charge($data, 'error');
		debug($result);
		$expected = array(
			'Error' => array(
				'message' => 'Invalid token id: tok_WIzWO5qKRmRXXX'
			)
		);
		$this->assertEquals($expected, $result);

		$result = $this->Stripe->charge($data, 'success');
		debug($result);
		$expected = array(
			'Success' => array(
				'id' => 'ch_tr3GjUPSCNDAIs'
			)
		);
		$this->assertEquals($expected, $result);

		$result = $this->Stripe->charge($data, 'user');
		debug($result);
		$expected = $data;
		$this->assertEquals($expected, $result);
	}

}