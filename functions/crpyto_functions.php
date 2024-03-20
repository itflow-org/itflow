<?php 

// Cryptography related functions

// Function to generate both crypto & URL safe random strings
function randomString($length = 16)
{
    // Generate some cryptographically safe random bytes
    //  Generate a little more than requested as we'll lose some later converting
    $random_bytes = random_bytes($length + 5);

    // Convert the bytes to something somewhat human-readable
    $random_base_64 = base64_encode($random_bytes);

    // Replace the nasty characters that come with base64
    $bad_chars = array("/", "+", "=");
    $random_string = str_replace($bad_chars, random_int(0, 9), $random_base_64);

    // Truncate the string to the requested $length and return
    return substr($random_string, 0, $length);
}

// Older keygen function - only used for TOTP currently
function key32gen()
{
    $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $chars .= "234567";
    while (1) {
        $key = '';
        srand((float) microtime() * 1000000);
        for ($i = 0; $i < 32; $i++) {
            $key .= substr($chars, (rand() % (strlen($chars))), 1);
        }
        break;
    }
    return $key;
}