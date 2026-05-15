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
     */
    private function format_single_choice_data(array $stats, array $options): array {
        $labels = array();
        $data = array();
        
        $option_map = array();
        foreach ($options as $i => $opt) {
            $option_map[$i] = $opt['option_text'];
        }
        
        if (empty($option_map)) {
            $option_map = $stats;
        }
        
        foreach ($option_map as $id => $text) {
            $labels[] = is_numeric($id) ? $text : $id;
            $data[] = isset($stats[(string)$id]) ? (int) $stats[(string)$id] : 0;
        }
        
        $total = array_sum($data);
        $percentages = array();
        if ($total > 0) {
            foreach ($data as $value) {
                $percentages[] = round(($value / $total) * 100, 1);
            }
        }
        
        return array(
            'type' => 'pie',
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
     */
    private function format_multiple_choice_data(array $stats, array $options): array {
        $labels = array();
        $data = array();
        
        $option_map = array();
        foreach ($options as $i => $opt) {
            $option_map[$i] = $opt['option_text'];
        }
        
        foreach ($option_map as $id => $text) {
            $labels[] = $text;
            $data[] = isset($stats[(string)$id]) ? (int) $stats[(string)$id] : 0;
        }
        
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
     */
    private function format_matrix_data(array $stats, array $options): array {
        $rows = $options['rows'] ?? array();
        $columns = $options['columns'] ?? array();
        
        $labels = $rows;
        $datasets = array();
        
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
     */
    private function format_text_data(array $stats): array {
        return array(
            'type' => 'text',
            'answers' => array_filter($stats, function($v) { return $v !== null && $v !== ''; }),
        );
    }

    /**
     * 生成颜色数组
     */
    private function generate_colors(int $count): array {
        $base_colors = array(
            '#1a73e8', '#00bcd4', '#0d47a1', '#4caf50', '#ff9800',
            '#e91e63', '#9c27b0', '#607d8b', '#795548', '#f44336',
        );
        
        $colors = array();
        for ($i = 0; $i < $count; $i++) {
            $colors[] = $base_colors[$i % count($base_colors)];
        }
        return $colors;
    }

    /**
     * 获取矩阵题颜色
     */
    private function get_matrix_color(int $index): string {
        $colors = array('#1a73e8', '#00bcd4', '#4caf50', '#ff9800', '#9c27b0', '#f44336');
        return $colors[$index % count($colors)];
    }

    /**
     * 获取答卷列表（带用户信息）
     */
    public function get_response_list(int $survey_id, int $limit = 50, int $offset = 0): array {
        $responses = $this->db->get_responses($survey_id, array(
            'limit' => $limit,
            'offset' => $offset,
        ));
        
        foreach ($responses as &$response) {
            if ($response['user_id']) {
                $user = get_userdata($response['user_id']);
                $response['user_name'] = $user ? $user->display_name : '用户 #' . $response['user_id'];
            } else {
                $response['user_name'] = '访客';
            }
            $response['ip_display'] = $this->format_ip_display($response['ip_address'] ?? '');
        }
        unset($response);
        
        return $responses;
    }

    /**
     * 获取单份答卷详情
     */
    public function get_response_detail(int $response_id): array {
        $response = $this->db->get_response($response_id);
        
        if (!$response) {
            return array();
        }
        
        if ($response['user_id']) {
            $user = get_userdata($response['user_id']);
            $response['user_name'] = $user ? $user->display_name : '用户 #' . $response['user_id'];
        } else {
            $response['user_name'] = '访客';
        }
        
        $response['answers'] = $this->db->get_answers($response_id);
        
        return $response;
    }

    /**
     * 格式化完成时间
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
     */
    public function generate_word_frequencies(array $texts): array {
        $word_counts = array();
        
        foreach ($texts as $text) {
            $words = preg_split('/[\s,.!?;:\x{FF0C}\x{3002}\x{FF01}\x{FF1F}\x{FF1B}\x{FF1A}\x{201C}\x{201D}\x{2018}\x{2019}\x{FF08}\x{FF09}()]+/u', $text);
            
            foreach ($words as $word) {
                $word = trim($word);
                
                if (mb_strlen($word) < 2) {
                    continue;
                }
                
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
        
        arsort($word_counts);
        
        return array_slice($word_counts, 0, 50, true);
    }

    /**
     * 格式化IP地址显示：IPv4/IPv6 省份.城市
     * IPv4优先用pconline（纯真库，最准，原生中文），IPv6用ip-api.com
     * 使用瞬态缓存7天
     *
     * @param string $ip IP地址
     * @return string 格式化后的显示文本
     */
    public function format_ip_display(string $ip): string {
        if (empty($ip)) {
            return '';
        }

        $is_ipv4 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $version = $is_ipv4 ? 'IPv4' : 'IPv6';

        $cache_key = 'wpsurvey_ip_' . md5($ip);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $version . ' ' . $cached;
        }

        $location = '';

        if ($is_ipv4) {
            // IPv4: 优先用 pconline（纯真库，国内最准，原生中文GBK编码）
            $response = wp_remote_get('https://whois.pconline.com.cn/ipJson.jsp?ip=' . urlencode($ip) . '&json=true', array(
                'timeout' => 3,
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $body_utf8 = mb_convert_encoding($body, 'UTF-8', 'GBK');
                $data = json_decode($body_utf8, true);
                if (!empty($data['pro']) && empty($data['err'])) {
                    $province = $data['pro'] ?? '';
                    $city = $data['city'] ?? '';
                    $pro_short = preg_replace('/(省|市|自治区|特别行政区)$/', '', $province);
                    $city_short = preg_replace('/(市|地区|州|盟)$/', '', $city);
                    if ($province && $city && $pro_short !== $city_short) {
                        $location = $pro_short . '.' . $city_short;
                    } elseif ($city) {
                        $location = $city_short;
                    } elseif ($province) {
                        $location = $pro_short;
                    }
                }
            }

            if (empty($location)) {
                $location = $this->ip_api_lookup($ip);
            }
        } else {
            $location = $this->ip_api_lookup($ip);
        }

        set_transient($cache_key, $location, 7 * DAY_IN_SECONDS);

        return $version . ($location ? ' ' . $location : '');
    }

    /**
     * ip-api.com 查询IP归属地（辅助方法）
     * 对IPv6定位可能不准，如果返回非中国则忽略
     *
     * @param string $ip IP地址
     * @return string 归属地字符串
     */
    private function ip_api_lookup(string $ip): string {
        $response = wp_remote_get('http://ip-api.com/json/' . urlencode($ip) . '?lang=zh-CN', array(
            'timeout' => 3,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['status']) || $body['status'] !== 'success') {
            return '';
        }

        $country = $body['country'] ?? '';
        $province = $body['regionName'] ?? '';
        $city = $body['city'] ?? '';

        $is_ipv6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false;
        if ($is_ipv6 && $country !== '中国') {
            return '';
        }

        $pro_short = preg_replace('/(省|市|自治区|特别行政区)$/', '', $province);
        $city_short = preg_replace('/(市|地区|州|盟)$/', '', $city);

        if ($province && $city && $pro_short !== $city_short) {
            return $pro_short . '.' . $city_short;
        } elseif ($city) {
            return $city_short;
        } elseif ($province) {
            return $pro_short;
        }

        return '';
    }
}
