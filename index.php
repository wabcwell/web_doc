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

// 如果没有选择文档，获取第一个顶级公开文档（权重值最小）
if (!$current_document) {
    $stmt = $db->prepare("SELECT * FROM documents WHERE is_public = 1 AND parent_id = 0 ORDER BY sort_order ASC, created_at ASC LIMIT 1");
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
    <link href="assets/css/static/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/static/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/static/prism.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 85px;
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
            padding: 15px 20px 10px 20px;
            border-bottom: 1px solid #e9ecef;
            background: #fff;
            flex-shrink: 0;
            height: var(--header-height);
            display: flex;
            flex-direction: column;
            justify-content: center;
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

        .content-header {
            width: clamp(500px, calc(100% - 60px), 1080px);
            margin: 0 auto;
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
                     <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                     <i class="bi bi-download"></i> 导出
                 </button>
                 
                 <!-- 导出格式选择模态框 -->
                 <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
                     <div class="modal-dialog modal-sm">
                         <div class="modal-content">
                             <div class="modal-header">
                                 <h5 class="modal-title" id="exportModalLabel">选择导出格式</h5>
                                 <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                             </div>
                             <div class="modal-body">
                                 <div class="d-grid gap-2">
                                     <a href="export.php?id=<?php echo $current_document['id']; ?>&format=pdf" target="_blank" class="btn btn-outline-danger">
                                         <i class="bi bi-file-earmark-pdf"></i> PDF格式
                                     </a>
                                     <a href="export.php?id=<?php echo $current_document['id']; ?>&format=md" target="_blank" class="btn btn-outline-primary">
                                         <i class="bi bi-file-earmark-text"></i> Markdown格式
                                     </a>
                                     <a href="export.php?id=<?php echo $current_document['id']; ?>&format=html" target="_blank" class="btn btn-outline-success">
                                         <i class="bi bi-file-earmark-code"></i> HTML格式
                                     </a>
                                     <hr>
                                     <button type="button" class="btn btn-outline-info" onclick="exportAsImage('png')" data-bs-dismiss="modal">
                                         <i class="bi bi-image"></i> PNG图片
                                     </button>
                                     <button type="button" class="btn btn-outline-warning" onclick="exportAsImage('jpg')" data-bs-dismiss="modal">
                                         <i class="bi bi-file-earmark-image"></i> JPG图片
                                     </button>
                                 </div>
                             </div>
                         </div>
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
    <script src="assets/js/static/prism.min.js"></script>
    <script src="assets/js/static/prism-javascript.min.js"></script>
    <script src="assets/js/static/prism-python.min.js"></script>
    <script src="assets/js/static/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        // 侧边栏收起/展开功能
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const toggleIcon = document.getElementById('toggleIcon');
            let hideTimer = null;

            // 从localStorage读取侧边栏状态
            let isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

            // 初始化侧边栏状态
            function initSidebarState() {
                if (isSidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                    toggleIcon.className = 'bi bi-chevron-right';
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                    toggleIcon.className = 'bi bi-chevron-left';
                }
                // 更新按钮显示状态
                updateButtonVisibility();
            }

            // 显示收起按钮
            function showToggleButton() {
                if (!isSidebarCollapsed) {
                    sidebarToggle.classList.add('visible');
                }
                // 清除之前的计时器
                if (hideTimer) {
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }
            }

            // 隐藏收起按钮
            function hideToggleButton() {
                if (!isSidebarCollapsed) {
                    hideTimer = setTimeout(() => {
                        sidebarToggle.classList.remove('visible');
                    }, 2000);
                }
            }

            // 更新按钮显示状态
            function updateButtonVisibility() {
                if (isSidebarCollapsed) {
                    // 收起状态下始终显示
                    sidebarToggle.classList.add('always-visible');
                    sidebarToggle.classList.remove('visible');
                } else {
                    // 展开状态下使用悬停显示
                    sidebarToggle.classList.remove('always-visible');
                }
            }

            // 切换侧边栏状态
            sidebarToggle.addEventListener('click', function() {
                isSidebarCollapsed = !isSidebarCollapsed;
                
                if (isSidebarCollapsed) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                    toggleIcon.className = 'bi bi-chevron-right';
                } else {
                    sidebar.classList.remove('collapsed');
                    mainContent.classList.remove('expanded');
                    sidebarToggle.classList.remove('collapsed');
                    toggleIcon.className = 'bi bi-chevron-left';
                }
                
                // 更新按钮显示状态
                updateButtonVisibility();
                
                // 保存状态到localStorage
                localStorage.setItem('sidebarCollapsed', isSidebarCollapsed);
            });

            // 鼠标悬停事件监听（仅在展开状态下有效）
            sidebar.addEventListener('mouseenter', showToggleButton);
            sidebar.addEventListener('mouseleave', hideToggleButton);
            
            // 收起按钮自身的悬停处理（仅在展开状态下有效）
            sidebarToggle.addEventListener('mouseenter', showToggleButton);
            sidebarToggle.addEventListener('mouseleave', hideToggleButton);

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

            // 页面加载时初始化侧边栏状态
            initSidebarState();

            // 所有文档默认已展开，无需额外处理
        });

        // 展开到当前文档路径的功能已移除（所有文档默认展开）

        // 移动端菜单切换
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('show');
        }

        // 图片导出功能
        function exportAsImage(format) {
            const contentElement = document.querySelector('.content-body');
            const title = document.querySelector('.content-header h1')?.textContent || 'document';
            
            if (!contentElement) {
                alert('无法找到文档内容');
                return;
            }

            // 显示加载提示
            const loadingToast = document.createElement('div');
            loadingToast.style.cssText = `
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: rgba(0,0,0,0.8);
                color: white;
                padding: 20px;
                border-radius: 8px;
                z-index: 9999;
                font-family: Arial, sans-serif;
            `;
            loadingToast.innerHTML = '<i class="bi bi-hourglass-split"></i> 正在生成图片...';
            document.body.appendChild(loadingToast);

            // 临时样式调整
            const originalOverflow = contentElement.style.overflow;
            contentElement.style.overflow = 'visible';

            html2canvas(contentElement, {
                backgroundColor: '#ffffff',
                scale: 2,
                useCORS: true,
                allowTaint: true,
                width: Math.min(contentElement.scrollWidth, 1200),
                height: contentElement.scrollHeight
            }).then(canvas => {
                // 恢复原始样式
                contentElement.style.overflow = originalOverflow;
                
                // 移除加载提示
                document.body.removeChild(loadingToast);

                // 创建下载链接
                const link = document.createElement('a');
                link.download = `${title}.${format}`;
                
                if (format === 'jpg') {
                    // 转换为JPEG
                    const imgData = canvas.toDataURL('image/jpeg', 0.9);
                    link.href = imgData;
                } else {
                    // PNG格式
                    const imgData = canvas.toDataURL('image/png');
                    link.href = imgData;
                }
                
                // 触发下载
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
            }).catch(error => {
                // 恢复原始样式
                contentElement.style.overflow = originalOverflow;
                
                // 移除加载提示
                if (document.body.contains(loadingToast)) {
                    document.body.removeChild(loadingToast);
                }
                
                console.error('导出失败:', error);
                alert('图片导出失败，请重试');
            });
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