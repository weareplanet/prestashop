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
 * Provider of payment method information from the gateway.
 */
class WeArePlanetProviderPaymentmethod extends WeArePlanetProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('weareplanet_methods');
    }

    /**
     * Returns the payment method by the given id.
     *
     * @param int $id
     * @return \WeArePlanet\Sdk\Model\PaymentMethod
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment methods.
     *
     * @return \WeArePlanet\Sdk\Model\PaymentMethod[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $methodService = new \WeArePlanet\Sdk\Service\PaymentMethodService(
            WeArePlanetHelper::getApiClient()
        );
        return $methodService->all();
    }

    protected function getId($entry)
    {
        /* @var \WeArePlanet\Sdk\Model\PaymentMethod $entry */
        return $entry->getId();
    }
}
