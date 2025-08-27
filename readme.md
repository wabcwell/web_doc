# 文档管理系统

一个基于 PHP 和 SQLite 的简洁文档管理系统，支持 Markdown 文档管理、用户权限控制和响应式界面。

## 功能特性

- 📚 **Markdown 文档管理** - 支持 Markdown 格式文档的创建、编辑和管理
- 👥 **用户权限系统** - 管理员和普通用户角色区分
- 📱 **响应式设计** - 基于 Bootstrap 的响应式界面
- 🔍 **全文搜索** - 支持文档内容的全文搜索
- 🖼️ **文件上传** - 支持图片和附件上传
- 📊 **管理后台** - 完整的后台管理界面

## 技术栈

- **后端**: PHP 8.2+
- **数据库**: SQLite 3
- **前端**: HTML5, CSS3, JavaScript
- **框架**: Bootstrap 5.3.0 (完全本地化)
- **编辑器**: Toast UI Editor (完全本地化)
- **Markdown解析**: Parsedown
- **图标**: Bootstrap Icons (完全本地化)

## 快速开始

### 环境要求

- PHP 8.2 或更高版本
- SQLite 3 支持
- Web 服务器 (Apache/Nginx/PHP内置服务器)

### 安装步骤

1. **克隆项目**
   ```bash
   git clone [你的仓库地址]
   cd web_doc
   ```

2. **启动开发服务器**
   ```bash
   php -S localhost:8000
   ```

3. **访问系统**
   - 前台首页: http://localhost:8000
   - 管理后台: http://localhost:8000/admin
   - 默认管理员: admin/admin123

### 目录结构

```
web_doc/
├── admin/              # 管理后台
│   ├── documents/      # 文档管理
│   ├── dashboard.php   # 管理面板
│   ├── users.php       # 用户管理
│   └── settings.php    # 系统设置
├── assets/             # 本地化静态资源
│   ├── css/
│   │   ├── static/     # 本地化CSS文件
│   │   │   ├── bootstrap.min.css    # Bootstrap 5.3.0
│   │   │   ├── bootstrap-icons.min.css  # Bootstrap Icons
│   │   │   └── prism.min.css        # 代码高亮
│   ├── fonts/          # 本地化字体文件
│   │   ├── bootstrap-icons.woff2    # Bootstrap Icons字体
│   │   └── bootstrap-icons.woff     # Bootstrap Icons字体
│   ├── js/
│   │   └── static/     # 本地化JS文件
│   │       ├── bootstrap.bundle.min.js  # Bootstrap JS
│   │       ├── prism.min.js             # 代码高亮
│   │       └── prism-*.min.js           # 各语言代码高亮
│   ├── images/         # 图片资源
│   └── icons/          # 图标文件
├── database/           # SQLite数据库
├── includes/           # 核心功能文件
├── uploads/            # 上传文件
├── index.php          # 前台首页
├── config.php         # 配置文件
└── README.md          # 项目文档
```

## 资源本地化

本项目所有外部依赖资源已完全本地化，无需网络连接即可正常运行：

### 🎨 样式资源
- **Bootstrap 5.3.0**: 本地CSS文件 `assets/css/static/bootstrap.min.css`
- **Bootstrap Icons**: 本地字体文件 `assets/fonts/bootstrap-icons.woff2` 和 `assets/fonts/bootstrap-icons.woff`
- **Prism代码高亮**: 本地CSS文件 `assets/css/static/prism.min.css`

### 📜 脚本资源
- **Bootstrap JS**: 本地JS文件 `assets/js/static/bootstrap.bundle.min.js`
- **Prism代码高亮**: 本地JS文件 `assets/js/static/prism.min.js`
- **Toast UI Editor**: 本地JS文件

### 🗂️ 本地化优势
- ✅ **零外部依赖**: 完全离线运行
- ✅ **加载更快**: 本地资源无网络延迟
- ✅ **稳定性高**: 不受CDN服务影响
- ✅ **隐私安全**: 无外部资源请求

## 使用说明

### 管理员功能

1. **登录后台**: 访问 `/admin` 使用管理员账户登录
2. **文档管理**: 创建、编辑、删除文档
3. **用户管理**: 管理用户账户和权限
4. **系统设置**: 配置系统参数

### 普通用户功能

- 浏览公开文档
- 搜索文档内容
- 查看文档详情

### 文档删除逻辑

系统支持智能删除文档，自动处理子文档的层级和排序，保持排序位置稳定：

#### 删除规则
- **直接子文档处理**：仅处理被删除文档的直接子文档
- **孙文档保持**：子文档的子文档层级和排序保持不变
- **排序继承**：子文档继承被删除文档的排序值，避免位置重大变化

#### 删除场景

1. **删除顶级文档**
   - **父级调整**：直接子文档的父级设为null（成为顶级文档）
   - **排序继承**：子文档排序权重 = 被删除的父级顶级文档的排序值（所有子文档相同）

2. **删除非顶级文档**
   - **父级调整**：直接子文档的父级设为被删除文档的原父级
   - **排序继承**：子文档排序权重 = 被删除的父级文档的排序值（所有子文档相同）

#### 示例
- 删除顶级文档"PHP教程"（排序值=5），其子文档"基础语法"、"面向对象"等都将继承排序值5
- 删除非顶级文档"第1章"（排序值=2），其子文档"1.1节"、"1.2节"等都将继承排序值2

#### 优势
- **排序稳定**：子文档保持相对位置，避免重新排序
- **层级清晰**：子文档自动调整到合适的父级层级
- **事务安全**：使用数据库事务确保数据一致性

## 开发指南

### 数据库结构

- **users**: 用户表
- **documents**: 文档表

### 主要文件

- `includes/auth.php`: 用户认证
- `includes/DocumentTree.php`: 文档管理
- `config.php`: 系统配置

## 贡献指南

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License