<?php
/**
 * 问卷编辑页面模板
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_new = empty($survey['id']);
$type_options = array(
    'satisfaction' => '满意度调查',
    'collection' => '信息采集',
    'vote' => '投票',
);
$status_options = array(
    'draft' => '草稿',
    'published' => '已发布',
    'closed' => '已关闭',
);
?>

<div class="wrap wpsurvey-admin-wrap">
    <h1><?php echo $is_new ? '添加问卷' : '编辑问卷'; ?></h1>
    
    <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
        <div class="wpsurvey-notice success">
            <p>问卷已保存</p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('wpsurvey_save_survey'); ?>
        <input type="hidden" name="survey_id" id="survey_id" value="<?php echo esc_attr($survey['id']); ?>">
        
        <div class="wpsurvey-edit-wrap">
            <!-- 主内容区 -->
            <div class="wpsurvey-edit-main">
                <!-- 问卷基础信息 -->
                <div class="wpsurvey-panel">
                    <div class="wpsurvey-panel-header">
                        <h2>问卷信息</h2>
                    </div>
                    <div class="wpsurvey-panel-body">
                        <table class="wpsurvey-form-table">
                            <tr>
                                <th>问卷标题 <span class="required">*</span></th>
                                <td>
                                    <input type="text" name="title" value="<?php echo esc_attr($survey['title']); ?>" 
                                           required placeholder="请输入问卷标题" style="width: 100%; max-width: 500px;">
                                </td>
                            </tr>
                            <tr>
                                <th>问卷描述</th>
                                <td>
                                    <?php
                                    wp_editor(
                                        $survey['description'],
                                        'description',
                                        array(
                                            'textarea_name' => 'description',
                                            'textarea_rows' => 4,
                                            'media_buttons' => false,
                                        )
                                    );
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>问卷类型</th>
                                <td>
                                    <select name="type" style="width: 200px;">
                                        <?php foreach ($type_options as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($survey['type'], $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>状态</th>
                                <td>
                                    <select name="status" style="width: 200px;">
                                        <?php foreach ($status_options as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>" <?php selected($survey['status'], $value); ?>>
                                                <?php echo esc_html($label); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- 题目构建器 -->
                <div class="wpsurvey-panel">
                    <div class="wpsurvey-panel-header">
                        <h2>题目设置</h2>
                        <div>
                            <select id="wpsurvey-new-question-type">
                                <option value="radio">单选题</option>
                                <option value="checkbox">多选题</option>
                                <option value="select">下拉选择</option>
                                <option value="text">单行文本</option>
                                <option value="textarea">多行文本</option>
                                <option value="rating">评分题</option>
                                <option value="matrix">矩阵题</option>
                            </select>
                            <button type="button" class="button wpsurvey-btn-add-question">
                                <span class="dashicons dashicons-plus"></span> 添加题目
                            </button>
                        </div>
                    </div>
                    <div class="wpsurvey-panel-body">
                        <div class="wpsurvey-questions-builder <?php echo !empty($survey['questions']) ? 'has-questions' : ''; ?>">
                            <?php if (!empty($survey['questions'])): ?>
                                <?php foreach ($survey['questions'] as $index => $question): ?>
                                    <?php
                                    // 构建选项 HTML
                                    $options_html = '';
                                    if (in_array($question['question_type'], array('radio', 'checkbox', 'select'))) {
                                        $options_html .= '<tr class="wpsurvey-options-row"><th>选项 <span class="required">*</span></th><td>';
                                        $options_html .= '<div class="wpsurvey-options-list">';
                                        
                                        $q_index = $index;
                                        foreach ($question['options'] as $opt_idx => $option) {
                                            $options_html .= '<div class="wpsurvey-option-item">';
                                            $options_html .= '<input type="hidden" name="questions[' . $q_index . '][option_ids][]" value="' . esc_attr($option['id']) . '">';
                                            $options_html .= '<input type="text" name="questions[' . $q_index . '][options][]" value="' . esc_attr($option['option_text']) . '" placeholder="选项内容">';
                                            $options_html .= '<select name="questions[' . $q_index . '][jump_to][]" class="wpsurvey-jump-select" title="选择此项后跳转到">';
                                            $options_html .= '<option value="">默认顺序</option>';
                                            $options_html .= '<option value="0"' . ($option['jump_to_question_id'] === 0 ? ' selected' : '') . '>结束问卷</option>';
                                            
                                            // 其他题目选项将通过JS加载
                                            foreach ($survey['questions'] as $target_q) {
                                                if ($target_q['id'] !== $question['id']) {
                                                    $q_text = wp_trim_words($target_q['question_text'], 10);
                                                    $options_html .= '<option value="' . $target_q['id'] . '"' . ($option['jump_to_question_id'] == $target_q['id'] ? ' selected' : '') . '>' . esc_html($q_text) . '</option>';
                                                }
                                            }
                                            
                                            $options_html .= '</select>';
                                            $options_html .= '<button type="button" class="wpsurvey-btn-delete-option"><span class="dashicons dashicons-no"></span></button>';
                                            $options_html .= '</div>';
                                        }
                                        
                                        $options_html .= '</div>';
                                        $options_html .= '<button type="button" class="wpsurvey-btn-add-option button"><span class="dashicons dashicons-plus"></span> 添加选项</button>';
                                        $options_html .= '</td></tr>';
                                    }
                                    
                                    // 评分题设置
                                    $rating_html = '';
                                    if ($question['question_type'] === 'rating') {
                                        $max_score = $question['settings']['max_score'] ?? 5;
                                        $rating_html .= '<tr class="wpsurvey-rating-row"><th>最高分值</th><td>';
                                        $rating_html .= '<select name="questions[' . $index . '][max_score]">';
                                        $rating_html .= '<option value="5"' . ($max_score == 5 ? ' selected' : '') . '>5星 / 5分</option>';
                                        $rating_html .= '<option value="10"' . ($max_score == 10 ? ' selected' : '') . '>10星 / 10分</option>';
                                        $rating_html .= '</select>';
                                        $rating_html .= '</td></tr>';
                                    }
                                    
                                    // 矩阵题设置
                                    $matrix_html = '';
                                    if ($question['question_type'] === 'matrix') {
                                        $rows = $question['settings']['rows'] ?? array();
                                        $columns = $question['settings']['columns'] ?? array();
                                        $matrix_html .= '<tr class="wpsurvey-matrix-row"><th>矩阵设置</th><td>';
                                        $matrix_html .= '<div class="wpsurvey-matrix-rows"><label>行标题（每行一个）：</label>';
                                        $matrix_html .= '<textarea name="questions[' . $index . '][matrix_rows]" rows="3">' . esc_textarea(implode("\n", $rows)) . '</textarea></div>';
                                        $matrix_html .= '<div class="wpsurvey-matrix-columns"><label>列标题（每列一个）：</label>';
                                        $matrix_html .= '<textarea name="questions[' . $index . '][matrix_columns]" rows="3">' . esc_textarea(implode("\n", $columns)) . '</textarea></div>';
                                        $matrix_html .= '</td></tr>';
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
                                    <div class="wpsurvey-question-item" data-index="<?php echo esc_attr($index); ?>">
                                        <div class="wpsurvey-question-header">
                                            <span class="wpsurvey-question-number">题目 <?php echo ($index + 1); ?></span>
                                            <span class="wpsurvey-question-type"><?php echo esc_html($type_labels[$question['question_type']]); ?></span>
                                            <button type="button" class="wpsurvey-btn-delete-question">
                                                <span class="dashicons dashicons-trash"></span>
                                            </button>
                                        </div>
                                        <div class="wpsurvey-question-body">
                                            <table class="wpsurvey-form-table">
                                                <tr>
                                                    <th>题目内容 <span class="required">*</span></th>
                                                    <td>
                                                        <textarea name="questions[<?php echo esc_attr($index); ?>][text]" rows="2" required><?php echo esc_textarea($question['question_text']); ?></textarea>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>题型</th>
                                                    <td>
                                                        <select name="questions[<?php echo esc_attr($index); ?>][type]" class="wpsurvey-question-type-select">
                                                            <?php foreach ($type_labels as $type => $label): ?>
                                                                <option value="<?php echo esc_attr($type); ?>" <?php selected($question['question_type'], $type); ?>><?php echo esc_html($label); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th>必填</th>
                                                    <td>
                                                        <label>
                                                            <input type="checkbox" name="questions[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked($question['required'], 1); ?>>
                                                            必填
                                                        </label>
                                                    </td>
                                                </tr>
                                                <?php echo $options_html; ?>
                                                <?php echo $rating_html; ?>
                                                <?php echo $matrix_html; ?>
                                            </table>
                                            <input type="hidden" name="questions[<?php echo esc_attr($index); ?>][id]" value="<?php echo esc_attr($question['id']); ?>">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="wpsurvey-empty-state" style="padding: 40px;">
                                    <span class="dashicons dashicons-plus-alt2" style="font-size: 48px;"></span>
                                    <h3>还没有题目</h3>
                                    <p>选择题型后点击"添加题目"按钮来创建题目</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 悬浮添加题目按钮（固定在主内容区右下角） -->
                        <button type="button" class="wpsurvey-floating-add-btn" id="wpsurvey-floating-add-btn" style="display: none;">
                            <span class="dashicons dashicons-plus"></span>
                            添加题目
                        </button>
                    </div>
                </div>
                
                <!-- 主内容区结束 -->
            </div>
            
            <!-- 侧边栏 -->
            <div class="wpsurvey-edit-sidebar">
                <!-- 保存按钮 -->
                <div class="wpsurvey-panel">
                    <div class="wpsurvey-panel-body">
                        <p>
                            <button type="submit" name="wpsurvey_save" class="button button-primary button-hero" style="width: 100%;">
                                保存问卷
                            </button>
                        </p>
                    </div>
                </div>
                
                <!-- 显示设置 -->
                <div class="wpsurvey-panel">
                    <div class="wpsurvey-panel-header">
                        <h2>显示设置</h2>
                    </div>
                    <div class="wpsurvey-panel-body">
                        <table class="wpsurvey-settings-group">
                            <tr>
                                <th>展示模式</th>
                                <td>
                                    <select name="display_mode">
                                        <option value="step" <?php selected($survey['display_mode'], 'step'); ?>>逐步展示（一题一页）</option>
                                        <option value="all" <?php selected($survey['display_mode'], 'all'); ?>>全部展示</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- 参与限制 -->
                <div class="wpsurvey-panel">
                    <div class="wpsurvey-panel-header">
                        <h2>参与限制</h2>
                    </div>
                    <div class="wpsurvey-panel-body">
                        <table class="wpsurvey-settings-group">
                            <tr>
                                <th>要求登录</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="require_login" value="1" <?php checked($survey['require_login'], 1); ?>>
                                        仅登录用户可参与
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>限制提交</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="limit_one" value="1" <?php checked($survey['limit_one'], 1); ?>>
                                        每人只能提交一次
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th>开始时间</th>
                                <td>
                                    <input type="datetime-local" name="start_time" value="<?php echo esc_attr($survey['start_time']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th>结束时间</th>
                                <td>
                                    <input type="datetime-local" name="end_time" value="<?php echo esc_attr($survey['end_time']); ?>">
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- 外观设置 -->
                <div class="wpsurvey-panel">
                    <div class="wpsurvey-panel-header">
                        <h2>外观设置</h2>
                    </div>
                    <div class="wpsurvey-panel-body">
                        <table class="wpsurvey-settings-group">
                            <tr>
                                <th>主色调</th>
                                <td>
                                    <div class="wpsurvey-color-picker">
                                        <input type="color" value="<?php echo esc_attr($survey['primary_color']); ?>">
                                        <input type="text" name="primary_color" value="<?php echo esc_attr($survey['primary_color']); ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>辅助色</th>
                                <td>
                                    <div class="wpsurvey-color-picker">
                                        <input type="color" value="<?php echo esc_attr($survey['accent_color']); ?>">
                                        <input type="text" name="accent_color" value="<?php echo esc_attr($survey['accent_color']); ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>按钮色</th>
                                <td>
                                    <div class="wpsurvey-color-picker">
                                        <input type="color" value="<?php echo esc_attr($survey['button_color']); ?>">
                                        <input type="text" name="button_color" value="<?php echo esc_attr($survey['button_color']); ?>">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th>自定义CSS</th>
                                <td>
                                    <textarea name="custom_css" rows="4" placeholder="可选的自定义CSS"><?php echo esc_textarea($survey['custom_css']); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <?php if (!$is_new): ?>
                <!-- 嵌入代码 -->
                <div class="wpsurvey-panel">
                    <div class="wpsurvey-panel-header">
                        <h2>嵌入方式</h2>
                    </div>
                    <div class="wpsurvey-panel-body">
                        <p><strong>短代码：</strong></p>
                        <div class="wpsurvey-shortcode-box">
                            <code><?php echo esc_html('[wpsurvey id="' . $survey['id'] . '"]'); ?></code>
                            <button type="button" class="wpsurvey-copy-btn button button-small">
                                <span class="dashicons dashicons-clipboard" style="font-size: 14px;"></span> 复制
                            </button>
                        </div>

                        <p style="margin-top: 15px;"><strong>独立页面：</strong></p>
                        <div class="wpsurvey-shortcode-box">
                            <code class="wpsurvey-url-box"><?php echo esc_html(WP_Survey_Frontend::get_survey_url($survey['id'])); ?></code>
                            <button type="button" class="wpsurvey-copy-btn button button-small">
                                <span class="dashicons dashicons-clipboard" style="font-size: 14px;"></span> 复制
                            </button>
                        </div>
                        <p class="wpsurvey-copy-hint" style="margin-top: 8px; font-size: 12px; color: #666;">
                            <span class="dashicons dashicons-info" style="font-size: 12px;"></span> 点击上方代码或按钮均可复制
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>
