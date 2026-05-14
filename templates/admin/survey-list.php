<?php
/**
 * 问卷列表页面模板
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wpsurvey-admin-wrap">
    <h1>问卷管理</h1>
    
    <?php if (isset($_GET['message'])): ?>
        <div class="wpsurvey-notice success">
            <p>
                <?php
                $messages = array(
                    'deleted' => '问卷已删除',
                    'published' => '问卷已发布',
                    'draft' => '问卷已设为草稿',
                    'duplicated' => '问卷已复制',
                );
                echo esc_html($messages[$_GET['message']] ?? '操作成功');
                ?>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="wpsurvey-survey-list">
        <form method="post">
            <?php wp_nonce_field('wpsurvey_bulk_action'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action">
                        <option value="-1">批量操作</option>
                        <option value="publish">发布</option>
                        <option value="draft">设为草稿</option>
                        <option value="duplicate">复制</option>
                        <option value="delete">删除</option>
                    </select>
                    <input type="submit" class="button action" value="应用">
                </div>
                
                <div class="alignleft actions">
                    <select name="status">
                        <option value="">所有状态</option>
                        <option value="draft" <?php selected(isset($_GET['status']) && $_GET['status'] === 'draft'); ?>>草稿</option>
                        <option value="published" <?php selected(isset($_GET['status']) && $_GET['status'] === 'published'); ?>>已发布</option>
                        <option value="closed" <?php selected(isset($_GET['status']) && $_GET['status'] === 'closed'); ?>>已关闭</option>
                    </select>
                    <input type="submit" class="button" value="筛选">
                </div>
                
                <div class="alignright">
                    <a href="<?php echo admin_url('admin.php?page=wpsurvey-add'); ?>" class="page-title-action">
                        添加问卷
                    </a>
                </div>
            </div>
            
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column column-cb check-column">
                            <input type="checkbox">
                        </th>
                        <th>标题</th>
                        <th>类型</th>
                        <th>状态</th>
                        <th>题目数</th>
                        <th>收集数</th>
                        <th>短代码</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($surveys)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="wpsurvey-empty-state">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    <h3>暂无问卷</h3>
                                    <p>点击上方"添加问卷"按钮创建第一个问卷</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php
                        $db = WP_Survey_DB::get_instance();
                        foreach ($surveys as $survey):
                            $question_count = count($db->get_questions($survey['id']));
                            $response_count = $db->get_responses_count($survey['id']);
                        ?>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" name="surveys[]" value="<?php echo esc_attr($survey['id']); ?>">
                                </th>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=wpsurvey-edit&id=' . $survey['id']); ?>">
                                            <?php echo esc_html($survey['title']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $type_labels = array(
                                        'satisfaction' => '满意度调查',
                                        'collection' => '信息采集',
                                        'vote' => '投票',
                                    );
                                    echo esc_html($type_labels[$survey['type']] ?? $survey['type']);
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = 'draft';
                                    if ($survey['status'] === 'published') {
                                        $status_class = 'published';
                                    } elseif ($survey['status'] === 'closed') {
                                        $status_class = 'closed';
                                    }
                                    ?>
                                    <span class="wpsurvey-status-badge wpsurvey-status-<?php echo esc_attr($status_class); ?>">
                                        <?php
                                        $status_labels = array(
                                            'draft' => '草稿',
                                            'published' => '已发布',
                                            'closed' => '已关闭',
                                        );
                                        echo esc_html($status_labels[$survey['status']] ?? $survey['status']);
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($question_count); ?></td>
                                <td><?php echo esc_html($response_count); ?></td>
                                <td>
                                    <code>[wpsurvey id="<?php echo esc_attr($survey['id']); ?>"]</code>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=wpsurvey-edit&id=' . $survey['id']); ?>">编辑</a>
                                    |
                                    <a href="<?php echo admin_url('admin.php?page=wpsurvey-stats&id=' . $survey['id']); ?>">统计</a>
                                    |
                                    <a href="#" class="wpsurvey-duplicate-survey" data-id="<?php echo esc_attr($survey['id']); ?>">复制</a>
                                    |
                                    <a href="#" class="wpsurvey-delete-survey" data-id="<?php echo esc_attr($survey['id']); ?>">删除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>
