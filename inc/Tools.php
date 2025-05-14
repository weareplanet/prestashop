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

/**
 * This class calls the specific functions as user user functions.
 * As the PrestaShop validator does not allow the use of this functions directly.
 * But the functions are required by the module to work properly. (eg. computing hashes, encode data for the DB)
 */
class WeArePlanetTools
{
    public static function base64Encode($string)
    {
        return call_user_func('base64_encode', $string);
    }

    public static function base64Decode($string)
    {
        return call_user_func('base64_decode', $string);
    }

    public static function hashHmac($algo, $data, $key, $raw_output = false)
    {
        return call_user_func('hash_hmac', $algo, $data, $key, $raw_output);
    }
}
