# 文档管理系统

一个基于 PHP 和 SQLite 的简洁文档管理系统，支持 Markdown 文档管理、用户权限控制和响应式界面。

## 功能特性

- 📚 **富文本编辑** - 所见即所得的编辑体验
- 👥 **用户权限系统** - 管理员和普通用户角色区分
- 📱 **响应式设计** - 基于 Bootstrap 的响应式界面
- 🔍 **全文搜索** - 支持文档内容的全文搜索
- 🖼️ **文件上传** - 支持图片和附件上传和管理
- 📊 **管理后台** - 完整的后台管理界面
- 📤 **多格式导出** - 支持 PDF、Markdown、HTML、PNG、JPG 格式导出
- 🎨 **代码高亮** - 支持多种编程语言的代码高亮显示
- 📋 **文档历史管理** - 支持文档版本历史查看和管理
- 🗑️ **文档回收站** - 支持已删除文档的恢复和永久删除管理

## 技术栈
- **后端**: PHP 8.2+ + SQLite
- **前端**: UEditor Plus + Bootstrap 5.3
- **本地化**: 所有资源本地化部署
- **导出**: PDF/HTML/PNG多格式支持

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

### 核心功能
- **文档管理**: 创建、编辑、删除、查看文档
- **版本控制**: 文档历史版本查看与恢复
- **回收站**: 已删除文档的恢复与永久删除
- **文件导出**: 支持PDF、HTML、Markdown、图片格式
- **用户权限**: 管理员与普通用户权限区分
- **全文搜索**: 快速查找文档内容
- **文件上传**: 支持图片和附件上传
- **文件管理**: 支持文件列表、编辑、删除、批量操作

### 文件管理功能

系统提供完整的文件管理功能，支持：
- 📁 **文件列表** - 支持按类型、时间筛选，支持排序
- ✏️ **文件编辑** - 可修改文件名、描述、备注信息
- 🗑️ **文件删除** - 支持单个和批量删除操作
- 📥 **文件下载** - 支持单个文件下载功能
- 🔍 **文件搜索** - 支持按文件名关键字搜索
- 📊 **批量操作** - 支持批量删除和批量下载

### 操作流程
1. **管理员**: 登录后台 → 管理文档/用户 → 系统配置
2. **普通用户**: 浏览文档 → 搜索内容 → 导出所需格式
3. **文档操作**: 编辑 → 查看历史 → 导出/分享

#### 技术实现
- **版本存储** - 每次文档更新自动创建新的历史版本记录
- **内容解析** - 使用自定义Markdown解析器渲染历史内容
- **性能优化** - 按需加载历史版本内容，减少初始加载时间
- **数据安全** - 历史版本数据与当前文档分离存储，确保数据完整性

### 文档删除逻辑

系统支持智能删除文档，自动处理子文档的层级和排序，保持排序位置稳定：

#### 文档删除逻辑
系统支持智能删除文档，自动处理子文档的层级和排序，保持排序位置稳定：

#### 删除规则
- **软删除机制**：删除文档时仅标记为已删除状态（del_status=1），记录删除时间
- **层级保持**：删除文档时，其子文档层级关系保持不变
- **数据保留**：删除的文档及其版本历史和编辑日志都会完整保留

#### 文档恢复
- **恢复机制**：管理员可通过回收站功能恢复已删除文档
- **数据完整性**：恢复后的文档保留所有历史数据

#### 示例
- 删除顶级文档"PHP教程"（排序值=5），其子文档"基础语法"、"面向对象"等都将继承排序值5
- 删除非顶级文档"第1章"（排序值=2），其子文档"1.1节"、"1.2节"等都将继承排序值2

#### 优势
- **排序稳定**：子文档保持相对位置，避免重新排序
- **层级清晰**：子文档自动调整到合适的父级层级
- **事务安全**：使用数据库事务确保数据一致性
- **数据安全**：软删除机制防止误删，支持数据恢复

## 开发指南

### 数据库结构

- **users**: 用户表（id, username, password, role, created_at）
- **documents**: 文档表（id, title, content, parent_id, sort_order, del_status, del_time, created_at, updated_at）
- **documents_version**: 文档版本表（id, document_id, content, created_at）
- **edit_log**: 操作记录表（id, document_id, user_id, action, created_at）
- **document_id_apportion**: 文档ID分配表
- **file_upload**: 文件上传记录表（id, file_path, alias, description, notes, file_type, file_size, uploaded_by, document_id, created_at, updated_at, del_status, deleted_at）


## 📝 编辑器集成

### UEditor Plus 集成

本项目已集成 [UEditor Plus](https://github.com/modstart-lib/ueditor-plus) 富文本编辑器，提供更强大的文档编辑功能。

#### 核心特性
- **富文本编辑** - 所见即所得的编辑体验
- **图片上传** - 拖拽、粘贴等多种上传方式
- **文件管理** - 内置文件管理器
- **代码高亮** - 支持多种编程语言
- **多媒体支持** - 视频、音频嵌入
- **零外部依赖** - 完全本地化部署

## 数据库迁移

### 迁移脚本

项目提供了一个迁移脚本 `migrate_sqlite_to_mysql.py`，用于将数据从SQLite数据库迁移到MySQL数据库。

### 使用方法

1. 安装依赖：
   ```bash
   pip install mysql-connector-python
   ```
2. 修改迁移脚本中的MySQL连接参数（可选，脚本已预配置）：
   ```python
   conn = mysql.connector.connect(
       host='192.168.100.193',
       port=33310,
       database='web_doc',
       user='web_doc',
       password='YjA60cdN9'
   )
   ```
3. 运行迁移脚本：
   ```bash
   python migrate_sqlite_to_mysql.py
   ```

### 注意事项

- 请确保在运行迁移脚本之前已创建MySQL数据库
- 脚本会自动在MySQL中创建所需的表结构并迁移数据
- 如果MySQL数据库中已存在相同ID的数据，迁移脚本会更新这些数据

### 迁移脚本详细说明

迁移脚本 `migrate_sqlite_to_mysql.py` 包含以下主要功能：

1. **数据库连接**：
   - `connect_sqlite()`：连接到SQLite数据库
   - `connect_mysql()`：连接到MySQL数据库

2. **表结构创建**：
   - `create_mysql_tables()`：在MySQL中创建所有必需的表结构，包括：
     - users：用户表
     - documents：文档表
     - documents_version：文档版本表
     - edit_log：编辑日志表
     - file_upload：文件上传表
     - document_id_apportion：文档ID分配表

3. **数据迁移**：
   - `migrate_users()`：迁移用户数据
   - `migrate_documents()`：迁移文档数据
   - `migrate_documents_version()`：迁移文档版本数据
   - `migrate_edit_log()`：迁移编辑日志数据
   - `migrate_file_upload()`：迁移文件上传数据
   - `migrate_document_id_apportion()`：迁移文档ID分配数据

4. **主函数**：
   - `main()`：协调整个迁移过程，依次执行连接数据库、创建表结构和迁移数据等操作

迁移脚本会处理数据类型差异，并在遇到重复ID时更新现有数据，确保数据完整性。

## 待办事项 (To-Do)

### 🔧 功能增强计划

#### 配置功能扩展
- [x] **集成富文本编辑器** - 已集成 [UEditor Plus](https://github.com/modstart-lib/ueditor-plus) 富文本编辑器
- [x] **文档历史版本数量自定义** - 已实现文档历史版本数量的自定义设置功能（max_history_versions）
- [x] **操作记录配置** - 已实现文档操作记录最大条数的配置选项（max_operation_logs）
- [ ] **回收站管理优化** - 添加回收站文档保留天数的上限设置功能

## 贡献指南

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License