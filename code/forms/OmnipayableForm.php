<?php

use Omnipay\Common\GatewayFactory;

/**
 * OmnipayableForm provides a base Form for sending payments via the Omnipay library.
 *
 * @author Tom Densham <tom.densham@studiobonito.co.uk>
 * @copyright (c) 2012, Studio Bonito Ltd.
 * @version 1.0
 */
abstract class OmnipayableForm extends Form
{
    /**
     * Store the current gateway.
     *
     * @var Omnipay\Common\GatewayInterface
     */
    protected $gateway;

    /**
     * Store the payment amount.
     *
     * @var int
     */
    protected $amount;

    /**
     * Factory method for creating the correct form for the currently configured gateway.
     *
     * @return OmnipayableForm
     */
    public static function create()
    {
        $args = func_get_args();

        $gateway = Config::inst()->get('Omnipayable', 'Gateway');

        $paymentFormClass = "OmnipayableForm_{$gateway}";

        if (!class_exists($paymentFormClass)) {
            user_error('There is no OmnipayableForm implemented for the currently configured gateway.', E_USER_ERROR);
        }

        return Injector::inst()->createWithArgs($paymentFormClass, $args);
    }

    /**
     * Create the Form and the relevant gateway.
     * Set properties on gateway from config.
     *
     * @param Controller $controller
     * @param string $name
     */
    public function __construct($controller, $name = 'OmnipayableForm')
    {
        $fields = $this->getPaymentFields();
        $actions = $this->getPaymentActions();
        $validator = $this->getRequiredFields();

        parent::__construct($controller, $name, $fields, $actions, $validator);

        $gateway = Config::inst()->get('Omnipayable', 'Gateway');
        $gateways = Config::inst()->get('Omnipayable', 'Gateways');

        $gatewayConfig = $gateways[$gateway];

        $this->gateway = GatewayFactory::create($gateway);

        foreach ($gatewayConfig as $key => $value) {
            $method = "set$key";
            if (method_exists($this->gateway, $method)) {
                $this->gateway->$method($value);
            }
        }
    }

    /**
     * Set the payment amount.
     * Remove the decimal point if present.
     *
     * @param int|float|string $amount
     */
    public function setAmount($amount)
    {
        $this->amount = (int) preg_replace('/([0-9]*)\.([0-9]*)/', '$1$2', $amount);
    }

    /**
     * Set the currency used to make the payment.
     *
     * For a list of valid currency codes see {@link Omnipay\Common\Currency}
     *
     * @param string $currencyCode
     */
    public function setCurrency($currencyCode)
    {
        $this->gateway->setCurrency($currencyCode);
    }

    /**
     * Process the form submission and send the payment request via the gateway.
     *
     * @param array $data
     * @param Form $form
     * @return null
     */
    public function doProcessPayment(array $data, Form $form)
    {
        try {
            $data = $this->processPaymentData($data);

            $data['Amount'] = $this->amount;

            $response = $this->gateway->purchase($data)->send();

            if ($response->isSuccessful()) {
                // Payment is complete
                $this->sessionMessage(_t('Omnipayable.SUCCESS', 'Payment successful!'), 'good');
                $this->extend('onPaymentSuccess');
            } elseif ($response->isRedirect()) {
                return $this->controller->redirect($response->getRedirectUrl()); // Redirect to offsite payment page
            } else {
                // Payment failed
                $this->sessionMessage(_t('Omnipayable.FAILURE', '{message}', array('message' => $response->getMessage())), 'bad');
                $this->extend('onPaymentFailure');
            }
        } catch (Exception $exception) {
            // Log any errors and present a user friendly message
            SS_Log::log($exception, SS_Log::ERR);
            $this->sessionMessage(_t('Omnipayable.ERROR', 'There was an error processing your payment. Please try agian later.'), 'bad');
        }

        return $this->controller->redirectBack();
    }

    /**
     * Handle the response from off-site gateways.
     *
     * @param SS_HTTPRequest $request
     * @return null
     */
    public function doProcessPaymentRedirect(SS_HTTPRequest $request)
    {
        try {
            $data = $request->getVars();

            $data['Amount'] = $this->amount;

            $response = $this->gateway->completePurchase($data)->send();

            if ($response->isSuccessful()) {
                // Payment is complete
                $this->sessionMessage(_t('Omnipayable.SUCCESS', 'Payment successful!'), 'good');
                $this->extend('onPaymentSuccess');
            } else {
                // Payment failed
                $this->sessionMessage(_t('Omnipayable.FAILURE', '{message}', array('message' => $response->getMessage())), 'bad');
                $this->extend('onPaymentFailure');
            }
        } catch (Exception $exception) {
            // Log any errors and present a user friendly message
            SS_Log::log($exception, SS_Log::ERR);
            $this->sessionMessage(_t('Omnipayable.ERROR', 'There was an error processing your payment. Please try agian later.'), 'bad');
        }

        return $this->controller->redirectBack();
    }

    /**
     * Get a list of available credit cards.
     *
     * @return array
     */
    protected function getCreditCardTypes()
    {
        return array(
            'visa'               => 'Visa',
            'mastercard'         => 'Mastercard',
            'discover'           => 'Discover',
            'americanexpress'    => 'American Express',
            'diners_club'        => 'Diners Club',
            'jcb'                => 'JCB',
            'switch'             => 'Switch',
            'solo'               => 'Solo',
            'dankort'            => 'Dankort',
            'maestro'            => 'Maestro',
            'forbrugsforeningen' => 'Forbrugs Foriningen',
            'laser'              => 'Laser'
        );
    }

    /**
     * Get a translatable list of months of the year.
     *
     * @return array
     */
    protected function getMonths()
    {
        return array(
            '01' => _t('OmnipayableForm.JANUARY', 'Jan'),
            '02' => _t('OmnipayableForm.FEBRUARY', 'Feb'),
            '03' => _t('OmnipayableForm.MARCH', 'Mar'),
            '04' => _t('OmnipayableForm.APRIL', 'Apr'),
            '05' => _t('OmnipayableForm.MAY', 'May'),
            '06' => _t('OmnipayableForm.JUNE', 'Jun'),
            '07' => _t('OmnipayableForm.JULY', 'Jul'),
            '08' => _t('OmnipayableForm.AUGUST', 'Aug'),
            '09' => _t('OmnipayableForm.SEPTEMBER', 'Sep'),
            '10' => _t('OmnipayableForm.OCTOBER', 'Oct'),
            '11' => _t('OmnipayableForm.NOVEMBER', 'Nov'),
            '12' => _t('OmnipayableForm.DECEMBER', 'Dec'),
        );
    }

    /**
     * Get a list of years starting from the current year.
     *
     * @param int $range
     * @return array
     */
    protected function getYears($range = 20)
    {
        $years = array();
        $currentYear = date('Y');
        $endOfRangeYear = $currentYear + $range;

        if ($currentYear < $endOfRangeYear) {
            $endOfRangeYear--;
            for ($year = $endOfRangeYear; $year >= $currentYear; $year--) {
                $years[$year] = $year;
            }
        } else {
            for ($year = $currentYear; $year > $endOfRangeYear; $year--) {
                $years[$year] = $year;
            }
        }

        return $years;
    }

    /**
     * Create form fields to represent all of the properties on the {@link Omnipay\Common\CreditCard} object.
     * The form fields are split up into relevent sections to help with adding/removing fields as needed.
     *
     * @return FieldList
     */
    protected function getCreditCardFields()
    {
        $fields = new FieldList();

        $tabindex = 1;

        // Create personal detail fields
        $firstNameTextField = new TextField('FirstName', _t('OmnipayableForm.FIRSTNAME', 'First name'));
        $firstNameTextField->setAttribute('tabindex', $tabindex++);
        $lastNameTextField = new TextField('LastName', _t('OmnipayableForm.LASTNAME', 'Last name'));
        $lastNameTextField->setAttribute('tabindex', $tabindex++);
        $companyTextField = new TextField('Company', _t('OmnipayableForm.COMPANY', 'Company'));
        $companyTextField->setAttribute('tabindex', $tabindex++);
        $emailEmailField = new EmailField('Email', _t('OmnipayableForm.EMAIL', 'Email'));
        $emailEmailField->setAttribute('tabindex', $tabindex++);

        // Create personal details group
        $personalFieldGroup = new FieldGroup();
        $personalFieldGroup->setName('PersonalDetails');
        $personalFieldGroup->setTitle(_t('OmnipayableForm.PERSONALDETAILS', 'Personal Detials'));

        // Add basic fields to personal details group
        $personalFieldGroup->push($firstNameTextField);
        $personalFieldGroup->push($lastNameTextField);
        $personalFieldGroup->push($companyTextField);
        $personalFieldGroup->push($emailEmailField);

        // Add personal details group to fields
        $fields->push($personalFieldGroup);

        // Create credit card detail fields
        $numberCreditCardField = new CreditCardField('Number', _t('OmnipayableForm.NUMBER', 'Card number'));
        $numberCreditCardField->setAttribute('tabindex', $tabindex++);
        $cvvTextField = new TextField('Cvv', _t('OmnipayableForm.CVV', 'Security number'));
        $cvvTextField->setAttribute('tabindex', $tabindex+=3);
        $expiryMonthDropdownField = new DropdownField('ExpiryMonth', _t('OmnipayableForm.EXPIRYMONTH', 'Expiry month'), $this->getMonths());
        $expiryMonthDropdownField->setAttribute('tabindex', $tabindex++);
        $expiryMonthDropdownField->setHasEmptyDefault(true);
        $expiryYearDropdownField = new DropdownField('ExpiryYear', _t('OmnipayableForm.EXPIRYYEAR', 'Expiry year'), $this->getYears(20));
        $expiryYearDropdownField->setAttribute('tabindex', $tabindex++);
        $expiryYearDropdownField->setHasEmptyDefault(true);
        $startMonthDropdownField = new DropdownField('StartMonth', _t('OmnipayableForm.STARTMONTH', 'Start month'), $this->getMonths());
        $startMonthDropdownField->setAttribute('tabindex', $tabindex++);
        $startMonthDropdownField->setHasEmptyDefault(true);
        $startYearDropdownField = new DropdownField('StartYear', _t('OmnipayableForm.STARTYEAR', 'Start year'), $this->getYears(-20));
        $startYearDropdownField->setAttribute('tabindex', $tabindex++);
        $startYearDropdownField->setHasEmptyDefault(true);
        $issueNumberTextField = new TextField('IssueNumber', _t('OmnipayableForm.ISSUENUMBER', 'Issue number'));
        $issueNumberTextField->setAttribute('tabindex', $tabindex++);
        $typeDropdownField = new DropdownField('Type', _t('OmnipayableForm.TYPE', 'Card type'), $this->getCreditCardTypes());
        $typeDropdownField->setAttribute('tabindex', $tabindex++);
        $typeDropdownField->setHasEmptyDefault(true);

        $expiryDateFieldGroup = new FieldGroup();
        $expiryDateFieldGroup->push($expiryMonthDropdownField);
        $expiryDateFieldGroup->push($expiryYearDropdownField);

        $startDateFieldGroup = new FieldGroup();
        $startDateFieldGroup->push($startMonthDropdownField);
        $startDateFieldGroup->push($startYearDropdownField);

        // Create credit card details group
        $creditCardFieldGroup = new FieldGroup();
        $creditCardFieldGroup->setName('CardDetails');
        $creditCardFieldGroup->setTitle(_t('OmnipayableForm.CREDITCARDDETAILS', 'Card Detials'));

        // Add credit card fields to credit card details group
        $creditCardFieldGroup->push($numberCreditCardField);
        $creditCardFieldGroup->push($cvvTextField);
        $creditCardFieldGroup->push($expiryDateFieldGroup);
        $creditCardFieldGroup->push($startDateFieldGroup);
        $creditCardFieldGroup->push($issueNumberTextField);
        $creditCardFieldGroup->push($typeDropdownField);

        // Add credit card details group to fields
        $fields->push($creditCardFieldGroup);

        // Create billing address fields
        $billingAddress1TextField = new TextField('BillingAddress1', _t('OmnipayableForm.BILLINGADDRESS1', 'Address 1'));
        $billingAddress1TextField->setAttribute('tabindex', $tabindex++);
        $billingAddress2TextField = new TextField('BillingAddress2', _t('OmnipayableForm.BILLINGADDRESS2', 'Address 2'));
        $billingAddress2TextField->setAttribute('tabindex', $tabindex++);
        $billingCity = new TextField('BillingCity', _t('OmnipayableForm.BILLINGCITY', 'City'));
        $billingCity->setAttribute('tabindex', $tabindex++);
        $billingPostcode = new TextField('BillingPostcode', _t('OmnipayableForm.BILLINGPOSTCODE', 'Postcode'));
        $billingPostcode->setAttribute('tabindex', $tabindex++);
        $billingState = new TextField('BillingState', _t('OmnipayableForm.BILLINGSTATE', 'State'));
        $billingState->setAttribute('tabindex', $tabindex++);
        $billingCountry = new CountryDropdownField('BillingCountry', _t('OmnipayableForm.BILLINGCOUNTRY', 'Country'));
        $billingCountry->setAttribute('tabindex', $tabindex++);
        $billingPhone = new PhoneNumberField('BillingPhone', _t('OmnipayableForm.BILLINGPHONE', 'Phone'));
        $billingPhone->setAttribute('tabindex', $tabindex++);

        // Create billing details group
        $billingFieldGroup = new FieldGroup();
        $billingFieldGroup->setName('BillingAddress');
        $billingFieldGroup->setTitle(_t('OmnipayableForm.BILLING', 'Billing Address'));

        // Add billiing fields to billing group
        $billingFieldGroup->push($billingAddress1TextField);
        $billingFieldGroup->push($billingAddress2TextField);
        $billingFieldGroup->push($billingCity);
        $billingFieldGroup->push($billingPostcode);
        $billingFieldGroup->push($billingState);
        $billingFieldGroup->push($billingCountry);
        $billingFieldGroup->push($billingPhone);

        // Add billing details group to fields
        $fields->push($billingFieldGroup);

        // Create shipping address fields
        $shippingAddress1TextField = new TextField('ShippingAddress1', _t('OmnipayableForm.SHIPPINGADDRESS1', 'Address 1'));
        $shippingAddress1TextField->setAttribute('tabindex', $tabindex++);
        $shippingAddress2TextField = new TextField('ShippingAddress2', _t('OmnipayableForm.SHIPPINGADDRESS2', 'Address 2'));
        $shippingAddress2TextField->setAttribute('tabindex', $tabindex++);
        $shippingCity = new TextField('ShippingCity', _t('OmnipayableForm.SHIPPINGCITY', 'City'));
        $shippingCity->setAttribute('tabindex', $tabindex++);
        $shippingPostcode = new TextField('ShippingPostcode', _t('OmnipayableForm.SHIPPINGPOSTCODE', 'Postcode'));
        $shippingPostcode->setAttribute('tabindex', $tabindex++);
        $shippingState = new TextField('ShippingState', _t('OmnipayableForm.SHIPPINGSTATE', 'State'));
        $shippingState->setAttribute('tabindex', $tabindex++);
        $shippingCountry = new CountryDropdownField('ShippingCountry', _t('OmnipayableForm.SHIPPINGCOUNTRY', 'Country'));
        $shippingCountry->setAttribute('tabindex', $tabindex++);
        $shippingPhone = new PhoneNumberField('ShippingPhone', _t('OmnipayableForm.SHIPPINGPHONE', 'Phone'));
        $shippingPhone->setAttribute('tabindex', $tabindex++);

        // Create shipping details group
        $shippingFieldGroup = new FieldGroup();
        $shippingFieldGroup->setName('ShippingAddress');
        $shippingFieldGroup->setTitle(_t('OmnipayableForm.SHIPPING', 'Shipping Address'));

        // Add billiing fields to shipping group
        $shippingFieldGroup->push($shippingAddress1TextField);
        $shippingFieldGroup->push($shippingAddress2TextField);
        $shippingFieldGroup->push($shippingCity);
        $shippingFieldGroup->push($shippingPostcode);
        $shippingFieldGroup->push($shippingState);
        $shippingFieldGroup->push($shippingCountry);
        $shippingFieldGroup->push($shippingPhone);

        // Add shipping details group to fields
        $fields->push($shippingFieldGroup);

        return $fields;
    }

    /**
     * Primary method for generating the form fields used.
     * Includes an extension to allow custom extensions.
     *
     * @return FieldList
     */
    protected function getPaymentFields()
    {
        $fields = new FieldList();

        $fields->merge($this->getCreditCardFields());

        // Allow easy customisatin of the payment fields
        $this->extend('updatePaymentFields', $fields);

        return $fields;
    }

    /**
     * Primary method for generating the form actions.
     * Includes an extension to allow custom extensions.
     *
     * @return FieldList
     */
    protected function getPaymentActions()
    {
        $actions = new FieldList();

        $this->extend('updatePaymentActions', $actions);

        $actions->push(new FormAction('doProcessPayment', _t('Omnipayable.PAY', 'Pay')));

        return $actions;
    }

    /**
     * Primary method for generating the required fields.
     * Includes an extension to allow custom extensions.
     *
     * @return RequiredFields
     */
    protected function getRequiredFields()
    {
        $required = array();

        $this->extend('updateRequiredFields', $required);

        return new RequiredFields($required);
    }

    /**
     * Process the form data in order to ensure that it is in a format that the gateway can use.
     * This method is useful when extending the form for specific gateways.
     *
     * @param array $data
     * @return array
     */
    protected function processPaymentData($data)
    {
        $creditCardData = array();
        $creditCardFields = $this->getCreditCardFields()->dataFields();

        foreach ($creditCardFields as $fieldName => $field) {
            // If there is no value for this field then skip
            if (!isset($data[$fieldName])) {
                continue;
            }
            // Process the field value through the form field
            $field->setValue($data[$fieldName]);
            $creditCardData[$fieldName] = $field->dataValue();
            unset($data[$fieldName]);
        }

        $data['Card'] = $creditCardData;

        return $data;
    }
}

/**
 * OmnipayableForm_GoCardless.
 *
 * @author Tom Densham <tom.densham@studiobonito.co.uk>
 * @copyright (c) 2012, Studio Bonito Ltd.
 * @version 1.0
 */
class OmnipayableForm_GoCardless extends OmnipayableForm
{
    protected function processPaymentData($data)
    {
        $data = parent::processPaymentData($data);

        $returnUrl = Controller::join_links(
            Director::absoluteBaseURL(),
            $this->controller->Link(),
            'PaymentForm', 'doProcessPaymentRedirect',
            "?BackURL={$this->request->getHeader('Referer')}"
        );

        $data['returnUrl'] = $returnUrl;

        return $data;
    }

    protected function getPaymentFields()
    {
        $fields = parent::getPaymentFields();

        $fields->removeByName('CardDetails');
        $fields->removeByName('ShippingAddress');

        return $fields;
    }
}

/**
 * OmnipayableForm_PayPal_Express.
 *
 * @author Tom Densham <tom.densham@studiobonito.co.uk>
 * @copyright (c) 2012, Studio Bonito Ltd.
 * @version 1.0
 */
class OmnipayableForm_PayPal_Express extends OmnipayableForm
{
    protected function processPaymentData($data)
    {
        $data = parent::processPaymentData($data);

        $returnUrl = Controller::join_links(
            Director::absoluteBaseURL(),
            $this->controller->Link(),
            'PaymentForm', 'doProcessPaymentRedirect',
            "?BackURL={$this->request->getHeader('Referer')}"
        );

        $data['returnUrl'] = $returnUrl;

        $data['cancelUrl'] = $returnUrl;

        return $data;
    }

    protected function getPaymentFields()
    {
        $fields = parent::getPaymentFields();

        $fields->removeByName('CardDetails');
        $fields->removeByName('ShippingAddress');

        return $fields;
    }
}

/**
 * OmnipayableForm_PayPal_Pro.
 *
 * @author Tom Densham <tom.densham@studiobonito.co.uk>
 * @copyright (c) 2012, Studio Bonito Ltd.
 * @version 1.0
 */
class OmnipayableForm_PayPal_Pro extends OmnipayableForm
{
    protected function processPaymentData($data)
    {
        $data = parent::processPaymentData($data);

        return $data;
    }

    protected function getCreditCardTypes()
    {
        return array(
            'visa'               => 'Visa',
            'mastercard'         => 'Mastercard',
            'discover'           => 'Discover',
            'americanexpress'    => 'American Express',
            'maestro'            => 'Maestro'
        );
    }

    protected function getPaymentFields()
    {
        $fields = parent::getPaymentFields();

        return $fields;
    }

    protected function getRequiredFields()
    {
        $require = parent::getRequiredFields();

        $extraRequiredFields = new RequiredFields(array(
            'FirstName',
            'LastName',
            'Number',
            'Cvv',
            'ExpiryMonth',
            'ExpiryYear',
            'Type',
            'BillingAddress1',
            'BillingCity',
            'BillingState',
            'BillingCountry',
            'BillingPostcode'
        ));

        $require->appendRequiredFields($extraRequiredFields);

        return $require;
    }
}

/**
 * OmnipayableForm_Stripe.
 *
 * @author Tom Densham <tom.densham@studiobonito.co.uk>
 * @copyright (c) 2012, Studio Bonito Ltd.
 * @version 1.0
 */
class OmnipayableForm_Stripe extends OmnipayableForm
{
    protected function getCreditCardTypes()
    {
        return array(
            'visa'            => 'Visa',
            'mastercard'      => 'Mastercard',
            'discover'        => 'Discover',
            'americanexpress' => 'American Express',
            'diners_club'     => 'Diners Club',
            'jcb'             => 'JCB'
        );
    }

    protected function getPaymentFields()
    {
        $fields = parent::getPaymentFields();

        $fields->removeByName('Company');
        $fields->removeByName('Email');
        $fields->removeByName('StartMonth');
        $fields->removeByName('StartYear');
        $fields->removeByName('IssueNumber');
        $fields->removeByName('Type');
        $fields->removeByName('BillingPhone');
        $fields->removeByName('ShippingAddress');

        return $fields;
    }

    protected function getRequiredFields()
    {
        $require = parent::getRequiredFields();

        $extraRequiredFields = new RequiredFields(array(
            'FirstName',
            'LastName',
            'Number',
            'Cvv',
            'ExpiryMonth',
            'ExpiryYear'
        ));

        $require->appendRequiredFields($extraRequiredFields);

        return $require;
    }
}
