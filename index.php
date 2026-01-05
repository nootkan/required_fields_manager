<?php
/*
Plugin Name: Required Fields Manager
Plugin URI:  https://example.com/
Description: Lets admin choose which core fields are required on registration and item publish/edit + enforces profile completeness.
Version:     1.3.0
Author:      Van Isle Web Solutions
License:     GPL-2.0-or-later
*/

if (!defined('ABS_PATH')) { exit('Direct access is not allowed.'); }

define('RFM_PREF', 'required_fields_manager');

/* =====================================================
 * INSTALL / UNINSTALL / CONFIG
 * ===================================================== */

function rfm_install() {
    $defaults = rfm_defaults();
    foreach ($defaults as $k => $v) {
        $existing = osc_get_preference($k, RFM_PREF);
        if ($existing === null || $existing === false) {
            osc_set_preference($k, $v, RFM_PREF);
        }
    }
}

function rfm_uninstall() {
    foreach (array_keys(rfm_defaults()) as $k) {
        osc_delete_preference($k, RFM_PREF);
    }
}

function rfm_configuration() {
    osc_admin_render_plugin(
        osc_plugin_path(dirname(__FILE__)) . '/admin.php'
    );
}

osc_register_plugin(osc_plugin_path(__FILE__), 'rfm_install');

if (defined('OC_ADMIN') && OC_ADMIN) {
    osc_add_hook(osc_plugin_path(__FILE__) . '_configure', 'rfm_configuration');
    osc_add_hook(osc_plugin_path(__FILE__) . '_uninstall', 'rfm_uninstall');
}

/* =====================================================
 * DEFAULTS / SETTINGS
 * ===================================================== */

function rfm_defaults() {
    return array(
        // Registration
        'reg_name'        => 0,
        'reg_username'    => 0,
        'reg_email'       => 1,
        'reg_phone'       => 0,
        'reg_country'     => 0,
        'reg_region'      => 0,
        'reg_city'        => 0,
        'reg_city_area'   => 0,
        'reg_zip'         => 0,
        'reg_address'     => 0,
        'reg_seller_type' => 0,

        // Item
        'item_title'       => 1,
        'item_description' => 1,
        'item_price'       => 0,
        'item_category'    => 1,
        'item_region'      => 0,
        'item_city'        => 0,
        'item_contact'     => 0,

        // Seller type required for everyone (your choice B)
        'item_seller_type' => 1,
    );
}

function rfm_get_settings() {
    $out = array();
    foreach (rfm_defaults() as $k => $def) {
        $val = osc_get_preference($k, RFM_PREF);
        $out[$k] = ($val === null || $val === false) ? $def : (int)$val;
    }
    return $out;
}

function rfm_save_settings($new) {
    foreach ($new as $k => $v) {
        osc_set_preference($k, (int)$v, RFM_PREF);
    }
}

/* =====================================================
 * HELPERS
 * ===================================================== */

function rfm_blank($v) {
    // If it's an array, check if it has any non-empty values
    if (is_array($v)) {
        foreach ($v as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }

    // Normal string check
    return trim((string)$v) === '';
}

/**
 * Store posted form values so the form repopulates after redirect.
 * Best-effort across forks.
 */
function rfm_store_form_values($type) {
    if (!class_exists('Session')) { return; }

    try {
        $sess = Session::newInstance();
        if (!is_object($sess)) { return; }

        if (method_exists($sess, '_setForm')) {
            if (method_exists('Params', 'getParamsAsArray')) {
                $sess->_setForm($type, Params::getParamsAsArray());
            } else {
                $sess->_setForm($type, $_POST);
            }
        }
    } catch (Exception $e) {
        // swallow
    }
}

/**
 * Store "extra registration fields" to apply after Osclass creates the user.
 */
function rfm_store_reg_extra($data) {
    if (!class_exists('Session')) { return; }
    try {
        $sess = Session::newInstance();
        if (is_object($sess) && method_exists($sess, '_set')) {
            $sess->_set('rfm_reg_extra', $data);
        }
    } catch (Exception $e) {}
}

function rfm_get_reg_extra() {
    if (!class_exists('Session')) { return array(); }
    try {
        $sess = Session::newInstance();
        if (is_object($sess) && method_exists($sess, '_get')) {
            $x = $sess->_get('rfm_reg_extra');
            return is_array($x) ? $x : array();
        }
    } catch (Exception $e) {}
    return array();
}

function rfm_clear_reg_extra() {
    if (!class_exists('Session')) { return; }
    try {
        $sess = Session::newInstance();
        if (is_object($sess) && method_exists($sess, '_drop')) {
            $sess->_drop('rfm_reg_extra');
        }
    } catch (Exception $e) {}
}

function rfm_fail($msg, $url, $formType = '') {
    if ($formType !== '') {
        rfm_store_form_values($formType);
    }

    osc_add_flash_error_message($msg);
    osc_redirect_to($url);
    exit;
}

/* =====================================================
 * USER META (Seller Type)
 * ===================================================== */

function rfm_get_table_prefix() {
    if (defined('DB_TABLE_PREFIX')) { return DB_TABLE_PREFIX; }
    return 'oc_'; // fallback
}

function rfm_set_user_meta($userId, $name, $value) {
    $userId = (int)$userId;
    if ($userId <= 0 || $name === '') { return false; }

    // Try if Osclass provides helper functions (some forks do)
    if (function_exists('osc_set_user_meta')) {
        return (bool)osc_set_user_meta($userId, $name, $value);
    }

    // Best-effort direct SQL into t_user_meta (common across Osclass forks)
    try {
        if (!class_exists('DBConnectionClass')) { return false; }
        $db = DBConnectionClass::newInstance()->getDb();
        if (!is_object($db)) { return false; }

        $table = rfm_get_table_prefix() . 't_user_meta';

        // Escape best-effort
        $nameEsc  = addslashes($name);
        $valueEsc = addslashes((string)$value);

        // Common schema: fk_i_user_id, s_name, s_value (unique on fk_i_user_id + s_name)
        $sql = "INSERT INTO {$table} (fk_i_user_id, s_name, s_value)
                VALUES ({$userId}, '{$nameEsc}', '{$valueEsc}')
                ON DUPLICATE KEY UPDATE s_value = '{$valueEsc}'";

        $db->query($sql);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function rfm_get_user_meta($userId, $name) {
    $userId = (int)$userId;
    if ($userId <= 0 || $name === '') { return ''; }

    if (function_exists('osc_get_user_meta')) {
        $v = osc_get_user_meta($userId, $name);
        return is_string($v) ? $v : '';
    }

    try {
        if (!class_exists('DBConnectionClass')) { return ''; }
        $db = DBConnectionClass::newInstance()->getDb();
        if (!is_object($db)) { return ''; }

        $table = rfm_get_table_prefix() . 't_user_meta';
        $nameEsc = addslashes($name);

        $sql = "SELECT s_value FROM {$table} WHERE fk_i_user_id = {$userId} AND s_name = '{$nameEsc}' LIMIT 1";
        $rs = $db->query($sql);
        if (is_object($rs) && method_exists($rs, 'row')) {
            $row = $rs->row();
            if (is_array($row) && isset($row['s_value'])) {
                return (string)$row['s_value'];
            }
        }
    } catch (Exception $e) {}
    return '';
}

/* =====================================================
 * PROFILE UPDATE (registration fields)
 * ===================================================== */

function rfm_update_user_profile_fields($userId, $fields) {
    $userId = (int)$userId;
    if ($userId <= 0 || !is_array($fields) || empty($fields)) { return; }

    // Best-effort via User model (common)
    if (class_exists('User') && method_exists('User', 'newInstance')) {
        try {
            $u = User::newInstance();
            if (is_object($u) && method_exists($u, 'update')) {
                $data = array_merge(array('pk_i_id' => $userId), $fields);
                $u->update($data);
                return;
            }
        } catch (Exception $e) {}
    }

    // If we can’t update, we silently skip (no fatal errors)
}

/* =====================================================
 * PROFILE COMPLETENESS CHECK
 * ===================================================== */

function rfm_profile_is_complete($userId, $settings) {
    $userId = (int)$userId;
    if ($userId <= 0) { return false; }

    // Pull current user record (best-effort)
    $user = array();
    if (function_exists('osc_user')) {
        $u = osc_user();
        if (is_array($u)) { $user = $u; }
    }
    if (empty($user) && function_exists('osc_logged_user_id') && (int)osc_logged_user_id() === $userId) {
        // Sometimes osc_user() is empty; that’s fine, we’ll just check meta and skip DB fields.
    }

    // Seller type (meta) required?
    if (!empty($settings['item_seller_type'])) {
        $stype = rfm_get_user_meta($userId, 'seller_type');
        if (rfm_blank($stype)) { return false; }
    }

    // Location required?
    if (!empty($settings['item_region'])) {
        $region = isset($user['s_region']) ? $user['s_region'] : '';
        if (rfm_blank($region)) { return false; }
    }
    if (!empty($settings['item_city'])) {
        $city = isset($user['s_city']) ? $user['s_city'] : '';
        if (rfm_blank($city)) { return false; }
    }

    // Optional: if admin chose to require address for registration, enforce it here too
    if (!empty($settings['reg_address'])) {
        $addr = isset($user['s_address']) ? $user['s_address'] : '';
        if (rfm_blank($addr)) { return false; }
    }

    return true;
}

/* =====================================================
 * MAIN VALIDATION HOOK (server-side)
 * ===================================================== */

function rfm_init_validate() {
    if (defined('OC_ADMIN') && OC_ADMIN) { return; }

    $page   = Params::getParam('page');
    $action = Params::getParam('action');

    // Registration submit
    if (
        ($page === 'register' && $action === 'register_post') ||
        ($page === 'user' && $action === 'register_post')
    ) {
        rfm_validate_registration();
        return;
    }

    // Item post/edit submit
    if (
        ($page === 'item' || $page === 'items') &&
        ($action === 'item_add_post' || $action === 'item_edit_post')
    ) {

        // Enforce profile completeness for logged-in users BEFORE allowing post/edit
        $settings = rfm_get_settings();
        if (function_exists('osc_is_web_user_logged_in') && osc_is_web_user_logged_in()) {
            $uid = function_exists('osc_logged_user_id') ? (int)osc_logged_user_id() : 0;
            if ($uid > 0 && !rfm_profile_is_complete($uid, $settings)) {
                $url = function_exists('osc_user_profile_url') ? osc_user_profile_url() : osc_base_url(true);
                rfm_fail(__('Please complete your profile (including Seller Type and Location) before posting.', RFM_PREF), $url, 'user');
            }
        }

        rfm_validate_item($action);
        return;
    }
}

osc_add_hook('init', 'rfm_init_validate', 1);

/* =====================================================
 * REGISTRATION VALIDATION + STORE EXTRA FIELDS
 * ===================================================== */

function rfm_validate_registration() {
    $s = rfm_get_settings();

    $checks = array(
        'reg_name'     => array('s_name', __('Name', RFM_PREF)),
        'reg_username' => array('s_username', __('Username', RFM_PREF)),
        'reg_email'    => array('s_email', __('Email', RFM_PREF)),
        'reg_phone'    => array('s_phone_mobile', __('Phone', RFM_PREF)),
        'reg_address'  => array('s_address', __('Address', RFM_PREF)),
    );

    foreach ($checks as $k => $cfg) {
        if (!empty($s[$k]) && rfm_blank(Params::getParam($cfg[0]))) {
            $url = function_exists('osc_register_account_url') ? osc_register_account_url() : osc_base_url(true);
            rfm_fail(sprintf(__('%s is required.', RFM_PREF), $cfg[1]), $url, 'user');
        }
    }

    // Optional location checks if you enable them in admin
    $locChecks = array(
        'reg_country'   => array('countryId', __('Country', RFM_PREF), 'country'),
        'reg_region'    => array('regionId', __('Region', RFM_PREF), 'region'),
        'reg_city'      => array('cityId', __('City', RFM_PREF), 'city'),
        'reg_city_area' => array('cityArea', __('City area', RFM_PREF), 'cityArea'),
        'reg_zip'       => array('zip', __('Zip code', RFM_PREF), 'zip'),
        'reg_address'   => array('s_address', __('Address', RFM_PREF), 'address'),
    );

    foreach ($locChecks as $k => $cfg) {
        if (!empty($s[$k])) {
            $v = Params::getParam($cfg[0]);
            if (rfm_blank($v) && !empty($cfg[2])) {
                $v = Params::getParam($cfg[2]);
            }
            if (rfm_blank($v)) {
                $url = function_exists('osc_register_account_url') ? osc_register_account_url() : osc_base_url(true);
                rfm_fail(sprintf(__('%s is required.', RFM_PREF), $cfg[1]), $url, 'user');
            }
        }
    }

    // Seller type required on registration?
    if (!empty($s['reg_seller_type'])) {
        $stype = Params::getParam('sellerType');
        if (rfm_blank($stype)) {
            $url = function_exists('osc_register_account_url') ? osc_register_account_url() : osc_base_url(true);
            rfm_fail(__('Seller type is required.', RFM_PREF), $url, 'user');
        }
    }

    // Store extra fields to apply AFTER Osclass creates the user
    $extra = array(
        'countryId'  => Params::getParam('countryId'),
        'region'     => Params::getParam('region'),
        'regionId'   => Params::getParam('regionId'),
        'city'       => Params::getParam('city'),
        'cityId'     => Params::getParam('cityId'),
        'cityArea'   => Params::getParam('cityArea'),
        'zip'        => Params::getParam('zip'),
        'address'    => Params::getParam('s_address'),
        'sellerType' => Params::getParam('sellerType'),
        'phone'      => Params::getParam('s_phone_mobile'),
    );
    rfm_store_reg_extra($extra);
}

/* =====================================================
 * ITEM VALIDATION (includes Seller Type required for everyone)
 * ===================================================== */

function rfm_validate_item($action) {
    $s = rfm_get_settings();

    $checks = array();

    if (!empty($s['item_title'])) {
        $checks[] = array('title', __('Title', RFM_PREF));
    }
    if (!empty($s['item_description'])) {
        $checks[] = array('description', __('Description', RFM_PREF));
    }
    if (!empty($s['item_price'])) {
        $checks[] = array('price', __('Price', RFM_PREF));
    }
    if (!empty($s['item_category'])) {
        $checks[] = array('catId', __('Category', RFM_PREF));
    }
    if (!empty($s['item_region'])) {
        $checks[] = array('regionId', __('Region', RFM_PREF), 'region');
    }
    if (!empty($s['item_city'])) {
        $checks[] = array('cityId', __('City', RFM_PREF), 'city');
    }

    // Seller type required for everyone (B)
    if (!empty($s['item_seller_type'])) {
        $checks[] = array('sellerType', __('Seller type', RFM_PREF));
    }

    if (!empty($s['item_contact'])) {
        $checks[] = array('contactName', __('Contact name', RFM_PREF), 'yourName');
        $checks[] = array('contactEmail', __('Contact email', RFM_PREF), 'yourEmail');
    }

    foreach ($checks as $c) {
        $key = $c[0];
        $label = $c[1];
        $alt = isset($c[2]) ? $c[2] : '';

        $val = Params::getParam($key);
        if (rfm_blank($val) && $alt !== '') {
            $val = Params::getParam($alt);
        }

        if (rfm_blank($val)) {
            $url = '';
            if ($action === 'item_edit_post' && function_exists('osc_item_edit_url')) {
                $url = osc_item_edit_url();
            }
            if ($url === '' && function_exists('osc_item_post_url')) {
                $url = osc_item_post_url();
            }
            if ($url === '') {
                $url = osc_base_url(true);
            }

            rfm_fail(sprintf(__('%s is required.', RFM_PREF), $label), $url, 'item');
        }
    }

    // If logged in, also persist seller type into user meta immediately (so it "sticks")
    if (!empty($s['item_seller_type']) && function_exists('osc_is_web_user_logged_in') && osc_is_web_user_logged_in()) {
        $uid = function_exists('osc_logged_user_id') ? (int)osc_logged_user_id() : 0;
        $stype = Params::getParam('sellerType');
        if ($uid > 0 && !rfm_blank($stype)) {
            rfm_set_user_meta($uid, 'seller_type', $stype);
        }
    }
}

/* =====================================================
 * APPLY REGISTRATION EXTRA FIELDS AFTER USER IS CREATED
 * We hook multiple names to be compatible across forks.
 * ===================================================== */

function rfm_after_user_register_apply_profile() {
    // Determine user id best-effort
    $uid = 0;
    if (function_exists('osc_logged_user_id')) {
        $uid = (int)osc_logged_user_id();
    }
    if ($uid <= 0 && function_exists('osc_user_id')) {
        $uid = (int)osc_user_id();
    }
    if ($uid <= 0) { return; }

    $extra = rfm_get_reg_extra();
    if (empty($extra)) { return; }

    $fields = array();

    // These field names match common Osclass user table columns
    if (!rfm_blank($extra['address']))  { $fields['s_address'] = $extra['address']; }
    if (!rfm_blank($extra['city']))     { $fields['s_city'] = $extra['city']; }
    if (!rfm_blank($extra['region']))   { $fields['s_region'] = $extra['region']; }
    if (!rfm_blank($extra['cityArea'])) { $fields['s_city_area'] = $extra['cityArea']; }
    if (!rfm_blank($extra['zip']))      { $fields['s_zip'] = $extra['zip']; }
    if (!rfm_blank($extra['phone']))    { $fields['s_phone_mobile'] = $extra['phone']; }

    // Country may be stored as code or name depending on fork; we try common column
    if (!rfm_blank($extra['countryId'])) {
        // Some forks use fk_c_country_code; some store s_country. We try s_country as safest.
        $fields['s_country'] = $extra['countryId'];
    }

    if (!empty($fields)) {
        rfm_update_user_profile_fields($uid, $fields);
    }

    // Seller type -> user meta
    if (!rfm_blank($extra['sellerType'])) {
        rfm_set_user_meta($uid, 'seller_type', $extra['sellerType']);
    }

    rfm_clear_reg_extra();
}

// Hook several common names (only one needs to exist)
osc_add_hook('user_register_completed', 'rfm_after_user_register_apply_profile');
osc_add_hook('register_completed', 'rfm_after_user_register_apply_profile');
osc_add_hook('after_user_register', 'rfm_after_user_register_apply_profile');