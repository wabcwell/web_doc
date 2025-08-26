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
    $stmt = $db->prepare("SELECT * FROM documents WHERE id = ? AND is_public = 1");
    $stmt->execute([$document_id]);
    $current_document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_document) {
        $content = $current_document['content'];
        $title = $current_document['title'];
    }
}

// 如果没有选择文档，获取第一篇公开文档
if (!$current_document && !empty($tree)) {
    $stmt = $db->prepare("SELECT * FROM documents WHERE is_public = 1 ORDER BY sort_order DESC, created_at ASC LIMIT 1");
    $stmt->execute();
    $current_document = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current_document) {
        $content = $current_document['content'];
        $title = $current_document['title'];
        $document_id = $current_document['id'];
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
    $content = $parsedown->text($content);
}

// 统计信息
$stmt = $db->query("SELECT COUNT(*) as total_docs FROM documents WHERE is_public = 1");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - 文档系统</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 300px;
            --header-height: 60px;
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
            overflow-y: auto;
            z-index: 1000;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            background: #fff;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            background: #fff;
        }

        .sidebar-content {
            padding: 0;
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
            padding: 10px 40px 5px 40px;
            border-bottom: 1px solid #e9ecef;
            background: #fff;
        }

        .content-body {
            padding: 15px 40px 40px 40px;
            max-width: none;
        }

        .document-meta {
            margin-bottom: 0.5em;
            padding: 5px 0;
            color: #6c757d;
            font-size: 14px;
            border-bottom: none;
        }

        .markdown-content {
            line-height: 1.6;
            color: #212529;
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



        .stats-badge {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 5px;
        }

        .search-box {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
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
            }

            .content-body {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- 左侧文档列表 -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0">
                <i class="bi bi-journal-text"></i> 文档中心
                <span class="stats-badge"><?php echo $stats['total_docs']; ?></span>
            </h5>
            <p class="text-muted small mb-0">浏览所有公开文档</p>
        </div>

        <div class="search-box">
            <input type="text" class="search-input" id="searchInput" placeholder="搜索文档...">
        </div>

        <div class="sidebar-content">
            <div class="document-tree">
                <?php
                function render_document_tree($documents, $level = 0) {
                    foreach ($documents as $doc) {
                        $active = isset($_GET['document']) && $_GET['document'] == $doc['id'] ? 'active' : '';
                        $indent_class = $level > 0 ? 'level-' . $level : '';
                        $has_children = !empty($doc['children']);
                        
                        echo '<div class="document-node" data-document-id="' . $doc['id'] . '">';
                        echo '<a href="?document=' . $doc['id'] . '" ';
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
                     最后更新：<?php echo date('Y年m月d日 H:i', strtotime($current_document['updated_at'])); ?>
                     <?php if ($current_document['tags']): ?>
                         <span class="ms-3">
                             <i class="bi bi-tags"></i>
                             <?php echo htmlspecialchars($current_document['tags']); ?>
                         </span>
                     <?php endif; ?>
                 </div>
                 <?php if ($current_document): ?>
                     <div>
                         <a href="export.php?id=<?php echo $current_document['id']; ?>&format=html" class="btn btn-outline-primary btn-sm">
                             <i class="bi bi-download"></i> 导出
                         </a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-python.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-php.min.js"></script>
    <script>
        // 搜索功能
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const documents = document.querySelectorAll('.document-item');
            
            documents.forEach(doc => {
                const title = doc.getAttribute('data-title').toLowerCase();
                if (title.includes(searchTerm)) {
                    doc.style.display = 'block';
                    // 如果匹配，展开其父级
                    const parent = doc.closest('.document-node');
                    if (parent) {
                        const parentId = parent.getAttribute('data-document-id');
                        const childrenContainer = document.getElementById('children-' + parentId);
                        if (childrenContainer) {
                            childrenContainer.classList.remove('collapsed');
                            const toggle = parent.querySelector('.document-toggle');
                            if (toggle) {
                                toggle.classList.remove('collapsed');
                                toggle.classList.add('expanded');
                            }
                        }
                    }
                } else {
                    doc.style.display = 'none';
                }
            });
        });

        // 展开/收起功能
        document.addEventListener('DOMContentLoaded', function() {
            // 为所有展开/收起箭头添加点击事件
            document.querySelectorAll('.document-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const documentId = this.getAttribute('data-toggle');
                    const childrenContainer = document.getElementById('children-' + documentId);
                    
                    if (childrenContainer) {
                        // 切换收起/展开状态
                        childrenContainer.classList.toggle('collapsed');
                        this.classList.toggle('collapsed');
                        this.classList.toggle('expanded');
                        
                        // 更新图标
                        const icon = this.querySelector('i');
                        if (childrenContainer.classList.contains('collapsed')) {
                            icon.className = 'bi bi-chevron-right';
                        } else {
                            icon.className = 'bi bi-chevron-down';
                        }
                    }
                });
            });

            // 所有文档默认已展开，无需额外处理
        });

        // 展开到当前文档路径的功能已移除（所有文档默认展开）

        // 移动端菜单切换
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

        // 代码高亮
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof Prism !== 'undefined') {
                Prism.highlightAll();
            }
        });

        // 键盘导航
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('searchInput').value = '';
                document.getElementById('searchInput').dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>