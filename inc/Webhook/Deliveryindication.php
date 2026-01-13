<?php
/**
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2026 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

/**
 * Webhook processor to handle delivery indication state transitions.
 */
class WeArePlanetWebhookDeliveryindication extends WeArePlanetWebhookOrderrelatedabstract
{

    /**
     *
     * @see WeArePlanetWebhookOrderrelatedabstract::loadEntity()
     * @return \WeArePlanet\Sdk\Model\DeliveryIndication
     */
    protected function loadEntity(WeArePlanetWebhookRequest $request)
    {
        $deliveryIndicationService = new \WeArePlanet\Sdk\Service\DeliveryIndicationService(
            WeArePlanetHelper::getApiClient()
        );
        return $deliveryIndicationService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($deliveryIndication)
    {
        /* @var \WeArePlanet\Sdk\Model\DeliveryIndication $deliveryIndication */
        return $deliveryIndication->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($deliveryIndication)
    {
        /* @var \WeArePlanet\Sdk\Model\DeliveryIndication $delivery_indication */
        return $deliveryIndication->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $deliveryIndication)
    {
        /* @var \WeArePlanet\Sdk\Model\DeliveryIndication $deliveryIndication */
        switch ($deliveryIndication->getState()) {
            case \WeArePlanet\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED:
                $this->review($order);
                break;
            default:
                break;
        }
    }

    protected function review(Order $sourceOrder)
    {
        WeArePlanetBasemodule::startRecordingMailMessages();
        $manualStatusId = Configuration::get(WeArePlanetBasemodule::CK_STATUS_MANUAL);
        WeArePlanetHelper::updateOrderMeta($sourceOrder, 'manual_check', true);
        $orders = $sourceOrder->getBrother();
        $orders[] = $sourceOrder;
        foreach ($orders as $order) {
            $order->setCurrentState($manualStatusId);
            $order->save();
        }
        WeArePlanetBasemodule::stopRecordingMailMessages();
    }
}
