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
 * Provider of payment connector information from the gateway.
 */
class WeArePlanetProviderPaymentconnector extends WeArePlanetProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('weareplanet_connectors');
    }

    /**
     * Returns the payment connector by the given id.
     *
     * @param int $id
     * @return \WeArePlanet\Sdk\Model\PaymentConnector
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment connectors.
     *
     * @return \WeArePlanet\Sdk\Model\PaymentConnector[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $connectorService = new \WeArePlanet\Sdk\Service\PaymentConnectorService(
            WeArePlanetHelper::getApiClient()
        );
        return $connectorService->all();
    }

    protected function getId($entry)
    {
        /* @var \WeArePlanet\Sdk\Model\PaymentConnector $entry */
        return $entry->getId();
    }
}
