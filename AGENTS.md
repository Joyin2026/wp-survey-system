## 项目概述

WP Survey System 是一个功能完整的 WordPress 问卷调查插件，支持满意度调查、信息采集、投票等场景。提供可视化问卷构建、AJAX 无刷新提交、条件跳转、图表统计等功能。

## 技术栈

- **语言**：PHP 8.2+
- **框架**：纯 PHP（WordPress 插件）
- **前端**：原生 JS + CSS，无构建工具
- **图表**：Chart.js（支持 CDN 或本地备份）
- **依赖管理**：无（纯插件，无需包管理器）

## 目录结构

```
wp-survey-system/
├── wp-survey-system.php          # 主插件文件（入口）
├── uninstall.php                  # 卸载处理
├── includes/                      # 核心类文件
│   ├── class-db.php              # 数据库操作
│   ├── class-survey.php          # 问卷业务逻辑
│   ├── class-admin.php           # 后台管理
│   ├── class-frontend.php        # 前端展示
│   ├── class-ajax.php            # AJAX 处理
│   ├── class-stats.php           # 统计分析
│   └── class-settings.php        # 全局设置
├── assets/
│   ├── css/                      # 样式文件
│   └── js/                       # 脚本文件（含 chart.min.js）
├── templates/
│   ├── admin/                    # 后台模板
│   └── frontend/                 # 前端模板
└── languages/                    # 国际化文件目录
```

## 关键入口

- **主文件**：`wp-survey-system.php`
- **插件主类**：`WP_Survey_System`（单例模式）
- **WordPress 挂载点**：通过 `init_hooks()` 注册各种 WordPress 钩子

## 运行与部署

### 安装方式
1. 将插件文件夹上传到 WordPress `wp-content/plugins/` 目录
2. 在后台插件页面启用
3. 通过左侧菜单"WP问卷调查"访问管理后台

### 短代码嵌入
```
[wpsurvey id="1"]
```

### 独立页面访问
```
/survey/{id}/
```

## 用户偏好与长期约束

1. **PHP 版本**：最低要求 PHP 8.2
2. **WordPress 版本**：最低要求 WordPress 6.0
3. **插件激活**：依赖 WordPress 环境，无法独立运行
4. **部署方式**：插件打包分发，非传统 Web 部署
5. **CDN 配置**：支持 BootCDN、CDNJS、jsDelivr 三种 CDN 源

## 常见问题与预防

1. **插件无法激活**：检查 PHP 版本是否 >= 8.2，WordPress 版本是否 >= 6.0
2. **问卷无法显示**：检查短代码是否正确，确认问卷已发布
3. **统计图表不显示**：检查 Chart.js CDN 是否可访问，或使用本地备份
4. **AJAX 请求失败**：检查 WordPress 固定链接设置，确认 REST API 可用
