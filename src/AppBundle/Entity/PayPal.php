<?php

namespace AppBundle\Entity;

use AppBundle\AppBundle;
use DateTime;

class PayPal
{
    private static $clientId = "AcxI4k68Xh5bpcbJvUdMTZtpGb4as_Vj_vrC4Td2TyJrsG6pwAFoVMYqnTjYgkCEQ4jFGMlWot3arXNV";
    private static $secret = "ELQBDhwaVjt5fSd3N9DBOM8S-IJRwltaExNec2FORK5k8uCROiahVSKcMDqciBC4yKbrGuPmeKUQI5_M";

    private static $baseUrl = "https://api.sandbox.paypal.com/v1/";

    private static $tokenCache;
    private static $tokenExpires;

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
        if (self::$tokenExpires>$current_date)
        {
            return self::$tokenCache;
        }

        $req = curl_init(self::$baseUrl."oauth2/token");
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
        curl_setopt($req, CURLOPT_USERPWD, self::$clientId.":".self::$secret);
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
        self::$tokenExpires = new DateTime();
        date_add(self::$tokenExpires, $interval);
        self::$tokenCache = $response['access_token'];

        return $response['access_token'];
    }

    /**
     * Creates a payment in the PayPal environment
     *
     * @param $skillId int The unique id of the skill to purchase
     * @param $amount float The total amount on this payment
     * @param $desc string The description as displayed to the user
     *
     * @throws \Exception if unable to request the payment
     *
     * @return Payment The payment with PayPal-specific information
     */
    public static function createPayment($skillId, $amount, $desc) {
        $token = self::getToken();

        $req = curl_init(self::$baseUrl."payments/payment");
        $headers = array(
            "Accept: application/json",
            "Authorization: Bearer $token",
            "Content-Type: application/json",
        );

        $body = array(
            "intent" => "sale",
            "redirect_urls" => array(
                "return_url" => "http://localhost:2017/app_dev.php/purchase-success",
                "cancel_url" => "http://localhost:2017/app_dev.php/purchase-cancel",
            ),
            "payer" => array(
                "payment_method" => "paypal",
            ),
            "transactions" => [array(
                "amount" => array(
                    "total" => $amount,
                    "currency" => "EUR",
                ),
                "description" => $desc,
            )],
        );

        $json =  json_encode($body);

        curl_setopt($req, CURLOPT_HEADER, false);
        curl_setopt($req, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, $json);
        $result = curl_exec($req);

        if (!curl_errno($req)) {
            switch ($http_code = curl_getinfo($req, CURLINFO_HTTP_CODE)) {
                case 200:  # OK
                    break;
                case 201:  # Created
                    break;
                default:
                    var_dump($result);
                    throw new \Exception("unable to request payment: HTTP error ".$http_code);
            }
        }

        $response = json_decode($result, true);
        curl_close($req);

        $payment = new Payment();
        $payment->total = $amount;
        $payment->skillId = $skillId;
        $payment->paypalId = $response['id'];
        $payment->state = $response['state'];
        $payment->timeCreated = date_create_from_format("Y-m-d?G:i:s*", $response['create_time']);
        if (in_array('update_time', $response))
            $payment->timeUpdated = date_create_from_format("Y-m-d?G:i:s*", $response['update_time']);
         else
            $payment->timeUpdated = $payment->timeCreated;

        foreach ($response['links'] as $link)
        {
            switch ($link["rel"])
            {
                case "approval_url":
                    $payment->redirectUrl = $link["href"];
                    break;
                case "execute":
                    $payment->executeUrl = $link["href"];
                    break;
            }
        }

        return $payment;
    }

    /**
     * Executes a payment in the PayPal environment
     *
     * @param $payment Payment The payment that will be executed
     * @param $payerId string The PayerID as given by the request as a result of the authorization
     *
     * @throws \Exception if unable to execute the payment
     *
     * @return Payment The updated payment with PayPal-specific information
     */
    public static function executePayment($payment, $payerId) {
        $token = self::getToken();

        $req = curl_init($payment->executeUrl);
        $headers = array(
            "Accept: application/json",
            "Authorization: Bearer $token",
            "Content-Type: application/json",
        );

        $body = array(
            "payer_id" => $payerId,
        );

        $json =  json_encode($body);

        curl_setopt($req, CURLOPT_HEADER, false);
        curl_setopt($req, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($req, CURLOPT_POST, true);
        curl_setopt($req, CURLOPT_POSTFIELDS, $json);
        $result = curl_exec($req);

        if (!curl_errno($req)) {
            switch ($http_code = curl_getinfo($req, CURLINFO_HTTP_CODE)) {
                case 200:  # OK
                    break;
                case 201:  # Created
                    break;
                default:
                    throw new \Exception("unable to request payment: HTTP error ".$http_code);
            }
        }

        $response = json_decode($result, true);
        curl_close($req);

        $payment->state = $response['state'];

        return $payment;
    }
}
