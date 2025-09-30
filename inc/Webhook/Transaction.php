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

/**
 * Webhook processor to handle transaction state transitions.
 */
class WeArePlanetWebhookTransaction extends WeArePlanetWebhookOrderrelatedabstract
{

    /**
     *
     * @see WeArePlanetWebhookOrderrelatedabstract::loadEntity()
     * @return \WeArePlanet\Sdk\Model\Transaction
     */
    protected function loadEntity(WeArePlanetWebhookRequest $request)
    {
        $transactionService = new \WeArePlanet\Sdk\Service\TransactionService(
            WeArePlanetHelper::getApiClient()
        );
        return $transactionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($transaction)
    {
        /* @var \WeArePlanet\Sdk\Model\Transaction $transaction */
        return $transaction->getMerchantReference();
    }

    protected function getTransactionId($transaction)
    {
        /* @var \WeArePlanet\Sdk\Model\Transaction $transaction */
        return $transaction->getId();
    }

    protected function processOrderRelatedInner(Order $order, $transaction)
    {
        /* @var \WeArePlanet\Sdk\Model\Transaction $transaction */
        $transactionInfo = WeArePlanetModelTransactioninfo::loadByOrderId($order->id);
        if ($transaction->getState() != $transactionInfo->getState()) {
            switch ($transaction->getState()) {
                case \WeArePlanet\Sdk\Model\TransactionState::AUTHORIZED:
                    $this->authorize($transaction, $order);
                    break;
                case \WeArePlanet\Sdk\Model\TransactionState::DECLINE:
                    $this->decline($transaction, $order);
                    break;
                case \WeArePlanet\Sdk\Model\TransactionState::FAILED:
                    $this->failed($transaction, $order);
                    break;
                case \WeArePlanet\Sdk\Model\TransactionState::FULFILL:
                    $this->authorize($transaction, $order);
                    $this->fulfill($transaction, $order);
                    break;
                case \WeArePlanet\Sdk\Model\TransactionState::VOIDED:
                    $this->voided($transaction, $order);
                    break;
                case \WeArePlanet\Sdk\Model\TransactionState::COMPLETED:
                    $this->waiting($transaction, $order);
                    break;
                default:
                    // Nothing to do.
                    break;
            }
        }
    }

    protected function authorize(\WeArePlanet\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (WeArePlanetHelper::getOrderMeta($sourceOrder, 'authorized')) {
            return;
        }
        // Do not send emails for this status update
        WeArePlanetBasemodule::startRecordingMailMessages();
        WeArePlanetHelper::updateOrderMeta($sourceOrder, 'authorized', true);
        $authorizedStatusId = Configuration::get(WeArePlanetBasemodule::CK_STATUS_AUTHORIZED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($authorizedStatusId);
            $order->save();
        }
        WeArePlanetBasemodule::stopRecordingMailMessages();
        if (Configuration::get(WeArePlanetBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Send stored messages
            $messages = WeArePlanetHelper::getOrderEmails($sourceOrder);
            if (count($messages) > 0) {
                if (method_exists('Mail', 'sendMailMessageWithoutHook')) {
                    foreach ($messages as $message) {
                        Mail::sendMailMessageWithoutHook($message, false);
                    }
                }
            }
        }
        WeArePlanetHelper::deleteOrderEmails($order);
        // Cleanup carts
        $originalCartId = WeArePlanetHelper::getOrderMeta($order, 'originalCart');
        if (! empty($originalCartId)) {
            $cart = new Cart($originalCartId);
            $cart->delete();
        }
        WeArePlanetServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function waiting(\WeArePlanet\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        WeArePlanetBasemodule::startRecordingMailMessages();
        $waitingStatusId = Configuration::get(WeArePlanetBasemodule::CK_STATUS_COMPLETED);
        if (! WeArePlanetHelper::getOrderMeta($sourceOrder, 'manual_check')) {
            $orders = $sourceOrder->getBrother();
            $orders[] = $sourceOrder;
            foreach ($orders as $order) {
                $order->setCurrentState($waitingStatusId);
                $order->save();
            }
        }
        WeArePlanetBasemodule::stopRecordingMailMessages();
        WeArePlanetServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function decline(\WeArePlanet\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(WeArePlanetBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            WeArePlanetBasemodule::startRecordingMailMessages();
        }

        $canceledStatusId = Configuration::get(WeArePlanetBasemodule::CK_STATUS_DECLINED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        WeArePlanetBasemodule::stopRecordingMailMessages();
        WeArePlanetServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function failed(\WeArePlanet\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        // Do not send email
        WeArePlanetBasemodule::startRecordingMailMessages();
        $errorStatusId = Configuration::get(WeArePlanetBasemodule::CK_STATUS_FAILED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($errorStatusId);
            $order->save();
        }
        WeArePlanetBasemodule::stopRecordingMailMessages();
        WeArePlanetHelper::deleteOrderEmails($sourceOrder);
        WeArePlanetServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function fulfill(\WeArePlanet\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(WeArePlanetBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            WeArePlanetBasemodule::startRecordingMailMessages();
        }
        $payedStatusId = Configuration::get(WeArePlanetBasemodule::CK_STATUS_FULFILL);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($payedStatusId);
            if (empty($order->invoice_date) || $order->invoice_date == '0000-00-00 00:00:00') {
                // Make sure invoice date is set, otherwise prestashop ignores the order in the statistics
                $order->invoice_date = date('Y-m-d H:i:s');
            }
            $order->save();
        }
        WeArePlanetBasemodule::stopRecordingMailMessages();
        WeArePlanetServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }

    protected function voided(\WeArePlanet\Sdk\Model\Transaction $transaction, Order $sourceOrder)
    {
        if (! Configuration::get(WeArePlanetBasemodule::CK_MAIL, null, null, $sourceOrder->id_shop)) {
            // Do not send email
            WeArePlanetBasemodule::startRecordingMailMessages();
        }
        $canceledStatusId = Configuration::get(WeArePlanetBasemodule::CK_STATUS_VOIDED);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($canceledStatusId);
            $order->save();
        }
        WeArePlanetBasemodule::stopRecordingMailMessages();
        WeArePlanetServiceTransaction::instance()->updateTransactionInfo($transaction, $sourceOrder);
    }
}
