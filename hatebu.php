<?php

namespace hatebu;

class Hatebu
{
    private $oauth_consumer_key;
    private $oauth_consumer_secret_key;
    private $request_token_url = "https://www.hatena.com/oauth/initiate";
    private $access_token_url = 'https://www.hatena.com/oauth/token';
    private $bookmark_api_url = 'http://api.b.hatena.ne.jp/1/my/bookmark';

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

        $authorization = array(
            'oauth_callback' => "oob",
            'oauth_consumer_key' => $this->oauth_consumer_key,
            'oauth_nonce' => md5(uniqid(rand(), true)),
            'oauth_signature_method' => "HMAC-SHA1",
            'oauth_timestamp' => time(),
            'oauth_version' => "1.0",
        );
        $authorization['oauth_signature'] = $this->create_request_oauth_signature($authorization, $method);

        // request for get token
        $headers = [
            sprintf(
                'Authorization: OAuth realm="",oauth_callback=%s,oauth_consumer_key=%s,oauth_nonce=%s,oauth_signature=%s,oauth_signature_method=%s,oauth_timestamp=%d,oauth_version=%s',
                $authorization['oauth_callback'],
                $authorization['oauth_consumer_key'],
                $authorization['oauth_nonce'],
                $authorization['oauth_signature'],
                $authorization['oauth_signature_method'],
                $authorization['oauth_timestamp'],
                $authorization['oauth_version']
            ),
            'Content-Type: application/x-www-form-urlencoded',
        ];
        $context = stream_context_create(
            array('http' => array(
                'method' => $method,
                'header' => implode(PHP_EOL, $headers)
            ))
        );

        $response = file_get_contents($this->request_token_url, false, $context);
        $responses = explode("&", $response);
        return array(
            'oauth_token' => rawurldecode(explode("=", $responses[0])[1]),
            'oauth_token_secret' => rawurldecode(explode("=", $responses[1])[1])
        );
    }

    /**
     * @param $authorization
     * @param $method
     * @return string
     */
    private function create_request_oauth_signature($authorization, $method)
    {
        ksort($authorization);
        $signatureBaseString = '';
        foreach ($authorization as $key => $val) {
            $signatureBaseString .= $key . '=' . rawurlencode($val) . '&';
        }
        $signatureBaseString = substr($signatureBaseString, 0, -1);
        $signatureBaseString =
            $method
            . '&' . rawurlencode($this->request_token_url)
            . '&' . rawurlencode($signatureBaseString);
        $signingKey = rawurlencode($this->oauth_consumer_secret_key) . '&';;

        return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
    }


    /**
     * @param $oauth_request_token
     * @param $oauth_request_token_secret
     * @param $oauth_verifier
     * @return array
     */
    public function get_access_token($oauth_request_token, $oauth_request_token_secret, $oauth_verifier)
    {
        $authorization = array(
            'oauth_consumer_key' => $this->oauth_consumer_key,
            'oauth_nonce' => md5(uniqid(rand(), true)),
            'oauth_signature_method' => "HMAC-SHA1",
            'oauth_timestamp' => time(),
            'oauth_token' => $oauth_request_token,
            'oauth_verifier' => $oauth_verifier,
            'oauth_version' => "1.0"
        );
        $authorization['oauth_signature'] = $this->create_access_oauth_signature(
            $authorization,
            'POST',
            $oauth_request_token_secret
        );
        $oauthHeader = 'OAuth ';
        foreach ($authorization as $key => $val) {
            $oauthHeader .= $key . '="' . rawurlencode($val) . '",';
        }
        $oauthHeader = substr($oauthHeader, 0, -1);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->access_token_url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Authorization: ' . $oauthHeader,
                'Content-Length:',
                'Expect:',
                'Content-Type:'
            )
        );
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_POST, true);

        $response = curl_exec($curl);

        curl_close($curl);
        $responses = explode("&", $response);
        return array(
            'oauth_token' => rawurldecode(explode("=", $responses[0])[1]),
            'oauth_token_secret' => rawurldecode(explode("=", $responses[1])[1]),
            'url_name' => rawurldecode(explode("=", $responses[2])[1]),
            'display_name' => rawurldecode(explode("=", $responses[3])[1])
        );
    }

    /**
     * @param $authorization
     * @param $method
     * @param $oauth_request_token_secret
     * @return string
     */
    private function create_access_oauth_signature($authorization, $method, $oauth_request_token_secret)
    {
        ksort($authorization);
        $signatureBaseString = '';
        foreach ($authorization as $key => $val) {
            $signatureBaseString .= $key . '=' . rawurlencode($val) . '&';
        }
        $signatureBaseString = substr($signatureBaseString, 0, -1);
        $signatureBaseString = $method
            . '&' . rawurlencode($this->access_token_url)
            . '&' . rawurlencode($signatureBaseString);
        $signingKey = rawurlencode($this->oauth_consumer_secret_key)
            . '&' . rawurlencode($oauth_request_token_secret);

        return base64_encode(hash_hmac('sha1', $signatureBaseString, $signingKey, true));
    }

    public function post_bookmark(String $url, String $comment, String $oauth_access_token, $oauth_access_token_secret)
    {
        $method = 'GET';
        $authorization = array(
            'oauth_consumer_key' => $this->oauth_consumer_key,
            'oauth_nonce' => md5(uniqid(rand(), true)),
            'oauth_signature_method' => "HMAC-SHA1",
            'oauth_timestamp' => time(),
            'oauth_token' => $oauth_access_token,
            'oauth_version' => "1.0"
        );
        $authorization['oauth_signature'] = $this->create_access_oauth_signature(
            $authorization,
            $method,
            $oauth_access_token_secret
        );
        $oauthHeader = 'OAuth ';
        foreach ($authorization as $key => $val) {
            $oauthHeader .= $key . '="' . rawurlencode($val) . '",';
        }
        $oauthHeader = substr($oauthHeader, 0, -1);
        $body = array(
            'url' => $url,
            'comment' => $comment,
        );

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HEADER, 1);
        curl_setopt($curl, CURLOPT_URL, $this->bookmark_api_url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Authorization :' . $oauthHeader,
            'Content-Length:',
            'Content-Type: application/x-www-form-urlencoded'
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);

        $response = curl_exec($curl);

        var_dump("RESPONSE\n", $response, "\nFINISHE\n");

        // 出力オプション
        $info = curl_getinfo($curl);
        print_r($info);

        curl_close($curl);
    }
}

