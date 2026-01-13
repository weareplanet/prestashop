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
 * This service provides functions to deal with WeArePlanet refunds.
 */
class WeArePlanetServiceRefund extends WeArePlanetServiceAbstract
{
    private static $refundableStates = array(
        \WeArePlanet\Sdk\Model\TransactionState::COMPLETED,
        \WeArePlanet\Sdk\Model\TransactionState::DECLINE,
        \WeArePlanet\Sdk\Model\TransactionState::FULFILL
    );

    /**
     * The refund API service.
     *
     * @var \WeArePlanet\Sdk\Service\RefundService
     */
    private $refundService;

    /**
     * Returns the refund by the given external id.
     *
     * @param int $spaceId
     * @param string $externalId
     * @return \WeArePlanet\Sdk\Model\Refund
     */
    public function getRefundByExternalId($spaceId, $externalId)
    {
        $query = new \WeArePlanet\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('externalId', $externalId));
        $query->setNumberOfEntities(1);
        $result = $this->getRefundService()->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            throw new Exception('The refund could not be found.');
        }
    }

    public function executeRefund(Order $order, array $parsedParameters)
    {
        $currentRefundJob = null;
        try {
            WeArePlanetHelper::startDBTransaction();
            $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction',
                        'refund'
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

            if (! in_array($transactionInfo->getState(), self::$refundableStates)) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'The transaction is not in a state to be refunded.',
                        'refund'
                    )
                );
            }

            if (WeArePlanetModelRefundjob::isRefundRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Please wait until the existing refund is processed.',
                        'refund'
                    )
                );
            }

            $refundJob = new WeArePlanetModelRefundjob();
            $refundJob->setState(WeArePlanetModelRefundjob::STATE_CREATED);
            $refundJob->setOrderId($order->id);
            $refundJob->setSpaceId($transactionInfo->getSpaceId());
            $refundJob->setTransactionId($transactionInfo->getTransactionId());
            $refundJob->setExternalId(uniqid($order->id . '-'));
            $refundJob->setRefundParameters($parsedParameters);
            $refundJob->save();
            // validate Refund Job
            $this->createRefundObject($refundJob);
            $currentRefundJob = $refundJob->getId();
            WeArePlanetHelper::commitDBTransaction();
        } catch (Exception $e) {
            WeArePlanetHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendRefund($currentRefundJob);
    }

    protected function sendRefund($refundJobId)
    {
        $refundJob = new WeArePlanetModelRefundjob($refundJobId);
        WeArePlanetHelper::startDBTransaction();
        WeArePlanetHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new WeArePlanetModelRefundjob($refundJobId);
        if ($refundJob->getState() != WeArePlanetModelRefundjob::STATE_CREATED) {
            // Already sent in the meantime
            WeArePlanetHelper::rollbackDBTransaction();
            return;
        }
        try {
            $executedRefund = $this->refund($refundJob->getSpaceId(), $this->createRefundObject($refundJob));
            $refundJob->setState(WeArePlanetModelRefundjob::STATE_SENT);
            $refundJob->setRefundId($executedRefund->getId());

            if ($executedRefund->getState() == \WeArePlanet\Sdk\Model\RefundState::PENDING) {
                $refundJob->setState(WeArePlanetModelRefundjob::STATE_PENDING);
            }
            $refundJob->save();
            WeArePlanetHelper::commitDBTransaction();
        } catch (\WeArePlanet\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \WeArePlanet\Sdk\Model\ClientError) {
                $refundJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WeArePlanetHelper::getModuleInstance()->l(
                                'Could not send the refund to %s. Error: %s',
                                'refund'
                            ),
                            'WeArePlanet',
                            WeArePlanetHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $refundJob->setState(WeArePlanetModelRefundjob::STATE_FAILURE);
                $refundJob->save();
                WeArePlanetHelper::commitDBTransaction();
            } else {
                $refundJob->save();
                WeArePlanetHelper::commitDBTransaction();
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error sending refund job with id %d: %s',
                        'refund'
                    ),
                    $refundJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelRefundjob');
                throw $e;
            }
        } catch (Exception $e) {
            $refundJob->save();
            WeArePlanetHelper::commitDBTransaction();
            $message = sprintf(
                WeArePlanetHelper::getModuleInstance()->l('Error sending refund job with id %d: %s', 'refund'),
                $refundJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelRefundjob');
            throw $e;
        }
    }

    public function applyRefundToShop($refundJobId)
    {
        $refundJob = new WeArePlanetModelRefundjob($refundJobId);
        WeArePlanetHelper::startDBTransaction();
        WeArePlanetHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
        // Reload refund job;
        $refundJob = new WeArePlanetModelRefundjob($refundJobId);
        if ($refundJob->getState() != WeArePlanetModelRefundjob::STATE_APPLY) {
            // Already processed in the meantime
            WeArePlanetHelper::rollbackDBTransaction();
            return;
        }
        try {
            $order = new Order($refundJob->getOrderId());
            $strategy = WeArePlanetBackendStrategyprovider::getStrategy();
            $appliedData = $strategy->applyRefund($order, $refundJob->getRefundParameters());
            $refundJob->setState(WeArePlanetModelRefundjob::STATE_SUCCESS);
            $refundJob->save();
            WeArePlanetHelper::commitDBTransaction();
            try {
                $strategy->afterApplyRefundActions($order, $refundJob->getRefundParameters(), $appliedData);
            } catch (Exception $e) {
                // We ignore errors in the after apply actions
            }
        } catch (Exception $e) {
            WeArePlanetHelper::rollbackDBTransaction();
            WeArePlanetHelper::startDBTransaction();
            WeArePlanetHelper::lockByTransactionId($refundJob->getSpaceId(), $refundJob->getTransactionId());
            $refundJob = new WeArePlanetModelRefundjob($refundJobId);
            $refundJob->increaseApplyTries();
            if ($refundJob->getApplyTries() > 3) {
                $refundJob->setState(WeArePlanetModelRefundjob::STATE_FAILURE);
                $refundJob->setFailureReason(array(
                    'en-US' => sprintf($e->getMessage())
                ));
            }
            $refundJob->save();
            WeArePlanetHelper::commitDBTransaction();
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $refundJob = WeArePlanetModelRefundjob::loadRunningRefundForTransaction($spaceId, $transactionId);
        if ($refundJob->getState() == WeArePlanetModelRefundjob::STATE_CREATED) {
            $this->sendRefund($refundJob->getId());
        } elseif ($refundJob->getState() == WeArePlanetModelRefundjob::STATE_APPLY) {
            $this->applyRefundToShop($refundJob->getId());
        }
    }

    public function updateRefunds($endTime = null)
    {
        $toSend = WeArePlanetModelRefundjob::loadNotSentJobIds();
        foreach ($toSend as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendRefund($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error updating refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelRefundjob');
            }
        }
        $toApply = WeArePlanetModelRefundjob::loadNotAppliedJobIds();
        foreach ($toApply as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->applyRefundToShop($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error applying refund job with id %d: %s',
                        'refund'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelRefundjob');
            }
        }
    }

    public function hasPendingRefunds()
    {
        $toSend = WeArePlanetModelRefundjob::loadNotSentJobIds();
        $toApply = WeArePlanetModelRefundjob::loadNotAppliedJobIds();
        return ! empty($toSend) || ! empty($toApply);
    }

    /**
     * Creates a refund request model for the given parameters.
     *
     * @param Order $order
     * @param array $refund
     *            Refund data to be determined
     * @return \WeArePlanet\Sdk\Model\RefundCreate
     */
    protected function createRefundObject(WeArePlanetModelRefundjob $refundJob)
    {
        $order = new Order($refundJob->getOrderId());

        $strategy = WeArePlanetBackendStrategyprovider::getStrategy();

        $spaceId = $refundJob->getSpaceId();
        $transactionId = $refundJob->getTransactionId();
        $externalRefundId = $refundJob->getExternalId();
        $parsedData = $refundJob->getRefundParameters();
        $amount = $strategy->getRefundTotal($parsedData);
        $type = $strategy->getWeArePlanetRefundType($parsedData);

        $reductions = $strategy->createReductions($order, $parsedData);
        $reductions = $this->fixReductions($amount, $spaceId, $transactionId, $reductions);

        $remoteRefund = new \WeArePlanet\Sdk\Model\RefundCreate();
        $remoteRefund->setExternalId($externalRefundId);
        $remoteRefund->setReductions($reductions);
        $remoteRefund->setTransaction($transactionId);
        $remoteRefund->setType($type);

        return $remoteRefund;
    }

    /**
     * Returns the fixed line item reductions for the refund.
     *
     * If the amount of the given reductions does not match the refund's grand total, the amount to refund is
     * distributed equally to the line items.
     *
     * @param float $refundTotal
     * @param int $spaceId
     * @param int $transactionId
     * @param \WeArePlanet\Sdk\Model\LineItemReductionCreate[] $reductions
     * @return \WeArePlanet\Sdk\Model\LineItemReductionCreate[]
     */
    protected function fixReductions($refundTotal, $spaceId, $transactionId, array $reductions)
    {
        $baseLineItems = $this->getBaseLineItems($spaceId, $transactionId);
        $reductionAmount = WeArePlanetHelper::getReductionAmount($baseLineItems, $reductions);

        $configuration = WeArePlanetVersionadapter::getConfigurationInterface();
        $computePrecision = $configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        if (Tools::ps_round($refundTotal, $computePrecision) != Tools::ps_round($reductionAmount, $computePrecision)) {
            $fixedReductions = array();
            $baseAmount = WeArePlanetHelper::getTotalAmountIncludingTax($baseLineItems);
            $rate = $refundTotal / $baseAmount;
            foreach ($baseLineItems as $lineItem) {
                $reduction = new \WeArePlanet\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    round($lineItem->getAmountIncludingTax() * $rate / $lineItem->getQuantity(), 8)
                );
                $fixedReductions[] = $reduction;
            }

            return $fixedReductions;
        } else {
            return $reductions;
        }
    }

    /**
     * Sends the refund to the gateway.
     *
     * @param int $spaceId
     * @param \WeArePlanet\Sdk\Model\RefundCreate $refund
     * @return \WeArePlanet\Sdk\Model\Refund
     */
    public function refund($spaceId, \WeArePlanet\Sdk\Model\RefundCreate $refund)
    {
        return $this->getRefundService()->refund($spaceId, $refund);
    }

    /**
     * Returns the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or else of the completed transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \WeArePlanet\Sdk\Model\Refund $refund
     * @return \WeArePlanet\Sdk\Model\LineItem[]
     */
    protected function getBaseLineItems($spaceId, $transactionId, \WeArePlanet\Sdk\Model\Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transactionId, $refund);
        if ($lastSuccessfulRefund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transactionId)->getLineItems();
        }
    }

    /**
     * Returns the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @throws Exception
     * @return \WeArePlanet\Sdk\Model\TransactionInvoice
     */
    protected function getTransactionInvoice($spaceId, $transactionId)
    {
        $query = new \WeArePlanet\Sdk\Model\EntityQuery();

        $filter = new \WeArePlanet\Sdk\Model\EntityQueryFilter();
        $filter->setType(\WeArePlanet\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter(
                    'state',
                    \WeArePlanet\Sdk\Model\TransactionInvoiceState::CANCELED,
                    \WeArePlanet\Sdk\Model\CriteriaOperator::NOT_EQUALS
                ),
                $this->createEntityFilter('completion.lineItemVersion.transaction.id', $transactionId)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $invoiceService = new \WeArePlanet\Sdk\Service\TransactionInvoiceService(
            WeArePlanetHelper::getApiClient()
        );
        $result = $invoiceService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            throw new Exception('The transaction invoice could not be found.');
        }
    }

    /**
     * Returns the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \WeArePlanet\Sdk\Model\Refund $refund
     * @return \WeArePlanet\Sdk\Model\Refund
     */
    protected function getLastSuccessfulRefund(
        $spaceId,
        $transactionId,
        \WeArePlanet\Sdk\Model\Refund $refund = null
    ) {
        $query = new \WeArePlanet\Sdk\Model\EntityQuery();

        $filter = new \WeArePlanet\Sdk\Model\EntityQueryFilter();
        $filter->setType(\WeArePlanet\Sdk\Model\EntityQueryFilterType::_AND);
        $filters = array(
            $this->createEntityFilter('state', \WeArePlanet\Sdk\Model\RefundState::SUCCESSFUL),
            $this->createEntityFilter('transaction.id', $transactionId)
        );
        if ($refund != null) {
            $filters[] = $this->createEntityFilter(
                'id',
                $refund->getId(),
                \WeArePlanet\Sdk\Model\CriteriaOperator::NOT_EQUALS
            );
        }

        $filter->setChildren($filters);
        $query->setFilter($filter);

        $query->setOrderBys(
            array(
                $this->createEntityOrderBy('createdOn', \WeArePlanet\Sdk\Model\EntityQueryOrderByType::DESC)
            )
        );
        $query->setNumberOfEntities(1);

        $result = $this->getRefundService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns the refund API service.
     *
     * @return \WeArePlanet\Sdk\Service\RefundService
     */
    protected function getRefundService()
    {
        if ($this->refundService == null) {
            $this->refundService = new \WeArePlanet\Sdk\Service\RefundService(
                WeArePlanetHelper::getApiClient()
            );
        }

        return $this->refundService;
    }
}
