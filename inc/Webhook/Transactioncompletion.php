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
 * Webhook processor to handle transaction completion state transitions.
 */
class WeArePlanetWebhookTransactioncompletion extends WeArePlanetWebhookOrderrelatedabstract
{

    /**
     *
     * @see WeArePlanetWebhookOrderrelatedabstract::loadEntity()
     * @return \WeArePlanet\Sdk\Model\TransactionCompletion
     */
    protected function loadEntity(WeArePlanetWebhookRequest $request)
    {
        $completionService = new \WeArePlanet\Sdk\Service\TransactionCompletionService(
            WeArePlanetHelper::getApiClient()
        );
        return $completionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getOrderId($completion)
    {
        /* @var \WeArePlanet\Sdk\Model\TransactionCompletion $completion */
        return $completion->getLineItemVersion()
            ->getTransaction()
            ->getMerchantReference();
    }

    protected function getTransactionId($completion)
    {
        /* @var \WeArePlanet\Sdk\Model\TransactionCompletion $completion */
        return $completion->getLinkedTransaction();
    }

    protected function processOrderRelatedInner(Order $order, $completion)
    {
        /* @var \WeArePlanet\Sdk\Model\TransactionCompletion $completion */
        switch ($completion->getState()) {
            case \WeArePlanet\Sdk\Model\TransactionCompletionState::FAILED:
                $this->update($completion, $order, false);
                break;
            case \WeArePlanet\Sdk\Model\TransactionCompletionState::SUCCESSFUL:
                $this->update($completion, $order, true);
                break;
            default:
                // Nothing to do.
                break;
        }
    }

    protected function update(\WeArePlanet\Sdk\Model\TransactionCompletion $completion, Order $order, $success)
    {
        $completionJob = WeArePlanetModelCompletionjob::loadByCompletionId(
            $completion->getLinkedSpaceId(),
            $completion->getId()
        );
        if (! $completionJob->getId()) {
            // We have no completion job with this id -> the server could not store the id of the completion after
            // sending the request. (e.g. connection issue or crash)
            // We only have on running completion which was not yet processed successfully and use it as it should be
            // the one the webhook is for.

            $completionJob = WeArePlanetModelCompletionjob::loadRunningCompletionForTransaction(
                $completion->getLinkedSpaceId(),
                $completion->getLinkedTransaction()
            );
            if (! $completionJob->getId()) {
                return;
            }
            $completionJob->setCompletionId($completion->getId());
        }

        if ($success) {
            $completionJob->setState(WeArePlanetModelCompletionjob::STATE_SUCCESS);
        } else {
            if ($completion->getFailureReason() != null) {
                $completionJob->setFailureReason($completion->getFailureReason()
                    ->getDescription());
            }
            $completionJob->setState(WeArePlanetModelCompletionjob::STATE_FAILURE);
        }
        $completionJob->save();
    }
}
