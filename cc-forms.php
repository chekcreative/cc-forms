<?php
/**
 * Plugin Name: Chek Creative Protected Zapier Forms
 * Description: Zapier Forms with Google reCAPTCHA security
 * Version: 1.0
 * Author: Chek Creative
 * Author URI: https://chekcreative.com
 * License: GPL3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * 
 * Chek Creative Protected Zapier Forms is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *  
 * Chek Creative Protected Zapier Forms is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with Chek Creative Protected Zapier Forms. If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 * 
 */


/** 
 * Add options to admin and create submenu under "Settings"
 */
add_action( 'admin_init', 'protected_zapier_options' );
function protected_zapier_options() {
  add_option( 'protected_zapier_options', array(
    'google_site' => '',
    'google_secret' => ''
  ));
}
add_action("admin_menu", "protected_zapier_submenu");
function protected_zapier_submenu() {
  add_submenu_page(
        'options-general.php',
        'Protected Zapier Settings',
        'Protected Zapier',
        'administrator',
        'protected-zapier-settings',
        'protected_zapier_settings' );
}

/**
 * Allow for admin-side google key editing
 */
function protected_zapier_settings() { ?>
  <div class="wrap">
    <h2><?php esc_html(get_admin_page_title()); ?></h2>
    <p>Please update the fields below</p>
    <table class="form-table" role="presentation">
      <tbody>
        <tr>
          <?php // custom options
            $protected_zapier_options = get_option('protected_zapier_options');
          ?>
          <th scope="row"><label for="stripe-public">Google Site Key</label></th>
          <td id="google-site-cell">
            <div>
              <input name="google_site" type="text" id="google_site" value="<?php echo $protected_zapier_options['google_site']?>" class="regular-text" data-offersave="false" data-incomingvalue="">
            </div>
            <p class="description">Can be retrieved from the <a href="https://www.google.com/recaptcha/admin/" target="_blank">Google reCAPTCHA Admin Dashboard</a>.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"><label for="stripe-public">Google Secret Key</label></th>
          <td id="stripe-secret-cell">
            <div>
              <input name="google_secret" type="password" id="google_secret" value="<?php echo $protected_zapier_options['google_secret']?>" class="regular-text" data-offersave="false" data-incomingvalue="">
            </div>
            <p class="description">Can be retrieved from the <a href="https://www.google.com/recaptcha/admin/" target="_blank">Google reCAPTCHA Admin Dashboard</a>.</p>
          </td>
        </tr>
        <tr>
          <th scope="row"></th>
          <td id="submit-cell">
            <input type="button" name="submit" id="google-keys-submit" class="button button-primary" value="Save Changes">
            <p id="submit-message" style="font-weight: bold; margin-top: 5px;"></p>
          </td>
        </tr>
      </tbody>
    </table>
  </div>

  <script>
    document.getElementById('google-keys-submit').addEventListener('click', function(e) {
      e.preventDefault();
      jQuery.ajax({
        url: "<?php echo admin_url('admin-ajax.php'); ?>",
        method: "POST",
        data: {
          action : 'update_google_keys',
          google_secret: document.getElementById('google_secret').value,
          google_site: document.getElementById('google_site').value
        },
        success: function(data) {
          var dataObject = JSON.parse(data);
          if(dataObject.success) {
            jQuery("#submit-message").html(dataObject.message);

            setTimeout(() => {
              jQuery("#submit-message").html('');
            }, 5000);
          } else {
            jQuery("#submit-message").html("Something went wrong.");
          }
        }
      });
    });
  </script>
<?php }

/**
 * Update Keys via WP AJAX (Admin only)
 */
add_action('wp_ajax_update_google_keys', 'update_option_func');
function update_option_func() {
  global $wpdb;
  $protected_zapier_options = get_option( 'protected_zapier_options');
  foreach ($protected_zapier_options as $key => $value) {
    if($_POST[$key]) {
      $protected_zapier_options[$key] = $_POST[$key];
      update_option( 'protected_zapier_options', $protected_zapier_options);
    }
  }
  echo json_encode([
    success => true,
    message => "Updated fields",
    data => get_option('protected_zapier_options')
  ]);

	wp_die(); // this is required to terminate immediately and return a proper response
}

/**
 * Global Form Submit using WP Ajax 
 */
add_action( 'wp_ajax_zapier_post', 'zapier_post_func' );
add_action( 'wp_ajax_nopriv_zapier_post', 'zapier_post_func' );
function zapier_post_func() {

  // check with google
  if($_POST && $_POST['token']) {
    $post = [
      'response' => $_POST['token'],
      'secret' => get_option( 'protected_zapier_options')['google_secret']
    ];
    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $response = curl_exec($ch);
    curl_close($ch);

    $response_obj = json_decode($response);


    // if good, send to zapier
    if($response_obj && $response_obj->success) {
      $post = $_POST['zapier_data'];
      $ch = curl_init($_POST['zapier']);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
      $response = curl_exec($ch);
      curl_close($ch);

      $response_obj = json_decode($response);

      // if zapier success, respond with success
      if($response_obj->status == 'success') {
        echo json_encode([
          'success' => true
        ]);
      
      // else respond with zapier fail
      } else {
        echo json_encode([
          'success' => false,
          'message' => 'There was an error on the server. Please reload the page and try again.',
          'details' => $response_obj
        ]);
      }

    // else respond with recaptcha fail
    } else {
      echo json_encode([
        'success' => false,
        'message' => "Error validating request. Please reload the page and try again.",
        'details' => $response_obj
      ]);
    }

  // else respond with google verification fail
  } else {
    echo json_encode([
      'success' => false,
      'message' => "Missing Google verification. Please reload the page and try again."
    ]);
  }

  wp_die();

}


/**
 * Update Checker
 */
require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/chekcreative/cc-forms/',
	__FILE__,
	'cc-forms'
);

$myUpdateChecker->getVcsApi()->enableReleaseAssets();