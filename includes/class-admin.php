<?php
/**
 * Admin class
 */
defined('ABSPATH') || exit;

class WP_Survey_Admin {
    private static $instance = null;
    private $survey;

    public static function get_instance(): WP_Survey_Admin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->survey = WP_Survey::get_instance();
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('plugin_action_links_' . WPSURVEY_PLUGIN_BASENAME, array($this, 'add_action_links'));
    }

    public function register_menu(): void {
        add_menu_page(
            'WP 问卷系统',
            'WP 问卷',
            'manage_options',
            'wpsurvey',
            array($this, 'page_survey_list'),
            'dashicons-clipboard',
            30
        );

        add_submenu_page(
            'wpsurvey',
            '问卷列表',
            '问卷列表',
            'manage_options',
            'wpsurvey',
            array($this, 'page_survey_list')
        );

        add_submenu_page(
            'wpsurvey',
            '添加问卷',
            '添加问卷',
            'manage_options',
            'wpsurvey-add',
            array($this, 'page_survey_edit')
        );

        add_submenu_page(
            'wpsurvey',
            '编辑问卷',
            '编辑问卷',
            'manage_options',
            'wpsurvey-edit',
            array($this, 'page_survey_edit')
        );

        add_submenu_page(
            'wpsurvey',
            '统计分析',
            '统计分析',
            'manage_options',
            'wpsurvey-stats',
            array($this, 'page_stats')
        );

        add_submenu_page(
            'wpsurvey',
            '全局设置',
            '全局设置',
            'manage_options',
            'wpsurvey-settings',
            array($this, 'page_settings')
        );
    }

    public function enqueue_assets(string $hook_suffix): void {
        if (strpos($hook_suffix, 'wpsurvey') === false) {
            return;
        }

        wp_enqueue_style(
            'wpsurvey-admin',
            WPSURVEY_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPSURVEY_VERSION
        );

        wp_enqueue_script(
            'wpsurvey-admin',
            WPSURVEY_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'jquery-ui-sortable'),
            WPSURVEY_VERSION,
            true
        );

        // 为统计页面加载 Chart.js
        if (strpos($hook_suffix, 'wpsurvey-stats') !== false) {
            $chart_url = WP_Survey_Settings::get_instance()->get_chart_js_url();
            wp_enqueue_script(
                'chartjs',
                $chart_url,
                array(),
                '4.4.1',
                false
            );
        }

        wp_localize_script('wpsurvey-admin', 'wpsurvey_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpsurvey_admin_nonce'),
            'strings' => array(
                'confirm_delete' => '确认删除此问卷吗？',
                'confirm_delete_question' => '确认删除此题吗？',
                'add_question' => '添加题目',
                'add_option' => '添加选项',
                'saving' => '保存中…',
                'saved' => '已保存',
                'error' => '保存失败',
            ),
        ));
    }

    public function add_action_links(array $links): array {
        $settings_link = '<a href="' . admin_url('admin.php?page=wpsurvey-settings') . '">设置</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function page_survey_list(): void {
        if (isset($_POST['action']) && isset($_POST['surveys']) && wp_verify_nonce($_POST['_wpnonce'], 'wpsurvey_bulk_action')) {
            $action = sanitize_text_field($_POST['action']);
            $ids = array_map('intval', $_POST['surveys']);
            
            foreach ($ids as $id) {
                switch ($action) {
                    case 'delete':
                        $this->survey->delete_survey($id);
                        break;
                    case 'publish':
                        $this->survey->update_survey($id, array('status' => 'published'));
                        break;
                    case 'draft':
                        $this->survey->update_survey($id, array('status' => 'draft'));
                        break;
                    case 'duplicate':
                        $this->survey->duplicate_survey($id);
                        break;
                }
            }
            
            wp_redirect(admin_url('admin.php?page=wpsurvey&message=' . $action));
            exit;
        }

        $args = array();
        if (isset($_GET['status']) && in_array($_GET['status'], array('draft', 'published', 'closed'))) {
            $args['status'] = sanitize_text_field($_GET['status']);
        }
        
        $surveys = $this->survey->get_surveys($args);
        $total = $this->survey->get_surveys_count($args);

        include WPSURVEY_PLUGIN_DIR . 'templates/admin/survey-list.php';
    }

    public function page_survey_edit(): void {
        $survey_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        
        if (isset($_POST['wpsurvey_save']) && wp_verify_nonce($_POST['_wpnonce'], 'wpsurvey_save_survey')) {
            $this->save_survey();
            return;
        }

        if ($survey_id > 0) {
            $survey = $this->survey->get_survey($survey_id);
            if (!$survey) {
                wp_die('问卷不存在');
            }
        } else {
            $survey = array(
                'id' => 0,
                'title' => '',
                'description' => '',
                'type' => 'collection',
                'status' => 'draft',
                'display_mode' => 'step',
                'require_login' => 1,
                'limit_one' => 1,
                'start_time' => '',
                'end_time' => '',
                'primary_color' => '#1a73e8',
                'accent_color' => '#00bcd4',
                'button_color' => '#0d47a1',
                'custom_css' => '',
                'questions' => array(),
            );
        }

        foreach ($survey['questions'] as &$question) {
            $question['options_with_jumps'] = array();
            foreach ($question['options'] as &$option) {
                $option['jump_question_text'] = '';
                if ($option['jump_to_question_id']) {
                    $jump_question = $this->survey->db()->get_question($option['jump_to_question_id']);
                    if ($jump_question) {
                        $option['jump_question_text'] = wp_trim_words($jump_question['question_text'], 10);
                    }
                }
                $question['options_with_jumps'][] = $option;
            }
            unset($option);  // 修复: 断开引用，避免后续 foreach 覆盖最后一个元素
        }
        unset($question);  // 修复: 断开外层引用

        include WPSURVEY_PLUGIN_DIR . 'templates/admin/survey-edit.php';
    }

    private function save_survey(): void {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }

        $survey_id = isset($_POST['survey_id']) ? (int) $_POST['survey_id'] : 0;
        
        $survey_data = array(
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => wp_kses_post($_POST['description'] ?? ''),
            'type' => sanitize_text_field($_POST['type'] ?? 'collection'),
            'status' => sanitize_text_field($_POST['status'] ?? 'draft'),
            'display_mode' => sanitize_text_field($_POST['display_mode'] ?? 'step'),
            'require_login' => isset($_POST['require_login']) ? 1 : 0,
            'limit_one' => isset($_POST['limit_one']) ? 1 : 0,
            'start_time' => !empty($_POST['start_time']) ? sanitize_text_field($_POST['start_time']) : null,
            'end_time' => !empty($_POST['end_time']) ? sanitize_text_field($_POST['end_time']) : null,
            'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#1a73e8'),
            'accent_color' => sanitize_hex_color($_POST['accent_color'] ?? '#00bcd4'),
            'button_color' => sanitize_hex_color($_POST['button_color'] ?? '#0d47a1'),
            'custom_css' => wp_strip_all_tags($_POST['custom_css'] ?? ''),
        );

        if ($survey_id > 0) {
            $this->survey->update_survey($survey_id, $survey_data);
        } else {
            $survey_id = $this->survey->create_survey($survey_data);
        }

        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            // 调试日志：记录原始 POST 数据
            if (defined('WPSURVEY_DEBUG') && WPSURVEY_DEBUG) {
                error_log('WPSurvey: save_survey - raw POST questions count=' . count($_POST['questions']));
                foreach ($_POST['questions'] as $idx => $q) {
                    $raw_id = isset($q['id']) ? (is_array($q['id']) ? json_encode($q['id']) : $q['id']) : 'NULL';
                    error_log('WPSurvey: raw question ' . $idx . ' id=' . $raw_id);
                }
            }
            $questions_data = $this->parse_questions_data($_POST['questions']);
            $this->survey->save_questions($survey_id, $questions_data);
        }

        wp_redirect(admin_url('admin.php?page=wpsurvey-edit&id=' . $survey_id . '&message=saved'));
        exit;
    }

    private function parse_questions_data(array $post_questions): array {
        $questions = array();
        
        // 调试日志
        if (defined('WPSURVEY_DEBUG') && WPSURVEY_DEBUG) {
            error_log('WPSurvey: parse_questions_data - received ' . count($post_questions) . ' questions');
        }

        foreach ($post_questions as $index => $q_data) {
            // 获取题型 - 同时兼容 'type' 和 'question_type' 字段名
            $question_type = sanitize_text_field($q_data['type'] ?? ($q_data['question_type'] ?? 'radio'));
            
            // 获取题目内容
            $question_text = wp_kses_post($q_data['text'] ?? '');
            
            // 如果题目内容为空但有有效 ID，则保留原题目（防止误删）
            // 如果既没有内容也没有 ID，则跳过（这是真正的无效题目）
            if (empty($question_text) && empty($q_data['id'])) {
                continue;
            }
            
            // 处理 id 字段：如果是数组（由于重复字段），取第一个非零值
            $question_id = 0;
            if (isset($q_data['id'])) {
                if (is_array($q_data['id'])) {
                    // 如果有重复字段，取第一个非零值
                    foreach ($q_data['id'] as $id_val) {
                        $id_val = (int) $id_val;
                        if ($id_val > 0) {
                            $question_id = $id_val;
                            break;
                        }
                    }
                } else {
                    $question_id = (int) $q_data['id'];
                }
            }
            
            // 调试日志
            if (defined('WPSURVEY_DEBUG') && WPSURVEY_DEBUG) {
                error_log('WPSurvey: parsing question ' . $index . ' - id=' . $question_id . ' text=' . substr($question_text, 0, 30));
            }
            
            $question = array(
                'id' => $question_id,
                'question_text' => $question_text,
                'question_type' => $question_type,
                'required' => isset($q_data['required']) ? 1 : 0,
                'settings' => array(),
            );

            $question['options'] = array();
            
            // 选择题类型的选项处理
            if (in_array($question_type, array('radio', 'checkbox', 'select'))) {
                $option_ids = isset($q_data['option_ids']) ? (array) $q_data['option_ids'] : array();
                $option_texts = isset($q_data['options']) ? (array) $q_data['options'] : array();
                $jump_targets = isset($q_data['jump_to']) ? (array) $q_data['jump_to'] : array();
                
                // 以选项文本数组的长度为基准，构建结构化选项数组
                $count = count($option_texts);

                for ($i = 0; $i < $count; $i++) {
                    $text = trim($option_texts[$i]);
                    if ($text === '') {
    
                        continue; // 跳过空选项
                    }
                    
                    $opt_id = isset($option_ids[$i]) ? (int) $option_ids[$i] : 0;
                    $jump = null;
                    if (isset($jump_targets[$i]) && $jump_targets[$i] !== '' && $jump_targets[$i] !== '0') {
                        $jump = (int) $jump_targets[$i];
                    }
                    

                    
                    $question['options'][] = array(
                        'id' => $opt_id,
                        'option_text' => sanitize_text_field($text),
                        'jump_to_question_id' => $jump,
                    );
                }
            }

            // 评分题设置 - 确保 max_score 被正确保存
            if ($question_type === 'rating') {
                $max_score = isset($q_data['max_score']) ? (int) $q_data['max_score'] : 5;
                if ($max_score !== 5 && $max_score !== 10) {
                    $max_score = 5; // 只能是 5 或 10
                }
                $question['settings'] = array(
                    'max_score' => $max_score,
                );
            }
            
            // 矩阵题设置 - 确保 rows 和 columns 被正确保存
            elseif ($question_type === 'matrix') {
                // 兼容多种可能的字段名格式
                $rows = array();
                $columns = array();
                
                // 处理 matrix_rows
                if (isset($q_data['matrix_rows'])) {
                    if (is_array($q_data['matrix_rows'])) {
                        $rows = array_filter(array_map('trim', array_map('sanitize_text_field', $q_data['matrix_rows'])));
                    } else {
                        // 如果是字符串（textarea 提交），按换行分割
                        $rows_text = trim($q_data['matrix_rows']);
                        if (!empty($rows_text)) {
                            $rows = array_filter(array_map('trim', explode("\n", $rows_text)));
                            $rows = array_map('sanitize_text_field', $rows);
                        }
                    }
                }
                
                // 处理 matrix_columns
                if (isset($q_data['matrix_columns'])) {
                    if (is_array($q_data['matrix_columns'])) {
                        $columns = array_filter(array_map('trim', array_map('sanitize_text_field', $q_data['matrix_columns'])));
                    } else {
                        // 如果是字符串（textarea 提交），按换行分割
                        $columns_text = trim($q_data['matrix_columns']);
                        if (!empty($columns_text)) {
                            $columns = array_filter(array_map('trim', explode("\n", $columns_text)));
                            $columns = array_map('sanitize_text_field', $columns);
                        }
                    }
                }
                
                $question['settings'] = array(
                    'rows' => array_values($rows), // 重新索引
                    'columns' => array_values($columns), // 重新索引
                );
            }
            
            // 文本题提示文字
            elseif ($question_type === 'text' || $question_type === 'textarea') {
                if (!empty($q_data['placeholder'])) {
                    $question['settings'] = array(
                        'placeholder' => sanitize_text_field($q_data['placeholder']),
                    );
                }
            }

            $questions[] = $question;
        }

        return $questions;
    }

    public function page_stats(): void {
        $survey_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        if ($survey_id > 0) {
            $survey = $this->survey->db()->get_survey($survey_id);
            if (!$survey) {
                wp_die('问卷不存在');
            }

            $stats = $this->survey->db()->get_survey_stats($survey_id);
            $questions = $this->survey->db()->get_questions($survey_id);
            // 为每道题加载选项和统计
            foreach ($questions as &$q) {
                $q['options'] = $this->survey->db()->get_options($q['id']);
                $q['stats'] = $this->survey->db()->get_question_stats($q['id'], $q['question_type']);
                // 矩阵题：rows/columns 存在 settings 里，传入 options 供图表渲染
                if ($q['question_type'] === 'matrix' && !empty($q['settings'])) {
                    $q['options'] = $q['settings'];
                }
            }
            $responses = $this->survey->db()->get_responses($survey_id, array('limit' => 100));

            // CSV 导出
            if (isset($_GET['export']) && $_GET['export'] === 'csv') {
                $this->export_stats_csv($survey, $questions, $responses);
                return;
            }

            include WPSURVEY_PLUGIN_DIR . 'templates/admin/stats.php';
        } else {
            $all_surveys = $this->survey->db()->get_surveys(array());
            $surveys_with_stats = array();
            foreach ($all_surveys as $s) {
                $s_stats = $this->survey->db()->get_survey_stats($s['id']);
                $s['stats'] = $s_stats;
                $surveys_with_stats[] = $s;
            }
            include WPSURVEY_PLUGIN_DIR . 'templates/admin/stats-overview.php';
        }
    }

    /**
     * 导出统计为 CSV
     */
    private function export_stats_csv(array $survey, array $questions, array $responses): void {
        $filename = 'survey_' . $survey['id'] . '_stats_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        // 输出 BOM 支持中文
        echo "\xEF\xBB\xBF";

        // 表头
        $headers = array('答卷ID', '用户', 'IP地址', '开始时间', '提交时间', '完成耗时(秒)');
        foreach ($questions as $q) {
            $headers[] = 'Q' . ($q['sort_order'] + 1) . ': ' . mb_substr($q['question_text'], 0, 30);
        }
        fputcsv(stdout(), $headers);

        // 数据行
        foreach ($responses as $resp) {
            $row = array(
                $resp['id'],
                $resp['user_id'] ? '用户#' . $resp['user_id'] : '访客',
                $resp['ip_address'],
                $resp['started_at'],
                $resp['submitted_at'] ?: '未提交',
                $resp['completion_time'] ?? '',
            );
            // 答案
            $answers = $this->survey->db()->get_answers($resp['id']);
            foreach ($questions as $q) {
                $ans = $answers[$q['id']] ?? '';
                if (is_array($ans)) {
                    $ans = implode(', ', $ans);
                }
                $row[] = $ans;
            }
            fputcsv(stdout(), $row);
        }

        exit;
    }

    public function page_settings(): void {
        if (isset($_POST['wpsurvey_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'wpsurvey_save_settings')) {
            $this->save_settings();
            return;
        }

        $current_settings = WP_Survey_Settings::get_instance()->get_settings();
        include WPSURVEY_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    private function save_settings(): void {
        if (!current_user_can('manage_options')) {
            wp_die('权限不足');
        }

        $post_settings = isset($_POST['settings']) && is_array($_POST['settings']) ? $_POST['settings'] : array();

        $settings = array(
            'primary_color' => sanitize_hex_color($post_settings['primary_color'] ?? '#1a73e8'),
            'accent_color' => sanitize_hex_color($post_settings['accent_color'] ?? '#00bcd4'),
            'button_color' => sanitize_hex_color($post_settings['button_color'] ?? '#0d47a1'),
            'default_display_mode' => sanitize_text_field($post_settings['default_display_mode'] ?? 'step'),
            'allow_guest' => isset($post_settings['allow_guest']) ? 1 : 0,
            'custom_css' => wp_strip_all_tags($post_settings['custom_css'] ?? ''),
            'chart_cdn' => sanitize_text_field($post_settings['chart_cdn'] ?? 'bootcdn'),
            'font_family' => sanitize_text_field($post_settings['font_family'] ?? ''),
        );

        WP_Survey_Settings::get_instance()->save($settings);

        wp_redirect(admin_url('admin.php?page=wpsurvey-settings&message=saved'));
        exit;
    }
}
