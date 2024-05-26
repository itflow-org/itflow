<?php
// http://www.faqs.org/rfcs/rfc6238.html
require_once(dirname(__FILE__).'/base32static.php');
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
