<?php

namespace AppBundle\Entity;

use AppBundle\AppBundle;
use DateTime;
use DateInterval;

class PayPal
{
    private static $clientId = "AcxI4k68Xh5bpcbJvUdMTZtpGb4as_Vj_vrC4Td2TyJrsG6pwAFoVMYqnTjYgkCEQ4jFGMlWot3arXNV";
    private static $secret = "ELQBDhwaVjt5fSd3N9DBOM8S-IJRwltaExNec2FORK5k8uCROiahVSKcMDqciBC4yKbrGuPmeKUQI5_M";

    private static $baseUrl = "https://api.sandbox.paypal.com/v1/oauth2/";

    private static $token_cache;
    private static $token_expires;

    /**
     * Fetches a new access token from the PayPal API
     *
     * @throws \Exception if unable to fetch access token
     *
     * @return string The access-token to be used in future requests
     */
    public static function getToken()
    {
        $current_date = new DateTime();
        if (PayPal::$token_expires>$current_date)
        {
            return PayPal::$token_cache;
        }

        $req = curl_init(PayPal::$baseUrl."token");
        $headers = array(
            "Accept: application/json",
            "Content-Type: application/x-www-form-urlencoded",
            "Accept-Language: en_US",
        );

        $body = "grant_type=client_credentials";

        curl_setopt($req, CURLOPT_HEADER, false);
        curl_setopt($req, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, $body);
        curl_setopt($req, CURLOPT_USERPWD, PayPal::$clientId.":".PayPal::$secret);
        $result = curl_exec($req);

        if (!curl_errno($req)) {
            switch ($http_code = curl_getinfo($req, CURLINFO_HTTP_CODE)) {
                case 200:  # OK
                    break;
                default:
                    throw new \Exception("unable to fetch PayPal access token: HTTP error ".$http_code);
            }
        }

        $response = json_decode($result, true);
        curl_close($req);

        $interval = date_interval_create_from_date_string($response['expires_in']."s");
        PayPal::$token_expires = new DateTime();
        date_add(PayPal::$token_expires, $interval);
        PayPal::$token_cache = $response['access_token'];

        return $response['access_token'];
    }
}
