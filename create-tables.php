<?php
/**
 * WP Survey System - 手动创建数据库表
 */
define('WP_USE_THEMES', false);
require('/www/wwwroot/www.yingtux.cn/wp-load.php');

echo "=== WP Survey System 数据库表创建工具 ===\n\n";

// 加载数据库类
require_once('/www/wwwroot/www.yingtux.cn/wp-content/plugins/wp-survey-system/includes/class-db.php');

echo "正在创建数据库表...\n\n";

try {
    $db = WP_Survey_DB::get_instance();
    $result = $db->create_tables();
    
    if ($result) {
        echo "✓ 数据库表创建成功！\n";
        
        // 验证表是否存在
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'wpsurveys',
            $wpdb->prefix . 'wpsurvey_questions',
            $wpdb->prefix . 'wpsurvey_options',
            $wpdb->prefix . 'wpsurvey_responses',
            $wpdb->prefix . 'wpsurvey_response_answers'
        );
        
        echo "\n检查表是否存在：\n";
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            echo ($exists ? "✓" : "✗") . " $table\n";
        }
    } else {
        echo "✗ 数据库表创建失败！\n";
        echo "错误信息：" . $wpdb->last_error . "\n";
    }
} catch (Exception $e) {
    echo "✗ 发生异常：\n";
    echo $e->getMessage() . "\n";
}

echo "\n=== 完成 ===\n";
