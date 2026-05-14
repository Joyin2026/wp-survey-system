<?php
/**
 * WP Survey System - 直接创建数据库表（绕过类）
 */
define('WP_USE_THEMES', false);
require('/www/wwwroot/www.yingtux.cn/wp-load.php');

echo "=== WP Survey System 直接创建数据库表 ===\n\n";

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$prefix = $wpdb->prefix . 'wpsurvey_';

echo "表前缀: $prefix\n\n";

// 加载 dbDelta 函数
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// 创建问卷表
echo "1. 创建问卷表...\n";
$sql_surveys = "CREATE TABLE {$prefix}surveys (
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

$result = dbDelta($sql_surveys);
echo "结果: " . (empty($result) ? "无变化" : json_encode($result)) . "\n\n";

// 创建题目表
echo "2. 创建题目表...\n";
$sql_questions = "CREATE TABLE {$prefix}questions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id BIGINT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('radio','checkbox','text','textarea','rating','matrix','select') NOT NULL,
    required TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    settings JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;";

$result = dbDelta($sql_questions);
echo "结果: " . (empty($result) ? "无变化" : json_encode($result)) . "\n\n";

// 创建选项表
echo "3. 创建选项表...\n";
$sql_options = "CREATE TABLE {$prefix}options (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id BIGINT UNSIGNED NOT NULL,
    option_text VARCHAR(500) NOT NULL,
    sort_order INT DEFAULT 0,
    jump_to_question_id BIGINT UNSIGNED DEFAULT NULL
) $charset_collate;";

$result = dbDelta($sql_options);
echo "结果: " . (empty($result) ? "无变化" : json_encode($result)) . "\n\n";

// 创建答卷表
echo "4. 创建答卷表...\n";
$sql_responses = "CREATE TABLE {$prefix}responses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    survey_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45),
    started_at DATETIME,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
) $charset_collate;";

$result = dbDelta($sql_responses);
echo "结果: " . (empty($result) ? "无变化" : json_encode($result)) . "\n\n";

// 创建答案表
echo "5. 创建答案表...\n";
$sql_answers = "CREATE TABLE {$prefix}answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    response_id BIGINT UNSIGNED NOT NULL,
    question_id BIGINT UNSIGNED NOT NULL,
    answer_value TEXT
) $charset_collate;";

$result = dbDelta($sql_answers);
echo "结果: " . (empty($result) ? "无变化" : json_encode($result)) . "\n\n";

// 验证所有表
echo "=== 验证表是否存在 ===\n";
$tables = array('surveys', 'questions', 'options', 'responses', 'answers');
foreach ($tables as $table) {
    $table_name = $prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    echo ($exists ? "✓" : "✗") . " $table_name\n";
}

echo "\n=== 完成 ===\n";
