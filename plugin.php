<?php
/*
Plugin Name: Hatena bookmark auto post
Plugin URI:
Description: 投稿時に記事へのブックマークを自動で行います
Version: 0.1
Author: nozomoto
Author URI: https://nozomoto.me
License: GPL2
*/

/*  Copyright 2019 nozomoto (email : nozomotoitech at gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

use hatebu\Hatebu;

require_once('lib/hatebu.php');
add_action('admin_menu', 'hbap_set_hatebu_auto_post');
function hbap_set_hatebu_auto_post()
{
    add_options_page(
        'Hatena bookmark auto post',
        'Hatebu AutoPost',
        'administrator',
        'hatebu-auto-post',
        'hbap_display_plugin_page'
    );

    add_settings_field(
        'hbap_consumer_key',
        'Consumer key',
        '',
        'general',
        'default'
    );
    add_settings_field(
        'hbap_consumer_key_secret',
        'Consumer key Secret',
        '',
        'general',
        'default'
    );
    register_setting(
        'hbap-group',
        'hbap_consumer_key',
        'consumer_key_validation'
    );
    register_setting(
        'hbap-group',
        'hbap_consumer_key_secret',
        'consumer_key_secret_validation'
    );

    add_options_page(
        'hatebu callback page',
        '',
        'administrator',
        'hpbap_callback_page',
        'hbap_display_callback_page'
    );

    add_options_page(
        'hatebu consumer key save func',
        '',
        'administrator',
        'hatebu_consumerkey_save_func',
        'hatebu_consumerkey_save'
    );
}

// 投稿時にはてブにポストする設定
add_action('publish_post', 'hbap_run_bookmark');
add_action('future_news', 'hbap_run_bookmark');
function hbap_run_bookmark()
{
    $consumer_key = get_option('hbap_consumer_key');
    $consumer_key_secret = get_option('hbap_consumer_key_secret');
    $access_token_secret = get_option('hbap_access_token_secret');
    if (!isset($access_token_secret) || !isset($consumer_key) || !isset($consumer_key_secret)) {
        exit(1);
    }
    $hatebu = new Hatebu($consumer_key, $consumer_key_secret);

    try {
        $hatebu->post_bookmark(
            'https://www.nozograph.com/2019/04/11/119',
            'test from plugin',
            get_option('hbap_access_token_secret')
        );
    } catch (Exception $e) {
        _log($e->getMessage());
        exit(1);
    }
}


// call back page from oauth server
function hbap_display_callback_page()
{
    $consumer_key = get_option('hbap_consumer_key');
    $consumer_key_secret = get_option('hbap_consumer_key_secret');
    $html = '';
    if (
        isset($_GET['oauth_token']) &&
        !empty($_GET['oauth_token']) &&
        is_string($_GET['oauth_token']) &&
        isset($_GET['oauth_verifier']) &&
        !empty($_GET['oauth_verifier']) &&
        is_string($_GET['oauth_verifier'])
    ) {
        // get oauth verifier and save it.
        $oauth_verifier = $_GET['oauth_verifier'];
        delete_option('hbap_verify_token');
        add_option('hbap_verify_token', $oauth_verifier);

        // get access token
        $hatebu = new Hatebu($consumer_key, $consumer_key_secret);
        try {
            $response = $hatebu->get_access_token(
                get_option('hbap_request_token'),
                get_option('hbap_request_token_secret'),
                $oauth_verifier
            );
        } catch (Exception $e) {
            _log($_GET);
            _log($e->getMessage());
            exit(1);
        }
        delete_option('hbap_access_token');
        add_option('hbap_access_token', $response['oauth_token']);
        delete_option('hbap_access_token_secret');
        add_option('hbap_access_token_secret', $response['oauth_token_secret']);

        $html .= '<h3>result</h3>';
        $html .= '<div>' . 'url_name: ' . $response['url_name'] . '</div>';
        $html .= '<div>' . 'display_name: ' . $response['display_name'] . '</div>';
        $html .= '<div>' . 'oauth_token: ' . $response['oauth_token'] . '</div>';
        $html .= '<div>' . 'oauth_token_secret: ' . $response['oauth_token_secret'] . '</div>';
    } else {
        $html .= '<h3>callback mistake. Please Retry</h3>';
    }
    echo $html;
}


function hatebu_consumerkey_save()
{
    $html = "";
    if (
        isset($_POST['hbap_consumer_key']) &&
        !empty($_POST['hbap_consumer_key']) &&
        is_string($_POST['hbap_consumer_key']) &&
        isset($_POST['hbap_consumer_key_secret']) &&
        !empty($_POST['hbap_consumer_key_secret']) &&
        is_string($_POST['hbap_consumer_key_secret'])
    ) {
        // Save Consumer Keys
        $consumer_key = $_POST['hbap_consumer_key'];
        $consumer_key_secret = $_POST['hbap_consumer_key_secret'];
        delete_option('hbap_consumer_key');
        add_option('hbap_consumer_key', $consumer_key);
        delete_option('hbap_consumer_key_secret');
        add_option('hbap_consumer_key_secret', $consumer_key_secret);

        // Get Request Token
        $hatebu = new Hatebu(get_option("hbap_consumer_key"), get_option("hbap_consumer_key_secret"));
        try {
            $request_tokens = $hatebu->get_request_token();
        } catch (Exception $e) {
            _log($e);
            exit(1);
        }

        delete_option('hbap_request_token');
        add_option('hbap_request_token', $request_tokens["oauth_token"]);
        delete_option('hbap_request_token_secret');
        add_option('hbap_request_token_secret', $request_tokens["oauth_token_secret"]);

        $html = "<h1>consumer key save page</h1>";
        $html .= '<h3> Consumer Key is ' . $consumer_key . '</h3>';
        $html .= '<h3> Consumer Secret key is ' . $consumer_key_secret . '</h3>';

        $html .= '<h3> Request Token is ' . $request_tokens["oauth_token"] . '</h3>';
        $html .= '<h3> Request Token Secret is ' . $request_tokens["oauth_token_secret"] . '</h3>';

        $html .= '<a href="' . 'https://www.hatena.ne.jp/oauth/authorize?oauth_token=' . $request_tokens["oauth_token"] . '">こちらのリンクからアクセス許可してください</a>';
    }
    echo $html;
}

function hbap_display_plugin_page()
{
    ?>
    <div class="wrap">
        <h2>Hatena bookmark auto postの管理画面</h2>
        <form method="post" action="options-general.php?page=hatebu_consumerkey_save_func">
            <?php
            settings_fields('hbap-group');
            do_settings_sections('default');
            ?>
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row"><label for="hbap_consumer_key">Consumer Key</label></th>
                    <td>
                        <input type="text" id="hbap_consumer_key" name="hbap_consumer_key"
                               value="<?php echo esc_attr(get_option('hbap_consumer_key')); ?>"
                        />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="hbap_consumer_key_secret">Consumer Key Secret</label></th>
                    <td>
                        <input type="text" id="hbap_consumer_key_secret" name="hbap_consumer_key_secret"
                               value="<?php echo esc_attr(get_option('hbap_consumer_key_secret')); ?>"
                        />
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php } ?>
