<?php

namespace hatebu;

class Hatebu
{
    private $oauth_consumer_key;
    private $oauth_consumer_secret_key;
    private $request_token_url = "https://www.hatena.com/oauth/initiate";
    private $access_token_url = 'https://www.hatena.com/oauth/token';
    private $bookmark_api_url = 'http://api.b.hatena.ne.jp/1/my/bookmark';
    private $oauth_signature_method = 'HMAC-SHA1';
    private $oauth_version = '1.0';

    /**
     * Hatebu constructor.
     * @param $consumer_key
     * @param $oauth_consumer_secret_key
     */
    public function __construct($consumer_key, $oauth_consumer_secret_key)
    {
        $this->oauth_consumer_key = $consumer_key;
        $this->oauth_consumer_secret_key = $oauth_consumer_secret_key;
    }

    /**
     * @return array
     */
    public function get_request_token()
    {
        $method = 'POST';
        $url = $this->request_token_url;
        $authorization = array(
            'oauth_callback' => "oob",
            'oauth_consumer_key' => $this->oauth_consumer_key,
            'oauth_nonce' => md5(uniqid(rand(), true)),
            'oauth_signature_method' => $this->oauth_signature_method,
            'oauth_timestamp' => time(),
            'oauth_version' => $this->oauth_version,
        );

        $body = array(
            'scope' => 'read_public,write_public,read_private,write_private'
//            'scope' => 'read_public,write_public'
        );
        $authorization['oauth_signature'] = $this->create_signature(
            $authorization,
            $method,
            NULL,
            $url,
            $body
        );

        $response = $this->send_request($url, $authorization, $method, $body);
        $responses = explode("&", $response);
        return array(
            'oauth_token' => rawurldecode(explode("=", $responses[0])[1]),
            'oauth_token_secret' => rawurldecode(explode("=", $responses[1])[1])
        );
    }

    /**
     * @param $oauth_request_token
     * @param $oauth_request_token_secret
     * @param $oauth_verifier
     * @return array
     */
    public function get_access_token($oauth_request_token, $oauth_request_token_secret, $oauth_verifier)
    {
        $method = 'POST';
        $url = $this->access_token_url;
        $authorization = array(
            'oauth_consumer_key' => $this->oauth_consumer_key,
            'oauth_nonce' => md5(uniqid(rand(), true)),
            'oauth_signature_method' => $this->oauth_signature_method,
            'oauth_timestamp' => time(),
            'oauth_token' => $oauth_request_token,
            'oauth_verifier' => $oauth_verifier,
            'oauth_version' => $this->oauth_version
        );
        $authorization['oauth_signature'] = $this->create_signature(
            $authorization,
            $method,
            $oauth_request_token_secret,
            $url
        );
        $response = $this->send_request($url, $authorization, $method);
        $responses = explode("&", $response);
        return array(
            'oauth_token' => rawurldecode(explode("=", $responses[0])[1]),
            'oauth_token_secret' => rawurldecode(explode("=", $responses[1])[1]),
            'url_name' => rawurldecode(explode("=", $responses[2])[1]),
            'display_name' => rawurldecode(explode("=", $responses[3])[1])
        );
    }


    public function post_bookmark(String $bookmark_url, String $comment, String $oauth_access_token, $oauth_access_token_secret)
    {
        $url = $this->bookmark_api_url;
        $method = 'POST';
        $authorization = array(
            'oauth_consumer_key' => $this->oauth_consumer_key,
            'oauth_nonce' => md5(uniqid(rand(), true)),
            'oauth_signature_method' => $this->oauth_signature_method,
            'oauth_timestamp' => time(),
            'oauth_version' => $this->oauth_version
        );

        $body = [
            'url' => $bookmark_url,
            'comment' => $comment,
        ];
        // ここでsignatureにbodyを含めると、認証が通らなくなる
        $authorization['oauth_signature'] = $this->create_signature(
            $authorization,
            $method,
            $oauth_access_token_secret,
            $url
        );

        return json_decode(
            $this->send_request($url, $authorization, $method, $body),
            true
        );
    }

    /**
     * @param $url
     * @param $authorization
     * @param $method
     * @param $body
     * @param $additional_headers
     * @return bool|string
     */
    private function send_request($url, $authorization, $method, $body = NULL, $additional_headers = NULL)
    {
        $oauthHeader = 'OAuth ';
        foreach ($authorization as $key => $val) {
            $oauthHeader .= $key . '="' . rawurlencode($val) . '",';
        }
        $oauthHeader = substr($oauthHeader, 0, -1);
        $header = array(
            'Authorization:' . $oauthHeader,
            'User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:26.0) Gecko/20100101 Firefox/26.0',
            'Expect:',
        );
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if (isset($additional_headers)) {
            $header = array_merge($header, $additional_headers);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        if (isset($body)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $res = curl_exec($curl);
        $res_info = curl_getinfo($curl);
        if ($res_info['http_code'] != 200 && $res_info['http_code'] != 201) {
            var_dump($res_info);
            var_dump($res);
            exit(1);
        }

        curl_close($curl);
        return $res;
    }

    /**
     * @param $authorization
     * @param $method
     * @param $token_secret
     * @param $url
     * @param null $body
     * @return string
     */
    private function create_signature($authorization, $method, $token_secret, $url, $body = NULL)
    {
        if (isset($body)) {
            $parameter_array = array_merge($authorization, $body);
        } else {
            $parameter_array = $authorization;
        }

        ksort($parameter_array);
        $signatureBaseString = '';
        foreach ($parameter_array as $key => $val) {
            $signatureBaseString .= $key . '=' . rawurlencode($val) . '&';
        }
        $signatureBaseString = substr($signatureBaseString, 0, -1);
        $signatureBaseString = sprintf("%s&%s&%s", $method, rawurlencode($url), rawurlencode($signatureBaseString));
        if (isset($token_secret)) {
            $signingKey = sprintf("%s&%s", rawurlencode($this->oauth_consumer_secret_key), rawurlencode($token_secret));
        } else {
            $signingKey = rawurlencode($this->oauth_consumer_secret_key) . '&';
        }

        return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
    }
}

