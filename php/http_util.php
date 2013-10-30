<?php

class HttpUtil
{
    public static function getContentByUrl($strUrl, $arrParam = array(), $postFields = '')
    {
        $ch = curl_init();

        $options = array(
            CURLOPT_URL => $strUrl,
            CURLOPT_HEADER => 0,
            CURLOPT_FAILONERROR => 1,
            CURLOPT_RETURNTRANSFER => true,
        );

        if(isset($arrParam['host'])) {
            $options[CURLOPT_HTTPHEADER] = array('Host: ' . $arrParam['host']);
        }
        if(isset($arrParam['timeout'])) {
            //$options[CURLOPT_TIMEOUT_MS] = intval($arrParam['timeout']);
            $options[CURLOPT_TIMEOUT] = intval($arrParam['timeout']);
        }
        if(!empty($postFields)) {
            $options[CURLOPT_POST] = 1;
            $options[CURLOPT_POSTFIELDS] = $postFields;
        }

        curl_setopt_array($ch, $options);

        $content = curl_exec($ch);

        if( $content === false ) {
            curl_close($ch);
            return false;
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if($httpCode != '200' ) {
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return $content;
    }
}