<?php
/**
 * 独立问卷页面模板
 * 
 * 通过 ?wpsurvey_id=N 直接访问时使用此模板
 * 使用主题的页眉和页脚，保持网站视觉一致性
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}

// 变量已由 class-frontend.php handle_survey_url() 注入
// $survey, $questions, $display_mode, $response_id, $primary_color, $accent_color, $button_color, $custom_css

$type_labels = array(
    'radio' => '单选题',
    'checkbox' => '多选题',
    'select' => '下拉选择',
    'text' => '单行文本',
    'textarea' => '多行文本',
    'rating' => '评分题',
    'matrix' => '矩阵题',
);

// 使用主题页眉
get_header();
?>

<div class="wpsurvey-single-wrap" style="max-width: 720px; margin: 30px auto; padding: 0 15px;">
    <?php include WPSURVEY_PLUGIN_DIR . 'templates/frontend/survey-form.php'; ?>
</div>

<?php
// 使用主题页脚
get_footer();
