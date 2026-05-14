<?php
/**
 * 插件诊断脚本
 * 在服务器上运行此脚本来诊断激活问题
 */

// 显示所有错误
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== WP Survey System 诊断工具 ===\n\n";

// 1. 检查 WordPress 环境
echo "1. 检查 WordPress 环境...\n";
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

require_once ABSPATH . 'wp-config.php';
require_once ABSPATH . 'wp-load.php';
echo "   WordPress 已加载\n";

// 2. 检查 PHP 版本
echo "\n2. PHP 版本: " . PHP_VERSION . "\n";
if (version_compare(PHP_VERSION, '8.2', '<')) {
    echo "   警告: 建议 PHP 8.2+\n";
}

// 3. 测试加载各 PHP 文件
echo "\n3. 测试加载 PHP 文件...\n";

$files = array(
    'includes/class-db.php',
    'includes/class-survey.php', 
    'includes/class-settings.php',
    'includes/class-admin.php',
    'includes/class-frontend.php',
    'includes/class-ajax.php',
    'includes/class-stats.php',
);

foreach ($files as $file) {
    $path = dirname(__FILE__) . '/' . $file;
    echo "   测试 {$file}... ";
    
    // 清除之前的错误
    error_reporting(E_ALL);
    
    try {
        include_once $path;
        echo "OK\n";
    } catch (Throwable $e) {
        echo "错误: " . $e->getMessage() . "\n";
    }
}

// 4. 测试数据库表创建
echo "\n4. 测试数据库表创建...\n";
try {
    $db = WP_Survey_DB::get_instance();
    echo "   WP_Survey_DB 实例化成功\n";
    
    // 检查表是否已存在
    global $wpdb;
    $table_name = $wpdb->prefix . 'wpsurveys';
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
    echo "   表 {$table_name}: " . ($exists ? "已存在" : "不存在") . "\n";
    
} catch (Throwable $e) {
    echo "   错误: " . $e->getMessage() . "\n";
    echo "   文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n=== 诊断完成 ===\n";
