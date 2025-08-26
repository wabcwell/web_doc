<?php
/**
 * 初始化文档系统 - 创建20篇多层级文档
 */

// 引入配置文件
require_once 'config.php';
require_once 'includes/init.php';

try {
    $db = get_db();
    
    // 清空现有文档
    $db->exec("DELETE FROM documents");
    $db->exec("DELETE FROM sqlite_sequence WHERE name='documents'");
    
    // 文档数据 - 多层级结构
    $docs = [
        // 第一层：主要分类
        ['title' => '📚 系统使用指南', 'content' => '# 系统使用指南

欢迎使用文档管理系统！本指南将帮助您快速上手。

## 主要功能
- 文档创建与管理
- 多层级目录结构
- 全文搜索
- 用户权限管理', 'parent_id' => null, 'sort_order' => 1],
        
        ['title' => '🛠️ 管理员手册', 'content' => '# 管理员手册

管理员专用文档，包含系统配置和用户管理。

## 管理员功能
- 用户管理
- 系统设置
- 文档审核', 'parent_id' => null, 'sort_order' => 2],
        
        ['title' => '💡 用户指南', 'content' => '# 用户指南

普通用户使用指南，帮助您快速掌握系统操作。

## 基础操作
- 浏览文档
- 搜索内容
- 个人设置', 'parent_id' => null, 'sort_order' => 3],
        
        ['title' => '📖 开发文档', 'content' => '# 开发文档

系统技术文档，包含架构设计和API说明。

## 技术栈
- PHP + SQLite
- Bootstrap 前端
- Markdown 支持', 'parent_id' => null, 'sort_order' => 4],
        
        ['title' => '📋 常见问题', 'content' => '# 常见问题

使用过程中可能遇到的常见问题及解决方案。

## 快速查找
使用搜索功能快速定位问题答案。', 'parent_id' => null, 'sort_order' => 5],
        
        // 第二层：系统使用指南子文档
        ['title' => '🚀 快速开始', 'content' => '# 快速开始

5分钟上手文档管理系统。

## 第一步：登录
1. 访问 http://localhost:8000/admin
2. 使用用户名：admin，密码：admin123
3. 进入管理后台

## 第二步：创建文档
1. 点击"文档管理"
2. 选择"新建文档"
3. 填写标题和内容
4. 选择父级目录
5. 保存发布', 'parent_id' => 1, 'sort_order' => 1],
        
        ['title' => '📝 文档编辑器', 'content' => '# 文档编辑器使用

强大的 Markdown 编辑器，支持实时预览。

## Markdown 语法支持
- 标题：# ## ###
- 列表：- 1.
- 链接：[文本](url)
- 图片：![alt](url)
- 代码块：```代码```

## 高级功能
- 表格支持
- 代码高亮
- 数学公式', 'parent_id' => 1, 'sort_order' => 2],
        
        ['title' => '🔍 搜索功能', 'content' => '# 搜索功能详解

全文搜索让您快速找到所需内容。

## 搜索技巧
- 关键词搜索：输入关键词
- 精确搜索：使用引号
- 多关键词：空格分隔

## 搜索结果
- 高亮显示匹配内容
- 显示文档路径
- 快速跳转', 'parent_id' => 1, 'sort_order' => 3],
        
        // 第三层：快速开始子文档
        ['title' => '👤 用户管理入门', 'content' => '# 用户管理入门

学习如何管理用户账户和权限。

## 用户角色
- **管理员**：完全权限
- **编辑者**：文档编辑权限

## 添加新用户
1. 进入"用户管理"
2. 点击"添加用户"
3. 设置用户名和密码
4. 分配角色', 'parent_id' => 6, 'sort_order' => 1],
        
        ['title' => '📁 目录结构管理', 'content' => '# 目录结构管理

创建合理的文档层级结构。

## 目录层级
- 支持无限层级
- 拖拽排序
- 父子关系

## 最佳实践
- 按功能分类
- 保持层级清晰
- 避免过深嵌套', 'parent_id' => 6, 'sort_order' => 2],
        
        // 第二层：管理员手册子文档
        ['title' => '⚙️ 系统配置', 'content' => '# 系统配置指南

系统管理员配置指南。

## 基本配置
- 网站名称设置
- 上传限制配置
- 时区设置

## 高级配置
- 数据库备份
- 安全设置
- 性能优化', 'parent_id' => 2, 'sort_order' => 1],
        
        ['title' => '👥 用户权限管理', 'content' => '# 用户权限管理

细粒度的权限控制系统。

## 权限类型
- 文档创建权限
- 文档编辑权限
- 文档删除权限
- 用户管理权限

## 权限分配
基于角色的权限管理，简化配置流程。', 'parent_id' => 2, 'sort_order' => 2],
        
        ['title' => '📊 系统监控', 'content' => '# 系统监控

监控系统运行状态和性能。

## 监控指标
- 用户活跃度
- 文档数量统计
- 系统性能

## 日志分析
- 访问日志
- 错误日志
- 操作日志', 'parent_id' => 2, 'sort_order' => 3],
        
        // 第二层：用户指南子文档
        ['title' => '🔍 基础搜索', 'content' => '# 基础搜索使用

快速找到您需要的文档。

## 搜索入口
- 顶部搜索框
- 侧边栏搜索
- 高级搜索页面

## 搜索技巧
- 使用关键词
- 支持模糊匹配
- 结果排序', 'parent_id' => 3, 'sort_order' => 1],
        
        ['title' => '👤 个人设置', 'content' => '# 个人设置

个性化您的使用体验。

## 个人资料
- 修改密码
- 更新邮箱
- 设置头像

## 偏好设置
- 界面主题
- 通知设置
- 语言选择', 'parent_id' => 3, 'sort_order' => 2],
        
        ['title' => '💾 数据导出', 'content' => '# 数据导出功能

将文档导出为不同格式。

## 支持格式
- **HTML**：网页格式
- **PDF**：打印格式
- **Markdown**：源文件格式

## 导出选项
- 单文档导出
- 批量导出
- 包含子文档', 'parent_id' => 3, 'sort_order' => 3],
        
        // 第二层：开发文档子文档
        ['title' => '🏗️ 系统架构', 'content' => '# 系统架构设计

文档管理系统的技术架构。

## 前端架构
- Bootstrap 5.3 框架
- 响应式设计
- 组件化开发

## 后端架构
- PHP 8.2+ 支持
- SQLite 数据库
- MVC 设计模式', 'parent_id' => 4, 'sort_order' => 1],
        
        ['title' => '🔌 API文档', 'content' => '# API接口文档

系统提供的API接口说明。

## 认证API
- 用户登录/登出
- 权限验证

## 文档API
- 创建文档
- 更新文档
- 删除文档
- 查询文档', 'parent_id' => 4, 'sort_order' => 2],
        
        ['title' => '🔧 开发指南', 'content' => '# 开发指南

为开发者提供的扩展指南。

## 环境要求
- PHP 8.2+
- SQLite 3
- Web服务器

## 开发工具
- 代码编辑器
- 数据库管理工具
- 版本控制', 'parent_id' => 4, 'sort_order' => 3],
        
        // 第二层：常见问题子文档
        ['title' => '❓ 登录问题', 'content' => '# 登录问题解决方案

解决登录相关常见问题。

## 忘记密码
- 联系管理员重置
- 使用密码找回功能

## 登录失败
- 检查用户名密码
- 清除浏览器缓存
- 检查网络连接', 'parent_id' => 5, 'sort_order' => 1],
        
        ['title' => '🐛 常见错误', 'content' => '# 常见错误及解决

系统使用中的常见错误。

## 文档无法保存
- 检查权限
- 清理缓存
- 检查网络

## 搜索无结果
- 检查关键词
- 扩大搜索范围
- 检查索引状态', 'parent_id' => 5, 'sort_order' => 2],
    ];
    
    $stmt = $db->prepare("INSERT INTO documents (title, content, parent_id, sort_order, user_id, is_public) VALUES (?, ?, ?, ?, 1, 1)");
    
    foreach ($docs as $doc) {
        $stmt->execute([
            $doc['title'],
            $doc['content'],
            $doc['parent_id'],
            $doc['sort_order']
        ]);
    }
    
    echo "✅ 成功初始化20篇多层级文档！\n";
    echo "📊 文档统计：\n";
    
    // 显示层级统计
    $result = $db->query("SELECT parent_id, COUNT(*) as count FROM documents GROUP BY parent_id");
    $levels = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📂 层级分布：\n";
    foreach ($levels as $level) {
        if ($level['parent_id'] === null) {
            echo "   根层级: {$level['count']} 篇文档\n";
        } else {
            echo "   父文档ID {$level['parent_id']}: {$level['count']} 篇子文档\n";
        }
    }
    
    // 显示文档树
    echo "\n🌳 文档树结构：\n";
    
    function displayTree($parent_id = null, $level = 0) {
        global $db;
        $indent = str_repeat('  ', $level);
        $stmt = $db->prepare("SELECT id, title FROM documents WHERE parent_id " . ($parent_id === null ? "IS NULL" : "= ?") . " ORDER BY sort_order");
        if ($parent_id === null) {
            $stmt->execute();
        } else {
            $stmt->execute([$parent_id]);
        }
        
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($docs as $doc) {
            echo $indent . "📄 " . $doc['title'] . " (ID: {$doc['id']})\n";
            displayTree($doc['id'], $level + 1);
        }
    }
    
    displayTree();
    
} catch (Exception $e) {
    echo "❌ 初始化失败: " . $e->getMessage() . "\n";
}