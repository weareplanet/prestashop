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

class WeArePlanetCheckoutModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $methodId = Tools::getValue('methodId', null);
        $cart = $this->context->cart;
        try {
            WeArePlanetFeehelper::removeFeeSurchargeProductsFromCart($cart);
            if ($methodId !== null) {
                WeArePlanetFeehelper::addSurchargeProductToCart($cart);
                $methodConfiguration = new WeArePlanetModelMethodconfiguration($methodId);
                WeArePlanetFeehelper::addFeeProductToCart($methodConfiguration, $cart);
                WeArePlanetServiceTransaction::instance()->getTransactionFromCart($cart);
            }
            $cartHash = WeArePlanetHelper::calculateCartHash($cart);
            $presentedCart = $this->cart_presenter->present($cart);
            $this->assignGeneralPurposeVariables();
            $reponse = array(
                'result' => 'success',
                'cartHash' => $cartHash,
                'preview' => $this->render(
                    'checkout/_partials/cart-summary',
                    array(
                        'cart' => $presentedCart,
                        'static_token' => Tools::getToken(false)
                    )
                )
            );

            if (Configuration::get('PS_FINAL_SUMMARY_ENABLED')) {
                $scope = $this->context->smarty->createData($this->context->smarty);
                $scope->assign(
                    array(
                        'show_final_summary' => Configuration::get('PS_FINAL_SUMMARY_ENABLED'),
                        'cart' => $presentedCart
                    )
                );
                $tpl = $this->context->smarty->createTemplate('checkout/_partials/steps/payment.tpl', $scope);
                $reponse['summary'] = $tpl->fetch();
            }

            ob_end_clean();
            header('Content-Type: application/json');
            $this->ajaxRender(json_encode($reponse));
            exit;
        } catch (Exception $e) {
            $this->context->cookie->pln_error = $this->module->l(
                'There was an issue during the checkout, please try again.',
                'checkout'
            );
            $this->ajaxRender(json_encode(array(
                'result' => 'failure'
            )));
            exit;
        }
    }

    public function setMedia()
    {
        // We do not need styling here
    }

    protected function displayMaintenancePage()
    {
        // We want never to see here the maintenance page.
    }

    protected function displayRestrictedCountryPage()
    {
        // We do not want to restrict the content by any country.
    }

    protected function canonicalRedirection($canonical_url = '')
    {
        // We do not need any canonical redirect
    }
}
