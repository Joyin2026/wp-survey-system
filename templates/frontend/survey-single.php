<?php
/**
 * 独立问卷页面模板
 * 
 * 通过 ?wpsurvey_id=N 直接访问时使用此模板
 * 包含完整的 HTML 页面结构
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
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($survey['title']); ?> - <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style>
        body { background: #f5f5f5; margin: 0; padding: 20px; }
        .wpsurvey-single-wrap { max-width: 720px; margin: 0 auto; }
        .wpsurvey-back-link { display: inline-block; margin-bottom: 15px; color: #666; text-decoration: none; font-size: 14px; }
        .wpsurvey-back-link:hover { color: #333; }
    </style>
</head>
<body>
    <div class="wpsurvey-single-wrap">
        <a href="<?php echo esc_url(home_url()); ?>" class="wpsurvey-back-link">&larr; 返回首页</a>
        <?php include WPSURVEY_PLUGIN_DIR . 'templates/frontend/survey-form.php'; ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
