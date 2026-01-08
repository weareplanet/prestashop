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
 * Provider of label descriptor information from the gateway.
 */
class WeArePlanetProviderLabeldescription extends WeArePlanetProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('weareplanet_label_description');
    }

    /**
     * Returns the label descriptor by the given code.
     *
     * @param int $id
     * @return \WeArePlanet\Sdk\Model\LabelDescriptor
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptors.
     *
     * @return \WeArePlanet\Sdk\Model\LabelDescriptor[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorService = new \WeArePlanet\Sdk\Service\LabelDescriptionService(
            WeArePlanetHelper::getApiClient()
        );
        return $labelDescriptorService->all();
    }

    protected function getId($entry)
    {
        /* @var \WeArePlanet\Sdk\Model\LabelDescriptor $entry */
        return $entry->getId();
    }
}
