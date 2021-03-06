<?php
/**
 * 2007-2017 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2017 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

use HummClasses\Helper\Logger;

require_once(dirname(__FILE__) . '/../../common/HummCommon.php');
require_once(dirname(__FILE__) . '/../../HummClasses/Helper/Logger.php');

/**
 * Class HummprestashopRedirectModuleFrontController
 */
class HummprestashopRedirectModuleFrontController extends ModuleFrontController
{
    /**
     * Do whatever you have to before redirecting the customer on the website of your payment processor.
     */
    const URLS = [
        'AU' => [
            'sandboxURL' => 'https://integration-cart.shophumm.com.au/Checkout?platform=Default',
            'liveURL' => 'https://cart.shophumm.com.au/Checkout?platform=Default',
            'sandbox_refund_address' => 'https://integration-buyerapi.shophumm.com.au/api/ExternalRefund/v1/processrefund',
            'live_refund_address' => 'https://buyerapi.shophumm.com.au/api/ExternalRefund/v1/processrefund',
        ],
        'NZ_Oxipay' => [
            'sandboxURL' => 'https://securesandbox.oxipay.co.nz/Checkout?platform=Default',
            'liveURL' => 'https://secure.oxipay.co.nz/Checkout?platform=Default',
            'sandbox_refund_address' => 'https://portalssandbox.oxipay.co.nz/api/ExternalRefund/processrefund',
            'live_refund_address' => 'https://portals.oxipay.co.nz/api/ExternalRefund/processrefund',
        ],
        'NZ_Humm' => [
            'sandboxURL' => 'https://integration-cart.shophumm.co.nz/Checkout?platform=Default',
            'liveURL' => 'https://cart.shophumm.co.nz/Checkout?platform=Default',
            'sandbox_refund_address' => 'https://integration-buyerapi.shophumm.co.nz/api/ExternalRefund/v1/processrefund',
            'live_refund_address' => 'https://buyerapi.shophumm.co.nz/api/ExternalRefund/v1/processrefund',
        ]
    ];

    /**
     * HummprestashopRedirectModuleFrontController constructor
     */
    public function postProcess()
    {

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $address_billing = new Address($cart->id_address_invoice);
        $address_shipping = new Address($cart->id_address_delivery);
        $country_billing = new Country($address_shipping->id_country);
        $country_shipping = new Country($address_shipping->id_country);
        $state_billing = new State($address_billing->id_state);
        $state_shipping = new State($address_shipping->id_state);
        $customerPhone = $address_billing->phone_mobile ? $address_billing->phone_mobile : ($address_billing->phone ? $address_billing->phone : '');
        $query = array(
            'x_currency' => $this->context->currency->iso_code,
            'x_url_callback' => $this->context->link->getModuleLink('hummprestashop', 'confirmation'),
            'x_url_complete' => $this->context->link->getModuleLink('hummprestashop', 'confirmation'),
            'x_url_cancel' => $this->context->link->getPageLink('order', true, null, "step=3"),
            'x_shop_name' => Configuration::get('PS_SHOP_NAME'),
            'x_shop_country' => $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT')),
            'x_account_id' => Configuration::get('HUMM_MERCHANT_ID'),
            'x_reference' => "{$cart->id}-{$customer->secure_key}",
            'x_invoice' => $cart->id,
            'x_amount' => $cart->getOrderTotal(true, Cart::BOTH),
            'x_customer_first_name' => $customer->firstname,
            'x_customer_last_name' => $customer->lastname,
            'x_customer_email' => $customer->email,
            'x_customer_phone' => $customerPhone,
            'x_customer_billing_address1' => $address_billing->address1,
            'x_customer_billing_address2' => $address_billing->address2,
            'x_customer_billing_city' => $address_billing->city,
            'x_customer_billing_state' => $state_billing->name,
            'x_customer_billing_zip' => $address_billing->postcode,
            'x_customer_billing_country' => $country_billing->iso_code,
            'x_customer_shipping_address1' => $address_shipping->address1,
            'x_customer_shipping_address2' => $address_shipping->address2,
            'x_customer_shipping_city' => $address_shipping->city,
            'x_customer_shipping_state' => $state_shipping->name,
            'x_customer_shipping_zip' => $address_shipping->postcode,
            'x_customer_shipping_country' => $country_shipping->iso_code,
            'x_test' => 'false',
            'x_transaction_timeout' => 120,
            'version_info' => 'Humm_' . HummCommon::HUMM_PLUGIN_VERSION . '_on_PS_' . substr(_PS_VERSION_, 0, 3)
        );
        $signature = HummCommon::generateSignature($query, Configuration::get('HUMM_API_KEY'));
        $query['x_signature'] = $signature;

        $this->context->smarty->assign(array(
            'nbProducts' => $cart->nbProducts(),
            'cust_currency' => $cart->id_currency,
            'currencies' => $this->module->getCurrency((int)$cart->id_currency),
            'total' => $cart->getOrderTotal(true, Cart::BOTH),
            'this_path' => $this->module->getPathUri(),
            'this_path_bw' => $this->module->getPathUri(),
            'form_query' => $this->postToCheckoutTemplate($this->getGatewayUrl(), $query),
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->module->name . '/'
        ));
        Logger::logContent(sprintf("form para log before redirect %s",json_encode($query)));
        $this->setTemplate('module:hummprestashop/views/templates/front/redirect.tpl');
    }

    /**
     * @return string
     */

    protected function getGatewayUrl()
    {
        $gatewayUrl = Configuration::get('HUMM_GATEWAY_URL');
        if (strtolower(substr($gatewayUrl, 0, 4)) == 'http') {
            return $gatewayUrl;
        }
        $title = $this->getTitle();
        $isSandbox = Configuration::get('HUMM_TEST') == 1 ? 'sandboxURL' : 'liveURL';
        Logger::logContent(json_encode(self::URLS[$title][$isSandbox]));
        return self::URLS[$title][$isSandbox];
    }

    /**
     * @param $message
     * @param bool $description
     * @throws PrestaShopException
     */

    protected function displayError($message, $description = false)
    {
        /**
         * Create the breadcrumb for your ModuleFrontController.
         */
        $this->context->smarty->assign('path', '
			<a href="' . $this->context->link->getPageLink('order', null, null, 'step=3') . '">' . $this->module->l('Payment') . '</a>
			<span class="navigation-pipe">&gt;</span>' . $this->module->l('Error'));

        /**
         * Set error message and description for the template.
         */
        array_push($this->errors, $this->module->l($message), $description);

        $this->setTemplate($this->local_path . 'error.tpl');

        return;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $forceHumm = Configuration::get('HUMM_FORCE_HUMM');

        $countryCode = Configuration::get('HUMM_COUNTRY');

        return 'NZ_Humm';

//      if ($countryCode == 'NZ') {
//            if ($forceHumm) {
//                return 'NZ_Humm';
//            } else {
//                return 'NZ_Oxipay';
//            }
//        } else {
//            return 'AU';
//      }
    }

    /**
     * @param $checkoutUrl
     * @param $payload
     * @return string
     * @throws Exception
     */
    protected function postToCheckoutTemplate($checkoutUrl, $payload)
    {

        try {
            $formItem = '';
            $beforeForm = sprintf("%s", "<form id='hummload' action='$checkoutUrl' method='post'>");
            foreach ($payload as $key => $value) {
                $formItem = sprintf("%s %s", $formItem, sprintf("<input type='hidden' id='%s' name='%s' value='%s'/>", $key, $key, htmlspecialchars($value, ENT_QUOTES)));
            }
            $afterForm = sprintf("%s", '</form>');
            $postForm = sprintf("%s %s %s", $beforeForm, $formItem, $afterForm);
            Logger::logContent(sprintf("Start Payment  ---PostFormTemplate: %s", $postForm));
            return $postForm;
        } catch (Exception $e) {
            Logger::logContent(sprintf("PostFormErrors=%s", $e->getMessage()));
            throw new Exception($e->getMessage());
        }

    }
}
