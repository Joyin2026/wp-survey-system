<?php
/**
 * 统计概览页面模板（用于选择问卷前的显示）
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wpsurvey-admin-wrap">
    <h1>统计分析</h1>
    
    <?php if (empty($surveys_with_stats)): ?>
        <div class="wpsurvey-empty-state">
            <span class="dashicons dashicons-clipboard"></span>
            <h3>暂无问卷</h3>
            <p>请先创建问卷后再查看统计数据</p>
            <a href="<?php echo admin_url('admin.php?page=wpsurvey-add'); ?>" class="button button-primary">创建问卷</a>
        </div>
    <?php else: ?>
        <p style="margin-bottom: 20px;">选择一个问卷查看详细统计数据：</p>
        
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
                <?php 
                $type_labels = array(
                    'satisfaction' => '满意度调查',
                    'collection' => '信息采集',
                    'vote' => '投票',
                );
                $status_labels = array(
                    'draft' => '草稿',
                    'published' => '已发布',
                    'closed' => '已关闭',
                );
                foreach ($surveys_with_stats as $s): 
                ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=wpsurvey-stats&id=' . $s['id']); ?>">
                                    <?php echo esc_html($s['title']); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html($type_labels[$s['type']] ?? $s['type']); ?></td>
                        <td>
                            <?php
                            $status_class = $s['status'];
                            $status_label = $status_labels[$s['status']] ?? $s['status'];
                            ?>
                            <span class="wpsurvey-status-badge wpsurvey-status-<?php echo esc_attr($status_class); ?>">
                                <?php echo esc_html($status_label); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($s['stats']['total']); ?></td>
                        <td><?php echo esc_html($s['stats']['today']); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=wpsurvey-stats&id=' . $s['id']); ?>">查看统计</a>
                            |
                            <a href="<?php echo admin_url('admin.php?page=wpsurvey-edit&id=' . $s['id']); ?>">编辑</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
