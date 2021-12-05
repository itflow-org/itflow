<?php




function hotp (string $algo, string $key, int $count, int $length = 6)
{
  // hmac $count as uint64 (big endian) with binary $key
  $hmac = hash_hmac($algo, pack("J", $count), $key, TRUE);

  // get least significant nibble of our $hmac, yielding $offset values 0..15
  $offset = unpack("C", $hmac, strlen($hmac)-1)[1] & 0x0F;

  // extract a uint32 (big endian) from our $hmac, and mask the most significant bit (the sign bit)
  $number = unpack("N", $hmac, $offset)[1] & 0x7FFFFFFF;

  // return token based on $number in $length decimal digits, padded with leading zeros
  return str_pad($number % (10 ** $length), $length, "0", STR_PAD_LEFT);
}


function totp (string $algo, string $key, int $unixtime, int $interval = 30, int $length = 6)
{
  return hotp($algo, $key, intdiv($unixtime, $interval), $length);
}





function hotp_token_ok (string $token, string $algo, string $key, int $count, int $window = 10, int $length = 6)
{
  $ok = FALSE;

  for ($i = -$window; $i <= $window; $i++)
  {
    $ok |= hash_equals(hotp($algo, $key, ($count + $i), $length), $token);
  }

  return $ok;
}


function totp_token_ok (string $token, string $algo, string $key, int $unixtime, int $window = 300, int $interval = 30, int $length = 6)
{
  return hotp_token_ok($algo, $key, $token, intdiv($unixtime, $interval), intdiv($window, $interval), $length);
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

$unixtimestamp = time();
//$unixtimestamp = "1638664893";
//$secretkey = key32gen();
$secretkey = "";

echo "Unix Time is: $unixtimestamp<br>";

echo "secret Key is: $secretkey<br>";


$test = totp("sha1","$secretkey","$unixtimestamp",30,6);

echo "<br>code is: $test";


?>