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
        add_action('template_redirect', array($this, 'handle_survey_url'));
        
        // 伪静态规则
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
    }

    /**
     * 注册伪静态 rewrite rules
     */
    public function add_rewrite_rules(): void {
        add_rewrite_rule('^survey/([0-9]+)/?$', 'index.php?wpsurvey_id=$matches[1]', 'top');
    }

    /**
     * 注册 query vars，允许 WordPress 识别 wpsurvey_id
     */
    public function add_query_vars(array $vars): array {
        $vars[] = 'wpsurvey_id';
        return $vars;
    }

    /**
     * 插件激活时 flush rewrite rules
     */
    public static function activate(): void {
        add_rewrite_rule('^survey/([0-9]+)/?$', 'index.php?wpsurvey_id=$matches[1]', 'top');
        flush_rewrite_rules();
    }

    /**
     * 插件停用时 flush rewrite rules
     */
    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * 获取问卷的独立访问 URL（使用伪静态格式）
     *
     * @param int $survey_id 问卷ID
     * @return string
     */
    public static function get_survey_url(int $survey_id): string {
        return home_url('/survey/' . $survey_id . '/');
    }

    public function enqueue_assets() {
        wp_enqueue_script('jquery');
        wp_enqueue_style('wpsurvey-frontend', WPSURVEY_PLUGIN_URL . 'assets/css/frontend.css', array(), WPSURVEY_VERSION);
        wp_enqueue_script('wpsurvey-frontend', WPSURVEY_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), WPSURVEY_VERSION, false);
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

    /**
     * 处理独立问卷URL (?wpsurvey_id=N 或 /survey/N/)
     * 当URL包含wpsurvey_id参数时，渲染问卷页面
     */
    public function handle_survey_url(): void {
        // 优先使用 get_query_var（支持伪静态 /survey/N/），同时兼容旧的 ?wpsurvey_id=N
        $survey_id = get_query_var('wpsurvey_id', 0);
        if (!$survey_id) {
            $survey_id = isset($_GET['wpsurvey_id']) ? (int) $_GET['wpsurvey_id'] : 0;
        }
        $survey_id = (int) $survey_id;
        
        if ($survey_id <= 0) {
            return; // 不是问卷URL，正常流程继续
        }

        $survey = $this->survey->db()->get_survey($survey_id);
        if (!$survey) {
            global $wp_query;
            $wp_query->set_404();
            status_header(404);
            return;
        }

        // 检查问卷访问权限
        $user_id = get_current_user_id();
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $access = $this->survey->check_survey_access($survey, $user_id, $ip_address);
        
        if (!$access['allowed']) {
            if (!empty($access['require_login']) && !$user_id) {
                // 需要登录但未登录，跳转到登录页
                $redirect_url = wp_login_url(get_permalink());
                wp_redirect(add_query_arg('wpsurvey_id', $survey_id, wp_login_url(home_url())));
                exit;
            }
            wp_die($access['message']);
        }

        // 加载资源
        $this->enqueue_assets();

        // 获取题目和选项
        $questions = $this->survey->db()->get_questions($survey_id);
        foreach ($questions as &$q) {
            if (in_array($q['question_type'], array('radio', 'checkbox', 'select'))) {
                $q['options'] = $this->survey->db()->get_options($q['id']);
            }
        }
        unset($q);

        $display_mode = $survey['display_mode'] ?? 'step';
        $response_id = 0;
        $primary_color = $survey['primary_color'] ?? '#1a73e8';
        $accent_color = $survey['accent_color'] ?? '#00bcd4';
        $button_color = $survey['button_color'] ?? '#0d47a1';
        $custom_css = $survey['custom_css'] ?? '';

        // 渲染独立页面
        status_header(200);
        include WPSURVEY_PLUGIN_DIR . 'templates/frontend/survey-single.php';
        exit;
    }
}
