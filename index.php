<?php
require_once 'config.php';
require_once 'includes/DocumentTree.php';

// 获取数据库连接
$db = get_db();
$documentTree = new DocumentTree($db);

// 获取当前文档ID
$document_id = isset($_GET['document']) ? intval($_GET['document']) : 0;

// 获取文档树
$tree = $documentTree->getTree();

// 获取当前文档内容
$current_document = null;
$content = '';
$title = '文档中心';

if ($document_id > 0) {
    $stmt = $db->prepare("SELECT * FROM documents WHERE document_id = ? AND is_public = 1 AND is_formal = 1 AND del_status = 0");
    $stmt->execute([$document_id]);
    $current_document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_document) {
        $content = $current_document['content'];
        $title = $current_document['title'];
    }
}

// 如果没有选择文档，获取第一个顶级公开且正式的文档（权重值最小）
if (!$current_document) {
    $stmt = $db->prepare("SELECT * FROM documents WHERE is_public = 1 AND is_formal = 1 AND del_status = 0 AND parent_id = 0 ORDER BY sort_order ASC, created_at ASC LIMIT 1");
    $stmt->execute();
    $current_document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_document) {
        $content = $current_document['content'];
        $title = $current_document['title'];
        $document_id = $current_document['document_id'];
    }
}

// 使用本地Parsedown渲染Markdown
require_once 'Parsedown.php';
// 初始化Markdown解析器
$parsedown = new Parsedown();

// 获取所有文档用于构建树
// 获取文档树由DocumentTree类处理
$documents = [];

if ($content) {
    // 智能判断内容格式：如果以HTML标签开头，则不进行Markdown转换
    $trimmed_content = trim($content);
    if (empty($trimmed_content)) {
        $content = '';
    } elseif (preg_match('/^\s*<[^>]+>/', $trimmed_content)) {
        // 内容以HTML标签开头，直接显示（无需赋值）
    } else {
        // 内容可能是Markdown格式，进行转换
        $content = $parsedown->text($content);
    }
}

// 统计信息
$stmt = $db->query("SELECT COUNT(*) as total_docs FROM documents WHERE is_public = 1 AND is_formal = 1 AND del_status = 0");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - 文档系统</title>
    <link href="assets/css/static/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/static/bootstrap-icons.min.css" rel="stylesheet">
    <link href="admin/assets/ueditorplus/third-party/SyntaxHighlighter/shCoreDefault.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 110px;
            --search-height: 70px;
            --header-total-height: calc(var(--header-height) + var(--search-height));
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: #fff;
            border-right: 1px solid #e9ecef;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar.collapsed {
            transform: translateX(-100%);
        }

        .sidebar-toggle {
            position: fixed;
            top: 160px;
            left: 260px;
            z-index: 1001;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid #e9ecef;
            border-radius: 0 4px 4px 0;
            padding: 8px 8px;
            cursor: pointer;
            transition: left 0.3s ease, opacity 0.2s ease, visibility 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            opacity: 0;
            visibility: hidden;
            height: 37px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-toggle.visible {
            opacity: 1;
            visibility: visible;
        }

        .sidebar-toggle.collapsed {
            left: 0px;
        }

        /* 当侧边栏收起时，按钮始终显示 */
        .sidebar-toggle.always-visible {
            opacity: 1;
            visibility: visible;
        }

        .sidebar-toggle:hover {
            background: rgba(248, 249, 250, 0.95);
        }

        .sidebar-toggle i {
            font-size: 12px;
            color: #6c757d;
            line-height: 1;
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f8f9fa;
            width: calc(100vw - 260px);
            overflow-x: hidden;
        }

        .main-content.expanded {
            margin-left: 0;
            width: 100vw;
        }



        .sidebar-header {
            padding: 15px 20px;
            background: #fff;
            flex-shrink: 0;
            height: var(--header-height);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            text-align: left;
        }

        .sidebar-header h5 {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .search-box {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            background: #fff;
            flex-shrink: 0;
            height: var(--search-height);
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            position: relative;
        }

        .sidebar-content::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar-content::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .sidebar-content::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .sidebar-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* 导出菜单响应式样式 */
        @media (max-width: 1000px) {
            #exportMenu {
                min-width: 110px;
                max-width: 120px;
            }
            
            #exportMenu .dropdown-item {
                font-size: 13px;
                padding: 8px 10px;
            }
        }

        @media (max-width: 768px) {
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .content-header .btn-group {
                width: 100%;
                justify-content: flex-start;
            }
            
            #exportMenu {
                min-width: 100px;
                max-width: 110px;
                font-size: 12px;
            }
        }

        .document-tree {
            padding: 10px 0;
        }

        .document-item {
            display: block;
            padding: 8px 20px 8px 8px;
            text-decoration: none;
            color: #495057;
            font-size: 14px;
            line-height: 1.5;
            border-left: 2px solid transparent;
            transition: all 0.2s ease;
        }

        .document-item:hover {
            background-color: #f8f9fa;
            color: #007bff;
            border-left-color: #007bff;
        }

        .document-item.active {
            background-color: #e7f3ff;
            color: #007bff;
            border-left-color: #007bff;
            font-weight: 500;
        }

        .document-item.level-1 {
            padding-left: 30px;
        }

        .document-item.level-2 {
            padding-left: 45px;
        }

        .document-item.level-3 {
            padding-left: 60px;
        }

        .document-item.level-4 {
            padding-left: 75px;
        }

        .document-toggle {
            cursor: pointer;
            color: #6c757d;
            transition: transform 0.2s ease;
            display: inline-block;
            width: 16px;
            text-align: center;
            margin-right: 4px;
        }

        .document-toggle:hover {
            color: #007bff;
        }

        .document-toggle.collapsed {
            transform: rotate(-90deg);
        }

        .document-toggle.expanded {
            transform: rotate(0deg);
        }

        .document-children {
            transition: max-height 0.3s ease;
            overflow: hidden;
        }

        .document-children.collapsed {
            max-height: 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .content-header {
            padding: 30px 30px 15px 30px;
            border-bottom: 1px solid #e9ecef;
            background: #fff;
            width: clamp(500px, calc(100% - 60px), 1080px);
            margin: 0 auto;
        }

        .content-body {
            padding: 15px 30px 40px 30px;
            width: clamp(500px, calc(100% - 60px), 1080px);
            margin: 0 auto;
            background: #fff;
            min-height: 1080px;
            box-sizing: border-box;
        }

        .document-meta {
            margin-bottom: 0.5em;
            padding: 5px 0;
            color: #6c757d;
            font-size: 14px;
            border-bottom: none;
        }

        .markdown-content {
            max-width: 100%;
            overflow-wrap: break-word;
            word-wrap: break-word;
            hyphens: auto;
        }

        .markdown-content img {
            max-width: 100%;
            height: auto;
        }

        .markdown-content table {
            max-width: 100%;
            overflow-x: auto;
            display: block;
        }

        .markdown-content pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 16px;
            overflow-x: auto;
            max-width: 100%;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .markdown-content p {
            margin-top: 0.5em;
            margin-bottom: 0.5em;
        }

        .markdown-content ul,
        .markdown-content ol {
            margin-top: 0.5em;
            margin-bottom: 0.5em;
            padding-left: 2em;
        }

        .markdown-content li {
            margin-top: 0.2em;
            margin-bottom: 0.2em;
        }

        .markdown-content pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 12px;
            margin-top: 0.5em;
            margin-bottom: 0.5em;
            overflow-x: auto;
        }

        .markdown-content code {
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 85%;
        }

        .markdown-content pre code {
            background: none;
            padding: 0;
            border-radius: 0;
        }



        .markdown-content h1 {
            margin-top: 0.8em;
            margin-bottom: 1em;
            font-weight: 600;
            color: #212529;
            font-size: 2em;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.3em;
        }

        .markdown-content h2 {
            margin-top: 0.8em;
            margin-bottom: 1em;
            font-weight: 600;
            color: #212529;
            font-size: 1.5em;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 0.3em;
        }

        .markdown-content h3 {
            margin-top: 0.8em;
            margin-bottom: 0.8em;
            font-weight: 600;
            color: #212529;
            font-size: 1.25em;
        }

        .markdown-content h4 {
            margin-top: 0.8em;
            margin-bottom: 0.6em;
            font-weight: 600;
            color: #212529;
            font-size: 1.1em;
        }

        .markdown-content h5 {
            margin-top: 0.8em;
            margin-bottom: 0.6em;
            font-weight: 600;
            color: #212529;
            font-size: 1em;
        }

        .markdown-content h6 {
            margin-top: 0.8em;
            margin-bottom: 0.6em;
            font-weight: 600;
            color: #212529;
            font-size: 0.9em;
        }

        .markdown-content pre {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 6px;
            padding: 16px;
            overflow-x: auto;
            margin-top: 0.5em;
            margin-bottom: 0.5em;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .markdown-content code {
            background: #f1f3f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 85%;
        }

        .markdown-content pre code {
            background: none;
            padding: 0;
            border-radius: 0;
        }

        /* Prism.js样式 */
        pre[class*="language-"] {
            font-size: 14px;
            line-height: 1.5;
            margin: 15px 0;
            border-radius: 6px;
            border: 1px solid #e1e5e9;
            background-color: #f8f9fa;
            padding: 15px;
            overflow-x: auto;
        }
        
        code[class*="language-"] {
            color: #333;
            font-family: 'Consolas', 'Monaco', 'Lucida Console', 'Liberation Mono', 'DejaVu Sans Mono', 'Bitstream Vera Sans Mono', 'Courier New', monospace;
        }
        
        /* SyntaxHighlighter 样式调整 */
        .syntaxhighlighter {
            border-radius: 8px;
            font-size: 14px !important;
            line-height: 1.6 !important;
            margin: 1em 0 !important;
            overflow-x: auto;
            position: relative;
        }

        .syntaxhighlighter table {
            border-radius: 8px;
        }

        .syntaxhighlighter .line {
            font-size: 14px !important;
            line-height: 1.6 !important;
        }

        /* 复制按钮样式 */
        .code-block-wrapper {
            position: relative;
            margin: 1em 0;
        }

        .copy-button {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 4px 8px;
            font-size: 12px;
            cursor: pointer;
            z-index: 10;
            transition: all 0.2s ease;
            opacity: 0.7;
        }

        .copy-button:hover {
            background: rgba(255, 255, 255, 1);
            border-color: #007bff;
            opacity: 1;
        }

        .copy-button.copied {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .copy-button i {
            margin-right: 2px;
        }



        .stats-badge {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 5px;
        }

        .search-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0,123,255,.25);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .main-content {
                margin-left: 0;
                width: 100vw;
            }

            .content-body {
                padding: 20px;
                width: 100%;
                margin: 0;
                box-sizing: border-box;
            }

            .content-header {
                width: 100%;
                margin: 0;
                padding: 20px 20px 10px 20px;
                box-sizing: border-box;
            }
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle" title="收起/展开目录">
        <i class="bi bi-chevron-left" id="toggleIcon"></i>
    </button>

    <!-- 左侧文档列表 -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0">
                <?php if ($logo_type === 'text'): ?>
                    <i class="bi bi-journal-text"></i> <?php echo htmlspecialchars($site_name); ?>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($logo_path); ?>" alt="Logo" style="max-height: 30px; max-width: 150px; vertical-align: middle;">
                <?php endif; ?>
                <span class="stats-badge"><?php echo $stats['total_docs']; ?></span>
            </h5>
            <p class="text-muted small mb-0"><?php echo htmlspecialchars($site_subtitle ?? '简洁高效的文档管理系统'); ?></p>
        </div>

        <div class="search-box">
            <input type="text" class="search-input" id="searchInput" placeholder="搜索文档...">
        </div>

        <div class="sidebar-content">
            <div class="document-tree">
                <?php
                function render_document_tree($documents, $level = 0) {
                    foreach ($documents as $doc) {
                        $active = isset($_GET['document']) && $_GET['document'] == $doc['document_id'] ? 'active' : '';
                        $indent_class = $level > 0 ? 'level-' . $level : '';
                        $has_children = !empty($doc['children']);
                        
                        echo '<div class="document-node" data-document-id="' . $doc['document_id'] . '">';
                        echo '<a href="?document=' . $doc['document_id'] . '" ';
                        echo 'class="document-item ' . $active . ' ' . $indent_class . '" ';
                        echo 'data-title="' . htmlspecialchars($doc['title']) . '">';
                        
                        // 添加展开/收起箭头（默认展开）
                        if ($has_children) {
                            echo '<span class="document-toggle expanded" data-toggle="' . $doc['id'] . '">';
                            echo '<i class="bi bi-chevron-down"></i>';
                            echo '</span>';
                        } else {
                            echo '<span style="display: inline-block; width: 16px; margin-right: 4px;"></span>';
                        }
                        
                        echo '<i class="bi bi-file-text"></i> ';
                        echo htmlspecialchars($doc['title']);
                        echo '</a>';
                        
                        // 子文档容器（默认展开）
                        if ($has_children) {
                            echo '<div class="document-children" id="children-' . $doc['id'] . '">';
                            render_document_tree($doc['children'], $level + 1);
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }
                }
                
                if (!empty($tree)) {
                    render_document_tree($tree);
                } else {
                    echo '<div class="empty-state">
                            <i class="bi bi-inbox" style="font-size: 48px; color: #dee2e6;"></i>
                            <p class="mt-3">暂无公开文档</p>
                          </div>';
                }
                ?>
            </div>
        </div>
    </div>

    <!-- 右侧内容区域 -->
    <div class="main-content">
        <div class="content-header">
             <div class="d-flex justify-content-between align-items-center">
                 <div class="document-meta mb-0 flex-grow-1">
                     <i class="bi bi-clock"></i>
                     最后更新：<?php echo $current_document ? date('Y年m月d日 H:i', strtotime($current_document['updated_at'])) : '暂无数据'; ?>
                     <?php if ($current_document && $current_document['tags']): ?>
                         <span class="ms-3">
                             <i class="bi bi-tags"></i>
                             <?php echo htmlspecialchars($current_document['tags']); ?>
                         </span>
                     <?php endif; ?>
                 </div>
                 <?php if ($current_document): ?>
                     <button type="button" class="btn btn-outline-primary btn-sm" onclick="showExportMenu()">
                         <i class="bi bi-download"></i> 导出
                     </button>
                     
                     <!-- 导出菜单 -->
                     <div id="exportMenu" style="display: none; position: absolute; z-index: 1000; background: white; border: 1px solid #e9ecef; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 4px; min-width: 120px; width: auto;">
                         <div style="padding: 2px 0;">
                             <button type="button" class="dropdown-item" onclick="exportToPDF()" style="display: block; width: 100%; padding: 10px 12px; color: #495057; background: none; border: none; text-align: left; cursor: pointer; border-radius: 4px; font-size: 14px; transition: background-color 0.15s ease;">
                                 <i class="bi bi-file-earmark-pdf" style="margin-right: 8px; color: #6c757d;"></i> PDF
                             </button>
                             <button type="button" class="dropdown-item" onclick="exportToMarkdown()" style="display: block; width: 100%; padding: 10px 12px; color: #495057; background: none; border: none; text-align: left; cursor: pointer; border-radius: 4px; font-size: 14px; transition: background-color 0.15s ease;">
                                <i class="bi bi-file-earmark-text" style="margin-right: 8px; color: #6c757d;"></i> Markdown
                            </button>
                             <button type="button" class="dropdown-item" onclick="exportToHTML()" style="display: block; width: 100%; padding: 10px 12px; color: #495057; background: none; border: none; text-align: left; cursor: pointer; border-radius: 4px; font-size: 14px; transition: background-color 0.15s ease;">
                                 <i class="bi bi-file-earmark-code" style="margin-right: 8px; color: #6c757d;"></i> HTML
                             </button>
                             <hr style="margin: 4px 8px; border: 0; border-top: 1px solid #f8f9fa;">
                             <button type="button" class="dropdown-item" onclick="(function(){ exportAsImage('png'); hideExportMenu(); })();" style="display: block; width: 100%; padding: 10px 12px; color: #495057; background: none; border: none; text-align: left; cursor: pointer; border-radius: 4px; font-size: 14px; transition: background-color 0.15s ease;">
                                 <i class="bi bi-image" style="margin-right: 8px; color: #6c757d;"></i> PNG
                             </button>
                             <button type="button" class="dropdown-item" onclick="(function(){ exportAsImage('jpg'); hideExportMenu(); })();" style="display: block; width: 100%; padding: 10px 12px; color: #495057; background: none; border: none; text-align: left; cursor: pointer; border-radius: 4px; font-size: 14px; transition: background-color 0.15s ease;">
                                 <i class="bi bi-file-earmark-image" style="margin-right: 8px; color: #6c757d;"></i> JPG
                             </button>
                         </div>
                     </div>
                 <?php endif; ?>
             </div>
         </div>

        <div class="content-body">
            <?php if ($content): ?>
                <div class="markdown-content">
                <?php echo $content; ?>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-file-earmark-text" style="font-size: 64px; color: #dee2e6;"></i>
                    <h4 class="mt-4">欢迎使用文档中心</h4>
                    <p class="text-muted">请从左侧选择一篇文档开始阅读</p>
                    
                    <?php if ($stats['total_docs'] > 0): ?>
                        <p class="text-muted">共有 <?php echo $stats['total_docs']; ?> 篇公开文档可供阅读</p>
                    <?php else: ?>
                        <p class="text-muted">当前没有公开的文档</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/static/bootstrap.bundle.min.js"></script>
    <!-- 使用Prism.js替代SyntaxHighlighter -->
    <script src="admin/assets/ueditorplus/third-party/SyntaxHighlighter/shCore.js"></script>
    <script>
        SyntaxHighlighter.all();
    </script>
    <script>
        // 将SyntaxHighlighter格式转换为Prism.js格式
        function convertAndHighlightCode() {
            console.log('Starting code highlighting conversion...');
            
            // 查找所有使用SyntaxHighlighter格式的pre标签
            const pres = document.querySelectorAll('pre[class*="brush:"]');
            console.log('Found', pres.length, 'code blocks to convert');
            
            if (pres.length === 0) {
                console.log('No SyntaxHighlighter format found, checking for other formats...');
                return;
            }
            
            pres.forEach((pre, index) => {
                console.log('Processing block', index + 1, 'class:', pre.className);
                
                const className = pre.className;
                const match = className.match(/brush:\s*(\w+)/);
                if (match) {
                    const language = match[1].toLowerCase();
                    console.log('Converting to language:', language);
                    
                    // 获取原始内容 - 优先使用textContent避免HTML实体问题
                    let content = pre.textContent || pre.innerText || '';
                    
                    // 清理内容：替换所有类型的空格和HTML实体
                    content = content
                        .replace(/&nbsp;/g, ' ')
                        .replace(/\u00A0/g, ' ')
                        .replace(/&amp;/g, '&')
                        .replace(/&lt;/g, '<')
                        .replace(/&gt;/g, '>')
                        .replace(/&quot;/g, '"')
                        .replace(/&#39;/g, "'")
                        .trim();
                    
                    console.log('Content length after cleanup:', content.length);
                    
                    // 替换class为Prism.js格式
                    pre.className = `language-${language}`;
                    
                    // 确保有code标签
                    if (!pre.querySelector('code')) {
                        const code = document.createElement('code');
                        code.className = `language-${language}`;
                        code.textContent = content;
                        pre.innerHTML = '';
                        pre.appendChild(code);
                    }
                }
            });
            
            // 初始化Prism.js
            setTimeout(() => {
                if (typeof Prism !== 'undefined') {
                    console.log('Prism loaded, highlighting...');
                    Prism.highlightAll();
                    console.log('Highlighting completed');
                } else {
                    console.error('Prism not loaded');
                }
            }, 100);
        }

        // 确保在DOM加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOMContentLoaded fired');
            // 延迟执行，确保所有内容都已加载
            setTimeout(convertAndHighlightCode, 500);
        });

        // 对于动态内容，也执行转换
        window.addEventListener('load', function() {
            console.log('window.load fired');
            setTimeout(convertAndHighlightCode, 1000);
        });

        // 对于AJAX加载的内容，提供一个手动触发的方法
        window.refreshCodeHighlighting = function() {
            console.log('Manual refresh triggered');
            convertAndHighlightCode();
        };
    </script>
    <script src="assets/js/static/third-party/html2canvas.min.js"></script>
    <script src="assets/js/static/third-party/jspdf.umd.min.js"></script>
    <script src="assets/js/static/third-party/html2pdf.bundle.min.js"></script>
    <script src="assets/js/export/export-functions.js"></script>
    <script>
        // 添加复制按钮功能
        function addCopyButtons() {
            // 查找所有代码块
            const codeBlocks = document.querySelectorAll('.syntaxhighlighter, pre[class*="language-"], .markdown-content pre');
            
            codeBlocks.forEach(block => {
                // 避免重复添加
                if (block.parentNode.classList.contains('code-block-wrapper')) {
                    return;
                }
                
                // 创建包装器
                const wrapper = document.createElement('div');
                wrapper.className = 'code-block-wrapper';
                
                // 创建复制按钮
                const copyButton = document.createElement('button');
                copyButton.className = 'copy-button';
                copyButton.innerHTML = '<i class="bi bi-clipboard"></i> 复制';
                copyButton.title = '复制代码';
                
                // 插入包装器
                block.parentNode.insertBefore(wrapper, block);
                wrapper.appendChild(block);
                wrapper.appendChild(copyButton);
                
                // 添加点击事件
                copyButton.addEventListener('click', function() {
                    let codeText = '';
                    
                    // 根据不同类型获取代码内容
                    if (block.classList.contains('syntaxhighlighter')) {
                        // SyntaxHighlighter
                        const lines = block.querySelectorAll('.line');
                        codeText = Array.from(lines).map(line => line.textContent).join('\n');
                    } else if (block.querySelector('code')) {
                        // Prism.js 或其他带code标签的
                        codeText = block.querySelector('code').textContent;
                    } else {
                        // 普通pre标签
                        codeText = block.textContent;
                    }
                    
                    // 复制到剪贴板
                    navigator.clipboard.writeText(codeText.trim()).then(() => {
                        // 显示成功状态
                        copyButton.innerHTML = '<i class="bi bi-check"></i> 已复制';
                        copyButton.classList.add('copied');
                        
                        // 2秒后恢复原状
                        setTimeout(() => {
                            copyButton.innerHTML = '<i class="bi bi-clipboard"></i> 复制';
                            copyButton.classList.remove('copied');
                        }, 2000);
                    }).catch(err => {
                        console.error('复制失败:', err);
                        // 备用复制方法
                        const textArea = document.createElement('textarea');
                        textArea.value = codeText.trim();
                        document.body.appendChild(textArea);
                        textArea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textArea);
                        
                        copyButton.innerHTML = '<i class="bi bi-check"></i> 已复制';
                        copyButton.classList.add('copied');
                        setTimeout(() => {
                            copyButton.innerHTML = '<i class="bi bi-clipboard"></i> 复制';
                            copyButton.classList.remove('copied');
                        }, 2000);
                    });
                });
            });
        }

        // 在页面加载完成后添加复制按钮
        document.addEventListener('DOMContentLoaded', function() {
            // 延迟执行，确保代码块已渲染完成
            setTimeout(addCopyButtons, 1000);
            
            // 为动态内容也添加复制按钮
            window.addCopyButtons = addCopyButtons;
        });

        // 监听内容变化，为新增代码块添加复制按钮
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList') {
                    let hasCodeBlocks = false;
                    mutation.addedNodes.forEach(function(node) {
                        if (node.nodeType === 1) { // 元素节点
                            if (node.matches && (node.matches('.syntaxhighlighter, pre[class*="language-"], .markdown-content pre') || 
                                node.querySelector && node.querySelector('.syntaxhighlighter, pre[class*="language-"], .markdown-content pre'))) {
                                hasCodeBlocks = true;
                            }
                        }
                    });
                    
                    if (hasCodeBlocks) {
                        setTimeout(addCopyButtons, 500);
                    }
                }
            });
        });

        // 开始监听
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    </script>
</body>
</html>
