<?php

namespace Inc\Base;

class KiriminAjaApi
{
    protected $base_url;
    protected $default_args;
    
    public function __construct()
    {
        global $wp_version;

        $this->base_url = 'https://dev-core.bakso.my.id';

        $dbApiToken = (new \Inc\Repositories\SettingRepository())->getSettingByKey('api_key')->value ?? '';
        
        $this->default_args = array(
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.0',
            'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url'),
            'blocking' => true,
            'headers' => array(
                'Authorization' => 'Bearer ' . $dbApiToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'wordpress'
            ),
            'cookies' => array(),
            'body' => null,
            'compress' => false,
            'decompress' => true,
            'sslverify' => false,
            'stream' => false,
            'filename' => null,
        );
    }

    private function populate_output($response)
    {
        if (is_wp_error($response)) {
            return array(
                'status' => false,
                'data' => $response->get_error_message()
            );
        }
        if (200 !== wp_remote_retrieve_response_code($response)) {
            (new \Inc\Base\BaseInit())->logThis('api_call_err',$response);
            return array(
                'status' => false,
                'data' => 'Error ' . wp_remote_retrieve_response_code($response)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $body = json_decode($body);
        if (false === $body->status) {
            return array(
                'status' => false,
                'data' => isset($body->text) ? $body->text : 'Unknown error'
            );
        }
        return array(
            'status' => true,
            'data' => $body
        );
    }
    

    public function get($endpoint, $body = array())
    {
        $args = wp_parse_args(array('body' => $body), $this->default_args);
        $response = wp_remote_get($this->base_url . $endpoint, $args);
        if (class_exists('WPMonolog')) {
            global $logger;
            $logger->addDebug('[GET] ' . $this->base_url . $endpoint . ' | ' . serialize($args) . ' | ' . serialize($this->populate_output($response)));
        }
        return $this->populate_output($response);
    }

    public function post($endpoint, $body = array())
    {
        $args = wp_parse_args(array('body' => wp_json_encode($body)), $this->default_args);
        $response = wp_remote_post($this->base_url . $endpoint, $args);
        if (class_exists('WPMonolog')) {
            global $logger;
            $logger->addDebug('[POST] ' . $this->base_url . $endpoint . ' | ' . serialize($args) . ' | ' . serialize($this->populate_output($response)));
        }
        return $this->populate_output($response);
    }
    
}