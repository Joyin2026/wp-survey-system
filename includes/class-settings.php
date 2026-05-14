<?php
/**
 * 全局设置类
 * 
 * 负责插件全局设置的获取、保存
 *
 * @package WP_Survey
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class WP_Survey_Settings {

    /**
     * 设置实例（单例）
     *
     * @var WP_Survey_Settings|null
     */
    private static ?WP_Survey_Settings $instance = null;

    /**
     * 设置选项名
     */
    const OPTION_NAME = 'wpsurvey_settings';

    /**
     * 默认设置
     */
    const DEFAULTS = array(
        'primary_color' => '#1a73e8',
        'accent_color' => '#00bcd4',
        'button_color' => '#0d47a1',
        'default_display_mode' => 'step',
        'allow_guest' => false,
        'custom_css' => '',
        'chart_cdn' => 'bootcdn', // bootcdn, cdnjs, jsdelivr
        'font_family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
    );

    /**
     * CDN 配置
     */
    const CDN_URLS = array(
        'bootcdn' => 'https://cdn.bootcdn.net/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
        'cdnjs' => 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js',
        'jsdelivr' => 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
    );

    /**
     * 获取实例
     *
     * @return WP_Survey_Settings
     */
    public static function get_instance(): WP_Survey_Settings {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        // 注册设置初始化钩子
        add_action('admin_init', array($this, 'register_settings'));
    }

    /**
     * 获取所有设置
     *
     * @return array
     */
    public function get_settings(): array {
        $saved = get_option(self::OPTION_NAME, array());
        return wp_parse_args($saved, self::DEFAULTS);
    }

    /**
     * 获取单个设置
     *
     * @param string $key 设置键
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed {
        $settings = $this->get_settings();
        return $settings[$key] ?? $default;
    }

    /**
     * 保存设置
     *
     * @param array $settings 要保存的设置
     * @return bool
     */
    public function save(array $settings): bool {
        // 合并默认值，确保所有字段都存在
        $settings = wp_parse_args($settings, $this->get_settings());
        
        return update_option(self::OPTION_NAME, $settings);
    }

    /**
     * 更新单个设置
     *
     * @param string $key 设置键
     * @param mixed $value 设置值
     * @return bool
     */
    public function update(string $key, mixed $value): bool {
        $settings = $this->get_settings();
        $settings[$key] = $value;
        return $this->save($settings);
    }

    /**
     * 重置设置到默认值
     *
     * @return bool
     */
    public function reset(): bool {
        return delete_option(self::OPTION_NAME);
    }

    /**
     * 注册 WordPress 设置
     *
     * @return void
     */
    public function register_settings(): void {
        register_setting(
            'wpsurvey_settings_group',
            self::OPTION_NAME,
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings'),
            )
        );
    }

    /**
     * 清理设置数据
     *
     * @param array $input 原始输入
     * @return array
     */
    public function sanitize_settings(array $input): array {
        $sanitized = array();

        // 颜色字段：验证十六进制格式
        $color_fields = array('primary_color', 'accent_color', 'button_color');
        foreach ($color_fields as $field) {
            if (isset($input[$field]) && preg_match('/^#[0-9a-fA-F]{6}$/', $input[$field])) {
                $sanitized[$field] = $input[$field];
            }
        }

        // 显示模式
        if (isset($input['default_display_mode']) && in_array($input['default_display_mode'], array('step', 'all'))) {
            $sanitized['default_display_mode'] = $input['default_display_mode'];
        }

        // 布尔值字段
        $sanitized['allow_guest'] = !empty($input['allow_guest']) ? 1 : 0;

        // 文本字段
        $sanitized['custom_css'] = wp_strip_all_tags($input['custom_css'] ?? '');
        $sanitized['font_family'] = sanitize_text_field($input['font_family'] ?? self::DEFAULTS['font_family']);

        // CDN 选择
        if (isset($input['chart_cdn']) && array_key_exists($input['chart_cdn'], self::CDN_URLS)) {
            $sanitized['chart_cdn'] = $input['chart_cdn'];
        }

        return $sanitized;
    }

    /**
     * 获取 Chart.js CDN URL
     *
     * @return string
     */
    public function get_chart_js_url(): string {
        $cdn = $this->get('chart_cdn', 'bootcdn');
        return self::CDN_URLS[$cdn] ?? self::CDN_URLS['bootcdn'];
    }

    /**
     * 获取可用 CDN 列表
     *
     * @return array
     */
    public function get_cdn_options(): array {
        return array(
            'bootcdn' => 'BootCDN (国内推荐)',
            'cdnjs' => 'CDNJS',
            'jsdelivr' => 'jsDelivr',
        );
    }
}
