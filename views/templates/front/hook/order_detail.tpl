{*
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div id="weareplanet_documents" style="display:none">
{if !empty($weArePlanetInvoice)}
	<a target="_blank" href="{$weArePlanetInvoice|escape:'html':'UTF-8'}">{l s='Download your %name% invoice as a PDF file.' sprintf=['%name%' => 'WeArePlanet'] mod='weareplanet'}</a>
{/if}
{if !empty($weArePlanetPackingSlip)}
	<a target="_blank" href="{$weArePlanetPackingSlip|escape:'html':'UTF-8'}">{l s='Download your %name% packing slip as a PDF file.' sprintf=['%name%' => 'WeArePlanet'] mod='weareplanet'}</a>
{/if}
</div>
