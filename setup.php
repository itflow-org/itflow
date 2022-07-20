<?php

if(file_exists("config.php")){
  include("config.php");
}

include("functions.php");
include("database_version.php");

if(!isset($config_enable_setup)){
  $config_enable_setup = 1;
}

if($config_enable_setup == 0){
  header("Location: login.php");
  exit;
}

$countries_array = array(
  "Afghanistan",
  "Albania",
  "Algeria",
  "Andorra",
  "Angola",
  "Antigua and Barbuda",
  "Argentina",
  "Armenia",
  "Australia",
  "Austria",
  "Azerbaijan",
  "Bahamas",
  "Bahrain",
  "Bangladesh",
  "Barbados",
  "Belarus",
  "Belgium",
  "Belize",
  "Benin",
  "Bhutan",
  "Bolivia",
  "Bosnia and Herzegovina",
  "Botswana",
  "Brazil",
  "Brunei",
  "Bulgaria",
  "Burkina Faso",
  "Burundi",
  "Cambodia",
  "Cameroon",
  "Canada",
  "Cape Verde",
  "Central African Republic",
  "Chad",
  "Chile",
  "China",
  "Colombi",
  "Comoros",
  "Congo (Brazzaville)",
  "Congo",
  "Costa Rica",
  "Cote d'Ivoire",
  "Croatia",
  "Cuba",
  "Cyprus",
  "Czech Republic",
  "Denmark",
  "Djibouti",
  "Dominica",
  "Dominican Republic",
  "East Timor (Timor Timur)",
  "Ecuador",
  "Egypt",
  "El Salvador",
  "Equatorial Guinea",
  "Eritrea",
  "Estonia",
  "Ethiopia",
  "Fiji",
  "Finland",
  "France",
  "Gabon",
  "Gambia, The",
  "Georgia",
  "Germany",
  "Ghana",
  "Greece",
  "Grenada",
  "Guatemala",
  "Guinea",
  "Guinea-Bissau",
  "Guyana",
  "Haiti",
  "Honduras",
  "Hungary",
  "Iceland",
  "India",
  "Indonesia",
  "Iran",
  "Iraq",
  "Ireland",
  "Israel",
  "Italy",
  "Jamaica",
  "Japan",
  "Jordan",
  "Kazakhstan",
  "Kenya",
  "Kiribati",
  "Korea, North",
  "Korea, South",
  "Kuwait",
  "Kyrgyzstan",
  "Laos",
  "Latvia",
  "Lebanon",
  "Lesotho",
  "Liberia",
  "Libya",
  "Liechtenstein",
  "Lithuania",
  "Luxembourg",
  "Macedonia",
  "Madagascar",
  "Malawi",
  "Malaysia",
  "Maldives",
  "Mali",
  "Malta",
  "Marshall Islands",
  "Mauritania",
  "Mauritius",
  "Mexico",
  "Micronesia",
  "Moldova",
  "Monaco",
  "Mongolia",
  "Morocco",
  "Mozambique",
  "Myanmar",
  "Namibia",
  "Nauru",
  "Nepal",
  "Netherlands",
  "New Zealand",
  "Nicaragua",
  "Niger",
  "Nigeria",
  "Norway",
  "Oman",
  "Pakistan",
  "Palau",
  "Panama",
  "Papua New Guinea",
  "Paraguay",
  "Peru",
  "Philippines",
  "Poland",
  "Portugal",
  "Qatar",
  "Romania",
  "Russia",
  "Rwanda",
  "Saint Kitts and Nevis",
  "Saint Lucia",
  "Saint Vincent",
  "Samoa",
  "San Marino",
  "Sao Tome and Principe",
  "Saudi Arabia",
  "Senegal",
  "Serbia and Montenegro",
  "Seychelles",
  "Sierra Leone",
  "Singapore",
  "Slovakia",
  "Slovenia",
  "Solomon Islands",
  "Somalia",
  "South Africa",
  "Spain",
  "Sri Lanka",
  "Sudan",
  "Suriname",
  "Swaziland",
  "Sweden",
  "Switzerland",
  "Syria",
  "Taiwan",
  "Tajikistan",
  "Tanzania",
  "Thailand",
  "Togo",
  "Tonga",
  "Trinidad and Tobago",
  "Tunisia",
  "Turkey",
  "Turkmenistan",
  "Tuvalu",
  "Uganda",
  "Ukraine",
  "United Arab Emirates",
  "United Kingdom",
  "United States",
  "Uruguay",
  "Uzbekistan",
  "Vanuatu",
  "Vatican City",
  "Venezuela",
  "Vietnam",
  "Yemen",
  "Zambia",
  "Zimbabwe"
);

$currencies_array = array(
  'ALL' => 'Albania Lek',
  'AFN' => 'Afghanistan Afghani',
  'ARS' => 'Argentina Peso',
  'AWG' => 'Aruba Guilder',
  'AUD' => 'Australia Dollar',
  'AZN' => 'Azerbaijan New Manat',
  'BSD' => 'Bahamas Dollar',
  'BBD' => 'Barbados Dollar',
  'BDT' => 'Bangladeshi taka',
  'BYR' => 'Belarus Ruble',
  'BZD' => 'Belize Dollar',
  'BMD' => 'Bermuda Dollar',
  'BOB' => 'Bolivia Boliviano',
  'BAM' => 'Bosnia and Herzegovina Convertible Marka',
  'BWP' => 'Botswana Pula',
  'BGN' => 'Bulgaria Lev',
  'BRL' => 'Brazil Real',
  'BND' => 'Brunei Darussalam Dollar',
  'KHR' => 'Cambodia Riel',
  'CAD' => 'Canada Dollar',
  'KYD' => 'Cayman Islands Dollar',
  'CLP' => 'Chile Peso',
  'CNY' => 'China Yuan Renminbi',
  'COP' => 'Colombia Peso',
  'CRC' => 'Costa Rica Colon',
  'HRK' => 'Croatia Kuna',
  'CUP' => 'Cuba Peso',
  'CZK' => 'Czech Republic Koruna',
  'DKK' => 'Denmark Krone',
  'DOP' => 'Dominican Republic Peso',
  'XCD' => 'East Caribbean Dollar',
  'EGP' => 'Egypt Pound',
  'SVC' => 'El Salvador Colon',
  'EEK' => 'Estonia Kroon',
  'EUR' => 'Euro Member Countries',
  'FKP' => 'Falkland Islands (Malvinas) Pound',
  'FJD' => 'Fiji Dollar',
  'GHC' => 'Ghana Cedis',
  'GIP' => 'Gibraltar Pound',
  'GTQ' => 'Guatemala Quetzal',
  'GGP' => 'Guernsey Pound',
  'GYD' => 'Guyana Dollar',
  'HNL' => 'Honduras Lempira',
  'HKD' => 'Hong Kong Dollar',
  'HUF' => 'Hungary Forint',
  'ISK' => 'Iceland Krona',
  'INR' => 'India Rupee',
  'IDR' => 'Indonesia Rupiah',
  'IRR' => 'Iran Rial',
  'IMP' => 'Isle of Man Pound',
  'ILS' => 'Israel Shekel',
  'JMD' => 'Jamaica Dollar',
  'JPY' => 'Japan Yen',
  'JEP' => 'Jersey Pound',
  'KZT' => 'Kazakhstan Tenge',
  'KPW' => 'Korea (North) Won',
  'KRW' => 'Korea (South) Won',
  'KGS' => 'Kyrgyzstan Som',
  'LAK' => 'Laos Kip',
  'LVL' => 'Latvia Lat',
  'LBP' => 'Lebanon Pound',
  'LRD' => 'Liberia Dollar',
  'LTL' => 'Lithuania Litas',
  'MKD' => 'Macedonia Denar',
  'MYR' => 'Malaysia Ringgit',
  'MUR' => 'Mauritius Rupee',
  'MXN' => 'Mexico Peso',
  'MNT' => 'Mongolia Tughrik',
  'MZN' => 'Mozambique Metical',
  'NAD' => 'Namibia Dollar',
  'NPR' => 'Nepal Rupee',
  'ANG' => 'Netherlands Antilles Guilder',
  'NZD' => 'New Zealand Dollar',
  'NIO' => 'Nicaragua Cordoba',
  'NGN' => 'Nigeria Naira',
  'NOK' => 'Norway Krone',
  'OMR' => 'Oman Rial',
  'PKR' => 'Pakistan Rupee',
  'PAB' => 'Panama Balboa',
  'PYG' => 'Paraguay Guarani',
  'PEN' => 'Peru Nuevo Sol',
  'PHP' => 'Philippines Peso',
  'PLN' => 'Poland Zloty',
  'QAR' => 'Qatar Riyal',
  'RON' => 'Romania New Leu',
  'RUB' => 'Russia Ruble',
  'SHP' => 'Saint Helena Pound',
  'SAR' => 'Saudi Arabia Riyal',
  'RSD' => 'Serbia Dinar',
  'SCR' => 'Seychelles Rupee',
  'SGD' => 'Singapore Dollar',
  'SBD' => 'Solomon Islands Dollar',
  'SOS' => 'Somalia Shilling',
  'ZAR' => 'South Africa Rand',
  'LKR' => 'Sri Lanka Rupee',
  'SEK' => 'Sweden Krona',
  'CHF' => 'Switzerland Franc',
  'SRD' => 'Suriname Dollar',
  'SYP' => 'Syria Pound',
  'TWD' => 'Taiwan New Dollar',
  'THB' => 'Thailand Baht',
  'TTD' => 'Trinidad and Tobago Dollar',
  'TRY' => 'Turkey Lira',
  'TRL' => 'Turkey Lira',
  'TVD' => 'Tuvalu Dollar',
  'UAH' => 'Ukraine Hryvna',
  'GBP' => 'United Kingdom Pound',
  'USD' => 'United States Dollar',
  'UYU' => 'Uruguay Peso',
  'UZS' => 'Uzbekistan Som',
  'VEF' => 'Venezuela Bolivar',
  'VND' => 'Viet Nam Dong',
  'YER' => 'Yemen Rial',
  'ZWD' => 'Zimbabwe Dollar'
);

// List of locales
$locales_array = [
  'af_NA'       => 'Afrikaans (Namibia)',
  'af_ZA'       => 'Afrikaans (South Africa)',
  'af'          => 'Afrikaans',
  'ak_GH'       => 'Akan (Ghana)',
  'ak'          => 'Akan',
  'sq_AL'       => 'Albanian (Albania)',
  'sq'          => 'Albanian',
  'am_ET'       => 'Amharic (Ethiopia)',
  'am'          => 'Amharic',
  'ar_DZ'       => 'Arabic (Algeria)',
  'ar_BH'       => 'Arabic (Bahrain)',
  'ar_EG'       => 'Arabic (Egypt)',
  'ar_IQ'       => 'Arabic (Iraq)',
  'ar_JO'       => 'Arabic (Jordan)',
  'ar_KW'       => 'Arabic (Kuwait)',
  'ar_LB'       => 'Arabic (Lebanon)',
  'ar_LY'       => 'Arabic (Libya)',
  'ar_MA'       => 'Arabic (Morocco)',
  'ar_OM'       => 'Arabic (Oman)',
  'ar_QA'       => 'Arabic (Qatar)',
  'ar_SA'       => 'Arabic (Saudi Arabia)',
  'ar_SD'       => 'Arabic (Sudan)',
  'ar_SY'       => 'Arabic (Syria)',
  'ar_TN'       => 'Arabic (Tunisia)',
  'ar_AE'       => 'Arabic (United Arab Emirates)',
  'ar_YE'       => 'Arabic (Yemen)',
  'ar'          => 'Arabic',
  'hy_AM'       => 'Armenian (Armenia)',
  'hy'          => 'Armenian',
  'as_IN'       => 'Assamese (India)',
  'as'          => 'Assamese',
  'asa_TZ'      => 'Asu (Tanzania)',
  'asa'         => 'Asu',
  'az_Cyrl'     => 'Azerbaijani (Cyrillic)',
  'az_Cyrl_AZ'  => 'Azerbaijani (Cyrillic, Azerbaijan)',
  'az_Latn'     => 'Azerbaijani (Latin)',
  'az_Latn_AZ'  => 'Azerbaijani (Latin, Azerbaijan)',
  'az'          => 'Azerbaijani',
  'bm_ML'       => 'Bambara (Mali)',
  'bm'          => 'Bambara',
  'eu_ES'       => 'Basque (Spain)',
  'eu'          => 'Basque',
  'be_BY'       => 'Belarusian (Belarus)',
  'be'          => 'Belarusian',
  'bem_ZM'      => 'Bemba (Zambia)',
  'bem'         => 'Bemba',
  'bez_TZ'      => 'Bena (Tanzania)',
  'bez'         => 'Bena',
  'bn_BD'       => 'Bengali (Bangladesh)',
  'bn_IN'       => 'Bengali (India)',
  'bn'          => 'Bengali',
  'bs_BA'       => 'Bosnian (Bosnia and Herzegovina)',
  'bs'          => 'Bosnian',
  'bg_BG'       => 'Bulgarian (Bulgaria)',
  'bg'          => 'Bulgarian',
  'my_MM'       => 'Burmese (Myanmar [Burma])',
  'my'          => 'Burmese',
  'ca_ES'       => 'Catalan (Spain)',
  'ca'          => 'Catalan',
  'tzm_Latn'    => 'Central Morocco Tamazight (Latin)',
  'tzm_Latn_MA' => 'Central Morocco Tamazight (Latin, Morocco)',
  'tzm'         => 'Central Morocco Tamazight',
  'chr_US'      => 'Cherokee (United States)',
  'chr'         => 'Cherokee',
  'cgg_UG'      => 'Chiga (Uganda)',
  'cgg'         => 'Chiga',
  'zh_Hans'     => 'Chinese (Simplified Han)',
  'zh_Hans_CN'  => 'Chinese (Simplified Han, China)',
  'zh_Hans_HK'  => 'Chinese (Simplified Han, Hong Kong SAR China)',
  'zh_Hans_MO'  => 'Chinese (Simplified Han, Macau SAR China)',
  'zh_Hans_SG'  => 'Chinese (Simplified Han, Singapore)',
  'zh_Hant'     => 'Chinese (Traditional Han)',
  'zh_Hant_HK'  => 'Chinese (Traditional Han, Hong Kong SAR China)',
  'zh_Hant_MO'  => 'Chinese (Traditional Han, Macau SAR China)',
  'zh_Hant_TW'  => 'Chinese (Traditional Han, Taiwan)',
  'zh'          => 'Chinese',
  'kw_GB'       => 'Cornish (United Kingdom)',
  'kw'          => 'Cornish',
  'hr_HR'       => 'Croatian (Croatia)',
  'hr'          => 'Croatian',
  'cs_CZ'       => 'Czech (Czech Republic)',
  'cs'          => 'Czech',
  'da_DK'       => 'Danish (Denmark)',
  'da'          => 'Danish',
  'nl_BE'       => 'Dutch (Belgium)',
  'nl_NL'       => 'Dutch (Netherlands)',
  'nl'          => 'Dutch',
  'ebu_KE'      => 'Embu (Kenya)',
  'ebu'         => 'Embu',
  'en_AS'       => 'English (American Samoa)',
  'en_AU'       => 'English (Australia)',
  'en_BE'       => 'English (Belgium)',
  'en_BZ'       => 'English (Belize)',
  'en_BW'       => 'English (Botswana)',
  'en_CA'       => 'English (Canada)',
  'en_GU'       => 'English (Guam)',
  'en_HK'       => 'English (Hong Kong SAR China)',
  'en_IN'       => 'English (India)',
  'en_IE'       => 'English (Ireland)',
  'en_JM'       => 'English (Jamaica)',
  'en_MT'       => 'English (Malta)',
  'en_MH'       => 'English (Marshall Islands)',
  'en_MU'       => 'English (Mauritius)',
  'en_NA'       => 'English (Namibia)',
  'en_NZ'       => 'English (New Zealand)',
  'en_MP'       => 'English (Northern Mariana Islands)',
  'en_PK'       => 'English (Pakistan)',
  'en_PH'       => 'English (Philippines)',
  'en_SG'       => 'English (Singapore)',
  'en_ZA'       => 'English (South Africa)',
  'en_TT'       => 'English (Trinidad and Tobago)',
  'en_UM'       => 'English (U.S. Minor Outlying Islands)',
  'en_VI'       => 'English (U.S. Virgin Islands)',
  'en_GB'       => 'English (United Kingdom)',
  'en_US'       => 'English (United States)',
  'en_ZW'       => 'English (Zimbabwe)',
  'en'          => 'English',
  'eo'          => 'Esperanto',
  'et_EE'       => 'Estonian (Estonia)',
  'et'          => 'Estonian',
  'ee_GH'       => 'Ewe (Ghana)',
  'ee_TG'       => 'Ewe (Togo)',
  'ee'          => 'Ewe',
  'fo_FO'       => 'Faroese (Faroe Islands)',
  'fo'          => 'Faroese',
  'fil_PH'      => 'Filipino (Philippines)',
  'fil'         => 'Filipino',
  'fi_FI'       => 'Finnish (Finland)',
  'fi'          => 'Finnish',
  'fr_BE'       => 'French (Belgium)',
  'fr_BJ'       => 'French (Benin)',
  'fr_BF'       => 'French (Burkina Faso)',
  'fr_BI'       => 'French (Burundi)',
  'fr_CM'       => 'French (Cameroon)',
  'fr_CA'       => 'French (Canada)',
  'fr_CF'       => 'French (Central African Republic)',
  'fr_TD'       => 'French (Chad)',
  'fr_KM'       => 'French (Comoros)',
  'fr_CG'       => 'French (Congo - Brazzaville)',
  'fr_CD'       => 'French (Congo - Kinshasa)',
  'fr_CI'       => 'French (Côte d’Ivoire)',
  'fr_DJ'       => 'French (Djibouti)',
  'fr_GQ'       => 'French (Equatorial Guinea)',
  'fr_FR'       => 'French (France)',
  'fr_GA'       => 'French (Gabon)',
  'fr_GP'       => 'French (Guadeloupe)',
  'fr_GN'       => 'French (Guinea)',
  'fr_LU'       => 'French (Luxembourg)',
  'fr_MG'       => 'French (Madagascar)',
  'fr_ML'       => 'French (Mali)',
  'fr_MQ'       => 'French (Martinique)',
  'fr_MC'       => 'French (Monaco)',
  'fr_NE'       => 'French (Niger)',
  'fr_RW'       => 'French (Rwanda)',
  'fr_RE'       => 'French (Réunion)',
  'fr_BL'       => 'French (Saint Barthélemy)',
  'fr_MF'       => 'French (Saint Martin)',
  'fr_SN'       => 'French (Senegal)',
  'fr_CH'       => 'French (Switzerland)',
  'fr_TG'       => 'French (Togo)',
  'fr'          => 'French',
  'ff_SN'       => 'Fulah (Senegal)',
  'ff'          => 'Fulah',
  'gl_ES'       => 'Galician (Spain)',
  'gl'          => 'Galician',
  'lg_UG'       => 'Ganda (Uganda)',
  'lg'          => 'Ganda',
  'ka_GE'       => 'Georgian (Georgia)',
  'ka'          => 'Georgian',
  'de_AT'       => 'German (Austria)',
  'de_BE'       => 'German (Belgium)',
  'de_DE'       => 'German (Germany)',
  'de_LI'       => 'German (Liechtenstein)',
  'de_LU'       => 'German (Luxembourg)',
  'de_CH'       => 'German (Switzerland)',
  'de'          => 'German',
  'el_CY'       => 'Greek (Cyprus)',
  'el_GR'       => 'Greek (Greece)',
  'el'          => 'Greek',
  'gu_IN'       => 'Gujarati (India)',
  'gu'          => 'Gujarati',
  'guz_KE'      => 'Gusii (Kenya)',
  'guz'         => 'Gusii',
  'ha_Latn'     => 'Hausa (Latin)',
  'ha_Latn_GH'  => 'Hausa (Latin, Ghana)',
  'ha_Latn_NE'  => 'Hausa (Latin, Niger)',
  'ha_Latn_NG'  => 'Hausa (Latin, Nigeria)',
  'ha'          => 'Hausa',
  'haw_US'      => 'Hawaiian (United States)',
  'haw'         => 'Hawaiian',
  'he_IL'       => 'Hebrew (Israel)',
  'he'          => 'Hebrew',
  'hi_IN'       => 'Hindi (India)',
  'hi'          => 'Hindi',
  'hu_HU'       => 'Hungarian (Hungary)',
  'hu'          => 'Hungarian',
  'is_IS'       => 'Icelandic (Iceland)',
  'is'          => 'Icelandic',
  'ig_NG'       => 'Igbo (Nigeria)',
  'ig'          => 'Igbo',
  'id_ID'       => 'Indonesian (Indonesia)',
  'id'          => 'Indonesian',
  'ga_IE'       => 'Irish (Ireland)',
  'ga'          => 'Irish',
  'it_IT'       => 'Italian (Italy)',
  'it_CH'       => 'Italian (Switzerland)',
  'it'          => 'Italian',
  'ja_JP'       => 'Japanese (Japan)',
  'ja'          => 'Japanese',
  'kea_CV'      => 'Kabuverdianu (Cape Verde)',
  'kea'         => 'Kabuverdianu',
  'kab_DZ'      => 'Kabyle (Algeria)',
  'kab'         => 'Kabyle',
  'kl_GL'       => 'Kalaallisut (Greenland)',
  'kl'          => 'Kalaallisut',
  'kln_KE'      => 'Kalenjin (Kenya)',
  'kln'         => 'Kalenjin',
  'kam_KE'      => 'Kamba (Kenya)',
  'kam'         => 'Kamba',
  'kn_IN'       => 'Kannada (India)',
  'kn'          => 'Kannada',
  'kk_Cyrl'     => 'Kazakh (Cyrillic)',
  'kk_Cyrl_KZ'  => 'Kazakh (Cyrillic, Kazakhstan)',
  'kk'          => 'Kazakh',
  'km_KH'       => 'Khmer (Cambodia)',
  'km'          => 'Khmer',
  'ki_KE'       => 'Kikuyu (Kenya)',
  'ki'          => 'Kikuyu',
  'rw_RW'       => 'Kinyarwanda (Rwanda)',
  'rw'          => 'Kinyarwanda',
  'kok_IN'      => 'Konkani (India)',
  'kok'         => 'Konkani',
  'ko_KR'       => 'Korean (South Korea)',
  'ko'          => 'Korean',
  'khq_ML'      => 'Koyra Chiini (Mali)',
  'khq'         => 'Koyra Chiini',
  'ses_ML'      => 'Koyraboro Senni (Mali)',
  'ses'         => 'Koyraboro Senni',
  'lag_TZ'      => 'Langi (Tanzania)',
  'lag'         => 'Langi',
  'lv_LV'       => 'Latvian (Latvia)',
  'lv'          => 'Latvian',
  'lt_LT'       => 'Lithuanian (Lithuania)',
  'lt'          => 'Lithuanian',
  'luo_KE'      => 'Luo (Kenya)',
  'luo'         => 'Luo',
  'luy_KE'      => 'Luyia (Kenya)',
  'luy'         => 'Luyia',
  'mk_MK'       => 'Macedonian (Macedonia)',
  'mk'          => 'Macedonian',
  'jmc_TZ'      => 'Machame (Tanzania)',
  'jmc'         => 'Machame',
  'kde_TZ'      => 'Makonde (Tanzania)',
  'kde'         => 'Makonde',
  'mg_MG'       => 'Malagasy (Madagascar)',
  'mg'          => 'Malagasy',
  'ms_BN'       => 'Malay (Brunei)',
  'ms_MY'       => 'Malay (Malaysia)',
  'ms'          => 'Malay',
  'ml_IN'       => 'Malayalam (India)',
  'ml'          => 'Malayalam',
  'mt_MT'       => 'Maltese (Malta)',
  'mt'          => 'Maltese',
  'gv_GB'       => 'Manx (United Kingdom)',
  'gv'          => 'Manx',
  'mr_IN'       => 'Marathi (India)',
  'mr'          => 'Marathi',
  'mas_KE'      => 'Masai (Kenya)',
  'mas_TZ'      => 'Masai (Tanzania)',
  'mas'         => 'Masai',
  'mer_KE'      => 'Meru (Kenya)',
  'mer'         => 'Meru',
  'mfe_MU'      => 'Morisyen (Mauritius)',
  'mfe'         => 'Morisyen',
  'naq_NA'      => 'Nama (Namibia)',
  'naq'         => 'Nama',
  'ne_IN'       => 'Nepali (India)',
  'ne_NP'       => 'Nepali (Nepal)',
  'ne'          => 'Nepali',
  'nd_ZW'       => 'North Ndebele (Zimbabwe)',
  'nd'          => 'North Ndebele',
  'nb_NO'       => 'Norwegian Bokmål (Norway)',
  'nb'          => 'Norwegian Bokmål',
  'nn_NO'       => 'Norwegian Nynorsk (Norway)',
  'nn'          => 'Norwegian Nynorsk',
  'nyn_UG'      => 'Nyankole (Uganda)',
  'nyn'         => 'Nyankole',
  'or_IN'       => 'Oriya (India)',
  'or'          => 'Oriya',
  'om_ET'       => 'Oromo (Ethiopia)',
  'om_KE'       => 'Oromo (Kenya)',
  'om'          => 'Oromo',
  'ps_AF'       => 'Pashto (Afghanistan)',
  'ps'          => 'Pashto',
  'fa_AF'       => 'Persian (Afghanistan)',
  'fa_IR'       => 'Persian (Iran)',
  'fa'          => 'Persian',
  'pl_PL'       => 'Polish (Poland)',
  'pl'          => 'Polish',
  'pt_BR'       => 'Portuguese (Brazil)',
  'pt_GW'       => 'Portuguese (Guinea-Bissau)',
  'pt_MZ'       => 'Portuguese (Mozambique)',
  'pt_PT'       => 'Portuguese (Portugal)',
  'pt'          => 'Portuguese',
  'pa_Arab'     => 'Punjabi (Arabic)',
  'pa_Arab_PK'  => 'Punjabi (Arabic, Pakistan)',
  'pa_Guru'     => 'Punjabi (Gurmukhi)',
  'pa_Guru_IN'  => 'Punjabi (Gurmukhi, India)',
  'pa'          => 'Punjabi',
  'ro_MD'       => 'Romanian (Moldova)',
  'ro_RO'       => 'Romanian (Romania)',
  'ro'          => 'Romanian',
  'rm_CH'       => 'Romansh (Switzerland)',
  'rm'          => 'Romansh',
  'rof_TZ'      => 'Rombo (Tanzania)',
  'rof'         => 'Rombo',
  'ru_MD'       => 'Russian (Moldova)',
  'ru_RU'       => 'Russian (Russia)',
  'ru_UA'       => 'Russian (Ukraine)',
  'ru'          => 'Russian',
  'rwk_TZ'      => 'Rwa (Tanzania)',
  'rwk'         => 'Rwa',
  'saq_KE'      => 'Samburu (Kenya)',
  'saq'         => 'Samburu',
  'sg_CF'       => 'Sango (Central African Republic)',
  'sg'          => 'Sango',
  'seh_MZ'      => 'Sena (Mozambique)',
  'seh'         => 'Sena',
  'sr_Cyrl'     => 'Serbian (Cyrillic)',
  'sr_Cyrl_BA'  => 'Serbian (Cyrillic, Bosnia and Herzegovina)',
  'sr_Cyrl_ME'  => 'Serbian (Cyrillic, Montenegro)',
  'sr_Cyrl_RS'  => 'Serbian (Cyrillic, Serbia)',
  'sr_Latn'     => 'Serbian (Latin)',
  'sr_Latn_BA'  => 'Serbian (Latin, Bosnia and Herzegovina)',
  'sr_Latn_ME'  => 'Serbian (Latin, Montenegro)',
  'sr_Latn_RS'  => 'Serbian (Latin, Serbia)',
  'sr'          => 'Serbian',
  'sn_ZW'       => 'Shona (Zimbabwe)',
  'sn'          => 'Shona',
  'ii_CN'       => 'Sichuan Yi (China)',
  'ii'          => 'Sichuan Yi',
  'si_LK'       => 'Sinhala (Sri Lanka)',
  'si'          => 'Sinhala',
  'sk_SK'       => 'Slovak (Slovakia)',
  'sk'          => 'Slovak',
  'sl_SI'       => 'Slovenian (Slovenia)',
  'sl'          => 'Slovenian',
  'xog_UG'      => 'Soga (Uganda)',
  'xog'         => 'Soga',
  'so_DJ'       => 'Somali (Djibouti)',
  'so_ET'       => 'Somali (Ethiopia)',
  'so_KE'       => 'Somali (Kenya)',
  'so_SO'       => 'Somali (Somalia)',
  'so'          => 'Somali',
  'es_AR'       => 'Spanish (Argentina)',
  'es_BO'       => 'Spanish (Bolivia)',
  'es_CL'       => 'Spanish (Chile)',
  'es_CO'       => 'Spanish (Colombia)',
  'es_CR'       => 'Spanish (Costa Rica)',
  'es_DO'       => 'Spanish (Dominican Republic)',
  'es_EC'       => 'Spanish (Ecuador)',
  'es_SV'       => 'Spanish (El Salvador)',
  'es_GQ'       => 'Spanish (Equatorial Guinea)',
  'es_GT'       => 'Spanish (Guatemala)',
  'es_HN'       => 'Spanish (Honduras)',
  'es_419'      => 'Spanish (Latin America)',
  'es_MX'       => 'Spanish (Mexico)',
  'es_NI'       => 'Spanish (Nicaragua)',
  'es_PA'       => 'Spanish (Panama)',
  'es_PY'       => 'Spanish (Paraguay)',
  'es_PE'       => 'Spanish (Peru)',
  'es_PR'       => 'Spanish (Puerto Rico)',
  'es_ES'       => 'Spanish (Spain)',
  'es_US'       => 'Spanish (United States)',
  'es_UY'       => 'Spanish (Uruguay)',
  'es_VE'       => 'Spanish (Venezuela)',
  'es'          => 'Spanish',
  'sw_KE'       => 'Swahili (Kenya)',
  'sw_TZ'       => 'Swahili (Tanzania)',
  'sw'          => 'Swahili',
  'sv_FI'       => 'Swedish (Finland)',
  'sv_SE'       => 'Swedish (Sweden)',
  'sv'          => 'Swedish',
  'gsw_CH'      => 'Swiss German (Switzerland)',
  'gsw'         => 'Swiss German',
  'shi_Latn'    => 'Tachelhit (Latin)',
  'shi_Latn_MA' => 'Tachelhit (Latin, Morocco)',
  'shi_Tfng'    => 'Tachelhit (Tifinagh)',
  'shi_Tfng_MA' => 'Tachelhit (Tifinagh, Morocco)',
  'shi'         => 'Tachelhit',
  'dav_KE'      => 'Taita (Kenya)',
  'dav'         => 'Taita',
  'ta_IN'       => 'Tamil (India)',
  'ta_LK'       => 'Tamil (Sri Lanka)',
  'ta'          => 'Tamil',
  'te_IN'       => 'Telugu (India)',
  'te'          => 'Telugu',
  'teo_KE'      => 'Teso (Kenya)',
  'teo_UG'      => 'Teso (Uganda)',
  'teo'         => 'Teso',
  'th_TH'       => 'Thai (Thailand)',
  'th'          => 'Thai',
  'bo_CN'       => 'Tibetan (China)',
  'bo_IN'       => 'Tibetan (India)',
  'bo'          => 'Tibetan',
  'ti_ER'       => 'Tigrinya (Eritrea)',
  'ti_ET'       => 'Tigrinya (Ethiopia)',
  'ti'          => 'Tigrinya',
  'to_TO'       => 'Tonga (Tonga)',
  'to'          => 'Tonga',
  'tr_TR'       => 'Turkish (Turkey)',
  'tr'          => 'Turkish',
  'uk_UA'       => 'Ukrainian (Ukraine)',
  'uk'          => 'Ukrainian',
  'ur_IN'       => 'Urdu (India)',
  'ur_PK'       => 'Urdu (Pakistan)',
  'ur'          => 'Urdu',
  'uz_Arab'     => 'Uzbek (Arabic)',
  'uz_Arab_AF'  => 'Uzbek (Arabic, Afghanistan)',
  'uz_Cyrl'     => 'Uzbek (Cyrillic)',
  'uz_Cyrl_UZ'  => 'Uzbek (Cyrillic, Uzbekistan)',
  'uz_Latn'     => 'Uzbek (Latin)',
  'uz_Latn_UZ'  => 'Uzbek (Latin, Uzbekistan)',
  'uz'          => 'Uzbek',
  'vi_VN'       => 'Vietnamese (Vietnam)',
  'vi'          => 'Vietnamese',
  'vun_TZ'      => 'Vunjo (Tanzania)',
  'vun'         => 'Vunjo',
  'cy_GB'       => 'Welsh (United Kingdom)',
  'cy'          => 'Welsh',
  'yo_NG'       => 'Yoruba (Nigeria)',
  'yo'          => 'Yoruba',
  'zu_ZA'       => 'Zulu (South Africa)',
  'zu'          => 'Zulu',
];

if(isset($_POST['add_database'])){

  // Check if database has been setup already. If it has, direct user to edit directly instead.
  if(file_exists('config.php')){
    $_SESSION['alert_message'] = "Database already configured. Any further changes should be made by editing the config.php file.";
    header("Location: setup.php?user");
    exit;
  }

  $host = $_POST['host'];
  $database = $_POST['database'];
  $username = $_POST['username'];
  $password = $_POST['password'];
  $config_base_url = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);

  $new_config = array();
  $new_config[] = "<?php\n\n";
  $new_config[] = sprintf("\$dbhost = '%s';\n", addslashes($host));
  $new_config[] = sprintf("\$dbusername = '%s';\n", addslashes($username));
  $new_config[] = sprintf("\$dbpassword = '%s';\n", addslashes($password));
  $new_config[] = sprintf("\$database = '%s';\n", addslashes($database));
  $new_config[] = "\$mysqli = mysqli_connect(\$dbhost, \$dbusername, \$dbpassword, \$database) or die('Database Connection Failed');\n";
  $new_config[] = "\$config_app_name = 'ITFlow';\n";
  $new_config[] = sprintf("\$config_base_url = '%s';\n", addslashes($config_base_url));
  $new_config[] = "\$config_https_only = TRUE;\n";

  file_put_contents("config.php", $new_config);

  include("config.php");

  // Name of the file
  $filename = 'db.sql';
  // Temporary variable, used to store current query
  $templine = '';
  // Read in entire file
  $lines = file($filename);
  // Loop through each line
  foreach ($lines as $line){
    // Skip it if it's a comment
    if(substr($line, 0, 2) == '--' || $line == '')
      continue;

    // Add this line to the current segment
    $templine .= $line;
    // If it has a semicolon at the end, it's the end of the query
    if(substr(trim($line), -1, 1) == ';'){
      // Perform the query
      mysqli_query($mysqli,$templine);
      // Reset temp variable to empty
      $templine = '';
    }
  }

  $_SESSION['alert_message'] = "Database successfully added";

  header("Location: setup.php?user");
  exit;

}

if(isset($_POST['add_user'])){
  $user_count = mysqli_num_rows(mysqli_query($mysqli,"SELECT COUNT(*) FROM users"));
  if($user_count < 0) {
    $_SESSION['alert_message'] = "Users already exist in the database. Clear them to reconfigure here.";
    header("Location: setup.php?company");
    exit;
  }

  $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
  $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

  //Generate master encryption key
  $site_encryption_master_key = keygen();

  //Generate user specific key
  $user_specific_encryption_ciphertext = setupFirstUserSpecificKey($_POST['password'], $site_encryption_master_key);

  mysqli_query($mysqli,"INSERT INTO users SET user_name = '$name', user_email = '$email', user_password = '$password', user_specific_encryption_ciphertext = '$user_specific_encryption_ciphertext', user_created_at = NOW()");

  $user_id = mysqli_insert_id($mysqli);

  mkdir_missing("uploads/users/$user_id");

  //Check to see if a file is attached
  if($_FILES['file']['tmp_name'] != ''){

    // get details of the uploaded file
    $file_error = 0;
    $file_tmp_path = $_FILES['file']['tmp_name'];
    $file_name = $_FILES['file']['name'];
    $file_size = $_FILES['file']['size'];
    $file_type = $_FILES['file']['type'];
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

    // sanitize file-name
    $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

    // check if file has one of the following extensions
    $allowed_file_extensions = array('jpg', 'gif', 'png');

    if(in_array($file_extension,$allowed_file_extensions) === false){
      $file_error = 1;
    }

    //Check File Size
    if($file_size > 2097152){
      $file_error = 1;
    }

    if($file_error == 0){
      // directory in which the uploaded file will be moved
      $upload_file_dir = "uploads/users/$user_id/";
      $dest_path = $upload_file_dir . $new_file_name;

      move_uploaded_file($file_tmp_path, $dest_path);

      //Set Avatar
      mysqli_query($mysqli,"UPDATE users SET user_avatar = '$new_file_name' WHERE user_id = $user_id");

      $_SESSION['alert_message'] = 'File successfully uploaded.';
    }else{

      $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
    }
  }

  //Create Settings
  mysqli_query($mysqli,"INSERT INTO user_settings SET user_id = $user_id, user_role = 3, user_default_company = 1");

  $_SESSION['alert_message'] = "User <strong>$name</strong> created!";

  header("Location: setup.php?company");
  exit;

}

if(isset($_POST['add_company_settings'])){

  $sql = mysqli_query($mysqli,"SELECT user_id FROM users");
  $row = mysqli_fetch_array($sql);
  $user_id = $row['user_id'];

  $name = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['name'])));
  $country = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['country'])));
  $address = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['address'])));
  $city = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['city'])));
  $state = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['state'])));
  $zip = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['zip'])));
  $phone = preg_replace("/[^0-9]/", '',$_POST['phone']);
  $email = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['email'])));
  $website = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['website'])));
  $locale = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['locale'])));
  $currency_code = trim(strip_tags(mysqli_real_escape_string($mysqli,$_POST['currency_code'])));

  mysqli_query($mysqli,"INSERT INTO companies SET company_name = '$name', company_address = '$address', company_city = '$city', company_state = '$state', company_zip = '$zip', company_country = '$country', company_phone = '$phone', company_email = '$email', company_website = '$website', company_locale = '$locale', company_currency = '$currency_code', company_created_at = NOW()");

  $company_id = mysqli_insert_id($mysqli);
  $config_base_url = mysqli_real_escape_string($mysqli,$_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']));

  mkdir_missing("uploads/clients/$company_id");
  file_put_contents("uploads/clients/$company_id/index.php", "");
  mkdir_missing("uploads/expenses/$company_id");
  file_put_contents("uploads/expenses/$company_id/index.php", "");
  mkdir_missing("uploads/settings/$company_id");
  file_put_contents("uploads/settings/$company_id/index.php", "");
  mkdir_missing("uploads/tmp/$company_id");
  file_put_contents("uploads/tmp/$company_id/index.php", "");

  //Check to see if a file is attached
  if($_FILES['file']['tmp_name'] != ''){

    // get details of the uploaded file
    $file_error = 0;
    $file_tmp_path = $_FILES['file']['tmp_name'];
    $file_name = $_FILES['file']['name'];
    $file_size = $_FILES['file']['size'];
    $file_type = $_FILES['file']['type'];
    $file_extension = strtolower(end(explode('.',$_FILES['file']['name'])));

    // sanitize file-name
    $new_file_name = md5(time() . $file_name) . '.' . $file_extension;

    // check if file has one of the following extensions
    $allowed_file_extensions = array('jpg', 'gif', 'png');

    if(in_array($file_extension,$allowed_file_extensions) === false){
      $file_error = 1;
    }

    //Check File Size
    if($file_size > 2097152){
      $file_error = 1;
    }

    if($file_error == 0){
      // directory in which the uploaded file will be moved
      $upload_file_dir = "uploads/settings/$company_id/";
      $dest_path = $upload_file_dir . $new_file_name;

      move_uploaded_file($file_tmp_path, $dest_path);

      mysqli_query($mysqli,"UPDATE companies SET company_logo = '$new_file_name' WHERE company_id = $company_id");

      $_SESSION['alert_message'] = 'File successfully uploaded.';
    }else{

      $_SESSION['alert_message'] = 'There was an error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
    }
  }

  //Set User Company Permissions
  mysqli_query($mysqli,"INSERT INTO user_companies SET user_id = $user_id, company_id = $company_id");

  $latest_database_version = LATEST_DATABASE_VERSION;
  mysqli_query($mysqli,"INSERT INTO settings SET company_id = $company_id, config_current_database_version = '$latest_database_version',  config_invoice_prefix = 'INV-', config_invoice_next_number = 1, config_recurring_prefix = 'REC-', config_recurring_next_number = 1, config_invoice_overdue_reminders = '1,3,7', config_quote_prefix = 'QUO-', config_quote_next_number = 1, config_recurring_auto_send_invoice = 1, config_default_net_terms = 30, config_send_invoice_reminders = 1, config_enable_cron = 0, config_ticket_next_number = 1, config_base_url = '$config_base_url'");

  //Create Some Data

  mysqli_query($mysqli,"INSERT INTO accounts SET account_name = 'Cash', opening_balance = 0, account_currency_code = '$currency_code', account_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Office Supplies', category_type = 'Expense', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Travel', category_type = 'Expense', category_color = 'red', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Advertising', category_type = 'Expense', category_color = 'green', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Service', category_type = 'Income', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Friend', category_type = 'Referral', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Search Engine', category_type = 'Referral', category_color = 'red', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Cash', category_type = 'Payment Method', category_color = 'blue', category_created_at = NOW(), company_id = $company_id");
  mysqli_query($mysqli,"INSERT INTO categories SET category_name = 'Check', category_type = 'Payment Method', category_color = 'red', category_created_at = NOW(), company_id = $company_id");

  mysqli_query($mysqli,"INSERT INTO calendars SET calendar_name = 'Default', calendar_color = 'blue', calendar_created_at = NOW(), company_id = $company_id");


  $_SESSION['alert_message'] = "Company <strong>$name</strong> created!";

  header("Location: setup.php?telemetry");

}

if(isset($_POST['add_telemetry'])){

  if($_POST['share_data'] == 1){

    $comments = trim(strip_tags($_POST['comments']));

    $sql = mysqli_query($mysqli,"SELECT * FROM companies LIMIT 1");
    $row = mysqli_fetch_array($sql);

    $company_name = $row['company_name'];
    $city = $row['company_city'];
    $state = $row['company_state'];
    $country = $row['company_country'];
    $currency = $row['company_currency'];

    $postdata = http_build_query(
      array(
        'company_name' => "$company_name",
        'city' => "$city",
        'state' => "$state",
        'country' => "$country",
        'currency' => "$currency",
        'comments' => "$comments"
      )
    );

    $opts = array('http' =>
      array(
        'method' => 'POST',
        'header' => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
      )
    );

    $context = stream_context_create($opts);

    $result = file_get_contents('https://telemetry.itflow.org', false, $context);

    echo $result;

  }

  //final setup stages
  $myfile = fopen("config.php", "a");

  $txt = "\$config_enable_setup = 0;\n\n";

  fwrite($myfile, $txt);

  fclose($myfile);

  header("Location: login.php");
  exit;

}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <title>ITFlow Setup</title>

    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- Custom Style Sheet -->
    <link href="plugins/select2/css/select2.min.css" rel="stylesheet" type="text/css">
    <link href="plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css" rel="stylesheet" type="text/css">

</head>

<body class="hold-transition sidebar-mini">

<div class="wrapper text-sm">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-primary navbar-dark">

        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav">
        </ul>
    </nav>
    <!-- /.navbar -->

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">

        <!-- Brand Logo -->
        <a href="https://itflow.org" class="brand-link">
            <h3 class="brand-text font-weight-light">ITFlow</h3>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="?database" class="nav-link <?php if(isset($_GET['database'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-database"></i>
                            <p>Database</p>
                        </a>
                    </li>

                    <li class="nav-item">
                        <a href="?user" class="nav-link <?php if(isset($_GET['user'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-user"></i>
                            <p>User</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?company" class="nav-link <?php if(isset($_GET['company'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-building"></i>
                            <p>Company</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?telemetry" class="nav-link <?php if(isset($_GET['telemetry'])) { echo "active"; } ?>">
                            <i class="nav-icon fas fa-share-alt"></i>
                            <p>Telemetry</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

        <!-- Main content -->
        <div class="content mt-3">
            <div class="container-fluid">

              <?php
              //Alert Feedback
              if(!empty($_SESSION['alert_message'])){
                ?>
                  <div class="alert alert-info" id="alert">
                    <?php echo $_SESSION['alert_message']; ?>
                      <button class='close' data-dismiss='alert'>&times;</button>
                  </div>
                <?php
                $_SESSION['alert_type'] = '';
                $_SESSION['alert_message'] = '';
              }
              ?>
              <?php if(isset($_GET['setup_checks'])){ ?>

                  <div class="card mb-3">
                      <div class="card-header">
                          <h6 class="mt-1"><i class="fa fa-fw fa-checkmark"></i> Setup Checks</h6>
                      </div>
                      <div class="card-body">
                          <ul class="mb-4">
                              <li>Upload is readable and writeable</li>
                              <li>PHP 7+ Installed</li>
                          </ul>
                          <div style="text-align: center;"><a href="?database" class="btn btn-lg btn-primary mb-5">Install</a></div>
                      </div>
                  </div>

              <?php } ?>

              <?php if(isset($_GET['database'])){ ?>

                  <div class="card card-dark">
                      <div class="card-header">
                          <h3 class="card-title"><i class="fa fa-fw fa-database"></i> Connect your Database</h3>
                      </div>
                      <div class="card-body">
                        <?php if(file_exists('config.php')){ ?>
                            Database already configured. Any further changes should be made by editing the config.php file,
                            or deleting it and refreshing this page.
                        <?php }else{ ?>
                            <form method="post" autocomplete="off">

                                <div class="form-group">
                                    <label>Database User <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="username" placeholder="Database User" autofocus required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Database Password <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                        </div>
                                        <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Enter a Database Password" autocomplete="new-password" required>
                                        <div class="input-group-append">
                                            <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Database Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-database"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="database" placeholder="Database Name" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Database Host <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-server"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="host" value="localhost" placeholder="Database Host" required>
                                    </div>
                                </div>

                                <hr>
                                <button type="submit" name="add_database" class="btn btn-primary">Next <i class="fa fa-fw fa-arrow-circle-right"></i></button>
                            </form>
                        <?php } ?>
                      </div>
                  </div>

              <?php }elseif(isset($_GET['user'])){ ?>

                  <div class="card card-dark">
                      <div class="card-header">
                          <h3 class="card-title"><i class="fa fa-fw fa-user"></i> Create your first user</h3>
                      </div>
                      <div class="card-body">

                          <form method="post" enctype="multipart/form-data" autocomplete="off">
                              <div class="form-group">
                                  <label>Name <strong class="text-danger">*</strong></label>
                                  <div class="input-group">
                                      <div class="input-group-prepend">
                                          <span class="input-group-text"><i class="fa fa-fw fa-user"></i></span>
                                      </div>
                                      <input type="text" class="form-control" name="name" placeholder="Full Name" autofocus required>
                                  </div>
                              </div>

                              <div class="form-group">
                                  <label>Email <strong class="text-danger">*</strong></label>
                                  <div class="input-group">
                                      <div class="input-group-prepend">
                                          <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                      </div>
                                      <input type="email" class="form-control" name="email" placeholder="Email Address" required>
                                  </div>
                              </div>

                              <div class="form-group">
                                  <label>Password <strong class="text-danger">*</strong></label>
                                  <div class="input-group">
                                      <div class="input-group-prepend">
                                          <span class="input-group-text"><i class="fa fa-fw fa-lock"></i></span>
                                      </div>
                                      <input type="password" class="form-control" data-toggle="password" name="password" placeholder="Enter a Password" autocomplete="new-password" required minlength="8">
                                      <div class="input-group-append">
                                          <span class="input-group-text"><i class="fa fa-fw fa-eye"></i></span>
                                      </div>
                                  </div>
                              </div>

                              <div class="form-group">
                                  <label>Avatar</label>
                                  <input type="file" class="form-control-file" accept="image/*;capture=camera" name="file">
                              </div>

                              <hr>

                              <button type="submit" name="add_user" class="btn btn-primary">Next <i class="fa fa-fw fa-arrow-circle-right"></i></button>
                          </form>
                      </div>
                  </div>

              <?php }elseif(isset($_GET['company'])){ ?>

                  <div class="card card-dark">
                      <div class="card-header">
                          <h3 class="card-title"><i class="fa fa-fw fa-building"></i> Company Details</h3>
                      </div>
                      <div class="card-body">
                        <?php if(mysqli_num_rows(mysqli_query($mysqli,"SELECT COUNT(*) FROM users")) < 0){ ?>
                            Database config invalid, or users already exist in the database.
                        <?php }else{ ?>
                            <form method="post" enctype="multipart/form-data" autocomplete="off">

                                <div class="form-group">
                                    <label>Company Name <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-building"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="name" placeholder="Company Name" autofocus required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Address</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-map-marker-alt"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="address" placeholder="Street Address">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>City</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-city"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="city" placeholder="City">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>State / Province</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="state" placeholder="State or Province">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Zip / Postal Code</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fab fa-fw fa-usps"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="zip" placeholder="Zip or Postal Code">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Country <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                        </div>
                                        <select class="form-control select2" name="country" required>
                                            <option value="">- Country -</option>
                                          <?php foreach($countries_array as $country_name) { ?>
                                              <option><?php echo $country_name; ?></option>
                                          <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Phone</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-phone"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="phone" placeholder="Phone Number">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Email</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-envelope"></i></span>
                                        </div>
                                        <input type="email" class="form-control" name="email" placeholder="Email address">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Website</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-globe"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="website" placeholder="Website address">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Locale <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-flag"></i></span>
                                        </div>
                                        <select class="form-control select2" name="locale" required>
                                            <option value="">- Select a Locale -</option>
                                          <?php foreach($locales_array as $locale_code => $locale_name) { ?>
                                              <option value="<?php echo $locale_code; ?>"><?php echo "$locale_code - $locale_name"; ?></option>
                                          <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Currency <strong class="text-danger">*</strong></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text"><i class="fa fa-fw fa-money-bill"></i></span>
                                        </div>
                                        <select class="form-control select2" name="currency_code" required>
                                            <option value="">- Currency -</option>
                                          <?php foreach($currencies_array as $currency_code => $currency_name) { ?>
                                              <option value="<?php echo $currency_code; ?>"><?php echo "$currency_code - $currency_name"; ?></option>
                                          <?php } ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Logo</label>
                                    <input type="file" class="form-control-file" name="file">
                                </div>

                                <hr>

                                <button type="submit" name="add_company_settings" class="btn btn-primary">Next <i class="fa fa-fw fa-arrow-circle-right"></i></button>

                            </form>
                        <?php } ?>
                      </div>
                  </div>


              <?php }elseif(isset($_GET['telemetry'])){ ?>

                  <div class="card card-dark">
                      <div class="card-header">
                          <h3 class="card-title"><i class="fa fa-fw fa-share-alt"></i> Telemetry</h3>
                      </div>
                      <div class="card-body">
                          <form method="post" autocomplete="off">
                              <h5>Would you like to share some data with us?</h5>

                              <hr>

                              <div class="form-check">
                                  <input type="checkbox" class="form-check-input" name="share_data" value="1">
                                  <label class="form-check-label ml-2">Share <small class="text-secondary"> (Including City, State/Province, Country, Currency and your comments)</small></label>
                              </div>

                              <br>

                              <div class="form-group">
                                  <label>Comments</label>
                                  <textarea class="form-control" rows="4" name="comments" placeholder="Any Comments?"></textarea>
                              </div>

                              <hr>

                              <p>Post installation, a few additional steps are required:</p>
                              <ul>
                                  <li>Backup your <a href="https://itflow.org/docs.php?doc=logins" target="_blank">master encryption key</a></li>
                                  <li><a href="https://itflow.org/docs.php?doc=alerts" target="_blank">Configure cron</a> for alerts</li>
                              </ul>

                              <hr>

                              <button type="submit" name="add_telemetry" class="btn btn-primary">Finish and Sign in <i class="fa fa-fw fa-check-circle"></i></button>

                          </form>

                      </div>
                  </div>

              <?php }else{ ?>

                  <div class="card card-dark">
                      <div class="card-header">
                          <h3 class="card-title"><i class="fa fa-fw fa-cube"></i> Welcome to ITFlow Setup</h3>
                      </div>
                      <div class="card-body">
                          <p><b>Thank you for choosing to try ITFlow!</b> Feel free to reach out on the <a href="https://forum.itflow.org/" target="_blank">forums</a> if you have any questions.</p>
                          <p>A database must be created before proceeding - click on the button below to get started! </p>
                          <hr>
                          <p class="text-muted">This program is <b>free software</b>: you can redistribute it and/or modify it under the terms of the <a href="https://www.gnu.org/licenses/gpl-3.0.en.html" target="_blank">GNU General Public License</a>. It is distributed in the hope that it will be useful, but <b>WITHOUT ANY WARRANTY</b>.</p>
                        <?php
                        // Check that there is access to write config.php
                        if(!file_put_contents("config.php", "Test")){
                          echo "<div class='alert alert-danger'>Warning: config.php is not writable. Ensure the webserver process has write access. Check the <a href='https://itflow.org/docs.php?doc=installation'>docs</a> for info.</div>";
                        }else{
                          // Else, able to write. Tidy up
                          unlink("config.php");
                        }
                        ?>
                          <hr>
                          <div style="text-align: center;">
                            <a href="?database" class="btn btn-primary">Begin Setup <i class="fa fa-fw fa-arrow-alt-circle-right"></i></a>
                          </div>
                      </div>
                  </div>

              <?php } ?>

            </div><!-- /.container-fluid -->
        </div>
        <!-- /.content -->
    </div>
    <!-- /.content-wrapper -->
</div>
<!-- ./wrapper -->

<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- Custom js-->
<script src='plugins/select2/js/select2.min.js'></script>
<script src="plugins/Show-Hide-Passwords-Bootstrap-4/bootstrap-show-password.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>

<!-- Custom js-->
<script src="js/app.js"></script>

</body>

</html>
