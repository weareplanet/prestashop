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
 * Webhook processor to handle refund state transitions.
 */
class WeArePlanetWebhookRefund extends WeArePlanetWebhookOrderrelatedabstract
{

    /**
     * Processes the received order related webhook request.
     *
     * @param WeArePlanetWebhookRequest $request
     */
    public function process(WeArePlanetWebhookRequest $request)
    {
        parent::process($request);
        $refund = $this->loadEntity($request);
        $refundJob = WeArePlanetModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getState() == WeArePlanetModelRefundjob::STATE_APPLY) {
            WeArePlanetServiceRefund::instance()->applyRefundToShop($refundJob->getId());
        }
    }

    /**
     *
     * @see WeArePlanetWebhookOrderrelatedabstract::loadEntity()
     * @return \WeArePlanet\Sdk\Model\Refund
     */
    protected function loadEntity(WeArePlanetWebhookRequest $request)
    {
        $refundService = new \WeArePlanet\Sdk\Service\RefundService(
            WeArePlanetHelper::getApiClient()
        );
        return $refundService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($refund)
    {
        /* @var \WeArePlanet\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getMerchantReference();
    }

    protected function getTransactionId($refund)
    {
        /* @var \WeArePlanet\Sdk\Model\Refund $refund */
        return $refund->getTransaction()->getId();
    }

    protected function processOrderRelatedInner(Order $order, $refund)
    {
        /* @var \WeArePlanet\Sdk\Model\Refund $refund */
        switch ($refund->getState()) {
            case \WeArePlanet\Sdk\Model\RefundState::FAILED:
                $this->failed($refund, $order);
                break;
            case \WeArePlanet\Sdk\Model\RefundState::SUCCESSFUL:
                $this->refunded($refund, $order);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function failed(\WeArePlanet\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = WeArePlanetModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(WeArePlanetModelRefundjob::STATE_FAILURE);
            $refundJob->setRefundId($refund->getId());
            if ($refund->getFailureReason() != null) {
                $refundJob->setFailureReason($refund->getFailureReason()
                    ->getDescription());
            }
            $refundJob->save();
        }
    }

    protected function refunded(\WeArePlanet\Sdk\Model\Refund $refund, Order $order)
    {
        $refundJob = WeArePlanetModelRefundjob::loadByExternalId(
            $refund->getLinkedSpaceId(),
            $refund->getExternalId()
        );
        if ($refundJob->getId()) {
            $refundJob->setState(WeArePlanetModelRefundjob::STATE_APPLY);
            $refundJob->setRefundId($refund->getId());
            $refundJob->save();
        }
    }
}
