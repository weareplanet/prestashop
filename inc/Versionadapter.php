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

use PrestaShop\PrestaShop\Adapter\ServiceLocator;

class WeArePlanetVersionadapter
{
    public static function getConfigurationInterface()
    {
        return ServiceLocator::get('\\PrestaShop\\PrestaShop\\Core\\ConfigurationInterface');
    }

    public static function getAddressFactory()
    {
        return ServiceLocator::get('\\PrestaShop\\PrestaShop\\Adapter\\AddressFactory');
    }

    public static function clearCartRuleStaticCache()
    {
	    call_user_func(array(
	      'CartRule',
	      'resetStaticCache'
	    ));
    }

    public static function getAdminOrderTemplate()
    {
	    return 'views/templates/admin/hook/admin_order.tpl';
    }

    public static function isVoucherOnlyWeArePlanet($postData)
    {
	    return isset($postData['cancel_product']['voucher'])
	      && isset($postData['cancel_product']['voucher_refund_type'])
	      && $postData['cancel_product']['voucher'] == 1
	      && $postData['cancel_product']['voucher_refund_type'] == 1
	      && ! isset($postData['cancel_product']['weareplanet_offline'])
	      && ! isset($postData['cancel_product']['credit_slip']);
    }
}
