<?php
/*
Plugin Name: Required Fields Manager
Plugin URI:  https://github.com/nootkan/required_fields_manager
Description: Lets admin choose which core fields are required on registration and item publish/edit. Automatically syncs selected registration values into user profiles without enforcing profile completion.
Version:     1.4.0
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

// Configure + uninstall hooks MUST be in index.php (not admin.php)
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
        'item_zip'         => 0,
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
    if (is_array($v)) {
        foreach ($v as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }
    return trim((string)$v) === '';
}

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
 * USER RECORD HELPERS (robust b_company read)
 * ===================================================== */

function rfm_get_user_row_by_id($userId) {
    $userId = (int)$userId;
    if ($userId <= 0) { return array(); }

    // Try osc_user() if it already matches the requested user
    if (function_exists('osc_user')) {
        $u = osc_user();
        if (is_array($u) && isset($u['pk_i_id']) && (int)$u['pk_i_id'] === $userId) {
            return $u;
        }
    }

    // Best-effort via User model
    if (class_exists('User') && method_exists('User', 'newInstance')) {
        try {
            $um = User::newInstance();
            if (is_object($um) && method_exists($um, 'findByPrimaryKey')) {
                $row = $um->findByPrimaryKey($userId);
                return is_array($row) ? $row : array();
            }
        } catch (Exception $e) {}
    }

    return array();
}

function rfm_get_b_company_value($userId) {
    $userId = (int)$userId;
    if ($userId <= 0) { return null; }

    // Try from user row
    $row = rfm_get_user_row_by_id($userId);
    if (isset($row['b_company'])) {
        return $row['b_company'];
    }

    // Try osc_user_field
    if (function_exists('osc_user_field')) {
        $v = osc_user_field('b_company');

        // IMPORTANT: some forks/themes return FALSE for "0"
        if ($v === false) {
            return 0;
        }

        return $v;
    }

    return null;
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
}

/* =====================================================
 * PROFILE UPDATE VALIDATION (SERVER-SIDE)
 * ===================================================== */

function rfm_validate_profile_update() {
    $settings = rfm_get_settings();

    if (!function_exists('osc_logged_user_id')) { return; }
    $uid = (int)osc_logged_user_id();
    if ($uid <= 0) { return; }

    // Seller type required? (validate SUBMITTED value)
    if (!empty($settings['item_seller_type'])) {
        $isCompany = Params::getParam('b_company');
        $v = (string)$isCompany;
        if ($v !== '0' && $v !== '1') {
            rfm_fail(
                __('Seller type is required.', RFM_PREF),
                (function_exists('osc_user_profile_url') ? osc_user_profile_url() : osc_base_url(true)),
                'user'
            );
        }
    }

    // Region required? (profile form can submit regionId OR region)
    if (!empty($settings['item_region'])) {
        $regionId = Params::getParam('regionId');
        $region   = Params::getParam('region');
        if (rfm_blank($regionId) && rfm_blank($region)) {
            rfm_fail(
                __('Region is required.', RFM_PREF),
                (function_exists('osc_user_profile_url') ? osc_user_profile_url() : osc_base_url(true)),
                'user'
            );
        }
    }

    // City required? (profile form can submit cityId OR city)
    if (!empty($settings['item_city'])) {
        $cityId = Params::getParam('cityId');
        $city   = Params::getParam('city');
        if (rfm_blank($cityId) && rfm_blank($city)) {
            rfm_fail(
                __('City is required.', RFM_PREF),
                (function_exists('osc_user_profile_url') ? osc_user_profile_url() : osc_base_url(true)),
                'user'
            );
        }
    }

    // Address required?
    if (!empty($settings['reg_address'])) {
        $addr = Params::getParam('address');
        if (rfm_blank($addr)) {
            $addr = Params::getParam('s_address');
        }

        if (rfm_blank($addr)) {
            rfm_fail(
                __('Address is required.', RFM_PREF),
                (function_exists('osc_user_profile_url') ? osc_user_profile_url() : osc_base_url(true)),
                'user'
            );
        }
    }
	
	// Postal code required?
    if (!empty($settings['item_zip'])) {
        $zip = Params::getParam('zip');
        if (rfm_blank($zip)) {
            rfm_fail(
                __('Postal code is required.', RFM_PREF),
                (function_exists('osc_user_profile_url') ? osc_user_profile_url() : osc_base_url(true)),
                'user'
            );
        }
    }
}

/* =====================================================
 * MAIN VALIDATION HOOK (server-side)
 * ===================================================== */

function rfm_init_validate() {
    if (defined('OC_ADMIN') && OC_ADMIN) {
        return;
    }

    $page   = Params::getParam('page');
    $action = Params::getParam('action');

    /* ===============================
     * REGISTRATION SUBMIT
     * =============================== */
    if (
        ($page === 'register' && $action === 'register_post') ||
        ($page === 'user' && $action === 'register_post')
    ) {
        rfm_validate_registration();
        return;
    }

    /* ===============================
     * PROFILE UPDATE (LOGGED-IN USERS)
     * =============================== */
    if ($page === 'user' && $action === 'profile_post') {
        rfm_validate_profile_update();
        return;
    }

    /* ===============================
     * ITEM POST / EDIT (GUESTS + USERS)
     * =============================== */
    if (
        ($page === 'item' || $page === 'items') &&
        ($action === 'item_add_post' || $action === 'item_edit_post')
    ) {
        // ✅ Validate ONLY what is submitted on the item form
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
        $stype = Params::getParam('b_company');
        $v = (string)$stype;
        if ($v !== '0' && $v !== '1') {
            $url = function_exists('osc_register_account_url') ? osc_register_account_url() : osc_base_url(true);
            rfm_fail(__('Seller type is required.', RFM_PREF), $url, 'user');
        }
    }

    $extra = array(
        'countryId'  => Params::getParam('countryId'),
        'region'     => Params::getParam('region'),
        'regionId'   => Params::getParam('regionId'),
        'city'       => Params::getParam('city'),
        'cityId'     => Params::getParam('cityId'),
        'cityArea'   => Params::getParam('cityArea'),
        'zip'        => Params::getParam('zip'),
        'address'    => Params::getParam('s_address'),
        'b_company'  => Params::getParam('b_company'),
        'phone'      => Params::getParam('s_phone_mobile'),
    );
    rfm_store_reg_extra($extra);
}

/* =====================================================
 * ITEM VALIDATION
 * ===================================================== */

function rfm_validate_item($action) {
    $s = rfm_get_settings();

    /* ===============================
     * PROFILE COMPLETION CHECK (LOGGED-IN USERS ONLY)
     * =============================== */
    if (function_exists('osc_is_web_user_logged_in') && osc_is_web_user_logged_in()) {
        $uid = (int)osc_logged_user_id();
        $userRow = rfm_get_user_row_by_id($uid);
        
        $missingFields = array();

        // Check seller type
        if (!empty($s['item_seller_type'])) {
            $isCompany = rfm_get_b_company_value($uid);
            $v = (string)$isCompany;
            if ($v !== '0' && $v !== '1') {
                $missingFields[] = __('Seller type', RFM_PREF);
            }
        }

        // Check region
        if (!empty($s['item_region'])) {
            $region = isset($userRow['s_region']) ? $userRow['s_region'] : '';
            if (rfm_blank($region)) {
                $missingFields[] = __('Region', RFM_PREF);
            }
        }

        // Check city
        if (!empty($s['item_city'])) {
            $city = isset($userRow['s_city']) ? $userRow['s_city'] : '';
            if (rfm_blank($city)) {
                $missingFields[] = __('City', RFM_PREF);
            }
        }

        // Check zip
        if (!empty($s['item_zip'])) {
            $zip = isset($userRow['s_zip']) ? $userRow['s_zip'] : '';
            if (rfm_blank($zip)) {
                $missingFields[] = __('Postal code', RFM_PREF);
            }
        }

        // If any required fields are missing, redirect to profile
        if (!empty($missingFields)) {
            $fieldsList = implode(', ', $missingFields);
            rfm_fail(
                sprintf(__('Please complete your profile first. Missing required fields: %s', RFM_PREF), $fieldsList),
                osc_user_profile_url(),
                'item'
            );
        }
    }

    /* ===============================
     * FORM FIELD VALIDATION
     * =============================== */
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
    if (!empty($s['item_zip'])) {
        $checks[] = array('zip', __('Zip code', RFM_PREF), 'zip');
    }

    /* ===============================
     * SELLER TYPE REQUIRED (GUESTS ONLY)
     * =============================== */
    if (!empty($s['item_seller_type'])) {
        // Guests: validate from FORM POST (theme uses sellerType: individual/business)
        if (!(function_exists('osc_is_web_user_logged_in') && osc_is_web_user_logged_in())) {
            $stype = Params::getParam('sellerType');

            if ($stype !== 'individual' && $stype !== 'business') {
                rfm_fail(
                    __('Seller type is required.', RFM_PREF),
                    osc_item_post_url(),
                    'item'
                );
            }

            // Normalize guest value to an internal b_company equivalent (0/1) for consistency.
            // NOTE: Guests do not have a user profile to store this into.
            $guestCompany = ($stype === 'business') ? 1 : 0;
        }
    }

    // Only validate contact fields for GUESTS (logged-in users don't have these fields on the form)
    if (!empty($s['item_contact']) && !(function_exists('osc_is_web_user_logged_in') && osc_is_web_user_logged_in())) {
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

    // If logged in, persist seller type into the USER record (b_company) immediately
    // (Only works if the item form actually submits b_company, which many themes do not.)
    if (!empty($s['item_seller_type']) && function_exists('osc_is_web_user_logged_in') && osc_is_web_user_logged_in()) {
        $uid = function_exists('osc_logged_user_id') ? (int)osc_logged_user_id() : 0;
        $stype = Params::getParam('b_company');
        $v = (string)$stype;

        if ($uid > 0 && ($v === '0' || $v === '1')) {
            rfm_update_user_profile_fields($uid, array('b_company' => (int)$stype));
        }
    }
}

/* =====================================================
 * APPLY REGISTRATION EXTRA FIELDS AFTER USER IS CREATED
 * ===================================================== */

function rfm_after_user_register_apply_profile() {
    $uid = 0;

    if (function_exists('osc_logged_user_id')) {
        $uid = (int) osc_logged_user_id();
    }
    if ($uid <= 0 && function_exists('osc_user_id')) {
        $uid = (int) osc_user_id();
    }
    if ($uid <= 0) {
        return;
    }

    $extra = rfm_get_reg_extra();
    if (empty($extra)) {
        return;
    }

    $fields = array();

    // Address
    if (!rfm_blank($extra['address'])) {
        $fields['s_address'] = $extra['address'];
    }

    // Region (name first, fallback from regionId)
    if (!rfm_blank($extra['region'])) {
        $fields['s_region'] = $extra['region'];
    } elseif (!rfm_blank($extra['regionId']) && class_exists('Region')) {
        $r = Region::newInstance()->findByPrimaryKey((int)$extra['regionId']);
        if (is_array($r) && isset($r['s_name'])) {
            $fields['s_region'] = $r['s_name'];
        }
    }

    // City (name first, fallback from cityId)
    if (!rfm_blank($extra['city'])) {
        $fields['s_city'] = $extra['city'];
    } elseif (!rfm_blank($extra['cityId']) && class_exists('City')) {
        $c = City::newInstance()->findByPrimaryKey((int)$extra['cityId']);
        if (is_array($c) && isset($c['s_name'])) {
            $fields['s_city'] = $c['s_name'];
        }
    }

    // City Area
    if (!rfm_blank($extra['cityArea'])) {
        $fields['s_city_area'] = $extra['cityArea'];
    }

    // Postal Code
    if (!rfm_blank($extra['zip'])) {
        $fields['s_zip'] = $extra['zip'];
    }

    // Phone
    if (!rfm_blank($extra['phone'])) {
        $fields['s_phone_mobile'] = $extra['phone'];
    }

    // Country
    if (!rfm_blank($extra['countryId'])) {
        $fields['s_country'] = $extra['countryId'];
    }

    // Seller type (core Osclass field)
    if (isset($extra['b_company'])) {
        $v = (string)$extra['b_company'];
        if ($v === '0' || $v === '1') {
            $fields['b_company'] = (int)$v;
        }
    }

    if (!empty($fields)) {
        rfm_update_user_profile_fields($uid, $fields);
    }

    rfm_clear_reg_extra();
}

osc_add_hook('user_register_completed', 'rfm_after_user_register_apply_profile');
osc_add_hook('register_completed', 'rfm_after_user_register_apply_profile');
osc_add_hook('after_user_register', 'rfm_after_user_register_apply_profile');