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
 * This service handles webhooks.
 */
class WeArePlanetServiceWebhook extends WeArePlanetServiceAbstract
{

    /**
     * The webhook listener API service.
     *
     * @var \WeArePlanet\Sdk\Service\WebhookListenerService
     */
    private $webhookListenerService;

    /**
     * The webhook url API service.
     *
     * @var \WeArePlanet\Sdk\Service\WebhookUrlService
     */
    private $webhookUrlService;

    private $webhookEntities = array();

    /**
     * Constructor to register the webhook entites.
     */
    public function __construct()
    {
        $this->webhookEntities[1487165678181] = new WeArePlanetWebhookEntity(
            1487165678181,
            'Manual Task',
            array(
                \WeArePlanet\Sdk\Model\ManualTaskState::DONE,
                \WeArePlanet\Sdk\Model\ManualTaskState::EXPIRED,
                \WeArePlanet\Sdk\Model\ManualTaskState::OPEN
            ),
            'WeArePlanetWebhookManualtask'
        );
        $this->webhookEntities[1472041857405] = new WeArePlanetWebhookEntity(
            1472041857405,
            'Payment Method Configuration',
            array(
                \WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE,
                \WeArePlanet\Sdk\Model\CreationEntityState::DELETED,
                \WeArePlanet\Sdk\Model\CreationEntityState::DELETING,
                \WeArePlanet\Sdk\Model\CreationEntityState::INACTIVE
            ),
            'WeArePlanetWebhookMethodconfiguration',
            true
        );
        $this->webhookEntities[1472041829003] = new WeArePlanetWebhookEntity(
            1472041829003,
            'Transaction',
            array(
                \WeArePlanet\Sdk\Model\TransactionState::AUTHORIZED,
                \WeArePlanet\Sdk\Model\TransactionState::DECLINE,
                \WeArePlanet\Sdk\Model\TransactionState::FAILED,
                \WeArePlanet\Sdk\Model\TransactionState::FULFILL,
                \WeArePlanet\Sdk\Model\TransactionState::VOIDED,
                \WeArePlanet\Sdk\Model\TransactionState::COMPLETED
            ),
            'WeArePlanetWebhookTransaction'
        );
        $this->webhookEntities[1472041819799] = new WeArePlanetWebhookEntity(
            1472041819799,
            'Delivery Indication',
            array(
                \WeArePlanet\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED
            ),
            'WeArePlanetWebhookDeliveryindication'
        );

        $this->webhookEntities[1472041831364] = new WeArePlanetWebhookEntity(
            1472041831364,
            'Transaction Completion',
            array(
                \WeArePlanet\Sdk\Model\TransactionCompletionState::FAILED,
                \WeArePlanet\Sdk\Model\TransactionCompletionState::SUCCESSFUL
            ),
            'WeArePlanetWebhookTransactioncompletion'
        );

        $this->webhookEntities[1472041867364] = new WeArePlanetWebhookEntity(
            1472041867364,
            'Transaction Void',
            array(
                \WeArePlanet\Sdk\Model\TransactionVoidState::FAILED,
                \WeArePlanet\Sdk\Model\TransactionVoidState::SUCCESSFUL
            ),
            'WeArePlanetWebhookTransactionvoid'
        );

        $this->webhookEntities[1472041839405] = new WeArePlanetWebhookEntity(
            1472041839405,
            'Refund',
            array(
                \WeArePlanet\Sdk\Model\RefundState::FAILED,
                \WeArePlanet\Sdk\Model\RefundState::SUCCESSFUL
            ),
            'WeArePlanetWebhookRefund'
        );
        $this->webhookEntities[1472041806455] = new WeArePlanetWebhookEntity(
            1472041806455,
            'Token',
            array(
                \WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE,
                \WeArePlanet\Sdk\Model\CreationEntityState::DELETED,
                \WeArePlanet\Sdk\Model\CreationEntityState::DELETING,
                \WeArePlanet\Sdk\Model\CreationEntityState::INACTIVE
            ),
            'WeArePlanetWebhookToken'
        );
        $this->webhookEntities[1472041811051] = new WeArePlanetWebhookEntity(
            1472041811051,
            'Token Version',
            array(
                \WeArePlanet\Sdk\Model\TokenVersionState::ACTIVE,
                \WeArePlanet\Sdk\Model\TokenVersionState::OBSOLETE
            ),
            'WeArePlanetWebhookTokenversion'
        );
    }

    /**
     * Installs the necessary webhooks in WeArePlanet.
     */
    public function install()
    {
        $spaceIds = array();
        foreach (Shop::getShops(true, null, true) as $shopId) {
            $spaceId = Configuration::get(WeArePlanetBasemodule::CK_SPACE_ID, null, null, $shopId);
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $webhookUrl = $this->getWebhookUrl($spaceId);
                if ($webhookUrl == null) {
                    $webhookUrl = $this->createWebhookUrl($spaceId);
                }
                $existingListeners = $this->getWebhookListeners($spaceId, $webhookUrl);
                foreach ($this->webhookEntities as $webhookEntity) {
                    /* @var WeArePlanetWebhookEntity $webhookEntity */
                    $exists = false;
                    foreach ($existingListeners as $existingListener) {
                        if ($existingListener->getEntity() == $webhookEntity->getId()) {
                            $exists = true;
                        }
                    }
                    if (! $exists) {
                        $this->createWebhookListener($webhookEntity, $spaceId, $webhookUrl);
                    }
                }
                $spaceIds[] = $spaceId;
            }
        }
    }

    /**
     *
     * @param int|string $id
     * @return WeArePlanetWebhookEntity
     */
    public function getWebhookEntityForId($id)
    {
        if (isset($this->webhookEntities[$id])) {
            return $this->webhookEntities[$id];
        }
        return null;
    }

    /**
     * Create a webhook listener.
     *
     * @param WeArePlanetWebhookEntity $entity
     * @param int $spaceId
     * @param \WeArePlanet\Sdk\Model\WebhookUrl $webhookUrl
     * @return \WeArePlanet\Sdk\Model\WebhookListenerCreate
     */
    protected function createWebhookListener(
        WeArePlanetWebhookEntity $entity,
        $spaceId,
        \WeArePlanet\Sdk\Model\WebhookUrl $webhookUrl
    ) {
        $webhookListener = new \WeArePlanet\Sdk\Model\WebhookListenerCreate();
        $webhookListener->setEntity($entity->getId());
        $webhookListener->setEntityStates($entity->getStates());
        $webhookListener->setName('Prestashop ' . $entity->getName());
        $webhookListener->setState(\WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookListener->setUrl($webhookUrl->getId());
        $webhookListener->setNotifyEveryChange($entity->isNotifyEveryChange());
        $webhookListener->setEnablePayloadSignatureAndState(true);

        return $this->getWebhookListenerService()->create($spaceId, $webhookListener);
    }

    /**
     * Returns the existing webhook listeners.
     *
     * @param int $spaceId
     * @param \WeArePlanet\Sdk\Model\WebhookUrl $webhookUrl
     * @return \WeArePlanet\Sdk\Model\WebhookListener[]
     */
    protected function getWebhookListeners($spaceId, \WeArePlanet\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $query = new \WeArePlanet\Sdk\Model\EntityQuery();
        $filter = new \WeArePlanet\Sdk\Model\EntityQueryFilter();
        $filter->setType(\WeArePlanet\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE),
                $this->createEntityFilter('url.id', $webhookUrl->getId())
            )
        );
        $query->setFilter($filter);
        return $this->getWebhookListenerService()->search($spaceId, $query);
    }

    /**
     * Creates a webhook url.
     *
     * @param int $spaceId
     * @return \WeArePlanet\Sdk\Model\WebhookUrlCreate
     */
    protected function createWebhookUrl($spaceId)
    {
        $webhookUrl = new \WeArePlanet\Sdk\Model\WebhookUrlCreate();
        $webhookUrl->setUrl($this->getUrl());
        $webhookUrl->setState(\WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookUrl->setName('Prestashop');
        return $this->getWebhookUrlService()->create($spaceId, $webhookUrl);
    }

    /**
     * Returns the existing webhook url if there is one.
     *
     * @param int $spaceId
     * @return \WeArePlanet\Sdk\Model\WebhookUrl
     */
    protected function getWebhookUrl($spaceId)
    {
        $query = new \WeArePlanet\Sdk\Model\EntityQuery();
        $filter = new \WeArePlanet\Sdk\Model\EntityQueryFilter();
        $filter->setType(\WeArePlanet\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE),
                $this->createEntityFilter('url', $this->getUrl())
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $this->getWebhookUrlService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Returns the webhook endpoint URL.
     *
     * @return string
     */
    protected function getUrl()
    {
        $link = Context::getContext()->link;

        $shopIds = Shop::getShops(true, null, true);
        asort($shopIds);
        $shopId = reset($shopIds);

        $languageIds = Language::getLanguages(true, $shopId, true);
        asort($languageIds);
        $languageId = reset($languageIds);

        $url = $link->getModuleLink('weareplanet', 'webhook', array(), true, $languageId, $shopId);
        // We have to parse the link, because of issue http://forge.prestashop.com/browse/BOOM-5799
        $urlQuery = parse_url($url, PHP_URL_QUERY) ?? '';
        if (stripos($urlQuery, 'controller=module') !== false && stripos($urlQuery, 'controller=webhook') !== false) {
            $url = str_replace('controller=module', 'fc=module', $url);
        }
        return $url;
    }

    /**
     * Returns the webhook listener API service.
     *
     * @return \WeArePlanet\Sdk\Service\WebhookListenerService
     */
    protected function getWebhookListenerService()
    {
        if ($this->webhookListenerService == null) {
            $this->webhookListenerService = new \WeArePlanet\Sdk\Service\WebhookListenerService(
                WeArePlanetHelper::getApiClient()
            );
        }
        return $this->webhookListenerService;
    }

    /**
     * Returns the webhook url API service.
     *
     * @return \WeArePlanet\Sdk\Service\WebhookUrlService
     */
    protected function getWebhookUrlService()
    {
        if ($this->webhookUrlService == null) {
            $this->webhookUrlService = new \WeArePlanet\Sdk\Service\WebhookUrlService(
                WeArePlanetHelper::getApiClient()
            );
        }
        return $this->webhookUrlService;
    }
}
