<?php
/**
 * 问卷表单模板
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}

$type_labels = array(
    'radio' => '单选题',
    'checkbox' => '多选题',
    'select' => '下拉选择',
    'text' => '单行文本',
    'textarea' => '多行文本',
    'rating' => '评分题',
    'matrix' => '矩阵题',
);
?>

<div class="wpsurvey-container">
    <div class="wpsurvey-card">
        <!-- 问卷头部 -->
        <div class="wpsurvey-header">
            <h1><?php echo esc_html($survey['title']); ?></h1>
            <?php if (!empty($survey['description'])): ?>
                <p class="description"><?php echo wp_kses_post($survey['description']); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if ($display_mode === 'step'): ?>
            <!-- 进度条（逐步模式） -->
            <div class="wpsurvey-progress">
                <div class="wpsurvey-progress-bar">
                    <div class="wpsurvey-progress-fill" style="width: 0%;"></div>
                </div>
                <div class="wpsurvey-progress-text">1 / <?php echo count($questions); ?></div>
            </div>
        <?php endif; ?>
        
        <!-- 问卷题目 -->
        <div class="wpsurvey-questions">
            <?php if ($display_mode === 'step' && !empty($questions)): ?>
                <!-- 逐步模式：显示开始按钮 -->
                <div class="wpsurvey-start-section">
                    <p style="text-align: center; margin-bottom: 30px;">
                        此问卷共 <strong><?php echo count($questions); ?></strong> 道题目
                    </p>
                    <div style="text-align: center;">
                        <button type="button" class="wpsurvey-btn wpsurvey-btn-primary wpsurvey-btn-start">
                            开始答题
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 操作按钮 -->
        <div class="wpsurvey-buttons" style="display: none;">
            <button type="button" class="wpsurvey-btn wpsurvey-btn-secondary wpsurvey-btn-prev" disabled>
                上一题
            </button>
            <button type="button" class="wpsurvey-btn wpsurvey-btn-primary wpsurvey-btn-next">
                下一题
            </button>
            <button type="button" class="wpsurvey-btn wpsurvey-btn-primary wpsurvey-btn-submit" style="display: none;">
                提交问卷
            </button>
        </div>
    </div>
</div>

<script>
// 后备：确保 wpsurvey 对象存在（防止 wp_localize_script 未生效）
if (typeof wpsurvey === 'undefined') {
    var wpsurvey = {
        ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo wp_create_nonce('wpsurvey_frontend_nonce'); ?>',
        i18n: {
            required: '<?php echo esc_js('此项为必填'); ?>',
            submit_error: '<?php echo esc_js('提交失败'); ?>'
        }
    };
}
// 前端初始化配置
var config = {
    'survey_id': <?php echo (int)$survey['id']; ?>,
    'response_id': <?php echo (int)$response_id; ?>,
    'questions': <?php echo json_encode($questions); ?>,
    'display_mode': '<?php echo esc_js($display_mode); ?>',
    'settings': {
        'primary_color': '<?php echo esc_js($primary_color); ?>',
        'accent_color': '<?php echo esc_js($accent_color); ?>',
        'button_color': '<?php echo esc_js($button_color); ?>',
        'custom_css': '<?php echo esc_js($custom_css); ?>',
    },
};
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        // 确保 Survey 对象有 escapeHtml 方法（修复旧版本兼容性问题）
        if (typeof Survey !== 'undefined' && typeof Survey.escapeHtml === 'undefined') {
            Survey.escapeHtml = function(text) {
                if (typeof Survey.encodeHTML === 'function') {
                    return Survey.encodeHTML(text);
                }
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            };
        }
        WPSurveyInit(config);
    });
}
</script>
