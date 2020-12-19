<?php declare(strict_types=1);
namespace BitFireHeader;

use const BitFire\REQUEST_HOST;
use const BitFire\REQUEST_PATH;

const CONFIG_ENFORCE_SSL = "encorfe_ssl_1year";

const FEATURES = array('geolocation', 'midi', 'notifications', 'push', 'sync-xhr', 'microphone', 'gyroscope', 'speaker', 'vibrate', 'fullscreen', 'payment');
const CSP = array('child-src', 'connect-src', 'default-src', 'font-src',
            'frame-src', 'img-src', 'manifest-src', 'media-src', 'object-src', 'prefetch-src',
            'script-src', 'style-src', 'webrtc-src', 'worker-src', 'base-uri',
            'form-action', 'frame-ancestors', 'upgrade-insecure-requests');

/**
 * add the security headers from config
 */
function send_security_headers(array $request) : void {

    header_remove('X-Powered-By');
    header_remove('Server');
    $path = $request[REQUEST_HOST].$request[REQUEST_PATH]."?_bitfire=report";

    header("X-Frame-Options: deny");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; report=$path");
    header("Referer-Policy: strict-origin-when-cross-origin");
    header('Report-To: {"group":"bitfire","max_age":2592000,"endpoints":[{"url"'.$path.'"}],"include_subdomains":true}');

    // set strict transport security (HSTS)
    if (\Bitfire\Config::enabled("enforce_ssl_1year")) {
        header("Strict-Transport-Security: max-age=31536000; preload");
    }

    // set a default feature policy
    if (\BitFire\Config::enabled("default_feature_policy")) {
        header(array_reduce(default_feature_policy(), function($acc, $item) {
                return  $acc . "{$item[0]} '{$item[1]}'; ";
            }, "Feature-Policy: ") );
    }
    
    if (\BitFire\Config::enabled("nel")) {
        header('{"report_to":"bitfire","max_age":2592000,"include_subdomains":true}');
    }
}

/**
 * create a default feature policy
 */
function default_feature_policy() : array {
    return array_reduce(FEATURES, function(array $policy, $feature) {
        $policy[$feature] = ($feature == 'geolocation' || $feature == 'payment') ? "*" : "self";
    }, array());
}