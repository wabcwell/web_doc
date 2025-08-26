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
- **框架**: Bootstrap 5.3.0
- **编辑器**: Toast UI Editor
- **Markdown解析**: Parsedown

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
├── assets/             # 静态资源
├── database/           # SQLite数据库
├── includes/           # 核心功能文件
├── uploads/            # 上传文件
├── index.php          # 前台首页
├── config.php         # 配置文件
└── README.md          # 项目文档
```

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