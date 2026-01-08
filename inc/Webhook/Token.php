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
 * Webhook processor to handle token state transitions.
 */
class WeArePlanetWebhookToken extends WeArePlanetWebhookAbstract
{
    public function process(WeArePlanetWebhookRequest $request)
    {
        $tokenService = WeArePlanetServiceToken::instance();
        $tokenService->updateToken($request->getSpaceId(), $request->getEntityId());
    }
}
