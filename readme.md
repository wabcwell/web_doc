# 文档管理系统

一个基于 PHP 和 SQLite 的简洁文档管理系统，支持 Markdown 文档管理、用户权限控制和响应式界面。

## 功能特性

- 📚 **Markdown 文档管理** - 支持 Markdown 格式文档的创建、编辑和管理
- 👥 **用户权限系统** - 管理员和普通用户角色区分
- 📱 **响应式设计** - 基于 Bootstrap 的响应式界面
- 🔍 **全文搜索** - 支持文档内容的全文搜索
- 🖼️ **文件上传** - 支持图片和附件上传
- 📊 **管理后台** - 完整的后台管理界面
- 📤 **多格式导出** - 支持 PDF、Markdown、HTML、PNG、JPG 格式导出
- 🎨 **代码高亮** - 支持多种编程语言的代码高亮显示
- 📋 **文档历史管理** - 支持文档版本历史查看和管理
- 🗑️ **文档回收站** - 支持已删除文档的恢复和永久删除管理

## 技术栈
- **后端**: PHP 8.2+ + SQLite
- **前端**: Lake Editor + Bootstrap 5.3
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

### 目录结构

```
web_doc/                                    # 项目根目录
├── admin/                                  # 🔧 管理后台系统
│   ├── dashboard.php                       # 管理后台首页
│   ├── settings.php                        # 系统设置页面（含网站副标题配置）
│   ├── login.php                           # 管理员登录页面
│   ├── logout.php                          # 退出登录
│   ├── sidebar.php                         # 后台侧边栏模板
│   ├── upload.php                          # 文件上传处理
│   ├── documents/                          # 📚 文档管理模块
│   │   ├── index.php                       # 文档列表页面
│   │   ├── add.php                         # 添加新文档
│   │   ├── edit.php                        # 编辑文档
│   │   ├── view.php                        # 查看文档详情
│   │   ├── delete.php                      # 删除文档（软删除）
│   │   ├── view_his.php                    # 查看文档历史版本
│   │   ├── rollback.php                    # 回滚到历史版本
│   │   └── edit_log.php                    # 文档编辑日志
│   ├── doc_recycle/                        # 🗑️ 文档回收站模块
│   │   ├── index.php                       # 回收站文档列表
│   │   ├── restore.php                     # 恢复已删除文档
│   │   ├── permdel.php                     # 永久删除文档
│   │   └── view.php                        # 查看回收站文档详情
│   └── user/                               # 👥 用户管理模块
│       ├── index.php                       # 用户列表页面
│       ├── add_user.php                    # 添加新用户
│       ├── edit_user.php                   # 编辑用户信息
│       └── delete_user.php                 # 删除用户
├── assets/                                 # 🎨 本地化静态资源（完全离线）
│   ├── css/                                # 样式文件
│   │   ├── admin.css                       # 管理后台专用样式
│   │   ├── fonts/                          # Bootstrap字体文件
│   │   └── static/                         # 核心样式库
│   │       ├── bootstrap.min.css         # Bootstrap 5.3.0
│   │       ├── bootstrap-icons.min.css   # Bootstrap图标样式
│   │       └── prism.min.css              # 代码高亮样式
│   ├── fonts/                              # 字体文件
│   │   ├── bootstrap-icons.woff          # Bootstrap图标字体
│   │   └── bootstrap-icons.woff2         # Bootstrap图标字体（压缩版）
│   ├── icons/                              # 自定义图标
│   ├── images/                             # 图片资源
│   │   └── logo.png                       # 默认Logo图片
│   └── js/                                 # JavaScript文件
│       └── static/                         # 核心脚本库
│           ├── bootstrap.bundle.min.js     # Bootstrap 5.3.0（含Popper）
│           ├── prism.min.js                # 代码高亮核心
│           ├── prism-javascript.min.js     # JavaScript代码高亮
│           ├── prism-python.min.js         # Python代码高亮
│           └── prism-php.min.js            # PHP代码高亮
├── database/                               # 🗄️ SQLite数据库相关
│   ├── docs.db                           # 主数据库文件（文档、用户、配置）
│   ├── .htaccess.template                # 数据库目录安全模板
│   ├── index.php                         # 数据库目录保护
│   └── router.php                        # 数据库路由处理
├── includes/                               # 🔧 核心功能文件
│   ├── DocumentTree.php                  # 文档树结构处理类
│   ├── auth.php                          # 用户认证功能
│   ├── init.php                          # 系统初始化配置
│   └── footer.php                        # 页脚模板
├── uploads/                                # 📤 文件上传目录
│   ├── logo/                             # Logo上传目录
│   └── documents/                        # 文档附件上传目录
├── config.php                             # ⚙️ 系统配置文件（含网站副标题）
├── config.example.php                     # 配置模板文件
├── index.php                             # 🏠 前台首页（文档展示）
├── search.php                            # 🔍 全文搜索页面
├── export.php                            # 📤 文档导出功能（PDF/HTML/MD/PNG/JPG）
├── Parsedown.php                         # Markdown解析器
├── SECURITY.md                           # 安全说明文档
├── .gitignore                           # Git忽略文件
└── README.md                            # 📖 项目说明文档（当前文件）
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

### 核心功能
- **文档管理**: 创建、编辑、删除、查看文档
- **版本控制**: 文档历史版本查看与恢复
- **回收站**: 已删除文档的恢复与永久删除
- **文件导出**: 支持PDF、HTML、Markdown、图片格式
- **用户权限**: 管理员与普通用户权限区分
- **全文搜索**: 快速查找文档内容
- **文件上传**: 支持图片和附件上传

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
- **软删除机制**：删除文档时仅标记为已删除状态，不会从数据库中物理删除记录
- **状态标记**：使用`del_status`字段标记删除状态（0=正常，1=已删除），并记录删除时间
- **直接子文档处理**：仅处理被删除文档的直接子文档
- **孙文档保持**：子文档的子文档层级和排序保持不变
- **排序继承**：子文档继承被删除文档的排序值，避免位置重大变化
- **数据保留**：删除的文档及其版本历史和编辑日志都会完整保留，支持数据恢复

#### 删除场景

1. **删除顶级文档**
   - **父级调整**：直接子文档的父级设为0（成为顶级文档）
   - **排序继承**：子文档排序权重 = 被删除的父级顶级文档的排序值（所有子文档相同）

2. **删除非顶级文档**
   - **父级调整**：直接子文档的父级设为被删除文档的原父级
   - **排序继承**：子文档排序权重 = 被删除的父级文档的排序值（所有子文档相同）

#### 文档恢复功能
- **恢复机制**：管理员可通过数据库直接修改`del_status`字段来恢复被删除的文档
- **数据完整性**：恢复后的文档会保留所有版本历史和编辑记录
- **层级关系**：恢复时需注意文档的层级关系可能需要重新调整

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

- **users**: 用户表
- **documents**: 文档表


## 待办事项 (To-Do)

### 🔧 功能增强计划

#### 编辑器升级
- [x] **集成富文本编辑器** - 已集成 [UEditor Plus](https://github.com/modstart-lib/ueditor-plus) 富文本编辑器，提供更丰富的文档编辑体验

#### 配置功能扩展
- [ ] **文档历史版本数量自定义** - 实现文档历史版本数量的自定义设置功能，允许管理员配置保留的历史版本数量
- [ ] **操作记录配置** - 开发文档操作记录最大条数的配置选项，支持自定义操作日志的存储上限
- [ ] **回收站管理优化** - 添加回收站文档保留天数的上限设置功能，支持自动清理过期回收文档

## 📝 编辑器集成

### UEditor Plus 集成

本项目已集成 [UEditor Plus](https://github.com/modstart-lib/ueditor-plus) 富文本编辑器，提供更强大的文档编辑功能。

#### 功能特性
- **富文本编辑** - 支持所见即所得的文档编辑体验
- **图片上传** - 支持拖拽上传、粘贴上传等多种方式
- **文件管理** - 内置文件管理器，支持图片和附件管理
- **代码高亮** - 支持多种编程语言的代码高亮显示
- **表格编辑** - 可视化表格编辑功能
- **多媒体支持** - 支持视频、音频等多媒体内容嵌入

#### 使用方式

##### 1. 文档创建
在 `admin/documents/add.php` 中使用UEditor Plus创建新文档：
```php
<!-- UEditor Plus 容器 -->
<div id="editor" style="height: 400px;"></div>

<!-- 引入UEditor Plus -->
<script type="text/javascript" src="/admin/assets/ueditorplus/ueditor.config.js"></script>
<script type="text/javascript" src="/admin/assets/ueditorplus/ueditor.all.js"></script>
<script>
    var ue = UE.getEditor('editor', {
        serverUrl: '/admin/ueditor_upload.php?document_id=' + document_id,
        UEDITOR_HOME_URL: '/admin/assets/ueditorplus/',
        initialFrameWidth: '100%',
        initialFrameHeight: 400
    });
</script>
```

##### 2. 文档编辑
在 `admin/documents/edit.php` 中使用UEditor Plus编辑现有文档：
```php
<!-- 预填充内容 -->
<script>
    var ue = UE.getEditor('editor', {
        serverUrl: '/admin/ueditor_upload.php?document_id=' + <?php echo $document['id']; ?>,
        initialContent: '<?php echo addslashes($document['content']); ?>',
        // 其他配置...
    });
</script>
```

##### 3. 文件上传配置
上传处理在 `admin/ueditor_upload.php` 中实现：
- 支持图片、文件、视频等多种类型上传
- 自动关联到对应的document_id
- 支持文件描述和分类管理

#### 技术实现

##### 文件结构
```
admin/assets/ueditorplus/
├── ueditor.config.js          # 编辑器配置文件
├── ueditor.all.js            # 编辑器核心文件
├── ueditor.all.min.js        # 压缩版核心文件
├── themes/                   # 主题样式
├── lang/                     # 语言包
├── dialogs/                  # 弹出框组件
└── third-party/              # 第三方插件
```

##### 配置示例
```javascript
window.UEDITOR_CONFIG = {
    serverUrl: '/admin/ueditor_upload.php',
    UEDITOR_HOME_URL: '/admin/assets/ueditorplus/',
    toolbars: [
        ['fullscreen', 'source', '|', 'undo', 'redo', '|',
         'bold', 'italic', 'underline', 'fontborder', 'strikethrough', '|',
         'forecolor', 'backcolor', 'insertorderedlist', 'insertunorderedlist', '|',
         'rowspacingtop', 'rowspacingbottom', 'lineheight', '|',
         'customstyle', 'paragraph', 'fontfamily', 'fontsize', '|',
         'directionalityltr', 'directionalityrtl', 'indent', '|',
         'justifyleft', 'justifycenter', 'justifyright', 'justifyjustify', '|',
         'touppercase', 'tolowercase', '|',
         'link', 'unlink', 'anchor', '|', 'imagenone', 'imageleft', 'imageright', 'imagecenter', '|',
         'insertimage', 'emotion', 'scrawl', 'insertvideo', 'music', 'attachment', '|',
         'horizontal', 'date', 'time', 'spechars', '|',
         'inserttable', 'deletetable', 'insertparagraphbeforetable', 'insertrow', 'deleterow', 'insertcol', 'deletecol', 'mergecells', 'mergeright', 'mergedown', 'splittocells', 'splittorows', 'splittocols', '|',
         'print', 'preview', 'searchreplace']
    ],
    initialFrameWidth: '100%',
    initialFrameHeight: 400,
    autoHeightEnabled: false,
    elementPathEnabled: false,
    wordCount: true,
    maximumWords: 10000
};
```

#### 项目链接
- **GitHub**: [https://github.com/modstart-lib/ueditor-plus](https://github.com/modstart-lib/ueditor-plus)
- **在线演示**: [https://open-demo.modstart.com/ueditor-plus/_examples/](https://open-demo.modstart.com/ueditor-plus/_examples/)
- **使用文档**: [https://open-doc.modstart.com/ueditor-plus](https://open-doc.modstart.com/ueditor-plus)

#### 集成优势
- **零外部依赖** - 所有资源已本地化部署
- **无缝集成** - 与现有文档管理系统完美融合
- **功能丰富** - 支持Word导入、Markdown导入等高级功能
- **响应式设计** - 适配各种屏幕尺寸
- **性能优化** - 压缩资源文件，加载速度更快

## 贡献指南

欢迎提交 Issue 和 Pull Request！

## 许可证

MIT License