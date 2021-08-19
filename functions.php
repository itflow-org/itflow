<?php

function keygen()
{
    $chars = "abcdefghijklmnopqrstuvwxyz";
    $chars .= "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $chars .= "0123456789";
    while (1) {
        $key = '';
        srand((double) microtime() * 1000000);
        for ($i = 0; $i < 16; $i++) {
            $key .= substr($chars, (rand() % (strlen($chars))), 1);
        }
        break;
    }
    return $key;
}

function key32gen()
{
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $chars .= "234567";
    while (1) {
        $key = '';
        srand((double) microtime() * 1000000);
        for ($i = 0; $i < 32; $i++) {
            $key .= substr($chars, (rand() % (strlen($chars))), 1);
        }
        break;
    }
    return $key;
}

function initials($str) {
    $ret = '';
    foreach (explode(' ', $str) as $word)
        $ret .= strtoupper($word[0]);
    return $ret;
}

function removeDirectory($path) {
  $files = glob($path . '/*');
  foreach ($files as $file) {
    is_dir($file) ? removeDirectory($file) : unlink($file);
  }
  rmdir($path);
  return;
}

function get_user_agent() {
    return  $_SERVER['HTTP_USER_AGENT'];
}
function get_ip() {
    $mainIp = '';
    if (getenv('HTTP_CLIENT_IP'))
        $mainIp = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $mainIp = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $mainIp = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $mainIp = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $mainIp = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $mainIp = getenv('REMOTE_ADDR');
    else
        $mainIp = 'UNKNOWN';
    return $mainIp;
}

function get_web_browser() {
    $user_agent = get_user_agent();
    $browser        =   "Unknown Browser";
    $browser_array  =   array(
        '/msie/i'       =>  'Internet Explorer',
        '/Trident/i'    =>  'Internet Explorer',
        '/firefox/i'    =>  'Firefox',
        '/safari/i'     =>  'Safari',
        '/chrome/i'     =>  'Chrome',
        '/edge/i'       =>  'Edge',
        '/opera/i'      =>  'Opera',
        '/netscape/i'   =>  'Netscape',
        '/maxthon/i'    =>  'Maxthon',
        '/konqueror/i'  =>  'Konqueror',
        '/ubrowser/i'   =>  'UC Browser',
        '/mobile/i'     =>  'Handheld Browser'
    );
    foreach ($browser_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $browser    =   $value;
        }
    }
    return $browser;
}

function get_os() {
    $user_agent = get_user_agent();
    $os_platform    =   "Unknown OS Platform";
    $os_array       =   array(
        '/windows nt 10/i'      =>  'Windows 10',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/windows nt 6.0/i'     =>  'Windows Vista',
        '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
        '/windows nt 5.1/i'     =>  'Windows XP',
        '/windows xp/i'         =>  'Windows XP',
        '/macintosh|mac os x/i' =>  'Mac OS X',
        '/linux/i'              =>  'Linux',
        '/ubuntu/i'             =>  'Ubuntu',
        '/iphone/i'             =>  'iPhone',
        '/ipod/i'               =>  'iPod',
        '/ipad/i'               =>  'iPad',
        '/android/i'            =>  'Android',
        '/blackberry/i'         =>  'BlackBerry',
        '/webos/i'              =>  'Mobile'
    );
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) {
            $os_platform    =   $value;
        }
    }   
    return $os_platform;
}

function get_device(){
    $tablet_browser = 0;
    $mobile_browser = 0;
    if (preg_match('/(tablet|ipad|playbook)|(android(?!.*(mobi|opera mini)))/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
        $tablet_browser++;
    }
    if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', strtolower($_SERVER['HTTP_USER_AGENT']))) {
        $mobile_browser++;
    }
    if ((strpos(strtolower($_SERVER['HTTP_ACCEPT']),'application/vnd.wap.xhtml+xml') > 0) or ((isset($_SERVER['HTTP_X_WAP_PROFILE']) or isset($_SERVER['HTTP_PROFILE'])))) {
        $mobile_browser++;
    }
    $mobile_ua = strtolower(substr(get_user_agent(), 0, 4));
    $mobile_agents = array(
        'w3c ','acs-','alav','alca','amoi','audi','avan','benq','bird','blac',
        'blaz','brew','cell','cldc','cmd-','dang','doco','eric','hipt','inno',
        'ipaq','java','jigs','kddi','keji','leno','lg-c','lg-d','lg-g','lge-',
        'maui','maxo','midp','mits','mmef','mobi','mot-','moto','mwbp','nec-',
        'newt','noki','palm','pana','pant','phil','play','port','prox',
        'qwap','sage','sams','sany','sch-','sec-','send','seri','sgh-','shar',
        'sie-','siem','smal','smar','sony','sph-','symb','t-mo','teli','tim-',
        'tosh','tsm-','upg1','upsi','vk-v','voda','wap-','wapa','wapi','wapp',
        'wapr','webc','winw','winw','xda ','xda-');
    if (in_array($mobile_ua,$mobile_agents)) {
        $mobile_browser++;
    }
    if (strpos(strtolower(get_user_agent()),'opera mini') > 0) {
        $mobile_browser++;
            //Check for tablets on opera mini alternative headers
        $stock_ua = strtolower(isset($_SERVER['HTTP_X_OPERAMINI_PHONE_UA'])?$_SERVER['HTTP_X_OPERAMINI_PHONE_UA']:(isset($_SERVER['HTTP_DEVICE_STOCK_UA'])?$_SERVER['HTTP_DEVICE_STOCK_UA']:''));
        if (preg_match('/(tablet|ipad|playbook)|(android(?!.*mobile))/i', $stock_ua)) {
            $tablet_browser++;
        }
    }
    if ($tablet_browser > 0) {
           // do something for tablet devices
        return 'Tablet';
    }
    else if ($mobile_browser > 0) {
           // do something for mobile devices
        return 'Mobile';
    }
    else {
           // do something for everything else
        return 'Computer';
    }   
}

function truncate($text, $chars = 25) {
    if (strlen($text) <= $chars) {
        return $text;
    }
    $text = $text." ";
    $text = substr($text,0,$chars);
    $text = substr($text,0,strrpos($text,' '));
    $text = $text."...";
    return $text;
}

function get_currency_symbol($cc = 'USD')
{
    $cc = strtoupper($cc);
    $currency = array(
    "USD" => "$" , //U.S. Dollar
    "AUD" => "$" , //Australian Dollar
    "BRL" => "R$" , //Brazilian Real
    "CAD" => "C$" , //Canadian Dollar
    "CZK" => "Kč" , //Czech Koruna
    "DKK" => "kr" , //Danish Krone
    "EUR" => "€" , //Euro
    "HKD" => "&#36" , //Hong Kong Dollar
    "HUF" => "Ft" , //Hungarian Forint
    "ILS" => "₪" , //Israeli New Sheqel
    "INR" => "₹", //Indian Rupee
    "JPY" => "¥" , //Japanese Yen 
    "MYR" => "RM" , //Malaysian Ringgit 
    "MXN" => "&#36" , //Mexican Peso
    "NOK" => "kr" , //Norwegian Krone
    "NZD" => "&#36" , //New Zealand Dollar
    "PHP" => "₱" , //Philippine Peso
    "PLN" => "zł" ,//Polish Zloty
    "GBP" => "£" , //Pound Sterling
    "SEK" => "kr" , //Swedish Krona
    "CHF" => "Fr" , //Swiss Franc
    "TWD" => "$" , //Taiwan New Dollar 
    "THB" => "฿" , //Thai Baht
    "TRY" => "₺" //Turkish Lira
    );
    
    if(array_key_exists($cc, $currency)){
        return $currency[$cc];
    }
}

function get_otp($secret_seed) {
    //TOTP seed (String representation)
    $otp = '';
    //number of seconds of otp period
    $time_window = 30;

    //time formating to epoch
    $exact_time = microtime(true);
    $rounded_time = floor($exact_time/$time_window);

    //binary represetation of time without padding
    $packed_time = pack("N", $rounded_time);

    //binary representation of time with padding
    $padded_packed_time = str_pad($packed_time,8, chr(0), STR_PAD_LEFT);

    //binary representation of seed
    $packed_secret_seed = pack("H*", $secret_seed);

    //HMAC SHA1 hash (time + seed)
    $hash = hash_hmac ('sha1', $padded_packed_time, $packed_secret_seed, true);

    $offset = ord($hash[19]) & 0xf;
    $otp = (
            ((ord($hash[$offset+0]) & 0x7f) << 24 ) |
            ((ord($hash[$offset+1]) & 0xff) << 16 ) |
            ((ord($hash[$offset+2]) & 0xff) << 8 ) |
            (ord($hash[$offset+3]) & 0xff)
    ) % pow(10, 6); 

    //adding pad to otp, in order to assure a "6" digits
    $otp = str_pad($otp, 6, "0", STR_PAD_LEFT);

    return $otp;
}

?>