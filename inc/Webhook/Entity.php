<?php
/**
 * WeArePlanet Prestashop
 *
 * This Prestashop module enables to process payments with WeArePlanet (https://www.weareplanet.com/).
 *
 * @author customweb GmbH (http://www.customweb.com/)
 * @copyright 2017 - 2025 customweb GmbH
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache Software License (ASL 2.0)
 */

class WeArePlanetWebhookEntity
{
    private $id;

    private $name;

    private $states;

    private $notifyEveryChange;

    private $handlerClassName;

    public function __construct($id, $name, array $states, $handlerClassName, $notifyEveryChange = false)
    {
        $this->id = $id;
        $this->name = $name;
        $this->states = $states;
        $this->notifyEveryChange = $notifyEveryChange;
        $this->handlerClassName = $handlerClassName;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getStates()
    {
        return $this->states;
    }

    public function isNotifyEveryChange()
    {
        return $this->notifyEveryChange;
    }

    public function getHandlerClassName()
    {
        return $this->handlerClassName;
    }
}
