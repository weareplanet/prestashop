{*
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<form action="{$orderUrl|escape:'html':'UTF-8'}" class="weareplanet-payment-form" data-method-id="{$methodId|escape:'html':'UTF-8'}">
	<div id="weareplanet-{$methodId|escape:'html':'UTF-8'}">
		<input type="hidden" id="weareplanet-iframe-possible-{$methodId|escape:'html':'UTF-8'}" name="weareplanet-iframe-possible-{$methodId|escape:'html':'UTF-8'}" value="{$iframe|escape:'html':'UTF-8'}" />
		<div id="weareplanet-loader-{$methodId|escape:'html':'UTF-8'}" class="weareplanet-loader"></div>
	</div>
</form>
