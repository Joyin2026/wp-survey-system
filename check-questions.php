<?php
/**
 * WP Survey System - 检查问卷题目数据
 */
define('WP_USE_THEMES', false);
require('/www/wwwroot/www.yingtux.cn/wp-load.php');

echo "=== WP Survey System - 检查问卷题目 ===\n\n";

global $wpdb;
$prefix = $wpdb->prefix . 'wpsurvey_';

// 获取所有问卷
echo "【问卷列表】\n";
$surveys = $wpdb->get_results("SELECT id, title, status FROM {$prefix}surveys ORDER BY id DESC", ARRAY_A);

if (empty($surveys)) {
    echo "没有找到任何问卷。\n";
    exit;
}

foreach ($surveys as $survey) {
    echo "\n--- 问卷 #{$survey['id']}: {$survey['title']} (状态: {$survey['status']}) ---\n";
    
    // 获取该问卷的题目
    $questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, question_text, question_type, sort_order, settings FROM {$prefix}questions WHERE survey_id = %d ORDER BY sort_order ASC, id ASC",
            $survey['id']
        ),
        ARRAY_A
    );
    
    if (empty($questions)) {
        echo "  (没有题目)\n";
        continue;
    }
    
    echo "  题目数量: " . count($questions) . "\n\n";
    
    foreach ($questions as $idx => $q) {
        echo "  [" . ($idx + 1) . "] ID={$q['id']} | 类型={$q['question_type']} | 排序={$q['sort_order']}\n";
        echo "      题目: " . mb_substr($q['question_text'], 0, 50) . (strlen($q['question_text']) > 50 ? '...' : '') . "\n";
        
        // 解析 settings
        $settings = json_decode($q['settings'], true);
        if (!empty($settings)) {
            echo "      设置: " . json_encode($settings, JSON_UNESCAPED_UNICODE) . "\n";
        }
        
        // 获取选项数量
        $options_count = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$prefix}options WHERE question_id = %d", $q['id'])
        );
        echo "      选项数: $options_count\n";
        
        // 显示选项
        if (in_array($q['question_type'], array('radio', 'checkbox', 'select'))) {
            $options = $wpdb->get_results(
                $wpdb->prepare("SELECT id, option_text, jump_to_question_id FROM {$prefix}options WHERE question_id = %d ORDER BY sort_order ASC", $q['id']),
                ARRAY_A
            );
            foreach ($options as $opt) {
                $jump = $opt['jump_to_question_id'] ? " -> 跳转至 #{$opt['jump_to_question_id']}" : "";
                echo "        - {$opt['option_text']}{$jump}\n";
            }
        }
        
        echo "\n";
    }
}

echo "\n=== 完成 ===\n";
