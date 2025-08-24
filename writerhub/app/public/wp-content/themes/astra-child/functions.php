<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );
         
if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'astra-theme-css' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );
add_filter('wp_nav_menu_items', function($items, $args) {
    if (isset($args->theme_location) && $args->theme_location === 'primary') {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            // Ultimate Member profile URL
            $profile_url = function_exists('um_user_profile_url') ? um_user_profile_url() : '#';
            $logout_url = wp_logout_url(home_url('/'));
            $items .= '
                <li class="menu-item menu-item-type-custom menu-item-username menu-item-has-children">
                    <a href="' . esc_url($profile_url) . '">' . esc_html($user->display_name) . '</a>
                    <ul class="sub-menu">
                        <li class="menu-item menu-item-user-profile">
                            <a href="' . esc_url($profile_url) . '">Profile</a>
                        </li>
                        <li class="menu-item menu-item-logout">
                            <a href="' . esc_url($logout_url) . '">Logout</a>
                        </li>
                    </ul>
                </li>
            ';
        } else {
            $items .= '<li class="menu-item menu-item-type-custom menu-item-login"><a href="/login/">Login</a></li>';
        }
    }
    return $items;
}, 20, 2);




// END ENQUEUE PARENT ACTION
