<?php
/**
 * 统计分析页面模板
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}

$stats_instance = WP_Survey_Stats::get_instance();
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

<div class="wrap wpsurvey-admin-wrap">
    <h1>
        统计分析
        <?php if ($survey): ?>
            - <?php echo esc_html($survey['title']); ?>
            <a href="<?php echo admin_url('admin.php?page=wpsurvey'); ?>" class="page-title-action">返回列表</a>
        <?php endif; ?>
    </h1>
    
    <?php if ($survey): ?>
        <!-- 统计概览 -->
        <div class="wpsurvey-stats-overview">
            <div class="wpsurvey-stat-card">
                <h3>总提交数</h3>
                <div class="stat-value"><?php echo esc_html($stats['total']); ?></div>
                <div class="stat-label">份答卷</div>
            </div>
            <div class="wpsurvey-stat-card" style="background: linear-gradient(135deg, #00bcd4, #4caf50);">
                <h3>今日新增</h3>
                <div class="stat-value"><?php echo esc_html($stats['today']); ?></div>
                <div class="stat-label">份</div>
            </div>
            <div class="wpsurvey-stat-card" style="background: linear-gradient(135deg, #9c27b0, #e91e63);">
                <h3>平均完成时间</h3>
                <div class="stat-value"><?php echo esc_html(WP_Survey_Stats::format_duration($stats['avg_time'])); ?></div>
                <div class="stat-label">耗时</div>
            </div>
        </div>
        
        <!-- 操作按钮 -->
        <p>
            <a href="<?php echo admin_url('admin.php?page=wpsurvey-stats&id=' . $survey_id . '&export=csv'); ?>" class="button">
                <span class="dashicons dashicons-download"></span> 导出CSV
            </a>
            <a href="<?php echo admin_url('admin.php?page=wpsurvey-edit&id=' . $survey_id); ?>" class="button">
                <span class="dashicons dashicons-edit"></span> 编辑问卷
            </a>
        </p>
        
        <!-- 题目统计 -->
        <div class="wpsurvey-question-stats">
            <?php foreach ($questions as $q_index => $question): ?>
                <?php
                $q_stats = $question['stats'] ?? array();
                $options = $question['options'] ?? array();
                $chart_data = $stats_instance->get_question_chart_data(
                    $question['id'],
                    $question['question_type'],
                    $options
                );
                ?>
                <div class="wpsurvey-panel" style="margin-bottom: 20px;">
                    <div class="wpsurvey-panel-header">
                        <h2>
                            题目 <?php echo ($q_index + 1); ?>: 
                            <?php echo esc_html(wp_trim_words($question['question_text'], 20)); ?>
                            <span class="wpsurvey-question-type"><?php echo esc_html($type_labels[$question['question_type']]); ?></span>
                        </h2>
                    </div>
                    <div class="wpsurvey-panel-body">
                        <?php if (in_array($question['question_type'], array('radio', 'checkbox', 'select'))): ?>
                            <!-- 选择题图表 -->
                            <?php if (!empty($chart_data['labels'])): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 30px;">
                                    <div class="wpsurvey-chart-container">
                                        <canvas id="chart-<?php echo esc_attr($question['id']); ?>" 
                                                data-chart-type="<?php echo esc_attr($chart_data['type']); ?>"
                                                data-labels="<?php echo esc_attr(json_encode($chart_data['labels'])); ?>"
                                                data-data="<?php echo esc_attr(json_encode($chart_data['datasets'][0]['data'])); ?>"
                                                data-colors="<?php echo esc_attr(json_encode($chart_data['datasets'][0]['backgroundColor'])); ?>">
                                        </canvas>
                                    </div>
                                    <div style="flex: 1; min-width: 200px;">
                                        <table class="widefat">
                                            <thead>
                                                <tr>
                                                    <th>选项</th>
                                                    <th>票数</th>
                                                    <th>占比</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($chart_data['labels'] as $i => $label): ?>
                                                    <tr>
                                                        <td><?php echo esc_html($label); ?></td>
                                                        <td><?php echo esc_html($chart_data['datasets'][0]['data'][$i]); ?></td>
                                                        <td><?php echo esc_html($chart_data['datasets'][0]['percentages'][$i] ?? 0); ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p style="color: #999;">暂无数据</p>
                            <?php endif; ?>
                            
                        <?php elseif ($question['question_type'] === 'rating'): ?>
                            <!-- 评分题图表 -->
                            <?php if (!empty($chart_data['labels'])): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 30px;">
                                    <div class="wpsurvey-chart-container">
                                        <canvas id="chart-<?php echo esc_attr($question['id']); ?>" 
                                                data-chart-type="bar"
                                                data-labels="<?php echo esc_attr(json_encode($chart_data['labels'])); ?>"
                                                data-data="<?php echo esc_attr(json_encode($chart_data['datasets'][0]['data'])); ?>"
                                                data-colors="<?php echo esc_attr(json_encode($chart_data['datasets'][0]['backgroundColor'])); ?>">
                                        </canvas>
                                    </div>
                                    <div>
                                        <p><strong>平均评分：</strong><?php echo esc_html($chart_data['average']); ?></p>
                                        <p><strong>总评分数：</strong><?php echo esc_html($chart_data['total']); ?></p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p style="color: #999;">暂无数据</p>
                            <?php endif; ?>
                            
                        <?php elseif ($question['question_type'] === 'matrix'): ?>
<!-- 矩阵题图表 -->
                            <?php if (!empty($chart_data['labels'])): ?>
                                <div class="wpsurvey-chart-container" style="max-width: 700px;">
                                    <canvas id="chart-<?php echo esc_attr($question['id']); ?>" 
                                            data-chart-type="bar"
                                            data-labels="<?php echo esc_attr(json_encode($chart_data['labels'])); ?>"
                                            data-datasets="<?php echo esc_attr(json_encode($chart_data['datasets'])); ?>">
                                    </canvas>
                                </div>
                            <?php else: ?>
                                <p style="color: #999;">暂无数据</p>
                            <?php endif; ?>
                            
                        <?php elseif (in_array($question['question_type'], array('text', 'textarea'))): ?>
                            <!-- 文本题列表 -->
                            <?php if (!empty($q_stats)): ?>
                                <div style="max-height: 300px; overflow-y: auto;">
                                    <ul style="list-style: none; margin: 0; padding: 0;">
                                        <?php foreach (array_slice($q_stats, 0, 50) as $answer): ?>
                                            <?php if ($answer !== null && $answer !== ''): ?>
                                                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">
                                                    <?php echo esc_html($answer); ?>
                                                </li>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (count($q_stats) > 50): ?>
                                        <p style="color: #999; margin-top: 10px;">共 <?php echo count($q_stats); ?> 条回复</p>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <p style="color: #999;">暂无数据</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- 答卷列表 -->
        <div class="wpsurvey-panel">
            <div class="wpsurvey-panel-header">
                <h2>答卷记录</h2>
            </div>
            <div class="wpsurvey-panel-body">
                <?php if (empty($responses)): ?>
                    <p style="color: #999;">暂无答卷记录</p>
                <?php else: ?>
                    <table class="widefat fixed">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户</th>
                                <th>IP地址</th>
                                <th>开始时间</th>
                                <th>提交时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($responses as $response): ?>
                                <tr>
                                    <td><?php echo esc_html($response['id']); ?></td>
                                    <td><?php echo esc_html($response['user_id'] ? '用户 #' . $response['user_id'] : '访客'); ?></td>
                                    <td style="word-break: break-all; max-width: 200px;"><?php echo esc_html($response['ip_display'] ?? $response['ip_address']); ?></td>
                                    <td><?php echo esc_html($response['started_at']); ?></td>
                                    <td><?php echo esc_html($response['submitted_at'] ?: '<em>未提交</em>'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // 初始化 Chart.js 图表
            if (typeof Chart !== 'undefined') {
                $('.wpsurvey-chart-container canvas').each(function() {
                    var canvas = $(this)[0];
                    var ctx = canvas.getContext('2d');
                    
                    var chartType = $(this).data('chart-type');
                    var labels = JSON.parse($(this).data('labels'));
                    
                    var config = {
                        type: chartType === 'pie' ? 'doughnut' : chartType,
                        data: {
                            labels: labels,
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: true,
                            plugins: {
                                legend: {
                                    position: chartType === 'bar' ? 'bottom' : 'right',
                                }
                            }
                        }
                    };
                    
                    if (chartType === 'pie' || chartType === 'doughnut') {
                        var data = $(this).data('data');
                        var colors = $(this).data('colors');
                        config.data.datasets = [{
                            data: typeof data === 'string' ? JSON.parse(data) : data,
                            backgroundColor: typeof colors === 'string' ? JSON.parse(colors) : colors,
                        }];
                    } else if (chartType === 'bar') {
                        var datasets = $(this).data('datasets');
                        if (datasets) {
                            // jQuery .data() 自动解析JSON，需要判断类型
                            config.data.datasets = typeof datasets === 'string' ? JSON.parse(datasets) : datasets;
                        } else {
                            var data = $(this).data('data');
                            var colors = $(this).data('colors');
                            config.data.datasets = [{
                                label: '数量',
                                data: typeof data === 'string' ? JSON.parse(data) : data,
                                backgroundColor: typeof colors === 'string' ? JSON.parse(colors) : colors,
                            }];
                        }
                    }
                    
                    new Chart(ctx, config);
                });
            }
        });
        </script>
        
    <?php else: ?>
        <!-- 所有问卷概览 -->
        <?php if (empty($surveys_with_stats)): ?>
            <p style="color: #999;">暂无问卷数据</p>
        <?php else: ?>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>问卷标题</th>
                        <th>类型</th>
                        <th>状态</th>
                        <th>总提交数</th>
                        <th>今日新增</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($surveys_with_stats as $s): ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wpsurvey-stats&id=' . $s['id']); ?>">
                                    <?php echo esc_html($s['title']); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html(WP_Survey::get_instance()->get_type_label($s['type'])); ?></td>
                            <td><?php echo esc_html(WP_Survey::get_instance()->get_status_label($s['status'])); ?></td>
                            <td><?php echo esc_html($s['stats']['total']); ?></td>
                            <td><?php echo esc_html($s['stats']['today']); ?></td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=wpsurvey-stats&id=' . $s['id']); ?>">查看统计</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>
</div>
