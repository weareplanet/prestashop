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
 * This service provides functions to deal with WeArePlanet transaction voids.
 */
class WeArePlanetServiceTransactionvoid extends WeArePlanetServiceAbstract
{

    /**
     * The transaction void API service.
     *
     * @var \WeArePlanet\Sdk\Service\TransactionVoidService
     */
    private $voidService;

    public function executeVoid($order)
    {
        $currentVoidId = null;
        try {
            WeArePlanetHelper::startDBTransaction();
            $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
            if ($transactionInfo === null) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Could not load corresponding transaction.',
                        'transactionvoid'
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
                        'The transaction is not in a state to be voided.',
                        'transactionvoid'
                    )
                );
            }
            if (WeArePlanetModelVoidjob::isVoidRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Please wait until the existing void is processed.',
                        'transactionvoid'
                    )
                );
            }
            if (WeArePlanetModelCompletionjob::isCompletionRunningForTransaction($spaceId, $transactionId)) {
                throw new Exception(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'There is a completion in process. The order can not be voided.',
                        'transactionvoid'
                    )
                );
            }

            $voidJob = new WeArePlanetModelVoidjob();
            $voidJob->setSpaceId($spaceId);
            $voidJob->setTransactionId($transactionId);
            $voidJob->setState(WeArePlanetModelVoidjob::STATE_CREATED);
            $voidJob->setOrderId(
                WeArePlanetHelper::getOrderMeta($order, 'weArePlanetMainOrderId')
            );
            $voidJob->save();
            $currentVoidId = $voidJob->getId();
            WeArePlanetHelper::commitDBTransaction();
        } catch (Exception $e) {
            WeArePlanetHelper::rollbackDBTransaction();
            throw $e;
        }
        $this->sendVoid($currentVoidId);
    }

    protected function sendVoid($voidJobId)
    {
        $voidJob = new WeArePlanetModelVoidjob($voidJobId);
        WeArePlanetHelper::startDBTransaction();
        WeArePlanetHelper::lockByTransactionId($voidJob->getSpaceId(), $voidJob->getTransactionId());
        // Reload void job;
        $voidJob = new WeArePlanetModelVoidjob($voidJobId);
        if ($voidJob->getState() != WeArePlanetModelVoidjob::STATE_CREATED) {
            // Already sent in the meantime
            WeArePlanetHelper::rollbackDBTransaction();
            return;
        }
        try {
            $void = $this->getVoidService()->voidOnline($voidJob->getSpaceId(), $voidJob->getTransactionId());
            $voidJob->setVoidId($void->getId());
            $voidJob->setState(WeArePlanetModelVoidjob::STATE_SENT);
            $voidJob->save();
            WeArePlanetHelper::commitDBTransaction();
        } catch (\WeArePlanet\Sdk\ApiException $e) {
            if ($e->getResponseObject() instanceof \WeArePlanet\Sdk\Model\ClientError) {
                $voidJob->setFailureReason(
                    array(
                        'en-US' => sprintf(
                            WeArePlanetHelper::getModuleInstance()->l(
                                'Could not send the void to %s. Error: %s',
                                'transactionvoid'
                            ),
                            'WeArePlanet',
                            WeArePlanetHelper::cleanExceptionMessage($e->getMessage())
                        )
                    )
                );
                $voidJob->setState(WeArePlanetModelVoidjob::STATE_FAILURE);
                $voidJob->save();
                WeArePlanetHelper::commitDBTransaction();
            } else {
                $voidJob->save();
                WeArePlanetHelper::commitDBTransaction();
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error sending void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $voidJobId,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelVoidjob');
                throw $e;
            }
        } catch (Exception $e) {
            $voidJob->save();
            WeArePlanetHelper::commitDBTransaction();
            $message = sprintf(
                WeArePlanetHelper::getModuleInstance()->l(
                    'Error sending void job with id %d: %s',
                    'transactionvoid'
                ),
                $voidJobId,
                $e->getMessage()
            );
            PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelVoidjob');
            throw $e;
        }
    }

    public function updateForOrder($order)
    {
        $transactionInfo = WeArePlanetHelper::getTransactionInfoForOrder($order);
        $spaceId = $transactionInfo->getSpaceId();
        $transactionId = $transactionInfo->getTransactionId();
        $voidJob = WeArePlanetModelVoidjob::loadRunningVoidForTransaction($spaceId, $transactionId);
        if ($voidJob->getState() == WeArePlanetModelVoidjob::STATE_CREATED) {
            $this->sendVoid($voidJob->getId());
        }
    }

    public function updateVoids($endTime = null)
    {
        $toProcess = WeArePlanetModelVoidjob::loadNotSentJobIds();

        foreach ($toProcess as $id) {
            if ($endTime !== null && time() + 15 > $endTime) {
                return;
            }
            try {
                $this->sendVoid($id);
            } catch (Exception $e) {
                $message = sprintf(
                    WeArePlanetHelper::getModuleInstance()->l(
                        'Error updating void job with id %d: %s',
                        'transactionvoid'
                    ),
                    $id,
                    $e->getMessage()
                );
                PrestaShopLogger::addLog($message, 3, null, 'WeArePlanetModelVoidjob');
            }
        }
    }

    public function hasPendingVoids()
    {
        $toProcess = WeArePlanetModelVoidjob::loadNotSentJobIds();
        return ! empty($toProcess);
    }

    /**
     * Returns the transaction void API service.
     *
     * @return \WeArePlanet\Sdk\Service\TransactionVoidService
     */
    protected function getVoidService()
    {
        if ($this->voidService == null) {
            $this->voidService = new \WeArePlanet\Sdk\Service\TransactionVoidService(
                WeArePlanetHelper::getApiClient()
            );
        }

        return $this->voidService;
    }
}
