{*
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
*}
{if (isset($showAuthorizedActions) && $showAuthorizedActions)}
	<div style="display:none;" class="hidden-print">
		<a class="btn btn-action weareplanet-management-btn"  id="weareplanet_void">
			<i class="icon-remove"></i>
			{l s='Void' mod='weareplanet'}
		</a>
		<a class="btn btn-action weareplanet-management-btn"  id="weareplanet_completion">
			<i class="icon-check"></i>
			{l s='Completion' mod='weareplanet'}
		</a>
	</div>

	<script type="text/javascript">
		var weareplanet_void_title = "{l s='Are you sure?' mod='weareplanet' js=1}";
		var weareplanet_void_btn_confirm_txt = "{l s='Void Order' mod='weareplanet' js=1}";
		var weareplanet_void_btn_deny_txt = "{l s='No' mod='weareplanet' js=1}";

		var weareplanet_completion_title = "{l s='Are you sure?' mod='weareplanet' js=1}";
		var weareplanet_completion_btn_confirm_txt = "{l s='Complete Order'  mod='weareplanet' js=1}";
		var weareplanet_completion_btn_deny_txt = "{l s='No' mod='weareplanet' js=1}";

		var weareplanet_msg_general_error = "{l s='The server experienced an unexpected error, please try again.'  mod='weareplanet' js=1}";
		var weareplanet_msg_general_title_succes = "{l s='Success'  mod='weareplanet' js=1}";
		var weareplanet_msg_general_title_error = "{l s='Error'  mod='weareplanet' js=1}";
		var weareplanet_btn_info_confirm_txt = "{l s='OK'  mod='weareplanet' js=1}";
	</script>

	<div id="weareplanet_void_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also void the following orders:' mod='weareplanet' js=1}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
							{l s='Order %d' sprintf=$other mod='weareplanet' js=1}
						</a>
					</li>
				{/foreach}
			</ul>
			{l s='If you only want to void this order, we recommend to remove all products from this order.' mod='weareplanet' js=1}
		{else}
			{l s='This action cannot be undone.' mod='weareplanet' js=1}
		{/if}
	</div>

	<div id="weareplanet_completion_msg" class="hidden-print" style="display:none">
		{if !empty($affectedOrders)}
			{l s='This will also complete the following orders:' mod='weareplanet'}
			<ul>
				{foreach from=$affectedOrders item=other}
					<li>
						<a href="{$link->getAdminLink('AdminOrders')|escape:'html':'UTF-8'}&amp;vieworder&amp;id_order={$other|intval}">
							{l s='Order %d' sprintf=$other mod='weareplanet'}
						</a>
					</li>
				{/foreach}
			</ul>
		{else}
			{l s='This finalizes the order, it no longer can be changed.' mod='weareplanet'}
		{/if}
	</div>
{/if}

{if (isset($showUpdateActions) && $showUpdateActions)}
	<div style="display:none;" class="hidden-print">
		<a class="btn btn-default weareplanet-management-btn" id="weareplanet_update">
			<i class="icon-refresh"></i>
			{l s='Update' mod='weareplanet'}
		</a>
	</div>
{/if}


{if isset($isWeArePlanetTransaction)}
	<div style="display:none;" class="hidden-print" id="weareplanet_is_transaction"></div>
{/if}

{if isset($editButtons)}
	<div style="display:none;" class="hidden-print" id="weareplanet_remove_edit"></div>
{/if}

{if isset($cancelButtons)}
	<div style="display:none;" class="hidden-print" id="weareplanet_remove_cancel"></div>
{/if}

{if isset($refundChanges)}
	<div style="display:none;" class="hidden-print" id="weareplanet_changes_refund">
		<p id="weareplanet_refund_online_text_total">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='WeArePlanet' mod='weareplanet'}</p>
		<p id="weareplanet_refund_offline_text_total" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='WeArePlanet' mod='weareplanet'}</p>
		<p id="weareplanet_refund_no_text_total" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='WeArePlanet' mod='weareplanet'}</p>
		<p id="weareplanet_refund_offline_span_total" class="checkbox" style="display: none;">
			<label for="weareplanet_refund_offline_cb_total">
				<input type="checkbox" id="weareplanet_refund_offline_cb_total" name="weareplanet_offline">
				{l s='Send as offline refund to %s.' sprintf='WeArePlanet' mod='weareplanet'}
			</label>
		</p>

		<p id="weareplanet_refund_online_text_partial">{l s='This refund is sent to %s and money is transfered back to the customer.' sprintf='WeArePlanet' mod='weareplanet'}</p>
		<p id="weareplanet_refund_offline_text_partial" style="display:none;">{l s='This refund is sent to %s, but [1]no[/1] money is transfered back to the customer.' tags=['<b>'] sprintf='WeArePlanet' mod='weareplanet'}</p>
		<p id="weareplanet_refund_no_text_partial" style="display:none;">{l s='This refund is [1]not[/1] sent to %s.' tags=['<b>'] sprintf='WeArePlanet' mod='weareplanet'}</p>
		<p id="weareplanet_refund_offline_span_partial" class="checkbox" style="display: none;">
			<label for="weareplanet_refund_offline_cb_partial">
				<input type="checkbox" id="weareplanet_refund_offline_cb_partial" name="weareplanet_offline">
				{l s='Send as offline refund to %s.' sprintf='WeArePlanet' mod='weareplanet'}
			</label>
		</p>
	</div>
{/if}

{if isset($completionPending)}
	<div style="display:none;" class="hidden-print" id="weareplanet_completion_pending">
	<span class="span label label-inactive weareplanet-management-info">
		<i class="icon-refresh"></i>
		{l s='Completion in Process' mod='weareplanet'}
	</span>
	</div>
{/if}

{if isset($voidPending)}
	<div style="display:none;" class="hidden-print" id="weareplanet_void_pending">
	<span class="span label label-inactive weareplanet-management-info">
		<i class="icon-refresh"></i>
		{l s='Void in Process' mod='weareplanet'}
	</span>

	</div>
{/if}

{if isset($refundPending)}
	<div style="display:none;" class="hidden-print" id="weareplanet_refund_pending">
	<span class="span label label-inactive weareplanet-management-info">
		<i class="icon-refresh"></i>
		{l s='Refund in Process' mod='weareplanet'}
	</span>
	</div>
{/if}


<script type="text/javascript">
	{if isset($voidUrl)}
	var weArePlanetVoidUrl = "{$voidUrl|escape:'javascript':'UTF-8'}";
	{/if}
	{if isset($completionUrl)}
	var weArePlanetCompletionUrl = "{$completionUrl|escape:'javascript':'UTF-8'}";
	{/if}
	{if isset($updateUrl)}
	var weArePlanetUpdateUrl = "{$updateUrl|escape:'javascript':'UTF-8'}";
	{/if}

</script>
