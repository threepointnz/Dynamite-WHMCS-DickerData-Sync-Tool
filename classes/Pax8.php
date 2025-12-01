<?php

/**
 * API example (cURL)
 curl --request POST \
  --url https://api.pax8.com/v1/token\
  --header 'content-type: application/json' \
  --data '{"client_id":"QXBWVbHOK6Qqa1ugAM9uXlPn1UFqoVBb","client_secret":"W3DCMz-t96iiZi9xhP9dPoHggy73av8WI9td50pE8yiJagaXFFiR8kDOzO4y1L3I","audience":"https://api.pax8.com","grant_type":"client_credentials"}'
 */

class Pax8
{
    private $url;
    private $accessToken;

    public function __construct($url, $access_token)
    {
        $this->url = $url;
        $this->accessToken = $access_token;
    }



}
