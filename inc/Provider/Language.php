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
 * Provider of language information from the gateway.
 */
class WeArePlanetProviderLanguage extends WeArePlanetProviderAbstract
{
    protected function __construct()
    {
        parent::__construct('weareplanet_languages');
    }

    /**
     * Returns the language by the given code.
     *
     * @param string $code
     * @return \WeArePlanet\Sdk\Model\RestLanguage
     */
    public function find($code)
    {
        return parent::find($code);
    }

    /**
     * Returns the primary language in the given group.
     *
     * @param string $code
     * @return \WeArePlanet\Sdk\Model\RestLanguage
     */
    public function findPrimary($code)
    {
        $code = Tools::substr($code, 0, 2);
        foreach ($this->getAll() as $language) {
            if ($language->getIso2Code() == $code && $language->getPrimaryOfGroup()) {
                return $language;
            }
        }

        return false;
    }

    /**
     * Returns a list of language.
     *
     * @return \WeArePlanet\Sdk\Model\RestLanguage[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $languageService = new \WeArePlanet\Sdk\Service\LanguageService(
            WeArePlanetHelper::getApiClient()
        );
        return $languageService->all();
    }

    protected function getId($entry)
    {
        /* @var \WeArePlanet\Sdk\Model\RestLanguage $entry */
        return $entry->getIetfCode();
    }
}
