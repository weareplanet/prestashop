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

use WeArePlanet\Sdk\Model\TransactionLineItemVersionCreate;

/**
 * This service provides functions to deal with WeArePlanet transaction completions.
 */
class WeArePlanetServiceTransactioncompletion extends WeArePlanetServiceAbstract
{

    /**
     * The transaction completion API service.
     *
     * @var \WeArePlanet\Sdk\Service\TransactionCompletionService
     */
    private $completionService;

    public function executeCompletion($order)
    {
        $currentCompletionJob = null;
        try {
            WeArePlanetHelper::startDBTransaction();
            $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactioncompletion'
                    )
                );
            }

            WeArePlanetHelper::lockByTransactionId(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            // Reload after locking
            $transactionInfo = WeArePlanetModelTransactioninfo::loadByTransaction(
                $transactionInfo->getSpaceId(),
                $transactionInfo->getTransactionId()
            );
            $spaceId = $transactionInfo->getSpaceId();
            $transactionId = $transactionInfo->getTransactionId();

            if ($transactionInfo->getState() != \WeArePlanet\Sdk\Model\TransactionState::AUTHORIZED) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be completed.',
                        'transactioncompletion'
                    )
                );
            }

            if (WeArePlanetModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Please wait until the existing completion is processed.',
                        'transactioncompletion'
                    )
                );
            }

            if (WeArePlanetModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'There is a void in process. The order can not be completed.',
                        'transactioncompletion'
                    )
                );
            }

            $completionJob = new WeArePlanetModelCompletionjob();
            $completionJob->setSpaceId($spaceId);
            $completionJob->setTransactionId($transactionId);
            $completionJob->setState(WeArePlanetModelCompletionjob::STATE_CREATED);
            $completionJob->setOrderId(
                WeArePlanetHelper::getOrderMeta($order, 'weArePlanetMainOrderId')
            );
            $completionJob->save();
            $currentCompletionJob = $completionJob->getId();
            WeArePlanetHelper::commitDBTransaction();
        } catch (Exception $e) {
            WeArePlanetHelper::rollbackDBTransaction();
            throw $e;
        }

        try {
            $this->updateLineItems($currentCompletionJob);
            $this->sendCompletion($currentCompletionJob);
        } catch (Exception $e) {
            throw $e;
        }
    }

    protected function updateLineItems($completionJobId)
    {
        $completionJob = new WeArePlanetModelCompletionjob($completionJobId);
        WeArePlanetHelper::startDBTransaction();
        WeArePlanetHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new WeArePlanetModelCompletionjob($completionJobId);

        if ($completionJob->getState() != WeArePlanetModelCompletionjob::STATE_CREATED) {
            // Already updated in the meantime
            WeArePlanetHelper::rollbackDBTransaction();
            return;
        }
        try {
            $baseOrder = new Order($completionJob->getOrderId());
            $collected = $baseOrder->getBrother()->getResults();
            $collected[] = $baseOrder;

            $lineItems = WeArePlanetServiceLineitem::instance()->getItemsFromOrders($collected);

	        $lineItemVersion = (new TransactionLineItemVersionCreate())
			  ->setTransaction((int)$completionJob->getTransactionId())
			  ->setLineItems($lineItems)
			  ->setExternalId(uniqid());
			
            WeArePlanetServiceTransaction::instance()->updateLineItems(
                $completionJob->getSpaceId(),
                $lineItemVersion
            );
            $completionJob->setState(WeArePlanetModelCompletionjob::STATE_ITEMS_UPDATED);
            $completionJob->save();
            WeArePlanetHelper::commitDBTransaction();
        } catch (\WeArePlanet\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \WeArePlanet\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WeArePlanetHelper::getModuleInstance()->l(
                                'Could not update the line items. Error: %s',
                                'transactioncompletion'
                            ),
                            WeArePlanetHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(WeArePlanetModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                WeArePlanetHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                WeArePlanetHelper::commitDBTransaction();
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error updating line items for completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            WeArePlanetHelper::commitDBTransaction();
            $message = sprintf(
                WeArePlanetHelper::getModuleInstance()->l(
                    'Error updating line items for completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelCompletionjob');
            throw $e;
        }
    }

    protected function sendCompletion($completionJobId)
    {
        $completionJob = new WeArePlanetModelCompletionjob($completionJobId);
        WeArePlanetHelper::startDBTransaction();
        WeArePlanetHelper::lockByTransactionId(
            $completionJob->getSpaceId(),
            $completionJob->getTransactionId()
        );
        // Reload completion job;
        $completionJob = new WeArePlanetModelCompletionjob($completionJobId);

        if ($completionJob->getState() != WeArePlanetModelCompletionjob::STATE_ITEMS_UPDATED) {
            // Already sent in the meantime
            WeArePlanetHelper::rollbackDBTransaction();
            return;
        }
        try {
            $completion = $this->getCompletionService()->completeOnline(
                $completionJob->getSpaceId(),
                $completionJob->getTransactionId()
            );
            $completionJob->setCompletionId($completion->getId());
            $completionJob->setState(WeArePlanetModelCompletionjob::STATE_SENT);
            $completionJob->save();
            WeArePlanetHelper::commitDBTransaction();
        } catch (\WeArePlanet\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \WeArePlanet\Sdk\Model\ClientError) {
                $completionJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WeArePlanetHelper::getModuleInstance()->l(
                                'Could not send the completion to %s. Error: %s',
                                'transactioncompletion'
                            ),
                            'WeArePlanet',
                            WeArePlanetHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $completionJob->setState(WeArePlanetModelCompletionjob::STATE_FAILURE);
                $completionJob->save();
                WeArePlanetHelper::commitDBTransaction();
            } else {
                $completionJob->save();
                WeArePlanetHelper::commitDBTransaction();
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error sending completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $completionJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelCompletionjob');
                throw $e;
            }
        } catch (Exception $e) {
            $completionJob->save();
            WeArePlanetHelper::commitDBTransaction();
            $message = sprintf(
                WeArePlanetHelper::getModuleInstance()->l(
                    'Error sending completion job with id %d: %s',
                    'transactioncompletion'
                ),
                $completionJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelCompletionjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $completionJob = WeArePlanetModelCompletionjob::loadRunningCompletionForTransaction(
            $spaceId,
            $transactionId
        );
        $this->updateLineItems($completionJob->getId());
        $this->sendCompletion($completionJob->getId());
    }

    public function updateCompletions($endTime = null)
    {
        $toProcess = WeArePlanetModelCompletionjob::loadNotSentJobIds();
        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->updateLineItems($id);
                $this->sendCompletion($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error updating completion job with id %d: %s',
                        'transactioncompletion'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelCompletionjob');
            }
        }
    }

    public function hasPendingCompletions()
    {
        $toProcess = WeArePlanetModelCompletionjob::loadNotSentJobIds();
        return ! empty($toProcess);
    }

    /**
     * Returns the transaction completion API service.
     *
     * @return \WeArePlanet\Sdk\Service\TransactionCompletionService
     */
    protected function getCompletionService()
    {
        if ($this->completionService == null) {
            $this->completionService = new \WeArePlanet\Sdk\Service\TransactionCompletionService(
                WeArePlanetHelper::getApiClient()
            );
        }
        return $this->completionService;
    }
}
