# WP Survey System - 问卷调查系统

## 插件简介

功能完整的 WordPress 问卷调查插件，支持满意度调查、信息采集、投票。提供可视化问卷构建、AJAX无刷新提交、条件跳转、图表统计等功能。

## 功能特性

### 问卷管理
- 支持多种问卷类型：满意度调查、信息采集、投票
- 可视化问卷构建器，支持拖拽排序题目
- 支持 7 种题型：单选题、多选题、下拉选择、单行文本、多行文本、评分题、矩阵题
- 条件跳转逻辑：每个选项可设置跳转到指定题目或结束
- 问卷状态管理：草稿、已发布、已关闭
- 时间限制：可设置问卷开始和结束时间
- 参与限制：可设置是否要求登录、每人限填一次

### 前端展示
- 短代码嵌入：`[wpsurvey id="X"]`
- 独立页面访问：`/survey/{id}/`
- 两种展示模式：逐步展示（一题一页）、全部展示
- 条件跳转：根据上一题选项自动跳转
- AJAX 无刷新提交
- 响应式设计，移动端友好
- 蓝青色调默认配色

### 统计分析
- 统计概览：总提交数、今日新增、平均完成时间
- Chart.js 图表可视化
- 单选题/多选题：环形图/柱状图
- 评分题：柱状图
- 矩阵题：堆叠柱状图
- 文本题：列表展示
- CSV 数据导出

### 全局设置
- 默认配色方案
- 默认展示模式
- 允许/禁止访客参与
- 自定义 CSS
- CDN 选择（支持 BootCDN、CDNJS、jsDelivr）

## 安装方法

1. 下载插件文件夹 `wp-survey-system`
2. 上传到 WordPress 插件目录 `wp-content/plugins/`
3. 在后台"插件"页面启用插件
4. 点击左侧菜单"WP问卷调查"开始使用

## 使用方法

### 创建问卷

1. 进入"WP问卷调查 → 添加问卷"
2. 填写问卷标题、描述，选择类型
3. 使用题目构建器添加题目
4. 配置显示设置和参与限制
5. 点击"保存问卷"

### 嵌入问卷

**方式一：短代码**
在文章或页面中插入：
```
[wpsurvey id="1"]
```

**方式二：独立页面**
访问：`https://yourdomain.com/survey/1/`

### 查看统计

进入"WP问卷调查 → 统计分析"，选择对应问卷查看详细数据。

## 文件结构

```
wp-survey-system/
├── wp-survey-system.php          # 主插件文件
├── uninstall.php                  # 卸载处理
├── includes/
│   ├── class-db.php              # 数据库操作
│   ├── class-survey.php          # 问卷业务逻辑
│   ├── class-admin.php           # 后台管理
│   ├── class-frontend.php        # 前端展示
│   ├── class-ajax.php            # AJAX处理
│   ├── class-stats.php           # 统计分析
│   └── class-settings.php        # 全局设置
├── assets/
│   ├── css/
│   │   ├── admin.css             # 后台样式
│   │   └── frontend.css          # 前端样式
│   └── js/
│       ├── admin.js              # 后台脚本
│       ├── frontend.js           # 前端脚本
│       └── chart.min.js          # Chart.js本地备份
├── templates/
│   ├── admin/                    # 后台模板
│   └── frontend/                 # 前端模板
└── languages/                    # 国际化文件目录
```

## 数据库表

插件使用以下自定义数据表（{prefix} 为 WordPress 数据库前缀）：

- `{prefix}wpsurveys` - 问卷表
- `{prefix}wpsurvey_questions` - 题目表
- `{prefix}wpsurvey_options` - 选项表
- `{prefix}wpsurvey_responses` - 答卷表
- `{prefix}wpsurvey_answers` - 答案表

## 兼容性

- WordPress 6.0+
- PHP 8.2+
- MySQL 5.7+

## 版本历史

### 1.0.0
- 初始版本
- 支持 7 种题型
- 可视化问卷构建器
- 条件跳转逻辑
- AJAX 无刷新提交
- Chart.js 图表统计
- CSV 数据导出
- 响应式设计

## 作者

瑾煜 - https://www.sjinyu.com

## 许可证

GPL v2 or later
