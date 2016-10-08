CakePHP Stripe Component
========================

**NOTE:** This plugin is CakePHP 2 only and **will not be updated for CakePHP 3**. For CakePHP 3, consider checking out [Omnipay](http://omnipay.thephpleague.com/). A great introduction on how to use it with CakePHP 3 can be found in [Jose's post](http://josediazgonzalez.com/2014/12/14/processing-payments-with-cakephp-3/).

This is a simple component that interfaces a CakePHP app with Stripe's PHP API library. Pass the component an array containing at least an amount and a Stripe token id, it will attempt the charge and return an array of the fields you want.

Version 2 adds the ability to create and retrieve customers, optionally subscribing them to a recurring payment plan or just charging them.

Compatibility:
--------------

Tested with CakePHP 2.2.x and up, though please note it's not compatible with CakePHP 3.x. The required Stripe PHP API library requires PHP 5 with cURL support and must be version 1.18.0 or below. This plugin will now work with version 2.0.0 or above without modification.

Installation:
-------------

**Using [Composer](http://getcomposer.org/)/[Packagist](https://packagist.org):**

In your project `composer.json` file:

```json
{
	"require": {
		"chronon/stripe": "~2.0"
	},
	"config": {
        "vendor-dir": "Vendor"
    }
}
```

This will install the plugin into `Plugin/Stripe`, and install the Stripe library (from Packagist) into your `Vendor` directory.

In your app's `Config/bootstrap.php`, import composer's autoload file:

```php
<?php
App::import('Vendor', array('file' => 'autoload'));
```
**Using git:**

Composer installation is highly recommended over git installation.

You will need the component (packaged as a plugin), and [Stripe's PHP library](https://github.com/stripe/stripe-php). The Stripe library needs to be in this plugin's Vendor directory and must be named 'Stripe'.  Using git you can add this plugin and the required Stripe library (as a git submodule). From your APP root (where you see your Model, Controller, Plugin, etc. directories) run:

	git clone --recursive git@github.com:chronon/CakePHP-StripeComponent-Plugin.git Plugin/Stripe

**OR**

	git clone --recursive https://github.com/chronon/CakePHP-StripeComponent-Plugin.git Plugin/Stripe

Configuration:
--------------

All configuration is in APP/Config/bootstrap.php.

**Required:** Load the plugin:

```php
<?php
CakePlugin::load('Stripe');
```

or load all plugins:

```php
<?php
CakePlugin::loadAll();
```

**Required:** Set your Stripe secret API keys (both testing and live):

```php
<?php
Configure::write('Stripe.TestSecret', 'yourStripeTestingAPIKeyHere');
Configure::write('Stripe.LiveSecret', 'yourStripeLiveAPIKeyHere');
```

**Optional:** Set Stripe mode, either 'Live' or 'Test'. Defaults to Test if not set.

```php
<?php
Configure::write('Stripe.mode', 'Test');
```

**Optional:** Set the currency. Defaults to 'usd'. Currently Stripe supports usd only.

```php
<?php
Configure::write('Stripe.currency', 'usd');
```

**Optional:** fields for the component to return mapped to => Stripe charge object response fields.  Defaults to `'stripe_id' => 'id'`. See the Stripe API docs for [Stripe\_Charge::create()](https://stripe.com/docs/api?lang=php#create_charge) for available fields. For example:

```php
<?php
Configure::write('Stripe.fields', array(
	'stripe_id' => 'id',
	'stripe_last4' => array('card' => 'last4'),
	'stripe_address_zip_check' => array('card' => 'address_zip_check'),
	'stripe_cvc_check' => array('card' => 'cvc_check'),
	'stripe_amount' => 'amount'
));
```

See Usage below if `Stripe.fields` is confusing.

**Optional:** add a logging config:

```php
<?php
CakeLog::config('stripe', array(
	'engine' => 'FileLog',
	'types' => array('info', 'error'),
	'scopes' => array('stripe'),
	'file' => 'stripe',
));
```

Making a Charge:
----------------

Make a payment form however you want, see the [Stripe docs](https://stripe.com/docs/tutorials/forms) for sample code or use Stripe's excellent [checkout](https://stripe.com/docs/checkout) button. Add the component to your controller:

```php
<?php
public $components = array(
	'Stripe.Stripe'
);
```

Format your form data so you can send the component an array containing at least an amount, a Stripe token (with key `stripeToken`), or a Stripe customer id (with key `stripeCustomer`):

```php
<?php
$data = array(
	'amount' => '7.59',
	'stripeToken' => 'tok_0NAEASV7h0m7ny', // either the token
	'stripeCustomer' => 'cus_2x62nI9WxHsL37' // or the customer id, not both.
);
```

Optionally you can include a `description` key (default is null):

> An arbitrary string which you can attach to a charge object. It is displayed when in the web
> interface alongside the charge. It's often a good idea to use an email address as a description
> for tracking later.

Optionally you can include a `capture` key set to true or false (default is true):

> Whether or not to immediately capture the charge. When false, the charge issues an authorization
> (or pre-authorization), and will need to be captured later. Uncaptured charges expire in 7 days.

For example:

```php
<?php
$data = array(
	'amount' => '7.59',
	'stripeToken' => 'tok_0NAEASV7h0m7ny',
	'description' => 'Casi Robot - casi@robot.com'
);

$result = $this->Stripe->charge($data);
```

If the charge was successful, `$result` will be an **array** as described by the configuration value of `Stripe.fields`. If `Stripe.fields` is not set:

```php
<?php
$result = array(
	'stripe_id' => 'ch_0NXLLCydWzSIeE'
);
```

If `Stripe.fields` is set, using the example described above in the Configuration section would give you:

```php
<?php
$result = array(
	'stripe_id' => 'ch_0NXLLCydWzSIeE',
	'stripe_last4' => '4242',
	'stripe_address_zip_check' => 'pass',
	'stripe_cvc_check' => 'pass',
	'stripe_amount' => 769
);
```

If the charge was not successful, `$result` will be a **string** containing an error message, and log the error.

Creating a Customer:
--------------------

Creating a customer with a card attached can be used for recurring billing/subscriptions, or can be charged immediately.

```php
<?php
$data = array(
	'stripeToken' => 'tok_0NAEASV7h0m7ny',
	'description' => 'Casi Robot - casi@robot.com'
);

$result = $this->Stripe->customerCreate($data);
```

If creating the customer was **successful**, `$result` will be an **array** as described by the configuration value of `Stripe.fields`. If `Stripe.fields` is not set:

```php
<?php
$result = array(
	'stripe_id' => 'cus_2x62nI9WxHsL37'
);
```

If creating the customer was **not successful**, `$result` will be a **string** containing an error message, and log the error.

You can pass the `customerCreate()` method any valid keys/data as described by Stripe's API for creating a customer. [See the API reference](https://stripe.com/docs/api#create_customer) for the list. A customer can be created without a card, but obviously can't be charged or subscribed until a card is attached.

Example: to create a customer and subscribe them to a [plan](https://stripe.com/docs/api#plans) in one step, you could do something like this:

```php
<?php
$data = array(
	'stripeToken' => 'tok_0NAEASV7h0m7ny',
	'description' => 'Casi Robot',
	'email' => 'casi@robot.com',
	'plan' => 'Silver Plan Deluxe'
);

$result = $this->Stripe->customerCreate($data);
```

Retrieving a Customer:
----------------------

Once a customer has been created, you can retrieve the customer object easily with the customer id.

```php
<?php
$customer = $this->Stripe->customerRetrieve('cus_2x62nI9WxHsL37');
```

Once you have the `$customer` object you can [update](https://stripe.com/docs/api#update_customer) and [delete](https://stripe.com/docs/api#delete_customer) as needed. For example, to change the email address of an existing customer:

```php
<?php
$customer = $this->Stripe->customerRetrieve('cus_2x62nI9WxHsL37');
$customer->email = 'new@address.com';
$customer->save();
```

Retrieve and charge a customer:

```php
<?php
$customer = $this->Stripe->customerRetrieve('cus_2x62nI9WxHsL37');
$chargeData = array(
	'amount' => '14.69',
	'stripeCustomer' => $customer['stripe_id']
);

$charge = $this->Stripe->charge($chargeData);
```

Retrieve and update a customer's card with a token:

```php
<?php
$customer = $this->Stripe->customerRetrieve('cus_2x62nI9WxHsL37');
$customer->card = $this->request->data['stripeToken'];
$customer->save();
```

Contributors:
-------------

@louisroy, @PhantomWatson
