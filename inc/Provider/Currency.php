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
 * Provider of currency information from the gateway.
 */
class WeArePlanetProviderCurrency extends WeArePlanetProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('weareplanet_currencies');
    }

    /**
     * Returns the currency by the given code.
     *
     * @param string $code
     * @return \WeArePlanet\Sdk\Model\RestCurrency
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns a list of currencies.
     *
     * @return \WeArePlanet\Sdk\Model\RestCurrency[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $currencyService = new \WeArePlanet\Sdk\Service\CurrencyService(
            WeArePlanetHelper::getApiClient()
        );
        return $currencyService->all();
    }

    protected function getId($entry)
    {
        /* @var \WeArePlanet\Sdk\Model\RestCurrency $entry */
        return $entry->getCurrencyCode();
    }
}
