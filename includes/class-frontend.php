<?php
if (!defined('ABSPATH')) { exit; }

class WP_Survey_Frontend {
    private static $instance = null;
    private $survey;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->survey = WP_Survey::get_instance();
        add_shortcode('wpsurvey', array($this, 'handle_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * 获取问卷的独立访问 URL
     *
     * @param int $survey_id 问卷ID
     * @return string
     */
    public static function get_survey_url(int $survey_id): string {
        return home_url('/?wpsurvey_id=' . $survey_id);
    }

    public function enqueue_assets() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('wpsurvey-frontend', WPSURVEY_PLUGIN_URL . 'assets/css/frontend.css', array(), WPSURVEY_VERSION);
        wp_enqueue_script('wpsurvey-frontend', WPSURVEY_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), WPSURVEY_VERSION, true);
        wp_localize_script('wpsurvey-frontend', 'wpsurvey', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpsurvey_frontend_nonce'),
            'i18n' => array('required' => '此项为必填', 'submit_error' => '提交失败'),
        ));
    }

    public function handle_shortcode($atts) {
        $atts = shortcode_atts(array('id' => 0), $atts, 'wpsurvey');
        $survey_id = (int) $atts['id'];
        if ($survey_id <= 0) { return '<div>无效问卷ID</div>'; }
        $this->enqueue_assets();
        $survey = $this->survey->db()->get_survey($survey_id);
        if (!$survey) { return '<div>问卷不存在</div>'; }
        $questions = $this->survey->db()->get_questions($survey_id);
        foreach ($questions as &$q) {
            if (in_array($q['question_type'], array('radio', 'checkbox', 'select'))) {
                $q['options'] = $this->survey->db()->get_options($q['id']);
            }
        }
        $display_mode = $survey['display_mode'] ?? 'step';
        // 模板所需变量
        $response_id = 0;
        $primary_color = $survey['primary_color'] ?? '#1a73e8';
        $accent_color = $survey['accent_color'] ?? '#00bcd4';
        $button_color = $survey['button_color'] ?? '#0d47a1';
        $custom_css = $survey['custom_css'] ?? '';
        ob_start();
        include WPSURVEY_PLUGIN_DIR . 'templates/frontend/survey-form.php';
        return ob_get_clean();
    }
}