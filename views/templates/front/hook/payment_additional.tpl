{*
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 *}
<div sytle="display:none" class="weareplanet-method-data" data-method-id="{$methodId|escape:'html':'UTF-8'}" data-configuration-id="{$configurationId|escape:'html':'UTF-8'}"></div>
<section>
  {if !empty($description)}
    {* The description has to be unfiltered to dispaly html correcty. We strip unallowed html tags before we assign the variable to smarty *}
    <p>{weareplanet_clean_html text=$description}</p>
  {/if}
  {if !empty($surchargeValues)}
	<span class="weareplanet-surcharge weareplanet-additional-amount"><span class="weareplanet-surcharge-text weareplanet-additional-amount-test">{l s='Minimum Sales Surcharge:' mod='weareplanet'}</span>
		<span class="weareplanet-surcharge-value weareplanet-additional-amount-value">
			{if $priceDisplayTax}
				{Tools::displayPrice($surchargeValues.surcharge_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='weareplanet'}
	        {else}
	        	{Tools::displayPrice($surchargeValues.surcharge_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='weareplanet'}
	        {/if}
       </span>
   </span>
  {/if}
  {if !empty($feeValues)}
	<span class="weareplanet-payment-fee weareplanet-additional-amount"><span class="weareplanet-payment-fee-text weareplanet-additional-amount-test">{l s='Payment Fee:' mod='weareplanet'}</span>
		<span class="weareplanet-payment-fee-value weareplanet-additional-amount-value">
			{if ($priceDisplayTax)}
	          	{Tools::displayPrice($feeValues.fee_total)|escape:'html':'UTF-8'} {l s='(tax excl.)' mod='weareplanet'}
	        {else}
	          	{Tools::displayPrice($feeValues.fee_total_wt)|escape:'html':'UTF-8'} {l s='(tax incl.)' mod='weareplanet'}
	        {/if}
       </span>
   </span>
  {/if}
  
</section>
