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
 * This service provides functions to deal with WeArePlanet tokens.
 */
class WeArePlanetServiceToken extends WeArePlanetServiceAbstract
{

    /**
     * The token API service.
     *
     * @var \WeArePlanet\Sdk\Service\TokenService
     */
    private $tokenService;

    /**
     * The token version API service.
     *
     * @var \WeArePlanet\Sdk\Service\TokenVersionService
     */
    private $tokenVersionService;

    public function updateTokenVersion($spaceId, $tokenVersionId)
    {
        $tokenVersion = $this->getTokenVersionService()->read($spaceId, $tokenVersionId);
        $this->updateInfo($spaceId, $tokenVersion);
    }

    public function updateToken($spaceId, $tokenId)
    {
        $query = new \WeArePlanet\Sdk\Model\EntityQuery();
        $filter = new \WeArePlanet\Sdk\Model\EntityQueryFilter();
        $filter->setType(\WeArePlanet\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('token.id', $tokenId),
                $this->createEntityFilter('state', \WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE)
            )
        );
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $tokenVersions = $this->getTokenVersionService()->search($spaceId, $query);
        if (! empty($tokenVersions)) {
            $this->updateInfo($spaceId, current($tokenVersions));
        } else {
            $info = WeArePlanetModelTokeninfo::loadByToken($spaceId, $tokenId);
            if ($info->getId()) {
                $info->delete();
            }
        }
    }

    protected function updateInfo($spaceId, \WeArePlanet\Sdk\Model\TokenVersion $tokenVersion)
    {
        // Return early, since token version state is OBSOLETE
        if ($tokenVersion->getState() === \WeArePlanet\Sdk\Model\TokenVersionState::OBSOLETE) {
            return;
        }
        $info = WeArePlanetModelTokeninfo::loadByToken($spaceId, $tokenVersion->getToken()->getId());
        if (! in_array(
            $tokenVersion->getToken()->getState(),
            array(
                \WeArePlanet\Sdk\Model\CreationEntityState::ACTIVE,
                \WeArePlanet\Sdk\Model\CreationEntityState::INACTIVE
            )
        )) {
            if ($info->getId()) {
                $info->delete();
            }
            return;
        }

        $info->setCustomerId($tokenVersion->getToken()
            ->getCustomerId());
        $info->setName($tokenVersion->getName());

        $paymentConnectorConfiguration = $tokenVersion->getPaymentConnectorConfiguration();

        if ($paymentConnectorConfiguration
            && $paymentConnectorConfiguration->getPaymentMethodConfiguration()
            && $paymentConnectorConfiguration->getConnector()) {
            $info->setPaymentMethodId(
                $paymentConnectorConfiguration
                    ->getPaymentMethodConfiguration()
                    ->getId()
            );
            $info->setConnectorId($paymentConnectorConfiguration->getConnector());
        } else {
            // Return early if Payment Connector Configuration is missing for token
            return;
        }

        $info->setSpaceId($spaceId);
        $info->setState($tokenVersion->getToken()
            ->getState());
        $info->setTokenId($tokenVersion->getToken()
            ->getId());
        $info->save();
    }

    public function deleteToken($spaceId, $tokenId)
    {
        $this->getTokenService()->delete($spaceId, $tokenId);
    }

    /**
     * Returns the token API service.
     *
     * @return \WeArePlanet\Sdk\Service\TokenService
     */
    protected function getTokenService()
    {
        if ($this->tokenService == null) {
            $this->tokenService = new \WeArePlanet\Sdk\Service\TokenService(
                WeArePlanetHelper::getApiClient()
            );
        }

        return $this->tokenService;
    }

    /**
     * Returns the token version API service.
     *
     * @return \WeArePlanet\Sdk\Service\TokenVersionService
     */
    protected function getTokenVersionService()
    {
        if ($this->tokenVersionService == null) {
            $this->tokenVersionService = new \WeArePlanet\Sdk\Service\TokenVersionService(
                WeArePlanetHelper::getApiClient()
            );
        }

        return $this->tokenVersionService;
    }
}
