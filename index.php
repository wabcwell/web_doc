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
    <link href="assets/css/index.css" rel="stylesheet">
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
    <script src="admin/assets/ueditorplus/third-party/SyntaxHighlighter/shCore.js"></script>
    <script>
        // 初始化SyntaxHighlighter
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof SyntaxHighlighter !== 'undefined') {
                SyntaxHighlighter.highlight();
            }
        });
    </script>
    <script src="assets/js/static/third-party/html2canvas.min.js"></script>
    <script src="assets/js/static/third-party/jspdf.umd.min.js"></script>
    <script src="assets/js/static/third-party/html2pdf.bundle.min.js"></script>
    <script src="assets/js/export/export-functions.js"></script>
    <script src="assets/js/index.js"></script>
</body>
</html>
