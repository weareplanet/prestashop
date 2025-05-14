{*
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
<tr>
	<td class="fixed-width-sm center">
		<img class="img-thumbnail" alt="{$method.configurationName|escape:'html':'UTF-8'}" src="{$method.imageUrl|escape:'html':'UTF-8'}" />
	</td>
	<td>
		<div id="anchor{$method.configurationName|escape:'html':'UTF-8'}">
			<div class="method_name">
				{$method.configurationName|escape:'html':'UTF-8'}
			</div>
		</div>
	</td>
	<td class="actions">
		<div class="btn-group-action">
			<div class="btn-group">
				<a class=" btn btn-default" href={$link->getAdminLink('AdminWeArePlanetMethodSettings')|escape:'html':'UTF-8'}&method_id={$method.id|escape:'html':'UTF-8'} title="{l s='Configure' mod='weareplanet'}"><i class="icon-wrench"></i> {l s='Configure' mod='weareplanet'}</a>
			</div>
		</div>
	</td>
</tr>
