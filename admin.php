<?php
/*
Plugin Name: Required Fields Manager
Plugin URI:  https://example.com/
Description: Lets admin choose which core fields are required on registration and item publish/edit.
Version:     1.2.1
Author:      Van Isle Web Solutions
License:     GPL-2.0-or-later
*/

if (!defined('ABS_PATH')) { exit('Direct access is not allowed.'); }

define('RFM_PREF', 'required_fields_manager');

/* =====================================================
 * ENTERPRISE / OSCLASSPOINT PLUGIN REGISTRATION
 * ===================================================== */

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
        'reg_name'     => 0,
        'reg_username' => 0,
        'reg_email'    => 1,
        'reg_phone'    => 0,
        'reg_address'  => 0,

        // Item
        'item_title'       => 1,
        'item_description' => 1,
        'item_price'       => 0,
        'item_category'    => 1,
        'item_region'      => 0,
        'item_city'        => 0,
        'item_contact'     => 0,
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
    return trim((string)$v) === '';
}

/**
 * Store posted form values so the form repopulates after redirect.
 * We mimic Osclass core behavior (best-effort across forks).
 */
function rfm_store_form_values($type) {
    if (!class_exists('Session')) { return; }

    try {
        $sess = Session::newInstance();
        if (!is_object($sess)) { return; }

        // Best effort: use the core convention if available
        if (method_exists($sess, '_setForm')) {
            // Params::getParamsAsArray() exists on most Osclass builds
            if (method_exists('Params', 'getParamsAsArray')) {
                $sess->_setForm($type, Params::getParamsAsArray());
            } else {
                // Fallback
                $sess->_setForm($type, $_POST);
            }
        }
    } catch (Exception $e) {
        // swallow - we still redirect with flash message
    }
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
 * MAIN VALIDATION HOOK
 * ===================================================== */

function rfm_init_validate() {
    // Never validate in admin
    if (defined('OC_ADMIN') && OC_ADMIN) { return; }

    $page   = Params::getParam('page');
    $action = Params::getParam('action');

    if (
        ($page === 'register' && $action === 'register_post') ||
        ($page === 'user' && $action === 'register_post')
    ) {
        rfm_validate_registration();
        return;
    }

    if (
        ($page === 'item' || $page === 'items') &&
        ($action === 'item_add_post' || $action === 'item_edit_post')
    ) {
        rfm_validate_item($action);
        return;
    }
}

osc_add_hook('init', 'rfm_init_validate', 1);

/* =====================================================
 * REGISTRATION VALIDATION
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
            rfm_fail(
                sprintf(__('%s is required.', RFM_PREF), $cfg[1]),
                $url,
                'user'
            );
        }
    }
}

/* =====================================================
 * ITEM VALIDATION
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
        // Some themes submit regionId, some region
        $checks[] = array('regionId', __('Region', RFM_PREF), 'region');
    }
    if (!empty($s['item_city'])) {
        // Some themes submit cityId, some city
        $checks[] = array('cityId', __('City', RFM_PREF), 'city');
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

            // Redirect back to edit or post form
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

            rfm_fail(
                sprintf(__('%s is required.', RFM_PREF), $label),
                $url,
                'item'
            );
        }
    }
}