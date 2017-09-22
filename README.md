# WebPush-PHP
<pre><code>
$push_safari = new WebPush("safari", array( "certificateFile"=>{your certificate file path}, "passPhrase"=>{pem password}, "expiryTime"=>{expiryTime} ) );

if( $push_safari->webPush( {devices token}, {your payload data} ) )
{
    # success code...
}
else
{
    # fail code...
    $error_message = $push_safari->getErrorMsg();
}


$push_fcm = new WebPush("fcm", array( "fcmApiAccessKey"=>{your access key}, "timeToLive"=>{21600} ) );

if( $push_fcm->webPush( {devices token}, {your payload data} ) )
{
    # success code...
}
else
{
    # fail code...
    $error_message = $push_fcm->getErrorMsg();
}
</code></pre>
