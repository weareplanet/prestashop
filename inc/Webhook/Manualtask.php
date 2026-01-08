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
 * Webhook processor to handle manual task state transitions.
 */
class WeArePlanetWebhookManualtask extends WeArePlanetWebhookAbstract
{

    /**
     * Updates the number of open manual tasks.
     *
     * @param WeArePlanetWebhookRequest $request
     */
    public function process(WeArePlanetWebhookRequest $request)
    {
        $manualTaskService = WeArePlanetServiceManualtask::instance();
        $manualTaskService->update();
    }
}
