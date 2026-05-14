<?php
/**
 * AJAX 处理类
 * 
 * 负责处理所有 AJAX 请求
 *
 * @package WP_Survey
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class WP_Survey_AJAX {

    /**
     * AJAX 实例（单例）
     *
     * @var WP_Survey_AJAX|null
     */
    private static ?WP_Survey_AJAX $instance = null;

    /**
     * 问卷业务逻辑实例
     *
     * @var WP_Survey
     */
    private WP_Survey $survey;

    /**
     * 获取实例
     *
     * @return WP_Survey_AJAX
     */
    public static function get_instance(): WP_Survey_AJAX {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->survey = WP_Survey::get_instance();
        
        // 注册 AJAX 钩子
        $this->register_hooks();
    }

    /**
     * 注册所有 AJAX 钩子
     *
     * @return void
     */
    private function register_hooks(): void {
        // 前端 AJAX（已登录和未登录都可用）
        add_action('wp_ajax_wpsurvey_start', array($this, 'handle_start'));
        add_action('wp_ajax_nopriv_wpsurvey_start', array($this, 'handle_start'));
        
        add_action('wp_ajax_wpsurvey_save_answer', array($this, 'handle_save_answer'));
        add_action('wp_ajax_nopriv_wpsurvey_save_answer', array($this, 'handle_save_answer'));
        
        add_action('wp_ajax_wpsurvey_save_answers_batch', array($this, 'handle_save_answers_batch'));
        add_action('wp_ajax_nopriv_wpsurvey_save_answers_batch', array($this, 'handle_save_answers_batch'));
        
        add_action('wp_ajax_wpsurvey_submit', array($this, 'handle_submit'));
        add_action('wp_ajax_nopriv_wpsurvey_submit', array($this, 'handle_submit'));
        
        // 后台 AJAX（仅管理员）
        add_action('wp_ajax_wpsurvey_get_question_form', array($this, 'handle_get_question_form'));
        add_action('wp_ajax_wpsurvey_delete_survey', array($this, 'handle_delete_survey'));
        add_action('wp_ajax_wpsurvey_duplicate_survey', array($this, 'handle_duplicate_survey'));
        add_action('wp_ajax_wpsurvey_get_jump_targets', array($this, 'handle_get_jump_targets'));
    }

    /**
     * 验证 AJAX 请求
     *
     * @param string $action 操作名称
     * @param bool $admin_only 是否仅限管理员
     * @return bool
     */
    private function verify_nonce(string $action, bool $admin_only = false): bool {
        $nonce = sanitize_text_field($_POST['nonce'] ?? $_GET['nonce'] ?? '');
        
        if (empty($nonce)) {
            // 调试：记录缺失 nonce 的情况
            error_log('WPSurvey AJAX: nonce is empty for action ' . $action);
            $this->send_json_error('缺少安全令牌，请刷新页面重试');
            return false;
        }
        
        // 优先使用 action 特定的 nonce，否则使用通用 nonce
        $nonce_action = 'wpsurvey_' . $action . '_nonce';
        if (!wp_verify_nonce($nonce, $nonce_action)) {
            // 尝试通用 admin nonce
            if (!wp_verify_nonce($nonce, 'wpsurvey_admin_nonce')) {
                // 尝试前端 nonce（用于前台问卷参与）
                if (!wp_verify_nonce($nonce, 'wpsurvey_frontend_nonce')) {
                    // 调试：记录 nonce 验证失败
                    error_log('WPSurvey AJAX: nonce verification failed. Action: ' . $action . ', Expected: ' . $nonce_action);
                    $this->send_json_error('安全验证失败，请刷新页面重试');
                    return false;
                }
            }
        }

        if ($admin_only && !current_user_can('manage_options')) {
            $this->send_json_error('权限不足');
            return false;
        }

        return true;
    }

    /**
     * 发送 JSON 成功响应
     *
     * @param mixed $data 数据
     * @return void
     */
    private function send_json_success(mixed $data = null): void {
        wp_send_json_success($data);
    }

    /**
     * 发送 JSON 错误响应
     *
     * @param string $message 错误消息
     * @param int $code 错误码
     * @return void
     */
    private function send_json_error(string $message, int $code = 400): void {
        wp_send_json_error(array('message' => $message), $code);
    }

    // ==================== 前端 AJAX 处理 ====================

    /**
     * 开始问卷（创建答卷）
     *
     * @return void
     */
    public function handle_start(): void {
        if (!$this->verify_nonce('start')) {
            return;
        }

        $survey_id = (int) sanitize_text_field($_POST['survey_id'] ?? 0);
        
        if ($survey_id <= 0) {
            $this->send_json_error('无效的问卷ID');
            return;
        }

        // 获取问卷
        $survey = $this->survey->db()->get_survey($survey_id);
        
        if (!$survey) {
            $this->send_json_error('问卷不存在');
            return;
        }

        // 检查访问权限
        $user_id = get_current_user_id();
        $ip_address = $this->get_client_ip();
        
        $access = $this->survey->check_survey_access($survey, $user_id, $ip_address);
        
        if (!$access['allowed']) {
            $this->send_json_error($access['message']);
            return;
        }

        // 创建或获取答卷
        $response_id = $this->get_or_create_response($survey_id, $user_id, $ip_address);
        
        if (!$response_id) {
            $this->send_json_error('无法创建答卷，请重试');
            return;
        }

        // 获取题目数据
        $questions = $this->survey->db()->get_questions($survey_id);
        
        // 为每个题目加载选项和跳转映射
        foreach ($questions as &$q) {
            if (in_array($q['question_type'], array('radio', 'checkbox', 'select'))) {
                $q['options'] = $this->survey->db()->get_options($q['id']);
                // 构建跳转映射: 选项索引 => 跳转目标question_id
                $q['jump_map'] = array();
                foreach ($q['options'] as $oi => $opt) {
                    $q['jump_map'][$oi] = $opt['jump_to_question_id'] ?? null;
                }
            }
        }
        unset($q);

        // 获取问卷设置
        $primary_color = !empty($survey['primary_color']) ? $survey['primary_color'] : '#1a73e8';
        $accent_color = !empty($survey['accent_color']) ? $survey['accent_color'] : '#00bcd4';
        $button_color = !empty($survey['button_color']) ? $survey['button_color'] : '#0d47a1';

        $this->send_json_success(array(
            'response_id' => $response_id,
            'survey_id' => $survey_id,
            'questions' => $questions,
            'display_mode' => $survey['display_mode'],
            'settings' => array(
                'primary_color' => $primary_color,
                'accent_color' => $accent_color,
                'button_color' => $button_color,
                'custom_css' => $survey['custom_css'] ?? '',
            ),
        ));
    }

    /**
     * 保存答案
     *
     * @return void
     */
    public function handle_save_answer(): void {
        if (!$this->verify_nonce('save_answer')) {
            return;
        }

        $response_id = (int) sanitize_text_field($_POST['response_id'] ?? 0);
        $question_id = (int) sanitize_text_field($_POST['question_id'] ?? 0);
        $answer_value = $_POST['answer_value'] ?? '';

        if ($response_id <= 0 || $question_id <= 0) {
            $this->send_json_error('参数错误');
            return;
        }

        // 处理答案值
        if (is_array($answer_value)) {
            $answer_value = array_map('sanitize_text_field', $answer_value);
        } else {
            $answer_value = sanitize_text_field($answer_value);
        }

        // 保存答案
        $result = $this->survey->db()->save_answer($response_id, $question_id, $answer_value);
        
        if (!$result) {
            $this->send_json_error('保存失败');
            return;
        }

        $this->send_json_success(array('saved' => true));
    }

    /**
     * 批量保存答案（提交前兜底保存）
     */
    public function handle_save_answers_batch(): void {
        if (!$this->verify_nonce('save_answers_batch')) {
            return;
        }

        $response_id = (int) sanitize_text_field($_POST['response_id'] ?? 0);
        $answers_json = sanitize_text_field($_POST['answers'] ?? '');

        if ($response_id <= 0) {
            $this->send_json_error('参数错误');
            return;
        }

        $answers = json_decode($answers_json, true);
        if (!is_array($answers)) {
            $this->send_json_error('答案格式错误');
            return;
        }

        foreach ($answers as $item) {
            $question_id = (int) ($item['question_id'] ?? 0);
            $answer_value = $item['answer_value'] ?? '';
            if ($question_id > 0) {
                $this->survey->db()->save_answer($response_id, $question_id, $answer_value);
            }
        }

        $this->send_json_success(array('saved' => true));
    }

    /**
     * 提交问卷
     *
     * @return void
     */
    public function handle_submit(): void {
        if (!$this->verify_nonce('submit')) {
            return;
        }

        $response_id = (int) sanitize_text_field($_POST['response_id'] ?? 0);
        
        if ($response_id <= 0) {
            $this->send_json_error('无效的答卷ID');
            return;
        }

        // 验证答卷存在
        $response = $this->survey->db()->get_response($response_id);
        
        if (!$response) {
            $this->send_json_error('答卷不存在');
            return;
        }

        // 检查是否已提交
        if (!empty($response['submitted_at'])) {
            $this->send_json_error('此问卷已提交');
            return;
        }

        // 提交答卷
        $result = $this->survey->db()->submit_response($response_id);
        
        if (!$result) {
            $this->send_json_error('提交失败，请重试');
            return;
        }

        $this->send_json_success(array(
            'submitted' => true,
            'message' => '感谢您的参与！您的答案已成功提交。',
        ));
    }

    // ==================== 后台 AJAX 处理 ====================

    /**
     * 获取题目表单
     *
     * @return void
     */
    public function handle_get_question_form(): void {
        if (!$this->verify_nonce('get_question_form', true)) {
            return;
        }

        $question_type = sanitize_text_field($_POST['question_type'] ?? 'radio');
        $question_id = (int) sanitize_text_field($_POST['question_id'] ?? 0);
        $index = (int) sanitize_text_field($_POST['index'] ?? 0);

        ob_start();
        $this->render_question_form($question_type, $question_id, $index);
        $html = ob_get_clean();

        $this->send_json_success(array('html' => $html));
    }

    /**
     * 渲染题目表单
     *
     * @param string $question_type 题型
     * @param int $question_id 题目ID
     * @param int $index 题目索引
     * @return void
     */
    private function render_question_form(string $question_type, int $question_id, int $index): void {
        $question = null;
        
        if ($question_id > 0) {
            $question = $this->survey->get_question($question_id);
        }

        $types = array('radio', 'checkbox', 'select', 'text', 'textarea', 'rating', 'matrix');
        ?>
        <div class="wpsurvey-question-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="wpsurvey-question-header">
                <span class="wpsurvey-question-number">题目 <?php echo ($index + 1); ?></span>
                <span class="wpsurvey-question-type">
                    <?php echo esc_html($this->get_question_type_label($question_type)); ?>
                </span>
                <button type="button" class="wpsurvey-btn-delete-question">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            
            <div class="wpsurvey-question-body">
                <table class="wpsurvey-form-table">
                    <tr>
                        <th>题目内容 <span class="required">*</span></th>
                        <td>
                            <textarea name="questions[<?php echo esc_attr($index); ?>][text]" 
                                      rows="2" required
                                      placeholder="请输入题目内容"><?php 
                                      echo esc_textarea($question['question_text'] ?? ''); 
                                ?></textarea>
                        </td>
                    </tr>
                    <tr>
                        <th>题型</th>
                        <td>
                            <select name="questions[<?php echo esc_attr($index); ?>][type]" 
                                    class="wpsurvey-question-type-select">
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>" 
                                            <?php selected($question_type, $type); ?>>
                                        <?php echo esc_html($this->get_question_type_label($type)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>必填</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="questions[<?php echo esc_attr($index); ?>][required]" 
                                       value="1"
                                       <?php checked($question['required'] ?? false, true); ?>>
                                必填
                            </label>
                        </td>
                    </tr>
                    <?php if (in_array($question_type, array('radio', 'checkbox', 'select'))): ?>
                    <tr class="wpsurvey-options-row">
                        <th>选项 <span class="required">*</span></th>
                        <td>
                            <div class="wpsurvey-options-list">
                                <?php
                                $options = $question['options'] ?? array();
                                if (empty($options)) {
                                    $options = array(array('id' => 0, 'option_text' => '', 'jump_to_question_id' => null));
                                }
                                foreach ($options as $opt_idx => $option):
                                ?>
                                <div class="wpsurvey-option-item">
                                    <input type="hidden" 
                                           name="questions[<?php echo esc_attr($index); ?>][option_ids][]" 
                                           value="<?php echo esc_attr($option['id'] ?? 0); ?>">
                                    <input type="text" 
                                           name="questions[<?php echo esc_attr($index); ?>][options][]"
                                           value="<?php echo esc_attr($option['option_text'] ?? ''); ?>"
                                           placeholder="选项内容">
                                    <select name="questions[<?php echo esc_attr($index); ?>][jump_to][]"
                                            class="wpsurvey-jump-select"
                                            title="选择此项后跳转到">
                                        <option value="">默认顺序</option>
                                        <option value="0" <?php selected($option['jump_to_question_id'] ?? '', 0); ?>>结束问卷</option>
                                    </select>
                                    <button type="button" class="wpsurvey-btn-delete-option">
                                        <span class="dashicons dashicons-no"></span>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="wpsurvey-btn-add-option button">
                                <span class="dashicons dashicons-plus"></span> 添加选项
                            </button>
                        </td>
                    </tr>
                    <?php elseif ($question_type === 'rating'): ?>
                    <tr class="wpsurvey-rating-row">
                        <th>最高分值</th>
                        <td>
                            <select name="questions[<?php echo esc_attr($index); ?>][max_score]">
                                <option value="5" <?php selected($question['settings']['max_score'] ?? 5, 5); ?>>5星 / 5分</option>
                                <option value="10" <?php selected($question['settings']['max_score'] ?? 5, 10); ?>>10星 / 10分</option>
                            </select>
                        </td>
                    </tr>
                    <?php elseif ($question_type === 'matrix'): ?>
                    <tr class="wpsurvey-matrix-row">
                        <th>矩阵设置</th>
                        <td>
                            <div class="wpsurvey-matrix-rows">
                                <label>行标题（每行一个）：</label>
                                <textarea name="questions[<?php echo esc_attr($index); ?>][matrix_rows]" 
                                          rows="3"
                                          placeholder="例如：&#10;商品质量&#10;配送速度&#10;客服态度"><?php
                                    if (!empty($question['settings']['rows'])) {
                                        echo esc_textarea(implode("\n", $question['settings']['rows']));
                                    }
                                ?></textarea>
                            </div>
                            <div class="wpsurvey-matrix-columns">
                                <label>列标题（每列一个）：</label>
                                <textarea name="questions[<?php echo esc_attr($index); ?>][matrix_columns]" 
                                          rows="3"
                                          placeholder="例如：&#10;非常满意&#10;满意&#10;一般&#10;不满意"><?php
                                    if (!empty($question['settings']['columns'])) {
                                        echo esc_textarea(implode("\n", $question['settings']['columns']));
                                    }
                                ?></textarea>
                            </div>
                        </td>
                    </tr>
                    <?php elseif ($question_type === 'text'): ?>
                    <tr>
                        <th>提示文字</th>
                        <td>
                            <input type="text" 
                                   name="questions[<?php echo esc_attr($index); ?>][placeholder]"
                                   value="<?php echo esc_attr($question['settings']['placeholder'] ?? ''); ?>"
                                   placeholder="请输入placeholder提示文字">
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                
                <input type="hidden" 
                       name="questions[<?php echo esc_attr($index); ?>][id]" 
                       value="<?php echo esc_attr($question_id); ?>">
            </div>
        </div>
        <?php
    }

    /**
     * 删除问卷
     *
     * @return void
     */
    public function handle_delete_survey(): void {
        if (!$this->verify_nonce('delete_survey', true)) {
            return;
        }

        $survey_id = (int) sanitize_text_field($_POST['survey_id'] ?? 0);
        
        if ($survey_id <= 0) {
            $this->send_json_error('无效的问卷ID');
            return;
        }

        $result = $this->survey->delete_survey($survey_id);
        
        if (!$result) {
            $this->send_json_error('删除失败');
            return;
        }

        $this->send_json_success(array('deleted' => true));
    }

    /**
     * 复制问卷
     *
     * @return void
     */
    public function handle_duplicate_survey(): void {
        if (!$this->verify_nonce('duplicate_survey', true)) {
            return;
        }

        $survey_id = (int) sanitize_text_field($_POST['survey_id'] ?? 0);
        
        if ($survey_id <= 0) {
            $this->send_json_error('无效的问卷ID');
            return;
        }

        $new_id = $this->survey->duplicate_survey($survey_id);
        
        if (!$new_id) {
            $this->send_json_error('复制失败');
            return;
        }

        $this->send_json_success(array(
            'duplicated' => true,
            'new_id' => $new_id,
            'edit_url' => admin_url('admin.php?page=wpsurvey-edit&id=' . $new_id),
        ));
    }

    /**
     * 获取跳转目标选项
     *
     * @return void
     */
    public function handle_get_jump_targets(): void {
        if (!$this->verify_nonce('get_jump_targets', true)) {
            return;
        }

        $survey_id = (int) sanitize_text_field($_POST['survey_id'] ?? 0);
        
        if ($survey_id <= 0) {
            $this->send_json_error('无效的问卷ID');
            return;
        }

        $questions = $this->survey->db()->get_questions($survey_id);
        
        $targets = array();
        foreach ($questions as $q) {
            $targets[] = array(
                'id' => $q['id'],
                'text' => wp_trim_words($q['question_text'], 15),
            );
        }

        $this->send_json_success(array('targets' => $targets));
    }

    // ==================== 辅助方法 ====================

    /**
     * 创建或获取答卷
     *
     * @param int $survey_id 问卷ID
     * @param int|null $user_id 用户ID
     * @param string|null $ip_address IP地址
     * @return int|false
     */
    private function get_or_create_response(int $survey_id, ?int $user_id, ?string $ip_address): int|false {
        global $wpdb;

        // 检查是否已有未完成的答卷
        $existing = $this->survey->db()->get_incomplete_response($survey_id, $user_id);
        
        if ($existing) {
            return $existing['id'];
        }

        // 创建新答卷
        return $this->survey->db()->create_response(array(
            'survey_id' => $survey_id,
            'user_id' => $user_id ?: null,
            'ip_address' => $ip_address ?: '',
            'started_at' => current_time('mysql'),
        ));
    }

    /**
     * 获取客户端 IP
     *
     * @return string
     */
    private function get_client_ip(): string {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = sanitize_text_field(trim($ips[0]));
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }

        return $ip;
    }

    /**
     * 获取题型标签
     *
     * @param string $type 题型
     * @return string
     */
    private function get_question_type_label(string $type): string {
        $labels = array(
            'radio' => '单选题',
            'checkbox' => '多选题',
            'select' => '下拉选择',
            'text' => '单行文本',
            'textarea' => '多行文本',
            'rating' => '评分题',
            'matrix' => '矩阵题',
        );
        return $labels[$type] ?? $type;
    }
}
