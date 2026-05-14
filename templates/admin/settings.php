<?php
/**
 * 全局设置页面模板
 *
 * @package WP_Survey
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wpsurvey-admin-wrap">
    <h1>全局设置</h1>
    
    <?php if (isset($_GET['message']) && $_GET['message'] === 'saved'): ?>
        <div class="wpsurvey-notice success">
            <p>设置已保存</p>
        </div>
    <?php endif; ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('wpsurvey_save_settings'); ?>
        
        <!-- 外观设置 -->
        <div class="wpsurvey-panel">
            <div class="wpsurvey-panel-header">
                <h2>外观设置</h2>
            </div>
            <div class="wpsurvey-panel-body">
                <table class="wpsurvey-settings-group">
                    <tr>
                        <th style="width: 200px;">主色调</th>
                        <td>
                            <div class="wpsurvey-color-picker">
                                <input type="color" value="<?php echo esc_attr($current_settings['primary_color']); ?>">
                                <input type="text" name="settings[primary_color]" value="<?php echo esc_attr($current_settings['primary_color']); ?>">
                            </div>
                            <p class="description">问卷卡片、进度条等元素的默认主色</p>
                        </td>
                    </tr>
                    <tr>
                        <th>辅助色</th>
                        <td>
                            <div class="wpsurvey-color-picker">
                                <input type="color" value="<?php echo esc_attr($current_settings['accent_color']); ?>">
                                <input type="text" name="settings[accent_color]" value="<?php echo esc_attr($current_settings['accent_color']); ?>">
                            </div>
                            <p class="description">进度条渐变等辅助元素颜色</p>
                        </td>
                    </tr>
                    <tr>
                        <th>按钮颜色</th>
                        <td>
                            <div class="wpsurvey-color-picker">
                                <input type="color" value="<?php echo esc_attr($current_settings['button_color']); ?>">
                                <input type="text" name="settings[button_color]" value="<?php echo esc_attr($current_settings['button_color']); ?>">
                            </div>
                            <p class="description">提交按钮等主要操作按钮的颜色</p>
                        </td>
                    </tr>
                    <tr>
                        <th>字体</th>
                        <td>
                            <input type="text" name="settings[font_family]" value="<?php echo esc_attr($current_settings['font_family']); ?>" style="width: 400px;">
                            <p class="description">前端字体，默认为系统默认字体</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- 功能设置 -->
        <div class="wpsurvey-panel">
            <div class="wpsurvey-panel-header">
                <h2>功能设置</h2>
            </div>
            <div class="wpsurvey-panel-body">
                <table class="wpsurvey-settings-group">
                    <tr>
                        <th style="width: 200px;">默认展示模式</th>
                        <td>
                            <select name="settings[default_display_mode]">
                                <option value="step" <?php selected($current_settings['default_display_mode'], 'step'); ?>>
                                    逐步展示（一题一页）
                                </option>
                                <option value="all" <?php selected($current_settings['default_display_mode'], 'all'); ?>>
                                    全部展示
                                </option>
                            </select>
                            <p class="description">新创建的问卷将使用此默认展示模式</p>
                        </td>
                    </tr>
                    <tr>
                        <th>允许访客参与</th>
                        <td>
                            <label>
                                <input type="checkbox" name="settings[allow_guest]" value="1" <?php checked($current_settings['allow_guest'], 1); ?>>
                                允许未登录用户参与问卷
                            </label>
                            <p class="description">启用后，新创建的问卷将默认允许访客参与</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- CDN 设置 -->
        <div class="wpsurvey-panel">
            <div class="wpsurvey-panel-header">
                <h2>CDN 设置</h2>
            </div>
            <div class="wpsurvey-panel-body">
                <table class="wpsurvey-settings-group">
                    <tr>
                        <th style="width: 200px;">Chart.js CDN</th>
                        <td>
                            <select name="settings[chart_cdn]">
                                <?php foreach ($cdn_options as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($current_settings['chart_cdn'], $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">用于加载 Chart.js 图表库的 CDN 服务</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- 自定义 CSS -->
        <div class="wpsurvey-panel">
            <div class="wpsurvey-panel-header">
                <h2>自定义 CSS</h2>
            </div>
            <div class="wpsurvey-panel-body">
                <table class="wpsurvey-settings-group">
                    <tr>
                        <th style="width: 200px;">全局自定义 CSS</th>
                        <td>
                            <textarea name="settings[custom_css]" rows="8" style="width: 100%; font-family: monospace;"><?php echo esc_textarea($current_settings['custom_css']); ?></textarea>
                            <p class="description">将添加到所有问卷页面头部的自定义 CSS 代码</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p>
            <button type="submit" name="wpsurvey_save_settings" class="button button-primary">
                保存设置
            </button>
        </p>
    </form>
    
    <div class="wpsurvey-panel" style="margin-top: 30px;">
        <div class="wpsurvey-panel-header">
            <h2>插件信息</h2>
        </div>
        <div class="wpsurvey-panel-body">
            <table class="wpsurvey-settings-group">
                <tr>
                    <th style="width: 200px;">版本</th>
                    <td><?php echo esc_html(WPSURVEY_VERSION); ?></td>
                </tr>
                <tr>
                    <th>作者</th>
                    <td>瑾煜</td>
                </tr>
                <tr>
                    <th>官方网站</th>
                    <td><a href="https://www.sjinyu.com" target="_blank">www.sjinyu.com</a></td>
                </tr>
            </table>
        </div>
    </div>
</div>
