<?php
/**
 * Plugin Name:     Ultimate Member - Redirect and Error Trace
 * Description:     Extension to Ultimate Member for logging of UM and WP Errors and Redirects.
 * Version:         1.1.1
 * Requires PHP:    7.4
 * Author:          Miss Veronica
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Author URI:      https://github.com/MissVeronica?tab=repositories
 * Plugin URI:      https://github.com/MissVeronica/um-redirect-error-trace
 * Update URI:      https://github.com/MissVeronica/um-redirect-error-trace
 * Text Domain:     ultimate-member
 * Domain Path:     /languages
 * UM version:      2.8.6
 */

 if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! class_exists( 'UM' ) ) return;

class UM_Redirect_Error_Trace {

    function __construct() {

        add_action( 'wp_error_added',                   array( $this, 'wp_redirect_custom_log' ), 10, 3 );
        add_filter( 'x_redirect_by',                    array( $this, 'wp_redirect_custom_log' ), 10, 3 );
        add_filter( 'um_submit_form_error',             array( $this, 'wp_redirect_custom_log' ), 10, 2 );
        add_action( 'um_submit_form_errors_hook_login', array( $this, 'um_submit_hook_login_custom_log' ),10, 1 );
    }

    public function um_submit_hook_login_custom_log( $submitted_data ) {

        $trace = array();
        $trace[] = date_i18n( 'Y-m-d H:i:s ', current_time( 'timestamp' )) . 'Login';

        foreach( $submitted_data['submitted'] as $key => $submitted ) {

            if ( $key == 'user_password' ) {
                $trace[] = ( empty( $submitted )) ? 'password empty' : 'password entered';

            } else {

                if ( $key == 'g-recaptcha-response' ) {
                    $trace[] = $key . ' string length ' . strlen( $submitted );

                } else {

                    if ( ! empty( $submitted )) {

                        switch ( $key ) {
                            case 'user_login':  $user_name = $submitted;
                                                break;

                            case 'username':    if ( is_email( $submitted ) ) {
                                                    $data = get_user_by( 'email', $submitted );
                                                    $submitted = 'valid email address';
                                                    $user_name = isset( $data->user_login ) ? $data->user_login : '';

                                                } else {
                                                    $user_name = $submitted;
                                                }
                                                break;

                            case 'user_email':  $data = get_user_by( 'email', $submitted );
                                                $submitted = 'valid email address';
                                                $user_name = isset( $data->user_login ) ? $data->user_login : '';
                                                break;

                            default:            if ( is_array( $submitted )) {
                                                    $trace[] = $key . '=' . implode( ', ', $submitted );
                                                } else {
                                                    $trace[] = $key . '=' . $submitted;
                                                }
                                                continue 2;
                        }

                        if ( ! empty( $user_name )) {

                            $data = get_user_by( 'login', $user_name );

                            $user_name2 = isset( $data->user_login ) ? $data->user_login : '';

                            if ( ! empty( $user_name2 )) {
                                $trace[] = $key . '=' . $user_name2 . ' submitted:' . $submitted;

                            } else {
                                $trace[] = $key . '=' . $submitted . ' failed for ' . $user_name;
                            }

                        } else {
                            $trace[] = $key . '=' . $submitted . ' failed';
                        }

                    } else {
                        $trace[] = $key . ' empty';
                    }
                }
            }
        }

        file_put_contents( WP_CONTENT_DIR . '/debug.log', implode( ', ', $trace ) . "\r\n", FILE_APPEND );
    }

    public function wp_redirect_custom_log( $x_redirect_by, $location, $status = 'UM' ) {

        global $current_user;

        $traces = debug_backtrace( DEBUG_BACKTRACE_PROVIDE_OBJECT );
        $plugin_trace = array();

        foreach ( $traces as $trace ) {

            if( isset( $trace['file'] )) {

                if ( strpos( $trace['file'], '/plugins/' ) !== false ) {

                    $file = explode( '/plugins/', $trace['file'] );
                    if( substr( $file[1], 0, 22 ) != 'wp_redirect_custom_log' ) {
                        $plugin_trace[] = $file[1] . ':' . $trace['line'];
                    }
                }

                if ( strpos( $trace['file'], '/themes/' ) !== false ) {

                    $file = explode( '/themes/', $trace['file'] );
                    $plugin_trace[] = 'TH: ' . $file[1] . ':' . $trace['line'];
                }

                if ( strpos( $trace['file'], '/wp-includes/' ) !== false ) {

                    $file = explode( '/wp-includes/', $trace['file'] );
                    $plugin_trace[] = 'WP: ' . $file[1] . ':' . $trace['line'];
                }

                if ( strpos( $trace['file'], '/wp-admin/' ) !== false ) {

                    $file = explode( '/wp-admin/', $trace['file'] );
                    $plugin_trace[] = 'WPA: ' . $file[1] . ':' . $trace['line'];
                }
            }
        }

        $trace = array();

        if ( ! empty( $current_user->ID )) {
            $trace[] = date_i18n( 'Y-m-d H:i:s ', current_time( 'timestamp' )) . 'ID ' . $current_user->ID;
            $trace[] = 'Prio ' . UM()->roles()->get_priority_user_role( $current_user->ID );

        } else {
            $trace[] = date_i18n( 'Y-m-d H:i:s ', current_time( 'timestamp' ));
        }

        if ( is_admin() && ! defined( 'DOING_AJAX' )) {
            $trace[] = 'Backend';
        }

        if ( is_numeric( $location )) {
            $trace[] = 'Redirect by ' . $x_redirect_by . ', ' . $location . ', ' .  str_replace( get_site_url(), 'site_url', $status );

        } else {

            $locale = '(' . get_locale() . ') ';
            if ( $status == 'UM' ) {
                $trace[] = 'UM error: ' . $locale . str_replace( get_site_url(), 'site_url', $x_redirect_by ) . ', meta_key: ' . $location;

            } else {

                $trace[] = 'WP error code: ' . $x_redirect_by . ', Message: ' . $locale . str_replace( get_site_url(), 'site_url', $location );

                if ( ! is_array( $status ) && ! empty( $status )) {
                    $trace[] = 'Data: ' . $status;
                }
            }
        }

        $rp_cookie = 'wp-resetpass-' . COOKIEHASH;

        if ( isset( $_COOKIE[$rp_cookie] )) {
            $trace[] = 'RP Cookie: ' . $_COOKIE[$rp_cookie];
        }

        if ( defined( 'DOING_AJAX' )) {
            $trace[] = 'AJAX';
        }

        $trace[] = 'PHP ' . PHP_VERSION;
        $trace[] = 'BackTrace: ' . implode( ', ', $plugin_trace );

        file_put_contents( WP_CONTENT_DIR . '/debug.log', implode( ' ', $trace ) . "\r\n", FILE_APPEND );

        return $x_redirect_by;
    }

}

new UM_Redirect_Error_Trace();

