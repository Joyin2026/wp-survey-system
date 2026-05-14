<?php
/**
 * 数据库操作类
 * 
 * 负责数据库表的创建、查询、插入、更新、删除等操作
 * 使用 WordPress 的 $wpdb 类和 prepared statements 确保安全
 *
 * @package WP_Survey
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class WP_Survey_DB {

    /**
     * 数据库实例（单例）
     *
     * @var WP_Survey_DB|null
     */
    private static ?WP_Survey_DB $instance = null;

    /**
     * 数据库前缀
     *
     * @var string
     */
    private string $prefix;

    /**
     * 获取实例
     *
     * @return WP_Survey_DB
     */
    public static function get_instance(): WP_Survey_DB {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        global $wpdb;
        $this->prefix = $wpdb->prefix;
    }

    /**
     * 获取带前缀的表名
     *
     * @param string $table 表名（不含前缀）
     * @return string
     */
    public function get_table_name(string $table): string {
        return $this->prefix . 'wpsurvey_' . $table;
    }

    /**
     * 创建数据库表
     *
     * @return void
     */
    public function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // 问卷表
        $sql_surveys = "CREATE TABLE {$this->get_table_name('surveys')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            type ENUM('satisfaction','collection','vote') DEFAULT 'collection',
            status ENUM('draft','published','closed') DEFAULT 'draft',
            display_mode ENUM('step','all') DEFAULT 'step',
            require_login TINYINT(1) DEFAULT 1,
            limit_one TINYINT(1) DEFAULT 1,
            start_time DATETIME NULL,
            end_time DATETIME NULL,
            primary_color VARCHAR(7) DEFAULT '#1a73e8',
            accent_color VARCHAR(7) DEFAULT '#00bcd4',
            button_color VARCHAR(7) DEFAULT '#0d47a1',
            custom_css TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset_collate;";

        // 题目表（不使用外键，由应用层保证数据完整性）
        $sql_questions = "CREATE TABLE {$this->get_table_name('questions')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            survey_id BIGINT UNSIGNED NOT NULL,
            question_text TEXT NOT NULL,
            question_type ENUM('radio','checkbox','text','textarea','rating','matrix','select') NOT NULL,
            required TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            settings JSON DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        // 选项表
        $sql_options = "CREATE TABLE {$this->get_table_name('options')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            question_id BIGINT UNSIGNED NOT NULL,
            option_text VARCHAR(500) NOT NULL,
            sort_order INT DEFAULT 0,
            jump_to_question_id BIGINT UNSIGNED DEFAULT NULL
        ) $charset_collate;";

        // 答卷表
        $sql_responses = "CREATE TABLE {$this->get_table_name('responses')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            survey_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45),
            started_at DATETIME,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset_collate;";

        // 答案表
        $sql_answers = "CREATE TABLE {$this->get_table_name('answers')} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            response_id BIGINT UNSIGNED NOT NULL,
            question_id BIGINT UNSIGNED NOT NULL,
            answer_value TEXT
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_surveys);
        dbDelta($sql_questions);
        dbDelta($sql_options);
        dbDelta($sql_responses);
        dbDelta($sql_answers);

        // 更新表选项（避免重复执行）
        update_option('wpsurvey_db_version', WPSURVEY_VERSION);
    }

    /**
     * 删除数据库表（卸载时使用）
     *
     * @return void
     */
    public function drop_tables(): void {
        global $wpdb;

        $tables = array('answers', 'responses', 'options', 'questions', 'surveys');
        
        foreach ($tables as $table) {
            $table_name = $this->get_table_name($table);
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }

        delete_option('wpsurvey_db_version');
    }

    // ==================== 问卷（Survey）操作 ====================

    /**
     * 获取所有问卷
     *
     * @param array $args 查询参数
     * @return array
     */
    public function get_surveys(array $args = array()): array {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'type' => '',
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        );

        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $where_sql = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $sql = "SELECT * FROM {$this->get_table_name('surveys')} 
                WHERE {$where_sql} 
                ORDER BY {$orderby} 
                LIMIT %d OFFSET %d";

        $values[] = $args['limit'];
        $values[] = $args['offset'];

        if (!empty($values)) {
            return $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
        }

        return $wpdb->get_results(
            "SELECT * FROM {$this->get_table_name('surveys')} 
             ORDER BY {$orderby} 
             LIMIT {$args['limit']} OFFSET {$args['offset']}",
            ARRAY_A
        );
    }

    /**
     * 获取问卷总数
     *
     * @param array $args 查询参数
     * @return int
     */
    public function get_surveys_count(array $args = array()): int {
        global $wpdb;

        $defaults = array(
            'status' => '',
            'type' => '',
        );

        $args = wp_parse_args($args, $defaults);
        
        $where = array('1=1');
        $values = array();

        if (!empty($args['status'])) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        if (!empty($args['type'])) {
            $where[] = 'type = %s';
            $values[] = $args['type'];
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM {$this->get_table_name('surveys')} WHERE {$where_sql}";

        if (!empty($values)) {
            return (int) $wpdb->get_var($wpdb->prepare($sql, $values));
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * 获取单个问卷
     *
     * @param int $id 问卷ID
     * @return array|null
     */
    public function get_survey(int $id): ?array {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('surveys')} WHERE id = %d",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * 创建问卷
     *
     * @param array $data 问卷数据
     * @return int|false
     */
    public function create_survey(array $data): int|false {
        global $wpdb;

        $defaults = array(
            'title' => '',
            'description' => '',
            'type' => 'collection',
            'status' => 'draft',
            'display_mode' => 'step',
            'require_login' => 1,
            'limit_one' => 1,
            'start_time' => null,
            'end_time' => null,
            'primary_color' => '#1a73e8',
            'accent_color' => '#00bcd4',
            'button_color' => '#0d47a1',
            'custom_css' => '',
        );

        $data = wp_parse_args($data, $defaults);
        $data = array_intersect_key($data, $defaults);

        $result = $wpdb->insert(
            $this->get_table_name('surveys'),
            $data,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 更新问卷
     *
     * @param int $id 问卷ID
     * @param array $data 更新的数据
     * @return bool
     */
    public function update_survey(int $id, array $data): bool {
        global $wpdb;

        // 允许更新的字段
        $allowed_fields = array(
            'title', 'description', 'type', 'status', 'display_mode',
            'require_login', 'limit_one', 'start_time', 'end_time',
            'primary_color', 'accent_color', 'button_color', 'custom_css'
        );

        $data = array_intersect_key($data, array_flip($allowed_fields));

        if (empty($data)) {
            return false;
        }

        $result = $wpdb->update(
            $this->get_table_name('surveys'),
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * 删除问卷
     *
     * @param int $id 问卷ID
     * @return bool
     */
    public function delete_survey(int $id): bool {
        global $wpdb;

        // 手动级联删除关联数据
        $questions = $this->get_questions($id);
        foreach ($questions as $question) {
            $this->delete_question($question['id']);
        }
        
        $wpdb->delete(
            $this->get_table_name('surveys'),
            array('id' => $id),
            array('%d')
        );

        return $wpdb->last_error === '';
    }

    // ==================== 题目（Question）操作 ====================

    /**
     * 获取问卷的所有题目
     *
     * @param int $survey_id 问卷ID
     * @return array
     */
    public function get_questions(int $survey_id): array {
        global $wpdb;

        $questions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('questions')} 
                 WHERE survey_id = %d 
                 ORDER BY sort_order ASC, id ASC",
                $survey_id
            ),
            ARRAY_A
        );

        // 解析 settings JSON 字段
        foreach ($questions as &$question) {
            if (!empty($question['settings'])) {
                $question['settings'] = json_decode($question['settings'], true);
            } else {
                $question['settings'] = array();
            }
        }

        return $questions;
    }

    /**
     * 获取单个题目（包含选项）
     *
     * @param int $question_id 题目ID
     * @return array|null
     */
    public function get_question(int $question_id): ?array {
        global $wpdb;

        $question = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('questions')} WHERE id = %d",
                $question_id
            ),
            ARRAY_A
        );

        if ($question && !empty($question['settings'])) {
            $question['settings'] = json_decode($question['settings'], true);
        }

        return $question ?: null;
    }

    /**
     * 创建题目
     *
     * @param array $data 题目数据
     * @return int|false
     */
    public function create_question(array $data): int|false {
        global $wpdb;

        $defaults = array(
            'survey_id' => 0,
            'question_text' => '',
            'question_type' => 'radio',
            'required' => 0,
            'sort_order' => 0,
            'settings' => null,
        );

        $data = wp_parse_args($data, $defaults);

        // 将 settings 数组转为 JSON
        if (is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }

        $result = $wpdb->insert(
            $this->get_table_name('questions'),
            $data,
            array('%d', '%s', '%s', '%d', '%d', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 更新题目
     *
     * @param int $id 题目ID
     * @param array $data 更新的数据
     * @return bool
     */
    public function update_question(int $id, array $data): bool {
        global $wpdb;

        $allowed_fields = array(
            'question_text', 'question_type', 'required', 'sort_order', 'settings'
        );

        $data = array_intersect_key($data, array_flip($allowed_fields));

        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings'], JSON_UNESCAPED_UNICODE);
        }

        if (empty($data)) {
            return false;
        }

        $result = $wpdb->update(
            $this->get_table_name('questions'),
            $data,
            array('id' => $id),
            null,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * 删除题目
     *
     * @param int $id 题目ID
     * @return bool
     */
    public function delete_question(int $id): bool {
        global $wpdb;

        // 手动级联删除关联数据（删除选项）
        $wpdb->delete(
            $this->get_table_name('options'),
            array('question_id' => $id),
            array('%d')
        );
        
        $wpdb->delete(
            $this->get_table_name('questions'),
            array('id' => $id),
            array('%d')
        );

        return $wpdb->last_error === '';
    }

    /**
     * 批量更新题目排序
     *
     * @param array $orders 格式：array(question_id => sort_order)
     * @return bool
     */
    public function update_questions_order(array $orders): bool {
        global $wpdb;

        foreach ($orders as $question_id => $sort_order) {
            $wpdb->update(
                $this->get_table_name('questions'),
                array('sort_order' => (int) $sort_order),
                array('id' => (int) $question_id),
                array('%d'),
                array('%d')
            );
        }

        return true;
    }

    // ==================== 选项（Option）操作 ====================

    /**
     * 获取题目的所有选项
     *
     * @param int $question_id 题目ID
     * @return array
     */
    public function get_options(int $question_id): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('options')} 
                 WHERE question_id = %d 
                 ORDER BY sort_order ASC, id ASC",
                $question_id
            ),
            ARRAY_A
        );
    }

    /**
     * 获取选项（带问卷内所有题目映射，用于跳转选择）
     *
     * @param int $option_id 选项ID
     * @return array|null
     */
    public function get_option(int $option_id): ?array {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('options')} WHERE id = %d",
                $option_id
            ),
            ARRAY_A
        );
    }

    /**
     * 创建选项
     *
     * @param array $data 选项数据
     * @return int|false
     */
    public function create_option(array $data): int|false {
        global $wpdb;

        $defaults = array(
            'question_id' => 0,
            'option_text' => '',
            'sort_order' => 0,
            'jump_to_question_id' => null,
        );

        $data = wp_parse_args($data, $defaults);

        // 修复: jump_to_question_id 为 null 时不能使用 %d 格式
        $formats = array('%d', '%s', '%d');
        if ($data['jump_to_question_id'] === null) {
            $formats[] = null;  // WordPress 会自动处理 NULL
        } else {
            $formats[] = '%d';
        }



        $result = $wpdb->insert(
            $this->get_table_name('options'),
            $data,
            $formats
        );



        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 更新选项
     *
     * @param int $id 选项ID
     * @param array $data 更新的数据
     * @return bool
     */
    public function update_option(int $id, array $data): bool {
        global $wpdb;

        $allowed_fields = array('option_text', 'sort_order', 'jump_to_question_id');
        $data = array_intersect_key($data, array_flip($allowed_fields));

        if (empty($data)) {
            return false;
        }

        // 处理跳转目标：null 表示默认顺序，0 表示结束
        if (isset($data['jump_to_question_id']) && $data['jump_to_question_id'] === '') {
            $data['jump_to_question_id'] = null;
        }

        // 修复: 显式构建 format 数组，正确处理 NULL 值
        $formats = array();
        foreach ($data as $key => $value) {
            if ($value === null) {
                $formats[] = null;  // WordPress 会将 null 作为 SQL NULL 处理
            } elseif (in_array($key, array('sort_order', 'jump_to_question_id'))) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        $result = $wpdb->update(
            $this->get_table_name('options'),
            $data,
            array('id' => $id),
            $formats,
            array('%d')
        );

        return $result !== false;
    }

    /**
     * 删除选项
     *
     * @param int $id 选项ID
     * @return bool
     */
    public function delete_option(int $id): bool {
        global $wpdb;

        $result = $wpdb->delete(
            $this->get_table_name('options'),
            array('id' => $id),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * 批量创建选项
     *
     * @param int $question_id 题目ID
     * @param array $options 选项文本数组
     * @return bool
     */
    public function create_options_batch(int $question_id, array $options): bool {
        global $wpdb;

        $sort_order = 0;
        foreach ($options as $option_text) {
            $option_text = trim($option_text);
            if (empty($option_text)) {
                continue;
            }

            $this->create_option(array(
                'question_id' => $question_id,
                'option_text' => $option_text,
                'sort_order' => $sort_order++,
            ));
        }

        return true;
    }

    // ==================== 答卷（Response）操作 ====================

    /**
     * 获取问卷的答卷列表
     *
     * @param int $survey_id 问卷ID
     * @param array $args 查询参数
     * @return array
     */
    public function get_responses(int $survey_id, array $args = array()): array {
        global $wpdb;

        $defaults = array(
            'orderby' => 'submitted_at',
            'order' => 'DESC',
            'limit' => 20,
            'offset' => 0,
        );

        $args = wp_parse_args($args, $defaults);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('responses')} 
                 WHERE survey_id = %d 
                 ORDER BY {$orderby} 
                 LIMIT %d OFFSET %d",
                $survey_id,
                $args['limit'],
                $args['offset']
            ),
            ARRAY_A
        );
    }

    /**
     * 获取答卷总数
     *
     * @param int $survey_id 问卷ID
     * @return int
     */
    public function get_responses_count(int $survey_id): int {
        global $wpdb;

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->get_table_name('responses')} WHERE survey_id = %d",
                $survey_id
            )
        );
    }

    /**
     * 获取单个答卷
     *
     * @param int $response_id 答卷ID
     * @return array|null
     */
    public function get_response(int $response_id): ?array {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('responses')} WHERE id = %d",
                $response_id
            ),
            ARRAY_A
        );
    }

    /**
     * 创建答卷
     *
     * @param array $data 答卷数据
     * @return int|false
     */
    public function create_response(array $data): int|false {
        global $wpdb;

        $defaults = array(
            'survey_id' => 0,
            'user_id' => null,
            'ip_address' => '',
            'started_at' => current_time('mysql'),
            'submitted_at' => null,
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $this->get_table_name('responses'),
            $data,
            array('%d', '%d', '%s', '%s', '%s')
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * 更新答卷状态（提交）
     *
     * @param int $response_id 答卷ID
     * @return bool
     */
    public function submit_response(int $response_id): bool {
        global $wpdb;

        $result = $wpdb->update(
            $this->get_table_name('responses'),
            array('submitted_at' => current_time('mysql')),
            array('id' => $response_id),
            array('%s'),
            array('%d')
        );

        return $result !== false;
    }

    /**
     * 检查用户是否已提交过问卷
     *
     * @param int $survey_id 问卷ID
     * @param int|null $user_id 用户ID
     * @param string|null $ip_address IP地址
     * @return bool
     */
    public function has_submitted(int $survey_id, ?int $user_id = null, ?string $ip_address = null): bool {
        global $wpdb;

        $where = 'survey_id = %d';
        $values = array($survey_id);

        if ($user_id && $user_id > 0) {
            $where .= ' AND user_id = %d';
            $values[] = $user_id;
        }

        if ($ip_address) {
            $where .= ' AND ip_address = %s';
            $values[] = $ip_address;
        }

        $sql = "SELECT COUNT(*) FROM {$this->get_table_name('responses')} WHERE {$where}";

        return (int) $wpdb->get_var($wpdb->prepare($sql, $values)) > 0;
    }

    /**
     * 获取用户已开始的答卷（未完成）
     *
     * @param int $survey_id 问卷ID
     * @param int|null $user_id 用户ID
     * @return array|null
     */
    public function get_incomplete_response(int $survey_id, ?int $user_id): ?array {
        global $wpdb;

        $where = 'survey_id = %d AND submitted_at IS NULL';
        $values = array($survey_id);

        if ($user_id && $user_id > 0) {
            $where .= ' AND user_id = %d';
            $values[] = $user_id;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->get_table_name('responses')} WHERE {$where} ORDER BY id DESC LIMIT 1",
                $values
            ),
            ARRAY_A
        );
    }

    // ==================== 答案（Answer）操作 ====================

    /**
     * 获取答卷的所有答案
     *
     * @param int $response_id 答卷ID
     * @return array
     */
    public function get_answers(int $response_id): array {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT a.*, q.question_type, q.question_text 
                 FROM {$this->get_table_name('answers')} a
                 LEFT JOIN {$this->get_table_name('questions')} q ON a.question_id = q.id
                 WHERE a.response_id = %d",
                $response_id
            ),
            ARRAY_A
        );
    }

    /**
     * 保存答案
     *
     * @param int $response_id 答卷ID
     * @param int $question_id 题目ID
     * @param mixed $answer_value 答案值
     * @return int|false
     */
    public function save_answer(int $response_id, int $question_id, $answer_value): int|false {
        global $wpdb;

        // 处理答案值：数组转为 JSON
        if (is_array($answer_value)) {
            $answer_value = json_encode($answer_value, JSON_UNESCAPED_UNICODE);
        }

        // Upsert：同一 response_id + question_id 只保留一条记录
        $existing_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->get_table_name('answers')} WHERE response_id = %d AND question_id = %d LIMIT 1",
                $response_id,
                $question_id
            )
        );

        if ($existing_id > 0) {
            // 更新已有记录
            $result = $wpdb->update(
                $this->get_table_name('answers'),
                array('answer_value' => $answer_value),
                array('id' => $existing_id),
                array('%s'),
                array('%d')
            );
            return $result !== false ? $existing_id : false;
        } else {
            // 插入新记录
            $result = $wpdb->insert(
                $this->get_table_name('answers'),
                array(
                    'response_id' => $response_id,
                    'question_id' => $question_id,
                    'answer_value' => $answer_value,
                ),
                array('%d', '%d', '%s')
            );
            return $result ? $wpdb->insert_id : false;
        }
    }

    /**
     * 批量保存答案
     *
     * @param int $response_id 答卷ID
     * @param array $answers 答案数组，格式：array(question_id => answer_value)
     * @return bool
     */
    public function save_answers_batch(int $response_id, array $answers): bool {
        foreach ($answers as $question_id => $answer_value) {
            $this->save_answer($response_id, (int) $question_id, $answer_value);
        }
        return true;
    }

    // ==================== 统计（Statistics）操作 ====================

    /**
     * 获取问卷统计概览
     *
     * @param int $survey_id 问卷ID
     * @return array
     */
    public function get_survey_stats(int $survey_id): array {
        global $wpdb;

        // 总提交数
        $total = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->get_table_name('responses')} 
                 WHERE survey_id = %d AND submitted_at IS NOT NULL",
                $survey_id
            )
        );

        // 今日新增
        $today = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->get_table_name('responses')} 
                 WHERE survey_id = %d AND DATE(submitted_at) = CURDATE()",
                $survey_id
            )
        );

        // 计算平均完成时间
        $avg_time = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(TIMESTAMPDIFF(SECOND, started_at, submitted_at)) 
                 FROM {$this->get_table_name('responses')} 
                 WHERE survey_id = %d AND submitted_at IS NOT NULL",
                $survey_id
            )
        );

        return array(
            'total' => $total,
            'today' => $today,
            'avg_time' => $avg_time ? round($avg_time) : 0,
        );
    }

    /**
     * 获取题目答案统计
     *
     * @param int $question_id 题目ID
     * @param string $question_type 题目类型
     * @return array
     */
    public function get_question_stats(int $question_id, string $question_type): array {
        global $wpdb;

        $answers = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT answer_value FROM {$this->get_table_name('answers')} WHERE question_id = %d",
                $question_id
            )
        );

        $stats = array();

        switch ($question_type) {
            case 'radio':
            case 'select':
                // 单选题统计
                // 注意：answer_value 可能是 "0"，不能用 !empty() 判断
                foreach ($answers as $answer) {
                    if ($answer !== null && $answer !== '') {
                        $stats[$answer] = isset($stats[$answer]) ? $stats[$answer] + 1 : 1;
                    }
                }
                break;

            case 'checkbox':
                // 多选题统计（答案可能是JSON数组）
                foreach ($answers as $answer) {
                    $values = json_decode($answer, true);
                    if (is_array($values)) {
                        foreach ($values as $v) {
                            $stats[$v] = isset($stats[$v]) ? $stats[$v] + 1 : 1;
                        }
                    } elseif ($answer !== null && $answer !== '') {
                        $stats[$answer] = isset($stats[$answer]) ? $stats[$answer] + 1 : 1;
                    }
                }
                break;

            case 'rating':
                // 评分题统计
                $ratings = array();
                foreach ($answers as $answer) {
                    if ($answer !== null && $answer !== '') {
                        $rating = (int) $answer;
                        if ($rating > 0) {
                            $ratings[] = $rating;
                        }
                    }
                }
                if (!empty($ratings)) {
                    $stats['ratings'] = $ratings;
                    $stats['average'] = round(array_sum($ratings) / count($ratings), 1);
                    $stats['max'] = max($ratings);
                    $stats['min'] = min($ratings);
                }
                break;

            case 'matrix':
                // 矩阵题统计
                foreach ($answers as $answer) {
                    $values = json_decode($answer, true);
                    if (is_array($values)) {
                        foreach ($values as $row => $col) {
                            if (!isset($stats[$row])) {
                                $stats[$row] = array();
                            }
                            $stats[$row][$col] = isset($stats[$row][$col]) ? $stats[$row][$col] + 1 : 1;
                        }
                    }
                }
                break;

            case 'text':
            case 'textarea':
                // 文本题：返回原始答案列表
                $stats = $answers;
                break;
        }

        return $stats;
    }

    /**
     * 导出答卷数据为CSV
     *
     * @param int $survey_id 问卷ID
     * @return array 包含 'filename' 和 'data'
     */
    public function export_csv(int $survey_id): array {
        global $wpdb;

        // 获取问卷信息
        $survey = $this->get_survey($survey_id);
        if (!$survey) {
            return array();
        }

        // 获取所有题目
        $questions = $this->get_questions($survey_id);

        // 构建表头
        $headers = array('答卷ID', '用户ID', 'IP地址', '开始时间', '提交时间');
        foreach ($questions as $q) {
            $headers[] = $q['question_text'];
        }

        // 获取所有答卷
        $responses = $this->get_responses($survey_id, array('limit' => 10000, 'offset' => 0));

        // 构建数据行
        $rows = array();
        foreach ($responses as $response) {
            $row = array(
                $response['id'],
                $response['user_id'] ?: '',
                $response['ip_address'],
                $response['started_at'],
                $response['submitted_at'] ?: '',
            );

            // 获取该答卷的所有答案
            $answers = $this->get_answers($response['id']);
            $answers_map = array();
            foreach ($answers as $answer) {
                $value = $answer['answer_value'];
                // 如果是多选数组，转换为可读格式
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = implode(', ', $decoded);
                }
                $answers_map[$answer['question_id']] = $value;
            }

            // 按题目顺序添加答案
            foreach ($questions as $q) {
                $row[] = $answers_map[$q['id']] ?? '';
            }

            $rows[] = $row;
        }

        return array(
            'filename' => sanitize_file_name($survey['title']) . '_responses_' . date('Ymd') . '.csv',
            'headers' => $headers,
            'rows' => $rows,
        );
    }
}
