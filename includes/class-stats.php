<?php
/**
 * 统计分析类
 * 
 * 负责问卷统计数据的生成和处理
 *
 * @package WP_Survey
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class WP_Survey_Stats {

    /**
     * 统计分析实例（单例）
     *
     * @var WP_Survey_Stats|null
     */
    private static ?WP_Survey_Stats $instance = null;

    /**
     * 数据库操作实例
     *
     * @var WP_Survey_DB
     */
    private WP_Survey_DB $db;

    /**
     * 获取实例
     *
     * @return WP_Survey_Stats
     */
    public static function get_instance(): WP_Survey_Stats {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 构造函数
     */
    private function __construct() {
        $this->db = WP_Survey_DB::get_instance();
    }

    /**
     * 获取问卷统计概览
     *
     * @param int $survey_id 问卷ID
     * @return array
     */
    public function get_overview(int $survey_id): array {
        return $this->db->get_survey_stats($survey_id);
    }

    /**
     * 获取题目统计数据（格式化后用于图表）
     *
     * @param int $question_id 题目ID
     * @param string $question_type 题型
     * @param array $options 选项列表（用于单选题/多选题）
     * @return array
     */
    public function get_question_chart_data(int $question_id, string $question_type, array $options = array()): array {
        $stats = $this->db->get_question_stats($question_id, $question_type);
        
        switch ($question_type) {
            case 'radio':
            case 'select':
                return $this->format_single_choice_data($stats, $options);
                
            case 'checkbox':
                return $this->format_multiple_choice_data($stats, $options);
                
            case 'rating':
                return $this->format_rating_data($stats);
                
            case 'matrix':
                return $this->format_matrix_data($stats, $options);
                
            case 'text':
            case 'textarea':
                return $this->format_text_data($stats);
                
            default:
                return array();
        }
    }

    /**
     * 格式化单选题数据
     *
     * @param array $stats 原始统计
     * @param array $options 选项列表
     * @return array
     */
    private function format_single_choice_data(array $stats, array $options): array {
        $labels = array();
        $data = array();
        
        // 构建选项映射：使用 sort_order 索引（0, 1, 2...）作为 key，而非 option ID
        // 因为前端JS提交的是选项索引（0, 1, 2...），stats 的 key 也是索引
        $option_map = array();
        foreach ($options as $i => $opt) {
            $option_map[$i] = $opt['option_text'];
        }
        
        // 如果没有选项，使用统计数据的键
        if (empty($option_map)) {
            $option_map = $stats;
        }
        
        foreach ($option_map as $id => $text) {
            $labels[] = is_numeric($id) ? $text : $id;
            // stats 的 key 是 "0", "1" 等字符串形式的索引
            $data[] = isset($stats[(string)$id]) ? (int) $stats[(string)$id] : 0;
        }
        
        // 计算百分比
        $total = array_sum($data);
        $percentages = array();
        if ($total > 0) {
            foreach ($data as $value) {
                $percentages[] = round(($value / $total) * 100, 1);
            }
        }
        
        return array(
            'type' => 'pie', // 或 'doughnut'
            'labels' => $labels,
            'datasets' => array(array(
                'data' => $data,
                'percentages' => $percentages,
                'backgroundColor' => $this->generate_colors(count($labels)),
            )),
            'total' => $total,
        );
    }

    /**
     * 格式化多选题数据
     *
     * @param array $stats 原始统计
     * @param array $options 选项列表
     * @return array
     */
    private function format_multiple_choice_data(array $stats, array $options): array {
        $labels = array();
        $data = array();
        
        // 构建选项映射：使用 sort_order 索引（0, 1, 2...）作为 key，而非 option ID
        $option_map = array();
        foreach ($options as $i => $opt) {
            $option_map[$i] = $opt['option_text'];
        }
        
        foreach ($option_map as $id => $text) {
            $labels[] = $text;
            // stats 的 key 是 "0", "1" 等字符串形式的索引
            $data[] = isset($stats[(string)$id]) ? (int) $stats[(string)$id] : 0;
        }
        
        // 计算百分比
        $total = array_sum($data);
        $percentages = array();
        if ($total > 0) {
            foreach ($data as $value) {
                $percentages[] = round(($value / $total) * 100, 1);
            }
        }
        
        return array(
            'type' => 'bar',
            'labels' => $labels,
            'datasets' => array(array(
                'label' => '选择次数',
                'data' => $data,
                'percentages' => $percentages,
                'backgroundColor' => $this->generate_colors(count($labels)),
            )),
            'total' => $total,
        );
    }

    /**
     * 格式化评分题数据
     *
     * @param array $stats 原始统计
     * @return array
     */
    private function format_rating_data(array $stats): array {
        if (empty($stats['ratings'])) {
            return array(
                'type' => 'bar',
                'labels' => array(),
                'datasets' => array(),
                'average' => 0,
            );
        }
        
        $ratings = $stats['ratings'];
        $max_score = $stats['max'] ?? 5;
        
        // 统计各分值的数量
        $counts = array();
        for ($i = 1; $i <= $max_score; $i++) {
            $counts[$i] = 0;
        }
        
        foreach ($ratings as $rating) {
            if (isset($counts[$rating])) {
                $counts[$rating]++;
            }
        }
        
        $labels = array();
        $data = array();
        
        foreach ($counts as $score => $count) {
            if ($max_score <= 5) {
                $labels[] = str_repeat('★', $score);
            } else {
                $labels[] = (string) $score . '分';
            }
            $data[] = $count;
        }
        
        return array(
            'type' => 'bar',
            'labels' => $labels,
            'datasets' => array(array(
                'label' => '评分分布',
                'data' => $data,
                'backgroundColor' => $this->generate_colors(count($labels)),
            )),
            'average' => $stats['average'] ?? 0,
            'total' => count($ratings),
        );
    }

    /**
     * 格式化矩阵题数据
     *
     * @param array $stats 原始统计
     * @param array $options 行列设置
     * @return array
     */
    private function format_matrix_data(array $stats, array $options): array {
        $rows = $options['rows'] ?? array();
        $columns = $options['columns'] ?? array();
        
        $labels = $rows;
        $datasets = array();
        
        // 为每一列创建一个数据集
        foreach ($columns as $col_idx => $col_name) {
            $col_data = array();
            
            foreach ($rows as $row_idx => $row_name) {
                $row_key = 'row_' . $row_idx;
                $count = isset($stats[$row_key][$col_idx]) ? $stats[$row_key][$col_idx] : 0;
                $col_data[] = $count;
            }
            
            $datasets[] = array(
                'label' => $col_name,
                'data' => $col_data,
                'backgroundColor' => $this->get_matrix_color($col_idx),
            );
        }
        
        return array(
            'type' => 'bar',
            'labels' => $labels,
            'datasets' => $datasets,
        );
    }

    /**
     * 格式化文本题数据
     *
     * @param array $stats 原始统计
     * @return array
     */
    private function format_text_data(array $stats): array {
        // 文本题返回原始答案列表
        return array(
            'type' => 'text',
            'answers' => array_filter($stats),
        );
    }

    /**
     * 生成颜色数组
     *
     * @param int $count 数量
     * @return array
     */
    private function generate_colors(int $count): array {
        $base_colors = array(
            '#1a73e8', // 主色-蓝
            '#00bcd4', // 辅助-青
            '#0d47a1', // 深蓝
            '#4caf50', // 绿色
            '#ff9800', // 橙色
            '#e91e63', // 粉色
            '#9c27b0', // 紫色
            '#607d8b', // 蓝灰
            '#795548', // 棕色
            '#f44336', // 红色
        );
        
        $colors = array();
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $base_colors[$i % count($base_colors)];
        }
        
        return $colors;
    }

    /**
     * 获取矩阵题颜色
     *
     * @param int $index 索引
     * @return string
     */
    private function get_matrix_color(int $index): string {
        $colors = array(
            '#1a73e8',
            '#00bcd4',
            '#4caf50',
            '#ff9800',
            '#9c27b0',
            '#f44336',
        );
        
        return $colors[$index % count($colors)];
    }

    /**
     * 获取答卷列表（带用户信息）
     *
     * @param int $survey_id 问卷ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array
     */
    public function get_response_list(int $survey_id, int $limit = 50, int $offset = 0): array {
        $responses = $this->db->get_responses($survey_id, array(
            'limit' => $limit,
            'offset' => $offset,
        ));
        
        // 补充用户信息
        foreach ($responses as &$response) {
            if ($response['user_id']) {
                $user = get_userdata($response['user_id']);
                $response['user_name'] = $user ? $user->display_name : '用户 #' . $response['user_id'];
            } else {
                $response['user_name'] = '访客';
            }
            // 补充IP归属地
            $response['ip_display'] = $this->format_ip_display($response['ip_address'] ?? '');
        }
        unset($response);
        
        return $responses;
    }

    /**
     * 获取单份答卷详情
     *
     * @param int $response_id 答卷ID
     * @return array
     */
    public function get_response_detail(int $response_id): array {
        $response = $this->db->get_response($response_id);
        
        if (!$response) {
            return array();
        }
        
        // 获取用户信息
        if ($response['user_id']) {
            $user = get_userdata($response['user_id']);
            $response['user_name'] = $user ? $user->display_name : '用户 #' . $response['user_id'];
        } else {
            $response['user_name'] = '访客';
        }
        
        // 获取答案
        $response['answers'] = $this->db->get_answers($response_id);
        
        return $response;
    }

    /**
     * 格式化完成时间
     *
     * @param int $seconds 秒数
     * @return string
     */
    public static function format_duration(int $seconds): string {
        if ($seconds < 60) {
            return $seconds . '秒';
        }
        
        $minutes = floor($seconds / 60);
        $remaining_seconds = $seconds % 60;
        
        if ($minutes < 60) {
            return $minutes . '分' . $remaining_seconds . '秒';
        }
        
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        
        return $hours . '时' . $remaining_minutes . '分';
    }

    /**
     * 生成词云数据（简单实现）
     *
     * @param array $texts 文本数组
     * @return array
     */
    public function generate_word_frequencies(array $texts): array {
        $word_counts = array();
        
        foreach ($texts as $text) {
            // 简单分词（按空格和标点）
            $words = preg_split('/[\s,.!?;:，。！？；：""\'\'（）()]+/u', $text);
            
            foreach ($words as $word) {
                $word = trim($word);
                
                // 过滤单字和常见停用词
                if (mb_strlen($word) < 2) {
                    continue;
                }
                
                // 常见停用词
                $stopwords = array('这个', '那个', '什么', '怎么', '没有', '不是', '就是', '可以', '因为', '所以');
                if (in_array($word, $stopwords)) {
                    continue;
                }
                
                if (isset($word_counts[$word])) {
                    $word_counts[$word]++;
                } else {
                    $word_counts[$word] = 1;
                }
            }
        }
        
        // 按频率排序
        arsort($word_counts);
        
        // 取前50个
        return array_slice($word_counts, 0, 50, true);
    }

    /**
     * 格式化IP地址显示：IPv4/IPv6 省份.城市
     * 使用 ip-api.com 免费接口查询归属地，带瞬态缓存
     *
     * @param string $ip IP地址
     * @return string 格式化后的显示文本
     */
    public function format_ip_display(string $ip): string {
        if (empty($ip)) {
            return '';
        }

        // 判断IP版本
        $version = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6';

        // 查缓存
        $cache_key = 'wpsurvey_ip_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $version . ' ' . $cached;
        }

        // 调用 ip-api.com 查询（HTTP免费接口，限45次/分钟）
        $response = wp_remote_get('http://ip-api.com/json/' . urlencode($ip) . '?lang=zh-CN', array(
            'timeout' => 3,
        ));

        $location = '';
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['status']) && $body['status'] === 'success') {
                $province = $body['regionName'] ?? '';
                $city = $body['city'] ?? '';
                if ($province && $city && $province !== $city) {
                    $location = $province . '.' . $city;
                } elseif ($city) {
                    $location = $city;
                } elseif ($province) {
                    $location = $province;
                }
            }
        }

        // 缓存7天
        set_transient($cache_key, $location, 7 * DAY_IN_SECONDS);

        return $version . ($location ? ' ' . $location : '');
    }
}
