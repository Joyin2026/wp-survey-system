<?php
/**
 * 插件卸载处理
 * 
 * 当管理员在 WordPress 后台删除此插件时，
 * 此文件中的代码将被执行，用于清理插件创建的所有数据和设置。
 *
 * @package WP_Survey
 */

// 如果不是由 WordPress 调用，则退出
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 删除插件选项
delete_option('wpsurvey_settings');
delete_option('wpsurvey_version');
delete_option('wpsurvey_activated');
delete_option('wpsurvey_db_version');

// 删除数据库表
global $wpdb;

$tables = array(
    $wpdb->prefix . 'wpsurvey_answers',
    $wpdb->prefix . 'wpsurvey_responses',
    $wpdb->prefix . 'wpsurvey_options',
    $wpdb->prefix . 'wpsurvey_questions',
    $wpdb->prefix . 'wpsurvey_surveys',
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// 清除任何缓存（如果有）
// wp_cache_flush();
