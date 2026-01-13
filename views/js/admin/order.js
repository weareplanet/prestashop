/**
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */
jQuery(function ($) {

    function getOrderIdFromUrl(string)
    {
        let urlSegment = string.split('weareplanet')[1];
        return urlSegment.split('/')[1]
    }
    
    function initialiseDocumentButtons()
    {
        if ($('[data-original-title="Download WeArePlanet Invoice"]').length) {
            $('[data-original-title="Download Packing Slip"]').click(function (e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(weareplanet_admin_token + "&action=weArePlanetPackingSlip&id_order=" + id_order, "_blank");
            });
        
            $('[data-original-title="Download WeArePlanet Invoice"]').click(function (e) {
                e.preventDefault();
                let id_order = getOrderIdFromUrl($(this).attr('href'));
                window.open(weareplanet_admin_token + "&action=weArePlanetInvoice&id_order=" + id_order, "_blank");
            });
        
            $('#order_grid_table tr').each(function () {
                let $this = $(this);
                let $row = $this.closest('tr');
                let isWPayment = "0";
                let $paymentStatusCol = $row.find('.column-osname');
                let isWPaymentCol = $row.find('.column-is_w_payment').html();
                if (isWPaymentCol) {
                    isWPayment = isWPaymentCol.trim();
                }
                let paymentStatusText = $paymentStatusCol.find('.btn').text();
                if (!paymentStatusText.includes("Payment accepted") || isWPayment.includes("0")) {
                    $row.find('[data-original-title="Download WeArePlanet Invoice"]').hide();
                    $row.find('[data-original-title="Download Packing Slip"]').hide();
                }
            });
        }
    }

    function hideIsWPaymentColumn()
    {
        $('th').each(function () {
            let $this = $(this);
            if ($this.html().includes("is_w_payment")) {
                $('table tr').find('td:eq(' + $this.index() + '),th:eq(' + $this.index() + ')').remove();
                return false;
            }
        });
    }
    
    function moveWeArePlanetDocuments()
    {
        var documentsTab = $('#weareplanet_documents_tab');
        documentsTab.children('a').addClass('nav-link');
    }
    
    function moveWeArePlanetActionsAndInfo()
    {
        var managementBtn = $('a.weareplanet-management-btn');
        var managementInfo = $('span.weareplanet-management-info');
        var orderActions = $('div.order-actions');
        var panel = $('div.panel');
        
        managementBtn.each(function (key, element) {
            $(element).detach();
            orderActions.find('.order-navigation').before(element);
        });
        managementInfo.each(function (key, element) {
            $(element).detach();
            orderActions.find('.order-navigation').before(element);
        });
        //to get the styling of prestashop we have to add this
        managementBtn.after("&nbsp;\n");
        managementInfo.after("&nbsp;\n");
    }
    
    function registerWeArePlanetActions()
    {
        $('#weareplanet_update').off('click.weareplanet').on(
            'click.weareplanet',
            updateWeArePlanet
        );
        $('#weareplanet_void').off('click.weareplanet').on(
            'click.weareplanet',
            showWeArePlanetVoid
        );
        $("#weareplanet_completion").off('click.weareplanet').on(
            'click.weareplanet',
            showWeArePlanetCompletion
        );
        $('#weareplanet_completion_submit').off('click.weareplanet').on(
            'click.weareplanet',
            executeWeArePlanetCompletion
        );
    }
    
    function showWeArePlanetInformationSuccess(msg)
    {
        showWeArePlanetInformation(msg, weareplanet_msg_general_title_succes, weareplanet_btn_info_confirm_txt, 'dark_green', function () {
            window.location.replace(window.location.href);});
    }
    
    function showWeArePlanetInformationFailures(msg)
    {
        showWeArePlanetInformation(msg, weareplanet_msg_general_title_error, weareplanet_btn_info_confirm_txt, 'dark_red', function () {
            window.location.replace(window.location.href);});
    }
    
    function showWeArePlanetInformation(msg, title, btnText, theme, callback)
    {
        $.jAlert({
            'type': 'modal',
            'title': title,
            'content': msg,
            'theme': theme,
            'replaceOtherAlerts': true,
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': btnText,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': callback
            }
            ],
            'onClose': callback
        });
    }
    
    function updateWeArePlanet()
    {
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    weArePlanetUpdateUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    location.reload();
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showWeArePlanetInformation(response.message, msg_weareplanet_confirm_txt);
                    }
                    return;
                }
                showWeArePlanetInformation(weareplanet_msg_general_error, msg_weareplanet_confirm_txt);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showWeArePlanetInformation(weareplanet_msg_general_error, msg_weareplanet_confirm_txt);
            }
        });
    }
    
        
    function showWeArePlanetVoid(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': weareplanet_void_title,
            'content': $('#weareplanet_void_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': weareplanet_void_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': weareplanet_void_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick':  executeWeArePlanetVoid

            }
            ],
            'theme':'blue'
        });
        return false;
    }

    function executeWeArePlanetVoid()
    {
        showWeArePlanetSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    weArePlanetVoidUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showWeArePlanetInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showWeArePlanetInformationFailures(response.message);
                        return;
                    }
                }
                showWeArePlanetInformationFailures(weareplanet_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showWeArePlanetInformationFailures(weareplanet_msg_general_error);
            }
        });
        return false;
    }
    
    
    function showWeArePlanetSpinner()
    {
        $.jAlert({
            'type': 'modal',
            'title': false,
            'content': '<div class="weareplanet-loader"></div>',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'class': 'unnoticeable',
            'replaceOtherAlerts': true
        });
    
    }
    
    function showWeArePlanetCompletion(e)
    {
        e.preventDefault();
        $.jAlert({
            'type': 'modal',
            'title': weareplanet_completion_title,
            'content': $('#weareplanet_completion_msg').text(),
            'class': 'multiple_buttons',
            'closeOnClick': false,
            'closeOnEsc': false,
            'closeBtn': false,
            'btns': [
            {
                'text': weareplanet_completion_btn_deny_txt,
                'closeAlert': true,
                'theme': 'black'
            },
            {
                'text': weareplanet_completion_btn_confirm_txt,
                'closeAlert': true,
                'theme': 'blue',
                'onClick': executeWeArePlanetCompletion
            }
            ],
            'theme':'blue'
        });

        return false;
    }
    
    
    function executeWeArePlanetCompletion()
    {
        showWeArePlanetSpinner();
        $.ajax({
            type:   'POST',
            dataType:   'json',
            url:    weArePlanetCompletionUrl,
            success:    function (response, textStatus, jqXHR) {
                if ( response.success === 'true' ) {
                    showWeArePlanetInformationSuccess(response.message);
                    return;
                } else if ( response.success === 'false' ) {
                    if (response.message) {
                        showWeArePlanetInformationFailures(response.message);
                        return;
                    }
                }
                showWeArePlanetInformationFailures(weareplanet_msg_general_error);
            },
            error:  function (jqXHR, textStatus, errorThrown) {
                showWeArePlanetInformationFailures(weareplanet_msg_general_error);
            }
        });
        return false;
    }
    
    function weArePlanetTotalRefundChanges()
    {
        var generateDiscount =  $('.standard_refund_fields').find('#generateDiscount').attr("checked") === 'checked';
        var sendOffline = $('#weareplanet_refund_offline_cb_total').attr("checked") === 'checked';
        weArePlanetRefundChanges('total', generateDiscount, sendOffline);
    }
    
    function weArePlanetPartialRefundChanges()
    {
    
        var generateDiscount = $('.partial_refund_fields').find('#generateDiscountRefund').attr("checked") === 'checked';
        var sendOffline = $('#weareplanet_refund_offline_cb_partial').attr("checked")  === 'checked';
        weArePlanetRefundChanges('partial', generateDiscount, sendOffline);
    }
    
    function weArePlanetRefundChanges(type, generateDiscount, sendOffline)
    {
        if (generateDiscount) {
            $('#weareplanet_refund_online_text_'+type).css('display','none');
            $('#weareplanet_refund_offline_span_'+type).css('display','block');
            if (sendOffline) {
                $('#weareplanet_refund_offline_text_'+type).css('display','block');
                $('#weareplanet_refund_no_text_'+type).css('display','none');
            } else {
                $('#weareplanet_refund_no_text_'+type).css('display','block');
                $('#weareplanet_refund_offline_text_'+type).css('display','none');
            }
        } else {
            $('#weareplanet_refund_online_text_'+type).css('display','block');
            $('#weareplanet_refund_no_text_'+type).css('display','none');
            $('#weareplanet_refund_offline_text_'+type).css('display','none');
            $('#weareplanet_refund_offline_span_'+type).css('display','none');
            $('#weareplanet_refund_offline_cb_'+type).attr('checked', false);
        }
    }
    
    function handleWeArePlanetLayoutChanges()
    {
        var addVoucher = $('#add_voucher');
        var addProduct = $('#add_product');
        var editProductChangeLink = $('.edit_product_change_link');
        var descOrderStandardRefund = $('#desc-order-standard_refund');
        var standardRefundFields = $('.standard_refund_fields');
        var partialRefundFields = $('.partial_refund_fields');
        var descOrderPartialRefund = $('#desc-order-partial_refund');

        if ($('#weareplanet_is_transaction').length > 0) {
            addVoucher.remove();
        }
        if ($('#weareplanet_remove_edit').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            $('.panel-vouchers').find('i.icon-minus-sign').closest('a').remove();
        }
        if ($('#weareplanet_remove_cancel').length > 0) {
            descOrderStandardRefund.remove();
        }
        if ($('#weareplanet_changes_refund').length > 0) {
            $('#refund_total_3').closest('div').remove();
            standardRefundFields.find('div.form-group').after($('#weareplanet_refund_online_text_total'));
            standardRefundFields.find('div.form-group').after($('#weareplanet_refund_offline_text_total'));
            standardRefundFields.find('div.form-group').after($('#weareplanet_refund_no_text_total'));
            standardRefundFields.find('#spanShippingBack').after($('#weareplanet_refund_offline_span_total'));
            standardRefundFields.find('#generateDiscount').off('click.weareplanet').on('click.weareplanet', weArePlanetTotalRefundChanges);
            $('#weareplanet_refund_offline_cb_total').on('click.weareplanet', weArePlanetTotalRefundChanges);
        
            $('#refund_3').closest('div').remove();
            partialRefundFields.find('button').before($('#weareplanet_refund_online_text_partial'));
            partialRefundFields.find('button').before($('#weareplanet_refund_offline_text_partial'));
            partialRefundFields.find('button').before($('#weareplanet_refund_no_text_partial'));
            partialRefundFields.find('#generateDiscountRefund').closest('p').after($('#weareplanet_refund_offline_span_partial'));
            partialRefundFields.find('#generateDiscountRefund').off('click.weareplanet').on('click.weareplanet', weArePlanetPartialRefundChanges);
            $('#weareplanet_refund_offline_cb_partial').on('click.weareplanet', weArePlanetPartialRefundChanges);
        }
        if ($('#weareplanet_completion_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#weareplanet_void_pending').length > 0) {
            addProduct.remove();
            addVoucher.remove();
            editProductChangeLink.closest('div').remove();
            descOrderPartialRefund.remove();
            descOrderStandardRefund.remove();
        }
        if ($('#weareplanet_refund_pending').length > 0) {
            descOrderStandardRefund.remove();
            descOrderPartialRefund.remove();
        }
        moveWeArePlanetDocuments();
        moveWeArePlanetActionsAndInfo();
    }
    
    function init()
    {
        handleWeArePlanetLayoutChanges();
        registerWeArePlanetActions();
        initialiseDocumentButtons();
        hideIsWPaymentColumn();
    }
    
    init();
});
