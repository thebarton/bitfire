<?php
$config=array (
  'bitfire_enabled' => true,
  'allow_ip_block' => true,
  'security_headers_enabled' => true,
  'enforce_ssl_1year' => false,
  'feature_policy_enabled' => false,
  'allowed_features' => 
  array (
    'notifications' => 'self',
    'push' => 'self',
    'geolocation' => 'self',
  ),
  'csp_policy_enabled' => true,
  'csp_default' => '*.googleapis.com *.gstatic.com \'unsafe-inline\' *.aweber.com *.wp.com ',
  'csp_policy' => 
  array (
    'font-src' => '\'self\' *.googleapis.com *.gstatic.com',
    'img-src' => '\'self\' data: *.wp.com *.aweber.com *.esurveyspro.com',
    'style-src-attr' => '\'unsafe-inline\' \'self\'',
    'style-src-elem' => '\'unsafe-inline\' \'self\' *.googleapis.com *.gstatic.com *.wigzopush.com *.paperform.co',
    'script-src' => '\'unsafe-inline\' \'unsafe-eval\' \'self\' *.wigzo.com *.wigzopush.com www.google-analytics.com *.woopra.com *.esurveyspro.com *.wp.com *.aweber.com',
    'object-src' => '\'none\'',
    'connect-src' => 'tracker.wigzopush.com *.google-analytics.com',
  ),
  'mfa_phone_number' => 0,
  'mfa_login_paths' => 
  array (
    '/bitfire' => true,
  ),
  'pro_key' => 'b4mKulEYCs6U8s3CxS/rAXsrvKGtRiWO6hVpgNNL6qQ=.p25GZzOS9F4WPdt0',
  'max_cache_age' => 0,
  'web_filter_enabled' => 'block',
  'decode_html' => true,
  'spam_filter_enabled' => 'report',
  'xss_block' => 'block',
  'sql_block' => 'block',
  'web_block' => 'block',
  'file_block' => 'block',
  'block_profanity' => false,
  'filtered_logging' => 
  array (
    'cc' => true,
    'card' => true,
    'cardnumber' => true,
    'exp' => true,
    'expiration' => true,
    'cvv' => true,
    'cvv1' => true,
    'cvv2' => true,
    'pass' => true,
    'password' => true,
    'password1' => true,
    'password2' => true,
  ),
  'botwhitelist' => 
  array (
    'googlebot' => 'google(bot?).com',
    'zoominfobot' => 'googleusercontent.com',
    'bingbot' => '(microsoft.com|msn.com)',
    'yahoo' => 'yahoo.com',
    'duckduckbot' => 'duckduckgo.com',
    'baidu' => 'baidu.(com|.jp)',
    '360spider' => 'baidu.(com|.jp)',
    'uptimerobot' => 'uptimerobot.com',
    'censysinspect' => 'censys-scanner.com',
    'applebot' => 'apple.com',
    'redditbot' => 'reddit.com',
    'naver.com' => 'naver.com',
    'yeti' => 'naver.com',
    'statuscake' => 'vultr.com',
    'yandex' => 'yandex.(ru|net|com)',
    'sogou.com' => 'sogou.com',
    'exabot' => 'exalead.com',
    'linkedinbot' => '(linkedin.com|microsoft.com|msn.com)',
    'facebookexternalhit' => 'AS32934',
    'appengine; appid: s~snapchat-proxy)' => '35\\.187\\.*\\.*',
    'tumblr' => 'AS2635',
    'whatsapp' => 'AS32934',
    'twitterbot' => 'AS13414',
    'embedly' => 'embed.ly',
    'gigabot' => 'gigablast.com',
    'alexa' => 'alexa.com',
    'jeeves' => 'ask.com',
    'aolbuild' => 'aol.com',
    'archive.org' => 'archive.org',
    'Pintrest' => 'pintrest.com',
    'curl' => '127.0.0.1,192.168.*,10.*',
    'wget' => '127.0.0.1,192.168.*,10.*',
    'observatory' => 'mozilla.com',
    'ahrefsbot' => 'ahrefs.com',
    'jetpack' => '192.0.116.*',
    'wordpress' => '66.147.240.*',
    'petalbot' => 'petalsearch.com',
    'paloaltonetworks.com' => 'googleusercontent.com',
    'cpanel-http-client' => '66.147.240.*',
  ),
  'allowed_methods' => 
  array (
    0 => 'GET',
    1 => 'OPTIONS',
    2 => 'POST',
    3 => 'PUT',
    4 => 'HEAD',
  ),
  'whitelist_enable' => true,
  'blacklist_enable' => true,
  'require_full_browser' => true,
  'honeypot_url' => '/fencepost/contact',
  'check_domain' => 'report',
  'valid_domains' => 
  array (
    0 => 'vipdiscountleads.com',
    1 => 'vipdiscountleads.net',
    2 => 'wowfreedom.com',
    3 => 'profitexecutive.com',
    4 => 'whenwedie.com',
    5 => 'voicebroadcastingpros.com',
    6 => '127.0.0.1',
  ),
  'rate_limit' => 'report',
  'rr_1m' => 25,
  'rr_5m' => 50,
  'cache_type' => 'nop',
  'report_file' => 'cache/would_block.json',
  'block_file' => 'cache/block.json',
  'debug_file' => false,
  'bitfire_param' => '_kbuqyrkc',
  'browser_cookie' => '_ltfc',
  'block_page' => 'blocked.php',
  'dashboard_path' => '/bitfire',
  'encryption_key' => 'PzYSeYq99o8iuzHny6YdsCT2',
  'secret' => 'RNQNeCaMExTKHPEI',
  'password' => 'victsing',
  'debug' => true,
  'web_uid' => 1297,
  'response_code' => 403,
  'ip_header' => 'REMOTE_ADDR',
  'dns_service' => '1.1.1.1',
  'short_block_time' => 600,
  'medium_block_time' => 3600,
  'long_block_time' => 86400,
);
