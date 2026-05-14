<?php
/**
 * 独立页面模板
 * 
 * 当访问 /survey/{id}/ 时使用此模板
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}

// 获取问卷ID
$survey_id = get_query_var('wpsurvey_id');

if (!$survey_id) {
    echo '<div class="wpsurvey-container"><div class="wpsurvey-card"><div class="wpsurvey-message"><span class="dashicons dashicons-warning"></span><p>无效的问卷ID</p></div></div></div>';
    return;
}

// 使用前端类渲染问卷
$frontend = WP_Survey_Frontend::get_instance();
echo $frontend->render_survey((int) $survey_id, true);
