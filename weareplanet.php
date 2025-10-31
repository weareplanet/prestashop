<?php

/**
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

if (!defined('_PS_VERSION_')) {
    exit();
}

use PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType;

require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'weareplanet_autoloader.php');
require_once(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'weareplanet-sdk' . DIRECTORY_SEPARATOR .
    'autoload.php');
class WeArePlanet extends PaymentModule
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name = 'weareplanet';
        $this->tab = 'payments_gateways';
        $this->author = 'wallee AG';
        $this->bootstrap = true;
        $this->need_instance = 0;
        $this->version = '2.0.3';
        $this->displayName = 'WeArePlanet';
        $this->description = $this->l('This PrestaShop module enables to process payments with %s.');
        $this->description = sprintf($this->description, 'WeArePlanet');
        $this->module_key = 'PrestaShop_ProductKey_V8';
        $this->ps_versions_compliancy = array(
            'min' => '8',
            'max' => _PS_VERSION_
        );
        parent::__construct();
        $this->confirmUninstall = sprintf(
            $this->l('Are you sure you want to uninstall the %s module?', 'abstractmodule'),
            'WeArePlanet'
        );

        // Remove Fee Item
        if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
            WeArePlanetFeehelper::removeFeeSurchargeProductsFromCart($this->context->cart);
        }
        if (!empty($this->context->cookie->pln_error)) {
            $errors = $this->context->cookie->pln_error;
            if (is_string($errors)) {
                $this->context->controller->errors[] = $errors;
            } elseif (is_array($errors)) {
                foreach ($errors as $error) {
                    $this->context->controller->errors[] = $error;
                }
            }
            unset($_SERVER['HTTP_REFERER']); // To disable the back button in the error message
            $this->context->cookie->pln_error = null;
        }
    }

    public function addError($error)
    {
        $this->_errors[] = $error;
    }

    public function getContext()
    {
        return $this->context;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function install()
    {
        if (!WeArePlanetBasemodule::checkRequirements($this)) {
            return false;
        }
        if (!parent::install()) {
            return false;
        }
        return WeArePlanetBasemodule::install($this);
    }

    public function uninstall()
    {
        return parent::uninstall() && WeArePlanetBasemodule::uninstall($this);
    }

    public function installHooks()
    {
        return WeArePlanetBasemodule::installHooks($this) && $this->registerHook('paymentOptions') &&
            $this->registerHook('actionFrontControllerSetMedia');
    }

    public function getBackendControllers()
    {
        return array(
            'AdminWeArePlanetMethodSettings' => array(
                'parentId' => Tab::getIdFromClassName('AdminParentPayment'),
                'name' => 'WeArePlanet ' . $this->l('Payment Methods')
            ),
            'AdminWeArePlanetDocuments' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'WeArePlanet ' . $this->l('Documents')
            ),
            'AdminWeArePlanetOrder' => array(
                'parentId' => -1, // No Tab in navigation
                'name' => 'WeArePlanet ' . $this->l('Order Management')
            )
        );
    }

    public function installConfigurationValues()
    {
        return WeArePlanetBasemodule::installConfigurationValues();
    }

    public function uninstallConfigurationValues()
    {
        return WeArePlanetBasemodule::uninstallConfigurationValues();
    }

    public function getContent()
    {
        $output = WeArePlanetBasemodule::handleSaveAll($this);
        $output .= WeArePlanetBasemodule::handleSaveApplication($this);
        $output .= WeArePlanetBasemodule::handleSaveEmail($this);
        $output .= WeArePlanetBasemodule::handleSaveIntegration($this);
        $output .= WeArePlanetBasemodule::handleSaveCartRecreation($this);
        $output .= WeArePlanetBasemodule::handleSaveFeeItem($this);
        $output .= WeArePlanetBasemodule::handleSaveDownload($this);
        $output .= WeArePlanetBasemodule::handleSaveSpaceViewId($this);
        $output .= WeArePlanetBasemodule::handleSaveOrderStatus($this);
        $output .= WeArePlanetBasemodule::displayHelpButtons($this);
        return $output . WeArePlanetBasemodule::displayForm($this);
    }

    public function getConfigurationForms()
    {
        return array(
            WeArePlanetBasemodule::getEmailForm($this),
            WeArePlanetBasemodule::getIntegrationForm($this),
            WeArePlanetBasemodule::getCartRecreationForm($this),
            WeArePlanetBasemodule::getFeeForm($this),
            WeArePlanetBasemodule::getDocumentForm($this),
            WeArePlanetBasemodule::getSpaceViewIdForm($this),
            WeArePlanetBasemodule::getOrderStatusForm($this)
        );
    }

    public function getConfigurationValues()
    {
        return array_merge(
            WeArePlanetBasemodule::getApplicationConfigValues($this),
            WeArePlanetBasemodule::getEmailConfigValues($this),
            WeArePlanetBasemodule::getIntegrationConfigValues($this),
            WeArePlanetBasemodule::getCartRecreationConfigValues($this),
            WeArePlanetBasemodule::getFeeItemConfigValues($this),
            WeArePlanetBasemodule::getDownloadConfigValues($this),
            WeArePlanetBasemodule::getSpaceViewIdConfigValues($this),
            WeArePlanetBasemodule::getOrderStatusConfigValues($this)
        );
    }

    public function getConfigurationKeys()
    {
        return WeArePlanetBasemodule::getConfigurationKeys();
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!isset($params['cart']) || !($params['cart'] instanceof Cart)) {
            return;
        }
        $cart = $params['cart'];
        try {
            $possiblePaymentMethods = WeArePlanetServiceTransaction::instance()->getPossiblePaymentMethods(
                $cart
            );
        } catch (WeArePlanetExceptionInvalidtransactionamount $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 2, null, 'WeArePlanet');
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText(
                $this->l('There is an issue with your cart, some payment methods are not available.')
            );
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:weareplanet/views/templates/front/hook/amount_error.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:weareplanet/views/templates/front/hook/amount_error_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name . "-error");
            return array(
                $paymentOption
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog($e->getMessage() . " CartId: " . $cart->id, 1, null, 'WeArePlanet');
            return array();
        }
        $shopId = $cart->id_shop;
        $language = Context::getContext()->language->language_code;
        $methods = array();
        foreach ($possiblePaymentMethods as $possible) {
            $methodConfiguration = WeArePlanetModelMethodconfiguration::loadByConfigurationAndShop(
                $possible->getSpaceId(),
                $possible->getId(),
                $shopId
            );
            if (!$methodConfiguration->isActive()) {
                continue;
            }
            $methods[] = $methodConfiguration;
        }
        $result = array();

        $this->context->smarty->registerPlugin(
            'function',
            'weareplanet_clean_html',
            array(
                'WeArePlanetSmartyfunctions',
                'cleanHtml'
            )
        );

        foreach (WeArePlanetHelper::sortMethodConfiguration($methods) as $methodConfiguration) {
            $parameters = WeArePlanetBasemodule::getParametersFromMethodConfiguration($this, $methodConfiguration, $cart, $shopId, $language);
            $parameters['priceDisplayTax'] = Group::getPriceDisplayMethod(Group::getCurrent()->id);
            $parameters['iframe'] = $cart->iframe;
            $parameters['orderUrl'] = $this->context->link->getModuleLink(
                'weareplanet',
                'order',
                array(),
                true
            );
            $this->context->smarty->assign($parameters);
            $paymentOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
            $paymentOption->setCallToActionText($parameters['name']);
            $paymentOption->setLogo($parameters['image']);
            $paymentOption->setAction($parameters['link']);
            $paymentOption->setAdditionalInformation(
                $this->context->smarty->fetch(
                    'module:weareplanet/views/templates/front/hook/payment_additional.tpl'
                )
            );
            $paymentOption->setForm(
                $this->context->smarty->fetch(
                    'module:weareplanet/views/templates/front/hook/payment_form.tpl'
                )
            );
            $paymentOption->setModuleName($this->name);
            $result[] = $paymentOption;
        }
        return $result;
    }

    public function hookActionFrontControllerSetMedia($arr)
    {
        if ($this->context->controller->php_self == 'order' || $this->context->controller->php_self == 'cart') {
            $uniqueId = $this->context->cookie->pln_device_id;
            if ($uniqueId == false) {
                $uniqueId = WeArePlanetHelper::generateUUID();
                $this->context->cookie->pln_device_id = $uniqueId;
            }
            $scriptUrl = WeArePlanetHelper::getBaseGatewayUrl() . '/s/' . Configuration::get(
                WeArePlanetBasemodule::CK_SPACE_ID
            ) . '/payment/device.js?sessionIdentifier=' . $uniqueId;
            $this->context->controller->registerJavascript(
                'weareplanet-device-identifier',
                $scriptUrl,
                array(
                    'server' => 'remote',
                    'attributes' => 'async="async"'
                )
            );
        }
        if ($this->context->controller->php_self == 'order') {
            $this->context->controller->registerStylesheet(
                'weareplanet-checkut-css',
                'modules/' . $this->name . '/views/css/frontend/checkout.css'
            );
            $this->context->controller->registerJavascript(
                'weareplanet-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/checkout.js'
            );
            Media::addJsDef(
                array(
                    'weArePlanetCheckoutUrl' => $this->context->link->getModuleLink(
                        'weareplanet',
                        'checkout',
                        array(),
                        true
                    ),
                    'weareplanetMsgJsonError' => $this->l(
                        'The server experienced an unexpected error, you may try again or try to use a different payment method.'
                    )
                )
            );
            if (isset($this->context->cart) && Validate::isLoadedObject($this->context->cart)) {
                try {
                    $jsUrl = WeArePlanetServiceTransaction::instance()->getJavascriptUrl($this->context->cart);
                    $this->context->controller->registerJavascript(
                        'weareplanet-iframe-handler',
                        $jsUrl,
                        array(
                            'server' => 'remote',
                            'priority' => 45,
                            'attributes' => 'id="weareplanet-iframe-handler"'
                        )
                    );
                } catch (Exception $e) {
                }
            }
        }
        if ($this->context->controller->php_self == 'order-detail') {
            $this->context->controller->registerJavascript(
                'weareplanet-checkout-js',
                'modules/' . $this->name . '/views/js/frontend/orderdetail.js'
            );
        }
    }

    public function hookActionAdminControllerSetMedia($arr)
    {
        WeArePlanetBasemodule::hookActionAdminControllerSetMedia($this, $arr);
        $this->context->controller->addCSS(__PS_BASE_URI__ . 'modules/' . $this->name . '/views/css/admin/general.css');
    }

    public function hasBackendControllerDeleteAccess(AdminController $backendController)
    {
        return $backendController->access('delete');
    }

    public function hasBackendControllerEditAccess(AdminController $backendController)
    {
        return $backendController->access('edit');
    }

    /**
     * Show the manual task in the admin bar.
     * The output is moved with javascript to the correct place as better hook is missing.
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader()
    {
        $result = WeArePlanetBasemodule::hookDisplayAdminAfterHeader($this);
        return $result;
    }

    public function hookWeArePlanetSettingsChanged($params)
    {
        return WeArePlanetBasemodule::hookWeArePlanetSettingsChanged($this, $params);
    }

    public function hookActionMailSend($data)
    {
        return WeArePlanetBasemodule::hookActionMailSend($this, $data);
    }

    public function validateOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null,
        $order_reference = null
    ) {
        WeArePlanetBasemodule::validateOrder($this, $id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop, $order_reference);
    }

    public function validateOrderParent(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null,
        $order_reference = null
    ) {
        parent::validateOrder($id_cart, $id_order_state, $amount_paid, $payment_method, $message, $extra_vars, $currency_special, $dont_touch_amount, $secure_key, $shop, $order_reference);
    }

    public function hookDisplayOrderDetail($params)
    {
        return WeArePlanetBasemodule::hookDisplayOrderDetail($this, $params);
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        WeArePlanetBasemodule::hookDisplayBackOfficeHeader($this, $params);
    }

    public function hookDisplayAdminOrderLeft($params)
    {
        return WeArePlanetBasemodule::hookDisplayAdminOrderLeft($this, $params);
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        return WeArePlanetBasemodule::hookDisplayAdminOrderTabOrder($this, $params);
    }

    public function hookDisplayAdminOrderMain($params)
    {
        return WeArePlanetBasemodule::hookDisplayAdminOrderMain($this, $params);
    }

    public function hookActionOrderSlipAdd($params)
    {
        $refundParameters = Tools::getAllValues();

        $order = $params['order'];

        if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
            $idOrder = Tools::getValue('id_order');
            if (!$idOrder) {
                $order = $params['order'];
                $idOrder = (int)$order->id;
            }
            $order = new Order((int) $idOrder);
            if (! Validate::isLoadedObject($order) || $order->module != $module->name) {
                return;
            }
        }

        $strategy = WeArePlanetBackendStrategyprovider::getStrategy();

        if ($strategy->isVoucherOnlyWeArePlanet($order, $refundParameters)) {
            return;
        }

        // need to manually set this here as it's expected downstream
        $refundParameters['partialRefund'] = true;

        $backendController = Context::getContext()->controller;
        $editAccess = 0;

        $access = Profile::getProfileAccess(
            Context::getContext()->employee->id_profile,
            (int) Tab::getIdFromClassName('AdminOrders')
        );
        $editAccess = isset($access['edit']) && $access['edit'] == 1;

        if ($editAccess) {
            try {
                $parsedData = $strategy->simplifiedRefund($refundParameters);
                WeArePlanetServiceRefund::instance()->executeRefund($order, $parsedData);
            } catch (Exception $e) {
                $backendController->errors[] = WeArePlanetHelper::cleanExceptionMessage($e->getMessage());
            }
        } else {
            $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
        }
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        return WeArePlanetBasemodule::hookDisplayAdminOrderTabLink($this, $params);
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        return WeArePlanetBasemodule::hookDisplayAdminOrderContentOrder($this, $params);
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        return WeArePlanetBasemodule::hookDisplayAdminOrderTabContent($this, $params);
    }

    public function hookDisplayAdminOrder($params)
    {
        return WeArePlanetBasemodule::hookDisplayAdminOrder($this, $params);
    }

    public function hookActionAdminOrdersControllerBefore($params)
    {
        return WeArePlanetBasemodule::hookActionAdminOrdersControllerBefore($this, $params);
    }

    public function hookActionObjectOrderPaymentAddBefore($params)
    {
        WeArePlanetBasemodule::hookActionObjectOrderPaymentAddBefore($this, $params);
    }

    public function hookActionOrderEdited($params)
    {
        WeArePlanetBasemodule::hookActionOrderEdited($this, $params);
    }

    public function hookActionOrderGridDefinitionModifier($params)
    {
        WeArePlanetBasemodule::hookActionOrderGridDefinitionModifier($this, $params);
    }

    public function hookActionOrderGridQueryBuilderModifier($params)
    {
        WeArePlanetBasemodule::hookActionOrderGridQueryBuilderModifier($this, $params);
    }

    public function hookActionProductCancel($params)
    {
        if ($params['action'] === CancellationActionType::PARTIAL_REFUND) {
            $idOrder = Tools::getValue('id_order');
            $refundParameters = Tools::getAllValues();

            $order = $params['order'];

            if (!Validate::isLoadedObject($order) || $order->module != $this->name) {
                return;
            }

            $strategy = WeArePlanetBackendStrategyprovider::getStrategy();
            if ($strategy->isVoucherOnlyWeArePlanet($order, $refundParameters)) {
                return;
            }

            // need to manually set this here as it's expected downstream
            $refundParameters['partialRefund'] = true;

            $backendController = Context::getContext()->controller;
            $editAccess = 0;

            $access = Profile::getProfileAccess(
                Context::getContext()->employee->id_profile,
                (int) Tab::getIdFromClassName('AdminOrders')
            );
            $editAccess = isset($access['edit']) && $access['edit'] == 1;

            if ($editAccess) {
                try {
                    $parsedData = $strategy->simplifiedRefund($refundParameters);
                    WeArePlanetServiceRefund::instance()->executeRefund($order, $parsedData);
                } catch (Exception $e) {
                    $backendController->errors[] = WeArePlanetHelper::cleanExceptionMessage($e->getMessage());
                }
            } else {
                $backendController->errors[] = Tools::displayError('You do not have permission to delete this.');
            }
        }
    }
}
