<?php
if (!defined('ABS_PATH') || !defined('OC_ADMIN') || !OC_ADMIN) {
    exit('Admin only.');
}

// Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $fields = array_keys(rfm_defaults());
    $save = array();

    foreach ($fields as $f) {
        $save[$f] = isset($_POST[$f]) ? 1 : 0;
    }

    rfm_save_settings($save);

    osc_add_flash_ok_message(__('Settings saved.', RFM_PREF));

    osc_redirect_to(osc_admin_render_plugin_url(
        osc_plugin_path(dirname(__FILE__)) . '/admin.php'
    ));
    exit;
}

$settings = rfm_get_settings();

osc_current_admin_theme_path('parts/header.php');

if (function_exists('osc_show_flash_message')) {
    osc_show_flash_message();
}
?>

<div class="container">
  <h2><?php _e('Required Fields Manager', RFM_PREF); ?></h2>

  <form method="post">

    <h3><?php _e('Registration', RFM_PREF); ?></h3>
    <?php foreach (array(
        'reg_name'        => 'Name',
        'reg_username'    => 'Username',
        'reg_email'       => 'Email',
        'reg_phone'       => 'Phone',
        'reg_country'     => 'Country',
        'reg_region'      => 'Region',
        'reg_city'        => 'City',
        'reg_city_area'   => 'City area',
        'reg_zip'         => 'Zip code',
        'reg_address'     => 'Address',
        'reg_seller_type' => 'Seller type'
    ) as $k => $label): ?>
      <label style="display:block; margin:6px 0;">
        <input type="checkbox" name="<?php echo osc_esc_html($k); ?>" <?php echo (!empty($settings[$k]) ? 'checked' : ''); ?>>
        <?php _e($label, RFM_PREF); ?>
      </label>
    <?php endforeach; ?>

    <h3 style="margin-top:20px;"><?php _e('Item Publish / Edit', RFM_PREF); ?></h3>
    <?php foreach (array(
        'item_title'       => 'Title',
        'item_description' => 'Description',
        'item_price'       => 'Price',
        'item_category'    => 'Category',
        'item_region'      => 'Region',
        'item_city'        => 'City',
        'item_zip'         => 'Postal Code',
        'item_contact'     => 'Contact name & email',
        'item_seller_type' => 'Seller type'
    ) as $k => $label): ?>
      <label style="display:block; margin:6px 0;">
        <input type="checkbox" name="<?php echo osc_esc_html($k); ?>" <?php echo (!empty($settings[$k]) ? 'checked' : ''); ?>>
        <?php _e($label, RFM_PREF); ?>
      </label>
    <?php endforeach; ?>

    <p style="margin-top:16px;">
      <button type="submit" class="btn btn-submit">
        <?php _e('Save settings', RFM_PREF); ?>
      </button>
    </p>

  </form>
</div>