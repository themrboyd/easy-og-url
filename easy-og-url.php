<?php
/*
Plugin Name: Easy OG URL
Plugin URI: https://github.com/themrboyd/easy-og-url
Description: Easily manage og:url for Facebook sharing. Compatible with Facebook requirements and overrides other plugins.
Version: 1.1
Author: Boyd Duang
Author URI: https://github.com/themrboyd
Donate link: https://ko-fi.com/boyduang, https://buymeacoffee.com/boydduang
*/

// Add meta box to post and page editor
function easy_og_url_add_meta_box() {
    $screens = ['post', 'page'];
    foreach ($screens as $screen) {
        add_meta_box(
            'easy_og_url_meta_box',
            'Easy OG:URL',
            'easy_og_url_meta_box_callback',
            $screen,
            'normal',
            'high'
        );
    }
}
add_action('add_meta_boxes', 'easy_og_url_add_meta_box');

// Meta box display callback
function easy_og_url_meta_box_callback($post) {
    wp_nonce_field('easy_og_url_meta_box', 'easy_og_url_meta_box_nonce');
    $value = get_post_meta($post->ID, '_easy_og_url', true);
    echo '<label for="easy_og_url_field">Custom OG:URL:</label>';
    echo '<input type="text" id="easy_og_url_field" name="easy_og_url_field" value="' . esc_attr($value) . '" size="25" />';
    echo '<p>Leave blank to use default OG:URL settings. For pages, you can set a custom OG:URL that differs from the page URL if needed.</p>';
}

// Save meta box content
function easy_og_url_save_meta_box($post_id) {
    if (!isset($_POST['easy_og_url_meta_box_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['easy_og_url_meta_box_nonce'], 'easy_og_url_meta_box')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    if (isset($_POST['easy_og_url_field'])) {
        $easy_og_url = sanitize_text_field($_POST['easy_og_url_field']);
        update_post_meta($post_id, '_easy_og_url', $easy_og_url);
    }
}
add_action('save_post', 'easy_og_url_save_meta_box');
add_action('save_page', 'easy_og_url_save_meta_box');

// Main function to set og:url
function easy_og_url_set($url) {
    global $post;
    $options = get_option('easy_og_url_options');
    
    if ($options['enabled'] == 1) {
        // Check for custom OG:URL in post/page meta
        if (is_singular() && $post) {
            $custom_og_url = get_post_meta($post->ID, '_easy_og_url', true);
            if (!empty($custom_og_url)) {
                return esc_url($custom_og_url);
            }
        }
        
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $custom_url = $options['custom_url'];
        
        if (strpos($current_url, $custom_url) === 0) {
            return $current_url;
        } else {
            return $custom_url;
        }
    }
    return $url;
}

// Function to add or replace og:url meta tag
function easy_og_url_add_to_head() {
    $options = get_option('easy_og_url_options');
    if ($options['enabled'] == 1) {
        // Remove any existing og:url meta tags
        remove_action('wp_head', 'easy_og_url_print');
        
        // Add our custom og:url meta tag
        add_action('wp_head', function() {
            $url = easy_og_url_set(get_permalink());
            echo '<meta property="og:url" content="' . esc_url($url) . '" />';
        }, 99999);

        if (function_exists('wpseo_init')) {
            // Use Yoast SEO's filter for og:url if Yoast is active
            add_filter('wpseo_opengraph_url', 'easy_og_url_set', 99999);
        }
    }
}
add_action('wp_head', 'easy_og_url_add_to_head', 1);

// Add settings page in the dashboard
function easy_og_url_add_admin_menu() {
    add_options_page('Easy OG URL Settings', 'Easy OG URL', 'manage_options', 'easy-og-url', 'easy_og_url_options_page');
}
add_action('admin_menu', 'easy_og_url_add_admin_menu');

// Function to display and save settings
function easy_og_url_options_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $options = get_option('easy_og_url_options');
    if (false === $options) {
        $options = array(
            'enabled' => 0,
            'custom_url' => home_url()
        );
        update_option('easy_og_url_options', $options);
    }

    if (isset($_POST['easy_og_url_submit'])) {
        $options['enabled'] = isset($_POST['easy_og_url_enabled']) ? 1 : 0;
        $options['custom_url'] = sanitize_text_field($_POST['easy_og_url']);
        update_option('easy_og_url_options', $options);
        echo '<div class="updated"><p>Settings saved. Please clear Facebook\'s cache for your website.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Easy OG URL Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr>
                    <th scope="row">Enable Easy OG URL</th>
                    <td>
                        <input type="checkbox" name="easy_og_url_enabled" <?php checked($options['enabled'], 1); ?> />
                        <?php if (function_exists('wpseo_init')): ?>
                            <p class="description">Yoast SEO is active. This plugin will override Yoast SEO's og:url settings.</p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Base Custom URL</th>
                    <td>
                        <input type="text" name="easy_og_url" value="<?php echo esc_attr($options['custom_url']); ?>" size="50" />
                        <p class="description">Enter the base URL you want to use for og:url tags. The plugin will use this base URL or the current page URL if it's a subpage of this base URL.</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="easy_og_url_submit" class="button-primary" value="Save Changes" />
            </p>
        </form>
        <h2>Important Notes</h2>
        <ol>
            <li>After changing settings, you need to clear Facebook's cache. Use the <a href="https://developers.facebook.com/tools/debug/" target="_blank">Facebook Sharing Debugger</a> to scrape your pages again.</li>
            <li>This plugin will set the og:url to either the base custom URL or the current page URL if it's a subpage of the base URL.</li>
            <li>If you're using a caching plugin, clear your website's cache after changing these settings.</li>
            <li>This plugin will take precedence over other plugins that set og:url, including Yoast SEO.</li>
            <li>You can set custom OG:URL for individual posts and pages by editing them and using the "Easy OG:URL" meta box.</li>
        </ol>
    </div>
    <?php
}

// Function to add settings link on plugin page
function easy_og_url_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=easy-og-url">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'easy_og_url_settings_link');