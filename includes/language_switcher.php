<?php
/**
 * Language Switcher Component
 * Displays a dropdown to change the application language
 */

// Get current language - same priority as i18n_init()
$current_language = 'en_US';

// Priority 1: Cookie (user manually selected)
if (isset($_COOKIE['itflow_language']) && !empty($_COOKIE['itflow_language'])) {
    $current_language = $_COOKIE['itflow_language'];
} 
// Priority 2: User DB setting (skipped here, would need session)
// Priority 3: Browser language
elseif (function_exists('i18n_get_browser_language')) {
    $current_language = i18n_get_browser_language();
}

$available_languages = i18n_get_available_languages();
$current_language_name = i18n_get_language_name($current_language);

// Handle language change request
if (isset($_GET['change_language']) && !empty($_GET['change_language'])) {
    $new_language = $_GET['change_language'];
    // Validate language code
    if (array_key_exists($new_language, $available_languages)) {
        // Set cookie for 1 year
        setcookie('itflow_language', $new_language, time() + 31536000, '/', '', false, true);
        // Reload current page without query parameters
        // Use PHP_SELF instead of REQUEST_URI to avoid XSS
        $redirect_url = htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8');
        header("Location: $redirect_url");
        exit();
    }
}
?>

<div class="dropdown d-inline-block">
    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="languageSwitcher" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-globe"></i> <?php echo htmlspecialchars($current_language_name, ENT_QUOTES, 'UTF-8'); ?>
    </button>
    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="languageSwitcher">
        <?php foreach ($available_languages as $lang_code => $lang_name): ?>
            <a class="dropdown-item <?php echo $lang_code === $current_language ? 'active' : ''; ?>" 
               href="?change_language=<?php echo urlencode($lang_code); ?>">
                <?php echo htmlspecialchars($lang_name, ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($lang_code === $current_language): ?>
                    <i class="fas fa-check float-right mt-1"></i>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
