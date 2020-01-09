<?php
require_once('./lib/hatebu.php');
$consumer_key = 'rBS4nEODLx49mg==';
$consumer_secrete_key = 'XFIqJtdZfxz+hFdm7a7IDiwGfJE=';
var_dump($consumer_key, $consumer_secrete_key);

var_dump('Create Request Token');
$hatebu = new hatebu\Hatebu($consumer_key, $consumer_secrete_key);
$request_tokens = $hatebu->get_request_token();
var_dump($request_tokens);

var_dump('Create Access Token');
print_r("https://www.hatena.ne.jp/oauth/authorize?oauth_token=" . $request_tokens['oauth_token'] . "\n");
print_r('input verify token: ');
$verify_token = trim(fgets(STDIN));
$access_tokens = $hatebu->get_access_token(
    $request_tokens['oauth_token'],
    $request_tokens['oauth_token_secret'],
    $verify_token
);
var_dump($access_tokens);

var_dump("POST_BOOKMARK_________________");

$result = $hatebu->post_bookmark(
   'https://www.nozograph.com/',
    'API叩いてブクマしてみた',
    $access_tokens['oauth_token'],
    $access_tokens['oauth_token_secret']
);
