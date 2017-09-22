# WebPush-PHP

$push_safari = new WebPush("safari", array( "certificateFile"=>{your certificate file path}, "passPhrase"=>{pem password}, "expiryTime"=>{expiryTime} ) );<br>

if( $push_safari->webPush( {devices token}, {your payload data} ) )<br>
{<br>
    # success code...<br>
}<br>
else<br>
{<br>
    # fail code...<br>
    $error_message = $push_safari->getErrorMsg();<br>
}<br>
<br>
<br>
$push_fcm = new WebPush("fcm", array( "fcmApiAccessKey"=>{your access key}, "timeToLive"=>{21600} ) );<br>
<br>
if( $push_fcm->webPush( {devices token}, {your payload data} ) )<br>
{<br>
    # success code...<br>
}<br>
else<br>
{<br>
    # fail code...<br>
    $error_message = $push_fcm->getErrorMsg();<br>
}<br>
