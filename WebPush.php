<?php
/**
 * @author 2017/9 Heat, York 
 */

class WebPush
{
    // Construct parameters
    private $type_array = array('fcm', 'safari');
    private $times, $type, $errorMsg, $status;
    // Fcm parameters
    private $xxx;
    // Safari parameters
    private $expiry, $certificateFile, $passphrase;
    // Package
    private $title, $body, $icon, $url, $button;

    /**
     * @param string $type
     * @param array  $array
     *
     * @return TRUE | FALSE
     */
    function __construct( $type, $array )
    {
        # 判斷是否在list裡
        if ( in_array($type, $this->type_array) )
        {
            $this->type = $type;
        }
        else
        {
            $this->errorMsg = "Undefined type: {$type}";
        }
        if ( ! empty( $this->errorMsg ) )return $this->status;

        # 判斷所需參數是否都有設定
        foreach ($array as $key => $value)
        {
            $this->verify( $key, $value );
        }
        if ( ! empty( $this->errorMsg ) )return $this->status;
    }

    /**
     * @param string $token
     * @param string $title
     * @param string $body
     * @param array  $url
     * @param string $icon
     * @param array  $button
     *
     * @return TRUE | FALSE
     */
    function __invoke( $token = FALSE, $title = FALSE, $body = FALSE, $url = FALSE, $icon = FALSE, $button = FALSE )
    {
        # 判斷所需參數是否都有設定
        $this->verify( "token", $token );
        $this->verify( "title", $title );
        $this->verify( "body", $body );
        $this->verify( "url", $url );
        if ( ! empty( $this->errorMsg ) )return $this->status;

        switch ($this->type)
        {
            case 'fcm':
                $this->verify( "icon", $icon );
                if ( ! empty( $this->errorMsg ) )return $this->status;
                $this->sendPushFcm($token, $title, $body, $url, $icon, $button);
                return $this->status;
                break;
            
            case 'safari':
                $this->sendPushSafari($token, $title, $body, $url);
                return $this->status;
                break;
        }
    }
    
    /**
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /* 驗證 */ 
    private function verify( $type, $para )
    {
        try
        {
            if(!empty($para))
            {
                $this->$type = $para;
            }
            else throw new Exception("Undefined variable: {$type}");
        }
        catch(Exception $e)
        {
            $this->errorMsg = $e->getMessage();
            $this->status = FALSE;
        }
    }

    /* Fcm 推播 */
    private function sendPushFcm( $token, $title, $body, $url, $icon , $button = FALSE )
    {
        // FCM Server Production
        $google_server_url = 'https://android.googleapis.com/gcm/send';

    }

    /* Safari 推播 */
    private function sendPushSafari( $token, $title, $body, $url )
    {

        # APNS Server Production
        $pushServer = 'ssl://gateway.push.apple.com:2195';
        $feedbackServer = 'ssl://feedback.push.apple.com:2196';

        # Create Stream config
        $streamContext = stream_context_create();
        stream_context_set_option( $streamContext, 'ssl', 'local_cert', $this->certificateFile );
        stream_context_set_option( $streamContext, 'ssl', 'passphrase', $this->passphrase );

        # Create Stream Connection
        $fp = stream_socket_client( $pushServer, $errorCode, $errorStr, 100, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $streamContext );

        if( ! $fp )$this->errorMsg = "Stream socket client failed.";
        if ( ! empty( $this->errorMsg ) )return $this->errorMsg;

        # This allows fread() to return right away when there are no errors.
        stream_set_blocking ($fp, 0);

        # payload
        $payload = array(
            'aps' => array(
                'alert' => array(
                    'title' => $this->title,
                    'body'=> $this->body,
                    'action'=> 'view',
                ),
                'url-args' => $this->url
            )
        );
        $json_payload = json_encode($payload);

        # Enhanced Notification
        $binary = pack('CNNnH*n', 1, 1, $expiry, 32, trim($token), strlen($json_payload)).$json_payload;
        # 如果發送失敗，則進行重發，連續３次失敗，則重新建立連接，並從下一個 DeviceToken 發送
        # 如果發送成功，但 token 回傳 Error 則中止當前推播，標記invalid，使用遞迴，從下一個Token重啟推播 
        $times = 1;
        while($times <= 3)
        {
            # 發送 or 重發
            $result = fwrite($fp, $binary);
            # 發送失敗
            if (!$result)
            {
                if ($times = 3)
                {
                    # 重新建立連接
                    fclose($fp);
                    $fp = stream_socket_client($pushServer, $errorCode, $errorStr, 100, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $streamContext);
                    if(!$fp)
                    {
                        $this->errorMsg = "Stream socket client failed.";
                        $this->status = FALSE;
                        return FALSE;
                    }
                    stream_set_blocking ($fp, 0);
                }
                $times++;
                continue;
            }
            # 發送成功
            else 
            {
                # 等待傳送結果 0.5秒
                usleep(500000);
                # 傳送結果
                $error_key = $this->checkAppleErrorResponse($fp);
                if ($error_key === "success") 
                {
                    # 記錄推播狀態
                    $this->status = $error_key;
                    # 結束 發送三次的 while 迴圈
                    break;
                }
                else
                {
                    # 關閉APNs連接
                    fclose($fp);
                    # 記錄推播狀態
                    $this->errorMsg = $error_key;
                    $this->status = FALSE;
                    return FALSE;
                }
            }
        }
        # 關閉APNs連接
        fclose($fp);
        return TRUE;
    }

    /* 檢查APNS Error Response */
    private function checkAppleErrorResponse(&$fp)
    {
        # byte1=always 8, byte2=StatusCode, bytes3,4,5,6=identifier(rowID). Should return nothing if OK.
        $apple_error_response = fread($fp, 6);
        # NOTE: Make sure you set stream_set_blocking($fp, 0) or else fread will pause your script and wait forever when there is no response to be sent.

        if ($apple_error_response) 
        {
            # unpack the error response (first byte 'command" should always be 8)
            $error_response = unpack('Ccommand/Cstatus_code/Nidentifier', $apple_error_response);

            if ($error_response['status_code'] == '0')
            {
                $error_response['status_code'] = '0-No errors encountered';
            }
            else if ($error_response['status_code'] == '1')
            {
                $error_response['status_code'] = '1-Processing error';
            }
            else if ($error_response['status_code'] == '2')
            {
                $error_response['status_code'] = '2-Missing device token';
            }
            else if ($error_response['status_code'] == '3')
            {
                $error_response['status_code'] = '3-Missing topic';
            }
            else if ($error_response['status_code'] == '4')
            {
                $error_response['status_code'] = '4-Missing payload';
            }
            else if ($error_response['status_code'] == '5')
            {
                $error_response['status_code'] = '5-Invalid token size';
            }
            else if ($error_response['status_code'] == '6')
            {
                $error_response['status_code'] = '6-Invalid topic size';
            }
            else if ($error_response['status_code'] == '7')
            {
                $error_response['status_code'] = '7-Invalid payload size';
            }
            else if ($error_response['status_code'] == '8')
            {
                $error_response['status_code'] = '8-Invalid token';
            }
            else if ($error_response['status_code'] == '10')
            {
                $error_response['status_code'] = '10-Shutdown';
            }
            else if ($error_response['status_code'] == '128')
            {
                $error_response['status_code'] = '128-Protocol error (APNs could not parse the notification)';
            }
            else if ($error_response['status_code'] == '255')
            {
                $error_response['status_code'] = '255-None (unknown)';
            }
            else
            {
                $error_response['status_code'] = $error_response['status_code'] . '-Not listed';
            }

            /*
                Identifier is the rowID (index) in the database that caused the problem, and Apple will disconnect you from server. To continue sending Push Notifications, just start at the next rowID after this Identifier.
            */
            return $error_response['identifier'];
        }
        return "success";
    }
}