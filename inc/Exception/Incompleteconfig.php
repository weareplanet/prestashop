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
 * This exception indicated the configuration is not complete
 */
class WeArePlanetExceptionIncompleteconfig extends Exception
{
    /**
     * Constructs a WeArePlanetExceptionIncompleteconfig object.
     *
     * @param string $message
     *   The message that this exception will show.
     * @param integer $code
     *   Exception's code number.
     * @param Throwable|null $previous
     *   The previously thrown exception.
     */
    public function __construct($message = "The configuration is not complete", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
