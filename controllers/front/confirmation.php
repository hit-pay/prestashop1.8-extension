<?php
/**
* 2007-2021 PrestaShop
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once _PS_MODULE_DIR_ . 'hitpay/classes/HitPayPayment.php';

use HitPay\Client;

/**
 * Class HitpayConfirmationModuleFrontController
 */
class HitpayConfirmationModuleFrontController extends ModuleFrontController
{
    /**
     * @return bool|void
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        if ((Tools::isSubmit('cart_id') == false)
            || (Tools::isSubmit('secure_key') == false)
            || (Tools::isSubmit('reference') == false)) {
            exit;
        }

        $cart_id = Tools::getValue('cart_id');
        $secure_key = Tools::getValue('secure_key');
        $status = Tools::getValue('status');

        if ($status == 'canceled') {
            Tools::redirect('index.php?controller=order');
        }

        $cart = new Cart((int) $cart_id);
        $customer = new Customer((int) $cart->id_customer);

        if ($secure_key != $customer->secure_key) {
            $this->context->smarty->assign(
                'errors',
                array(
                    $this->module->l(
                        'An error occured. Please contact the merchant to have more informations'
                    )
                )
            );

            return $this->setTemplate('module:hitpay/views/templates/front/error.tpl');
        }

        try {
            $payment_id = Tools::getValue('reference');
            /**
             * @var HitPayPayment $hitpay_payment
             */
            $saved_payment = HitPayPayment::getById($payment_id);
            if (Validate::isLoadedObject($saved_payment)
                && $saved_payment->status == 'completed'
                && number_format($saved_payment->amount, 2) == number_format($cart->getOrderTotal(), 2)
                && $saved_payment->order_id
            ) {
                Tools::redirect(
                    'index.php?controller=order-confirmation&id_cart='
                    . $saved_payment->cart_id
                    . '&id_module='
                    . $this->module->id
                    . '&id_order='
                    . $saved_payment->order_id
                    . '&key='
                    . $secure_key
                );
            } else {
                $ajax_url = $this->context->link->getModuleLink('hitpay', 'status');
                $this->context->smarty->assign('hitpay_payment_id', $payment_id);
                $this->context->smarty->assign('hitpay_cart_id', $cart_id);
                $this->context->smarty->assign('status_ajax_url', $ajax_url);
                $this->context->smarty->assign('hitpay_img_path', _MODULE_DIR_.'hitpay/views/img/');
                return $this->setTemplate('module:hitpay/views/templates/front/confirmation.tpl');
            }
        } catch (\Exception $e) {
            PrestaShopLogger::addLog(
                date("Y-m-d H:i:s").': '.'HitPay: ' . $e->getMessage(),
                3,
                null,
                'HitPay'
            );

            $this->context->smarty->assign(
                'errors',
                array(
                    $this->module->l('Something went wrong, please contact the merchant')
                )
            );

            return $this->setTemplate('module:hitpay/views/templates/front/error.tpl');
        }
    }
}
