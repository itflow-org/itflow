<?php
/**
 * i18n (Internationalization) Helper Functions
 * 
 * Provides translation support for ITFlow
 */

// Global language array
global $lang;
$lang = [];

/**
 * Get browser's preferred language
 * 
 * @return string Language code (e.g., 'de_DE', 'en_US')
 */
function i18n_get_browser_language() {
    $available_languages = array_keys(i18n_get_available_languages());
    
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browser_languages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        
        foreach ($browser_languages as $lang_item) {
            // Extract language code (e.g., "de-DE" or "de")
            $lang_code = strtok($lang_item, ';');
            $lang_code = trim($lang_code);
            
            // Convert format: de-DE -> de_DE, de -> de_DE
            $lang_code = str_replace('-', '_', $lang_code);
            
            // Check for exact match
            if (in_array($lang_code, $available_languages)) {
                return $lang_code;
            }
            
            // Check for language prefix match (e.g., "de" matches "de_DE")
            $lang_prefix = substr($lang_code, 0, 2);
            foreach ($available_languages as $available) {
                if (substr($available, 0, 2) === $lang_prefix) {
                    return $available;
                }
            }
        }
    }
    
    return 'en_US'; // Default fallback
}

/**
 * Initialize i18n system
 * Loads all language module files from locale directory
 * Priority: 1. Cookie, 2. User DB setting, 3. Browser language, 4. Default (en_US)
 * 
 * @param string $locale Language code (e.g., 'en_US', 'de_DE')
 * @return bool Success status
 */
function i18n_init($locale = null) {
    global $lang, $session_user_id, $mysqli;
    
    // Priority 1: Check for language cookie (user manually selected)
    if (isset($_COOKIE['itflow_language']) && !empty($_COOKIE['itflow_language'])) {
        $locale = $_COOKIE['itflow_language'];
    }
    
    // Priority 2: Get user's preferred language from database if logged in
    if (empty($locale) && isset($session_user_id) && !empty($session_user_id)) {
        $sql = mysqli_query($mysqli, "SELECT user_config_language FROM users WHERE user_id = $session_user_id");
        if ($sql && mysqli_num_rows($sql) > 0) {
            $row = mysqli_fetch_assoc($sql);
            if (!empty($row['user_config_language'])) {
                $locale = $row['user_config_language'];
            }
        }
    }
    
    // Priority 3: Auto-detect browser language
    if (empty($locale)) {
        $locale = i18n_get_browser_language();
    }
    
    // Priority 4: Fallback to system default
    if (empty($locale)) {
        $locale = 'en_US';
    }
    
    // Whitelist of allowed locales to prevent path injection attacks
    $allowed_locales = ['en_US', 'de_DE'];
    if (!in_array($locale, $allowed_locales, true)) {
        $locale = 'en_US'; // Fallback to safe default
    }
    
    // Load all language module files from locale directory
    // Using whitelisted locale, not directly from user input
    $lang_dir = __DIR__ . "/../lang/{$locale}/";
    
    if (is_dir($lang_dir)) {
        $module_files = glob($lang_dir . "*.php");
        if (!empty($module_files)) {
            foreach ($module_files as $module_file) {
                require_once $module_file;
            }
            return true;
        }
    }
    
    // Fallback to English if requested language not found
    $fallback_dir = __DIR__ . "/../lang/en_US/";
    if (is_dir($fallback_dir)) {
        $fallback_files = glob($fallback_dir . "*.php");
        foreach ($fallback_files as $fallback_file) {
            require_once $fallback_file;
        }
    }
    
    return false;
}

/**
 * Translate a key to current language
 * 
 * @param string $key Translation key
 * @param string $default Default text if translation not found
 * @return string Translated text or default
 */
function __($key, $default = '') {
    global $lang;
    
    if (isset($lang[$key])) {
        return $lang[$key];
    }
    
    // Return default or key if no translation found
    return !empty($default) ? $default : $key;
}

/**
 * Translate with placeholder replacement
 * 
 * @param string $key Translation key
 * @param array $replacements Associative array of placeholders and values
 * @param string $default Default text if translation not found
 * @return string Translated text with replacements
 * 
 * Example: __t('welcome_user', ['%name%' => 'John'], 'Welcome %name%')
 */
function __t($key, $replacements = [], $default = '') {
    $text = __($key, $default);
    
    foreach ($replacements as $placeholder => $value) {
        $text = str_replace($placeholder, $value, $text);
    }
    
    return $text;
}

/**
 * Get available languages
 * Returns hardcoded whitelist of supported languages
 * 
 * @return array Array of available language codes and names
 */
function i18n_get_available_languages() {
    // Hardcoded whitelist of supported languages for security
    // Add new languages here when translation files are available
    $allowed_locales = ['en_US', 'de_DE'];
    
    $languages = [];
    foreach ($allowed_locales as $locale) {
        $languages[$locale] = i18n_get_language_name($locale);
    }
    
    return $languages;
}

/**
 * Get human-readable language name
 * 
 * @param string $locale Language code
 * @return string Language name
 */
function i18n_get_language_name($locale) {
    $language_names = [
        'en_US' => 'English (US)',
        'en_GB' => 'English (UK)',
        'de_DE' => 'Deutsch',
        'fr_FR' => 'Français',
        'es_ES' => 'Español',
        'it_IT' => 'Italiano',
        'nl_NL' => 'Nederlands',
        'pt_BR' => 'Português (Brasil)',
        'pl_PL' => 'Polski',
        'ru_RU' => 'Русский',
        'ja_JP' => '日本語',
        'zh_CN' => '简体中文',
    ];
    
    return $language_names[$locale] ?? $locale;
}
