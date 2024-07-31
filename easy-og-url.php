<?php
/*
Plugin Name: Easy OG URL
Plugin URI: https://github.com/themrboyd/easy-og-url
Description: Easily manage og:url for Facebook sharing. Compatible with Facebook requirements and overrides other plugins.
Version: 1.0
Author: Boyd Duang
Author URI: https://github.com/themrboyd
*/

// Function to set og:url
function custom_og_url($url) {
    $options = get_option('custom_og_url_options');
    if ($options['enabled'] == 1) {
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $custom_url = $options['custom_url'];
        
        // Check if the current URL is part of the custom URL
        if (strpos($current_url, $custom_url) === 0) {
            return $current_url; // Use current URL if it's part of the custom URL
        } else {
            return $custom_url; // Use custom URL if the current URL is unrelated
        }
    }
    return $url;
}

// Function to check if Yoast SEO is active
function is_yoast_seo_active() {
    return class_exists('WPSEO_Options');
}

// Function to add or replace og:url meta tag
function add_custom_og_url() {
    $options = get_option('custom_og_url_options');
    if ($options['enabled'] == 1) {
        // Remove any existing og:url meta tags
        remove_all_actions('wp_head', 'print_og_url');
        
        // Add our custom og:url meta tag
        add_action('wp_head', function() {
            $url = custom_og_url(get_permalink());
            echo '<meta property="og:url" content="' . esc_url($url) . '" />';
        }, 99999);

        if (is_yoast_seo_active()) {
            // Use Yoast SEO's filter for og:url
            add_filter('wpseo_opengraph_url', 'custom_og_url', 99999);
        }
    }
}

// Call the main function
add_action('wp_head', 'add_custom_og_url', 1);

// Add settings page in the dashboard
function custom_og_url_menu() {
    add_options_page('Custom OG URL Settings', 'Custom OG URL', 'manage_options', 'custom-og-url', 'custom_og_url_options');
}
add_action('admin_menu', 'custom_og_url_menu');

// Function to display and save settings
function custom_og_url_options() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $options = get_option('custom_og_url_options');
    if (false === $options) {
        $options = array(
            'enabled' => 0,
            'custom_url' => home_url()
        );
        update_option('custom_og_url_options', $options);
    }

    if (isset($_POST['custom_og_url_submit'])) {
        $options['enabled'] = isset($_POST['custom_og_url_enabled']) ? 1 : 0;
        $options['custom_url'] = sanitize_text_field($_POST['custom_og_url']);
        update_option('custom_og_url_options', $options);
        echo '<div class="updated"><p>Settings saved. Please clear Facebook\'s cache for your website.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Custom OG URL Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Custom OG URL</th>
                    <td>
                        <input type="checkbox" name="custom_og_url_enabled" <?php checked($options['enabled'], 1); ?> />
                        <?php if (is_yoast_seo_active()): ?>
                            <p class="description">Yoast SEO is active. This plugin will override Yoast SEO's og:url settings.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Base Custom URL</th>
                    <td>
                        <input type="text" name="custom_og_url" value="<?php echo esc_attr($options['custom_url']); ?>" size="50" />
                        <p class="description">Enter the base URL you want to use for og:url tags. The plugin will use this base URL or the current page URL if it's a subpage of this base URL.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="custom_og_url_submit" class="button-primary" value="Save Changes" />
            </p>
        </form>
        <h2>Important Notes</h2>
        <ol>
            <li>After changing settings, you need to clear Facebook's cache. Use the <a href="https://developers.facebook.com/tools/debug/" target="_blank">Facebook Sharing Debugger</a> to scrape your pages again.</li>
            <li>This plugin will set the og:url to either the base custom URL or the current page URL if it's a subpage of the base URL.</li>
            <li>If you're using a caching plugin, clear your website's cache after changing these settings.</li>
            <li>This plugin will take precedence over other plugins that set og:url, including Yoast SEO.</li>
        </ol>
    </div>
    <?php
}

// Function to add settings link on plugin page
function custom_og_url_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=custom-og-url">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'custom_og_url_settings_link');