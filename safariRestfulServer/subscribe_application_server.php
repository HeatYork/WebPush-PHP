<?php
/**
 * RESTful Service for safari subscribe
 * 
 * @author (20161107)york
 *
 */

/****************
 * website_json *
 ****************/
# websitePushID         開發者帳戶中指定的 websitePushID
# allowedDomains        可以跳出通知的domains(陣列)
# urlFormatString       跳轉的網址(%@可以多個 也是推送通知時給的陣列參數順序)
# authenticationToken   一個字串可以幫助您識別用戶 它被包括在您的Web服務的後續請求中 字串必須大於等於16個字
# webServiceURL         用於向Web服務發出請求的位置(此RESTful Server位置)
$website_json = {
    "websiteName": "york",
    "websitePushID": "web.tw.com.york.www",
    "allowedDomains": [
        "http://www.york.com.tw",
        "https://www.york.com.tw",
    ],
    "urlFormatString": "https://www.york.com.tw/subscribe_safari/apns_landing.php?%@",
    "authenticationToken": "19f8d7a6e9fb8a7f6d9330dabe2",
    "webServiceURL": "https://www.york.com.tw/subscribe_safari"
};
/****************
 * website_json *
 ****************/


/****************
 * 參數準備 START *
 ****************/

# 開發者後台申請下來掛載到鑰匙圈輸出的證書p12
$certificate_path = "/var/www/html/subscribe_safari/cert.p12";
# apple開發的intermediate.pem 證書
$intermediate_certificate_path = "/var/www/html/subscribe_safari/intermediate.pem";
# 開發者後台申請下來掛載到鑰匙圈輸出的證書p12的密碼
 $certificate_password = "";

/**************
 * 參數準備 END *
 **************/

/**************
 * MAIN START *
 **************/

# 依照window.safari.pushNotification.requestPermission的結果選擇提供的服務
switch( checkUrl($_SERVER['REQUEST_URI']) )
{   
    case "log":
        ob_end_clean();
        # 處理 $webServiceURL / v1 / log
        subscribe_safari_log(file_get_contents("php://input"));
        break;

    case "pushPackages":
        ob_end_clean();
        # 處理 $webServiceURL / v1 / pushPackages / <websitePushID>
        pushPackages(file_get_contents("php://input"));
        break;

    case "devices":
        ob_end_clean();
        # 處理 $webServiceURL / v1/ devices / <deviceToken> / registrations / <websitePushID>
        $siteRestHandler->devices(file_get_contents("php://input", flase, r_u_context()));
        break;

    case "" :
        #404 - not found;
        break;
}

/************
 * MAIN END *
 ************/

/**
 * string checkUrl( string $str )
 * Safari 註冊 Module
 * 判斷近來網址類型
 * @author  2016/11/10 York 
 * @param   str           str，用戶端safari post過來的網址
 * @return  回傳Url類型
 */
function checkUrl($str)
{
    if(false !== ($rst = strpos($str, 'log')))
    {
        return 'log';
    }
    if(false !== ($rst = strpos($str, 'pushPackages'))) 
    {
        return 'pushPackages';
    }
    if(false !== ($rst = strpos($str, 'devices'))) 
    {
        return 'devices';
    }
}

/**
 * string subscribe_safari_log( string $responseData )
 * Safari 註冊 Module
 * 寫log by webServer
 * @author  2016/11/10 York 
 * @param   responseData           responseData，寫log的內容
 */
function subscribe_safari_log($responseData)
{
    # 設定時區
    date_default_timezone_set("Asia/Taipei");
    # 轉換陣列成json格式
    if(is_array($responseData))
    {
        array_walk_recursive($contentData, function(&$value, $key) 
        {
            if(is_string($value))$value = urlencode($value);
        });
        $contentData = urldecode(json_encode($contentData, JSON_FORCE_OBJECT));
    }
    # 開啟檔案
    $txt_path = "log/subscribe_safari_log.log";
    $txt = fopen($txt_path, "a");
    # 撰寫responseData
    fwrite($txt, "\r\n[".date("Y/m/d H:i:s")."]-$responseData");
    # 關閉檔案
    fclose($txt);
}
/**
 * string subscribe_safari_log( string $responseData )
 * Safari 註冊 Module
 * 建立pushpackage及回傳
 * @author  2017/01/09 York 
 * @param   userInfo           domain name
 * @return  echo file_get_contents($package_path);
 */
function pushPackages($userInfo)
{
    # 紀錄哪個userInfo要求推包
    $webpushid = substr($_SERVER['REQUEST_URI'], 25);
    subscribe_safari_log( "which webpushid require url: {" . $_SERVER['REQUEST_URI'] . "}" );
    subscribe_safari_log( "which webpushid require pushPackages: {\"webpushid\":\"$webpushid\"}" );
    subscribe_safari_log( "Get userInfo: $userInfo");

    # 記錄得到的$userInfo
    $array = json_decode($domainstr, true);
    subscribe_safari_log( "得到的userInfo轉array: " . $array );

    # 解開$userInfo
    $domain = $array['key'];
    subscribe_safari_log( "得到的key: " . $domain );

    # 記錄指定的 certificate_path
    subscribe_safari_log( "指定的certificate_path: " . $certificate_path );

    # 記錄指定的 certificate_path
    subscribe_safari_log( "指定的intermediate_certificate_path: " . $intermediate_certificate_path );

    # 壓縮推包
    $package_path = create_push_package();
    if ( empty( $package_path ) )
    {
        http_response_code(500);
        die;
    }

    # 傳送推包給safari
    # 重要-清除緩衝
    ob_end_clean();
    # return zip的路徑;
    header("Content-type: application/zip");

    subscribe_safari_log($package_path);
    echo file_get_contents($package_path);

    # 完成傳送推包給safari寫log
    $zip_path = substr($package_path, 3);
    subscribe_safari_log("Rturn pushPackages done: {\"webpushid\":\"$webpushid\",\"package_path\":\"$zip_path\"}");

    # 將做出來的出來的zip刪除
    unlink($package_path);
}

/**
 * string devices( string $test )
 * Safari 註冊 Module
 * 記錄devices 更新
 * @author  2017/01/09 York 
 */
function devices($test)
{   
    # 記錄哪個webpushid,Token更動通知權限
    $method = $_SERVER['REQUEST_METHOD'];
    $webpushid = substr($_SERVER['REQUEST_URI'], 99);
    $updataToken = substr($_SERVER['REQUEST_URI'], 20, 64);
    # 整理array
    $devices_array = array(
        "devices"       =>"Updating permission",
        "method"        =>$method,
        "webpushid"     =>$webpushid,
        "updataToken"   =>$updataToken,
        );
    subscribe_safari_log($devices_array);
    # subscribe_safari_log($_SERVER['REQUEST_URI']);
    # subscribe_safari_log($test);
}

/**
 * string devices( string $test )
 * Safari 註冊 Module
 * 設定回傳header
 * @author  2017/01/09 York 
 * @return  
 */
function r_u_context()
{
    $context = stream_context_create(
        array
        (
            'http' => array(
                'header'  => 'Authorization:ApplePushNotifications 19f8d7a6e9fb8a7f6d9330dabe2'
            )
        )
    );
    return stream_context_create($context);
}

# 創建推包
function create_push_package()
{
    # 建立臨時目錄用於創建package
    $package_dir ="/tmp/safari_pushPackage" . time();
    subscribe_safari_log("建立臨時目錄: ".$package_dir);

    clearstatcache();
    if(!mkdir($package_dir, 0775, true))
    { 
        unlink($package_dir);
        die;
    }

    # 複製基本package_files
    copy_pushPackageRaw_files($package_dir);
    
    # create manifest的json
    create_manifest($package_dir);
    
    # create signature的檔案
    create_signature($package_dir, $certificate_path, $certificate_password);
    
    # 指定zip的路徑
    $package_path = package_raw_data($package_dir);
    
    # 將複製出來的package_dir刪除
    $files = glob("{$package_dir}/*");
    foreach($files as $file)
    { 
        if(is_file($file))
        unlink($file);
    }
    $files = glob("{$package_dir}/icon.iconset/*");
    foreach($files as $file)
    { 
        if(is_file($file))
        unlink($file);
    }
    rmdir("{$package_dir}/icon.iconset");
    rmdir($package_dir);
    # 回傳zip的路徑
    return $package_path;
}

# 複製基本檔案到臨時推包到$package_path
function copy_pushPackageRaw_files($package_dir)
{
    mkdir($package_dir . '/icon.iconset');
    foreach(raw_files() as $raw_file)
    {
        copy("/var/www/hmtl/subscribe_safari/pushPackageRaw/{$raw_file}", "{$package_dir}/{$raw_file}");
    }
}

# 建立icon和website的檔案路徑的array
function raw_files()
{
    return array(
        'icon.iconset/icon_16x16.png',
        'icon.iconset/icon_16x16@2x.png',
        'icon.iconset/icon_32x32.png',
        'icon.iconset/icon_32x32@2x.png',
        'icon.iconset/icon_128x128.png',
        'icon.iconset/icon_128x128@2x.png',
        'website.json'
    );
}

# 建立manifest的json (由website.json轉換的)
function create_manifest($package_dir)
{
    # 取得 SHA1 push package 全部資料的 hashes
    $manifest_data = array();
    foreach (raw_files() as $raw_file)
    {
        $manifest_data[$raw_file] = sha1(file_get_contents("$package_dir/$raw_file"));
    }
    file_put_contents("$package_dir/manifest.json", json_encode((object)$manifest_data));
}

# 建立signature的檔案
function create_signature($package_dir, $cert_path, $cert_password)
{
    # 加載推送通知證書
    $pkcs12 = file_get_contents($cert_path);
    $certs = array();

    if(!openssl_pkcs12_read($pkcs12, $certs, $cert_password))
    {
        return;
    }

    $signature_path = "{$package_dir}/signature";

    # 使用證書中的私鑰對manifest.json文件進行簽名
    $cert_data = openssl_x509_read($certs['cert']);
    $private_key = openssl_pkey_get_private($certs['pkey'], $cert_password);

    openssl_pkcs7_sign("$package_dir/manifest.json", $signature_path, $cert_data, $private_key, array(), PKCS7_BINARY | PKCS7_DETACHED, $intermediate_certificate_path);

    # 將簽名從PEM轉換為DER
    $signature_pem = file_get_contents($signature_path);
    $matches = array();
    if (!preg_match('~Content-Disposition:[^\n]+\s*?([A-Za-z0-9+=/\r\n]+)\s*?-----~', $signature_pem, $matches))
    {
        return;
    }
    $signature_der = base64_decode($matches[1]);
    file_put_contents($signature_path, $signature_der);
}

# 建立zip並回傳zip路徑
function package_raw_data($package_dir)
{
    $zip_path = "$package_dir.zip";
    $zip = new ZipArchive();
    if (!$zip->open("$package_dir.zip", ZIPARCHIVE::CREATE))
    {
        subscribe_safari_log('Could not create' . $zip_path);
        return;
    }
    $raw_files = raw_files();
    $raw_files[] = 'manifest.json';
    $raw_files[] = 'signature';
    foreach ($raw_files as $raw_file)
    {
        $zip->addFile("$package_dir/$raw_file", $raw_file);
    }
    $zip->close();
}  
