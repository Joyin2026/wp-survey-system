<?php
/**
 * 问卷业务逻辑类
 * 
 * 负责问卷的 CRUD 操作和数据处理
 *
 * @package WP_Survey
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class WP_Survey {

    /**
     * 问卷业务逻辑实例（单例）
     *
     * @var WP_Survey|null
     */
    private static ?WP_Survey $instance = null;

    /**
     * 数据库操作实例
     *
     * @var WP_Survey_DB
     */
    private WP_Survey_DB $db;

    /**
     * 获取实例
     *
     * @return WP_Survey
     */
    public static function get_instance(): WP_Survey {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->db = WP_Survey_DB::get_instance();
    }

    /**
     * 获取数据库操作实例
     *
     * @return WP_Survey_DB
     */
    public function db(): WP_Survey_DB {
        return $this->db;
    }

    // ==================== 问卷操作 ====================

    /**
     * 获取所有问卷列表
     *
     * @param array $args 查询参数
     * @return array
     */
    public function get_surveys(array $args = array()): array {
        return $this->db->get_surveys($args);
    }

    /**
     * 获取问卷总数
     *
     * @param array $args 查询参数
     * @return int
     */
    public function get_surveys_count(array $args = array()): int {
        return $this->db->get_surveys_count($args);
    }

    /**
     * 获取单个问卷（包含题目和选项）
     *
     * @param int $id 问卷ID
     * @return array|null
     */
    public function get_survey(int $id): ?array {
        $survey = $this->db->get_survey($id);
        
        if (!$survey) {
            return null;
        }

        // 获取题目列表
        $questions = $this->db->get_questions($id);
        
        // 为每个题目加载选项
        foreach ($questions as &$question) {
            if (in_array($question['question_type'], array('radio', 'checkbox', 'select'))) {
                $question['options'] = $this->db->get_options($question['id']);
            } else {
                $question['options'] = array();
            }
        }

        $survey['questions'] = $questions;

        return $survey;
    }

    /**
     * 创建问卷
     *
     * @param array $data 问卷数据
     * @return int|false
     */
    public function create_survey(array $data): int|false {
        return $this->db->create_survey($data);
    }

    /**
     * 更新问卷
     *
     * @param int $id 问卷ID
     * @param array $data 更新的数据
     * @return bool
     */
    public function update_survey(int $id, array $data): bool {
        return $this->db->update_survey($id, $data);
    }

    /**
     * 删除问卷
     *
     * @param int $id 问卷ID
     * @return bool
     */
    public function delete_survey(int $id): bool {
        return $this->db->delete_survey($id);
    }

    /**
     * 复制问卷
     *
     * @param int $id 源问卷ID
     * @return int|false 新问卷ID
     */
    public function duplicate_survey(int $id): int|false {
        $survey = $this->db->get_survey($id);
        if (!$survey) {
            return false;
        }

        // 创建新问卷（复制基础信息）
        $new_survey_data = array(
            'title' => $survey['title'] . ' (副本)',
            'description' => $survey['description'],
            'type' => $survey['type'],
            'status' => 'draft',
            'display_mode' => $survey['display_mode'],
            'require_login' => $survey['require_login'],
            'limit_one' => $survey['limit_one'],
            'start_time' => $survey['start_time'],
            'end_time' => $survey['end_time'],
            'primary_color' => $survey['primary_color'],
            'accent_color' => $survey['accent_color'],
            'button_color' => $survey['button_color'],
        );

        $new_survey_id = $this->db->create_survey($new_survey_data);
        if (!$new_survey_id) {
            return false;
        }

        // 复制题目
        $questions = $this->db->get_questions($id);
        $old_to_new_question_ids = array();

        foreach ($questions as $question) {
            $new_question_id = $this->db->create_question(array(
                'survey_id' => $new_survey_id,
                'question_text' => $question['question_text'],
                'question_type' => $question['question_type'],
                'required' => $question['required'],
                'sort_order' => $question['sort_order'],
                'settings' => $question['settings'],
            ));

            if ($new_question_id) {
                $old_to_new_question_ids[$question['id']] = $new_question_id;

                // 复制选项
                $options = $this->db->get_options($question['id']);
                foreach ($options as $option) {
                    $this->db->create_option(array(
                        'question_id' => $new_question_id,
                        'option_text' => $option['option_text'],
                        'sort_order' => $option['sort_order'],
                        'jump_to_question_id' => null, // 跳转目标需要重新设置
                    ));
                }
            }
        }

        // 更新跳转目标ID映射（只复制同问卷内的跳转）
        foreach ($old_to_new_question_ids as $old_id => $new_id) {
            $options = $this->db->get_options($new_id);
            foreach ($options as $option) {
                if ($option['jump_to_question_id'] && isset($old_to_new_question_ids[$option['jump_to_question_id']])) {
                    $this->db->update_option($option['id'], array(
                        'jump_to_question_id' => $old_to_new_question_ids[$option['jump_to_question_id']],
                    ));
                }
            }
        }

        return $new_survey_id;
    }

    // ==================== 题目操作 ====================

    /**
     * 获取问卷的所有题目
     *
     * @param int $survey_id 问卷ID
     * @return array
     */
    public function get_questions(int $survey_id): array {
        return $this->db->get_questions($survey_id);
    }

    /**
     * 获取单个题目（包含选项）
     *
     * @param int $question_id 题目ID
     * @return array|null
     */
    public function get_question(int $question_id): ?array {
        $question = $this->db->get_question($question_id);
        
        if (!$question) {
            return null;
        }

        if (in_array($question['question_type'], array('radio', 'checkbox', 'select'))) {
            $question['options'] = $this->db->get_options($question_id);
        } else {
            $question['options'] = array();
        }

        return $question;
    }

    /**
     * 创建题目
     *
     * @param array $data 题目数据
     * @param array $options 选项数组（可选）
     * @return int|false
     */
    public function create_question(array $data, array $options = array()): int|false {
        $question_id = $this->db->create_question($data);
        
        if (!$question_id) {
            return false;
        }

        // 创建选项
        if (!empty($options)) {
            foreach ($options as $option_text) {
                $option_text = trim($option_text);
                if (!empty($option_text)) {
                    $this->db->create_option(array(
                        'question_id' => $question_id,
                        'option_text' => $option_text,
                    ));
                }
            }
        }

        return $question_id;
    }

    /**
     * 更新题目
     *
     * @param int $id 题目ID
     * @param array $data 更新的数据
     * @param array $options 选项数组（可选，用于替换）
     * @return bool
     */
    public function update_question(int $id, array $data, array $options = array()): bool {
        $result = $this->db->update_question($id, $data);
        
        if (!$result) {
            return false;
        }

        // 如果提供了选项，替换现有选项
        if (!empty($options)) {
            // 获取现有选项
            $existing_options = $this->db->get_options($id);
            
            // 更新或创建选项
            $sort_order = 0;
            foreach ($options as $index => $option_text) {
                $option_text = trim($option_text);
                if (empty($option_text)) {
                    continue;
                }

                if (isset($existing_options[$index])) {
                    // 更新现有选项
                    $this->db->update_option($existing_options[$index]['id'], array(
                        'option_text' => $option_text,
                        'sort_order' => $sort_order++,
                    ));
                } else {
                    // 创建新选项
                    $this->db->create_option(array(
                        'question_id' => $id,
                        'option_text' => $option_text,
                        'sort_order' => $sort_order++,
                    ));
                }
            }

            // 删除多余的选项
            $new_count = count(array_filter($options));
            for ($i = $new_count; $i < count($existing_options); $i++) {
                if (isset($existing_options[$i])) {
                    $this->db->delete_option($existing_options[$i]['id']);
                }
            }
        }

        return true;
    }

    /**
     * 删除题目
     *
     * @param int $id 题目ID
     * @return bool
     */
    public function delete_question(int $id): bool {
        return $this->db->delete_question($id);
    }

    /**
     * 保存问卷题目（批量）
     *
     * @param int $survey_id 问卷ID
     * @param array $questions_data 题目数据数组
     * @return bool
     */
    public function save_questions(int $survey_id, array $questions_data): bool {
        // 获取现有题目
        $existing_questions = $this->db->get_questions($survey_id);
        $existing_ids = wp_list_pluck($existing_questions, 'id');
        
        // 记录调试信息（仅在启用调试时）
        if (defined('WPSURVEY_DEBUG') && WPSURVEY_DEBUG) {
            error_log('WPSurvey: save_questions - survey_id=' . $survey_id);
            error_log('WPSurvey: existing_ids=' . json_encode($existing_ids));
            error_log('WPSurvey: questions_data count=' . count($questions_data));
            foreach ($questions_data as $idx => $q) {
                error_log('WPSurvey: question ' . $idx . ' id=' . ($q['id'] ?? 'NULL') . ' text=' . substr($q['question_text'] ?? '', 0, 30));
            }
        }

        // 处理新题目数据
        $new_ids = array();
        $sort_order = 0;

        foreach ($questions_data as $q_data) {
            $question_id = isset($q_data['id']) ? (int) $q_data['id'] : 0;
            $options = isset($q_data['options']) ? $q_data['options'] : array();
            $new_question_id = 0; // 初始化新题目ID变量
            
            // 确保 settings 字段存在
            $settings = isset($q_data['settings']) && is_array($q_data['settings']) 
                ? $q_data['settings'] 
                : array();
            
            // 注意：不再跳过"重复内容"的新题目，因为用户可能确实需要创建相同内容的题目
            // 或者由于前端索引问题导致误判

            if ($question_id > 0 && in_array($question_id, $existing_ids)) {
                // 更新现有题目
                $this->db->update_question($question_id, array(
                    'question_text' => $q_data['question_text'],
                    'question_type' => $q_data['question_type'],
                    'required' => isset($q_data['required']) ? 1 : 0,
                    'sort_order' => $sort_order,
                    'settings' => $settings,
                ));

                // 更新选项：先获取并删除旧选项，再创建新选项
                $existing_options = $this->db->get_options($question_id);
                foreach ($existing_options as $opt) {
                    $this->db->delete_option($opt['id']);
                }

                // 创建新选项
                $opt_order = 0;
                foreach ($options as $opt_idx => $opt_text) {
                    $opt_text = trim($opt_text);
                    if (!empty($opt_text)) {
                        $jump_target = null;
                        if (isset($q_data['jump_options'][$opt_idx])) {
                            $jump_target = $q_data['jump_options'][$opt_idx];
                            if ($jump_target === '' || $jump_target === 0) {
                                $jump_target = null; // "结束问卷" 或 "默认" 转为 null
                            }
                        }
                        $this->db->create_option(array(
                            'question_id' => $question_id,
                            'option_text' => $opt_text,
                            'sort_order' => $opt_order++,
                            'jump_to_question_id' => $jump_target,
                        ));
                    }
                }
            } else {
                // 创建新题目
                $new_question_id = $this->db->create_question(array(
                    'survey_id' => $survey_id,
                    'question_text' => $q_data['question_text'],
                    'question_type' => $q_data['question_type'],
                    'required' => isset($q_data['required']) ? 1 : 0,
                    'sort_order' => $sort_order,
                    'settings' => $settings,
                ));

                // 创建选项
                $opt_order = 0;
                foreach ($options as $opt_idx => $opt_text) {
                    $opt_text = trim($opt_text);
                    if (!empty($opt_text)) {
                        $jump_target = null;
                        if (isset($q_data['jump_options'][$opt_idx])) {
                            $jump_target = $q_data['jump_options'][$opt_idx];
                            if ($jump_target === '' || $jump_target === 0) {
                                $jump_target = null;
                            }
                        }
                        $this->db->create_option(array(
                            'question_id' => $new_question_id,
                            'option_text' => $opt_text,
                            'sort_order' => $opt_order++,
                            'jump_to_question_id' => $jump_target,
                        ));
                    }
                }
                
                $question_id = $new_question_id;
            }

            // 确保将正确的题目ID添加到列表
            if ($question_id > 0) {
                $new_ids[] = $question_id;
            } elseif (!empty($new_question_id)) {
                $new_ids[] = $new_question_id;
            }
            $sort_order++;
        }

        // 删除被移除的题目（不在新数据中的旧题目）
        $deleted_ids = array();
        foreach ($existing_ids as $existing_id) {
            if (!in_array($existing_id, $new_ids)) {
                $this->db->delete_question($existing_id);
                $deleted_ids[] = $existing_id;
            }
        }
        
        // 调试日志
        if (defined('WPSURVEY_DEBUG') && WPSURVEY_DEBUG) {
            error_log('WPSurvey: new_ids=' . json_encode($new_ids));
            error_log('WPSurvey: deleted_ids=' . json_encode($deleted_ids));
        }

        return true;
    }

    // ==================== 问卷状态检查 ====================

    /**
     * 检查问卷是否可以参与
     *
     * @param array $survey 问卷数据
     * @param int|null $user_id 用户ID
     * @param string|null $ip_address IP地址
     * @return array ['allowed' => bool, 'message' => string]
     */
    public function check_survey_access(array $survey, ?int $user_id = null, ?string $ip_address = null): array {
        // 检查问卷状态
        if ($survey['status'] !== 'published') {
            return array(
                'allowed' => false,
                'message' => '此问卷暂未开放',
            );
        }

        // 检查时间限制
        $now = current_time('mysql');
        
        if (!empty($survey['start_time']) && $survey['start_time'] > $now) {
            return array(
                'allowed' => false,
                'message' => '此问卷尚未开始',
            );
        }

        if (!empty($survey['end_time']) && $survey['end_time'] < $now) {
            return array(
                'allowed' => false,
                'message' => '此问卷已结束',
            );
        }

        // 检查是否需要登录
        if ($survey['require_login'] && !$user_id) {
            return array(
                'allowed' => false,
                'message' => '请先登录后再参与问卷',
                'require_login' => true,
            );
        }

        // 检查是否限制每人只能填写一次
        if ($survey['limit_one'] && $user_id) {
            if ($this->db->has_submitted($survey['id'], $user_id, null)) {
                return array(
                    'allowed' => false,
                    'message' => '您已填写过此问卷',
                );
            }
        } elseif ($survey['limit_one'] && $ip_address) {
            if ($this->db->has_submitted($survey['id'], null, $ip_address)) {
                return array(
                    'allowed' => false,
                    'message' => '此IP地址已填写过此问卷',
                );
            }
        }

        return array(
            'allowed' => true,
            'message' => '',
        );
    }

    /**
     * 获取问卷类型的标签
     *
     * @param string $type 类型
     * @return string
     */
    public function get_type_label(string $type): string {
        $types = array(
            'satisfaction' => '满意度调查',
            'collection' => '信息采集',
            'vote' => '投票',
        );
        return $types[$type] ?? $type;
    }

    /**
     * 获取问卷状态的标签
     *
     * @param string $status 状态
     * @return string
     */
    public function get_status_label(string $status): string {
        $statuses = array(
            'draft' => '草稿',
            'published' => '已发布',
            'closed' => '已关闭',
        );
        return $statuses[$status] ?? $status;
    }

    /**
     * 获取题型标签
     *
     * @param string $type 类型
     * @return string
     */
    public function get_question_type_label(string $type): string {
        $types = array(
            'radio' => '单选题',
            'checkbox' => '多选题',
            'select' => '下拉选择',
            'text' => '单行文本',
            'textarea' => '多行文本',
            'rating' => '评分题',
            'matrix' => '矩阵题',
        );
        return $types[$type] ?? $type;
    }
}
