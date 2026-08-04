<?php

//TOTP
//simple PHP implementation of a Time-based One-Time Password (TOTP) authentication mechanism (as described in RFC 6238). It uses HMAC-SHA1 with a time-based counter (stepping in 30-second intervals by default) to generate and verify 6-digit codes, much like Google Authenticator or other 2FA apps.

//base32static
/**
 * Encode in Base32 based on RFC 4648.
 * Requires 20% more space than base64
 * Great for case-insensitive filesystems like Windows and URL's  (except for = char which can be excluded using the pad option for urls)
 *
 * @package default
 * @author Bryan Ruiz
 **/
class Base32Static {
    private static $map = array(
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', //  7
        'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', // 15
        'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', // 23
        'Y', 'Z', '2', '3', '4', '5', '6', '7', // 31
        '='  // padding character
    );

    private static $flippedMap = array(
        'A'=>'0', 'B'=>'1', 'C'=>'2', 'D'=>'3', 'E'=>'4', 'F'=>'5', 'G'=>'6', 'H'=>'7',
        'I'=>'8', 'J'=>'9', 'K'=>'10', 'L'=>'11', 'M'=>'12', 'N'=>'13', 'O'=>'14', 'P'=>'15',
        'Q'=>'16', 'R'=>'17', 'S'=>'18', 'T'=>'19', 'U'=>'20', 'V'=>'21', 'W'=>'22', 'X'=>'23',
        'Y'=>'24', 'Z'=>'25', '2'=>'26', '3'=>'27', '4'=>'28', '5'=>'29', '6'=>'30', '7'=>'31'
    );

    /**
     * Use padding false when encoding for urls
     *
     * @return base32 encoded string
     * @author Bryan Ruiz
     **/
    public static function encode($input, $padding = true) {
        if (empty($input)) return "";

        $input = str_split($input);
        $binaryString = "";

        for ($i = 0; $i < count($input); $i++) {
            $binaryString .= str_pad(base_convert(ord($input[$i]), 10, 2), 8, '0', STR_PAD_LEFT);
        }

        $fiveBitBinaryArray = str_split($binaryString, 5);
        $base32 = "";
        $i=0;

        while($i < count($fiveBitBinaryArray)) {
            $base32 .= self::$map[base_convert(str_pad($fiveBitBinaryArray[$i], 5, '0'), 2, 10)];
            $i++;
        }

        if ($padding && ($x = strlen($binaryString) % 40) != 0) {
            if ($x == 8) $base32 .= str_repeat(self::$map[32], 6);
            else if ($x == 16) $base32 .= str_repeat(self::$map[32], 4);
            else if ($x == 24) $base32 .= str_repeat(self::$map[32], 3);
            else if ($x == 32) $base32 .= self::$map[32];
        }

        return $base32;
    }

    public static function decode($input) {
        if (empty($input)) return;

        $paddingCharCount = substr_count($input, self::$map[32]);
        $allowedValues = array(6,4,3,1,0);

        if (!in_array($paddingCharCount, $allowedValues)) return false;

        for ($i=0; $i<4; $i++){
            if ($paddingCharCount == $allowedValues[$i] &&
                substr($input, -($allowedValues[$i])) != str_repeat(self::$map[32], $allowedValues[$i])) return false;
        }

        $input = str_replace('=', '', $input);
        $input = str_split($input);
        $binaryString = "";

        for ($i=0; $i < count($input); $i = $i+8) {
            $x = "";

            if (!in_array($input[$i], self::$map)) return false;

            for ($j=0; $j < 8; $j++) {
                $x .= str_pad(base_convert(@self::$flippedMap[@$input[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
            }

            $eightBits = str_split($x, 8);

            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y:"";
            }
        }

        return $binaryString;
    }
}

//http://www.faqs.org/rfcs/rfc6238.html
class TokenAuth6238 {

    /**
     * verify
     *
     * @param string $secretkey Secret clue (base 32).
     * @return bool True if success, false if failure
     */
    public static function verify($secretkey, $code, $rangein30s = 3) {
        $key = base32static::decode($secretkey);
        $unixtimestamp = time()/30;

        for($i=-($rangein30s); $i<=$rangein30s; $i++) {
            $checktime = (int)($unixtimestamp+$i);
            $thiskey = self::oath_hotp($key, $checktime);

            if ((int)$code == self::oath_truncate($thiskey, 6)) {
                return true;
            }

        }
        return false;
    }
    public static function getTokenCode($secretkey) {
        $result = "";
        $key = base32static::decode($secretkey);
        $unixtimestamp = time()/30;

        $checktime = (int)($unixtimestamp);
        $thiskey = self::oath_hotp($key, $checktime);
        $result = $result . self::oath_truncate($thiskey, 6);

        $result = "000000" . $result;
        return substr($result, -6);
    }
    public static function getTokenCodeDebug($secretkey, $rangein30s = 3) {
        $result = "";
        print "<br/>SecretKey: $secretkey <br/>";

        $key = base32static::decode($secretkey);
        print "Key(base 32 decode): $key <br/>";

        $unixtimestamp = time()/30;
        print "UnixTimeStamp (time()/30): $unixtimestamp <br/>";
        for($i=-($rangein30s); $i<=$rangein30s; $i++) {
            $checktime = (int)($unixtimestamp+$i);
            print "Calculating oath_hotp from (int)(unixtimestamp +- 30sec offset): $checktime basing on secret key<br/>";

            $thiskey = self::oath_hotp($key, $checktime, true);
            print "======================================================<br/>";
            print "CheckTime: $checktime oath_hotp:".$thiskey."<br/>";
            $result = $result." # ".self::oath_truncate($thiskey, 6, true);
        }

        return $result;
    }

    private static function oath_hotp ($key, $counter, $debug=false) {
        $result = "";
        $orgcounter = $counter;
        $cur_counter = array(0,0,0,0,0,0,0,0);

        if ($debug) {
            print "Packing counter $counter (".dechex($counter).")into binary string - pay attention to hex representation of key and binary representation<br/>";
        }

        for($i=7;$i>=0;$i--) { // C for unsigned char, * for  repeating to the end of the input data
            $cur_counter[$i] = pack ('C*', $counter);

            if ($debug)  {
                print $cur_counter[$i]."(".dechex(ord($cur_counter[$i])).")"." from $counter <br/>";
            }

            $counter = $counter >> 8;
        }

        if ($debug) {
            foreach ($cur_counter as $char) {
                print ord($char) . " ";
            }

            print "<br/>";
        }

        $binary = implode($cur_counter);
        // Pad to 8 characters
        str_pad($binary, 8, chr(0), STR_PAD_LEFT);

        if ($debug)  {
            print "Prior to HMAC calculation pad with zero on the left until 8 characters.<br/>";
            print "Calculate sha1 HMAC(Hash-based Message Authentication Code https://en.wikipedia.org/wiki/HMAC).<br/>";
            print "hash_hmac ('sha1', $binary, $key)<br/>";
        }
        $result = hash_hmac ('sha1', $binary, $key);

        if ($debug) {
            print "Result: $result <br/>";
        }
        return $result;
    }
    private static function oath_truncate($hash, $length = 6, $debug=false) {
        $result="";

        // Convert to dec
        if ($debug) {
            print "converting hex hash into characters<br/>";
        }

        $hashcharacters = str_split($hash, 2);

        if ($debug) {
            print_r($hashcharacters);
            print "<br/>and convert to decimals:<br/>";
        }
        for ($j=0; $j<count($hashcharacters); $j++) {
            $hmac_result[]=hexdec($hashcharacters[$j]);
        }

        if ($debug) {
            print_r($hmac_result);
        }
        // http://php.net/manual/ru/function.hash-hmac.php
        // adopted from brent at thebrent dot net 21-May-2009 08:17 comment
        $offset = $hmac_result[19] & 0xf;

        if ($debug) {
            print "Calculating offset as 19th element of hmac:".$hmac_result[19]."<br/>";
            print "offset:".$offset;
        }

        $result = (
                (($hmac_result[$offset+0] & 0x7f) << 24) |
                (($hmac_result[$offset+1] & 0xff) << 16) |
                (($hmac_result[$offset+2] & 0xff) << 8) |
                ($hmac_result[$offset+3] & 0xff)
            ) % pow(10, $length);
        return $result;
    }

}
