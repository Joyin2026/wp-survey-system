<?php
/**
 * Plugin Name: WP Survey System
 * Version: 1.0.0
 * Description: WordPress 问卷调查系统
 * Requires at least: 6.0
 * Requires PHP: 8.2
 * Author: 瑾煜
 * Author URI: https://blog.sjinyu.com
 * Plugin URI - 插件官方网站
 * Plugin URI: https://www.sjinyu.com
 */

if (!defined('ABSPATH')) { exit; }

define('WPSURVEY_VERSION', '1.0.5');
define('WPSURVEY_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSURVEY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPSURVEY_PLUGIN_BASENAME', plugin_basename(__FILE__));

class WP_Survey_System {
    private static $instance = null;
    public $db;
    public $survey;
    public $admin;
    public $frontend;
    public $ajax;
    public $stats;
    public $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once WPSURVEY_PLUGIN_DIR . 'includes/class-db.php';
        require_once WPSURVEY_PLUGIN_DIR . 'includes/class-settings.php';
        require_once WPSURVEY_PLUGIN_DIR . 'includes/class-survey.php';
        require_once WPSURVEY_PLUGIN_DIR . 'includes/class-ajax.php';
        require_once WPSURVEY_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once WPSURVEY_PLUGIN_DIR . 'includes/class-admin.php';
        require_once WPSURVEY_PLUGIN_DIR . 'includes/class-stats.php';
    }

    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init_components'), 0);
        add_action('init', array($this, 'load_textdomain'));
    }

    public function activate() {
        $this->db = WP_Survey_DB::get_instance();
        $this->db->create_tables();
        // 先注册 rewrite rules，再 flush
        add_rewrite_rule("^survey/([0-9]+)/?$", "index.php?wpsurvey_id=$matches[1]", "top");
        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function init_components() {
        $this->settings = WP_Survey_Settings::get_instance();
        $this->db = WP_Survey_DB::get_instance();
        $this->survey = WP_Survey::get_instance();
        $this->stats = WP_Survey_Stats::get_instance();
        $this->ajax = WP_Survey_AJAX::get_instance();
        $this->frontend = WP_Survey_Frontend::get_instance();
        $this->admin = WP_Survey_Admin::get_instance();
    }

    public function load_textdomain() {
        load_plugin_textdomain('wp-survey', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}

function wp_survey_system() {
    return WP_Survey_System::get_instance();
}

wp_survey_system();
