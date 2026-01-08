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
 * This exception indicating an error with the transaction amount
 */
class WeArePlanetExceptionInvalidtransactionamount extends Exception
{
    private $itemTotal;

    private $orderTotal;

    public function __construct($itemTotal, $orderTotal)
    {
        parent::__construct("The item total '" . $itemTotal . "' does not match the order total '" . $orderTotal . "'.");
        $this->itemTotal = $itemTotal;
        $this->orderTotal = $orderTotal;
    }

    public function getItemTotal()
    {
        return $this->itemTotal;
    }

    public function getOrderTotal()
    {
        return $this->orderTotal;
    }
}
