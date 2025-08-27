<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
require_once '../../includes/init.php';

// 检查登录和权限
Auth::requireLogin();

// 获取文档ID
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($document_id <= 0) {
    $_SESSION['error'] = '无效的文档ID';
    header('Location: index.php');
    exit;
}

// 获取文档信息
$document = get_document($document_id);
if (!$document) {
    $_SESSION['error'] = '文档不存在';
    header('Location: index.php');
    exit;
}

// 处理回滚操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rollback_version'])) {
    $rollback_version = intval($_POST['rollback_version']);
    
    // 获取要回滚的版本
    $target_version = get_document_version($document_id, $rollback_version);
    
    if ($target_version) {
        try {
            $db = get_db();
            
            // 获取当前文档信息用于记录日志
            $current_doc = get_document($document_id);
            
            // 更新文档到目标版本
            $stmt = $db->prepare("UPDATE documents SET title = ?, content = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$target_version['title'], $target_version['content'], $document_id]);
            
            // 记录回滚操作到编辑日志
            log_edit(
                $document_id,
                $_SESSION['user_id'],
                'rollback',
                $current_doc['title'],
                $target_version['title'],
                $current_doc['content'],
                $target_version['content']
            );
            
            // 保存回滚后的版本为新版本
            $new_version = save_document_version(
                $document_id,
                $target_version['title'],
                $target_version['content'],
                $_SESSION['user_id']
            );
            
            $_SESSION['success'] = "成功回滚到版本 $rollback_version (新版本: $new_version)";
            header("Location: view_his.php?id=$document_id");
            exit;
        } catch (Exception $e) {
            $_SESSION['error'] = '回滚失败: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = '指定的版本不存在';
    }
}

// 获取编辑日志和历史版本
$edit_logs = get_edit_logs($document_id);
$versions = get_document_versions($document_id);
$current_version = get_current_version_number($document_id);

?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文档历史记录 - <?php echo htmlspecialchars($document['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
    <link href="../../assets/css/admin.css" rel="stylesheet">
    <style>
        .version-card {
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        .version-card:hover {
            border-left-color: #0a58ca;
            transform: translateX(5px);
        }
        .log-item {
            border-left: 3px solid #28a745;
            padding-left: 15px;
            margin-bottom: 10px;
        }
        .log-item.rollback {
            border-left-color: #ffc107;
        }
        .log-item.delete {
            border-left-color: #dc3545;
        }
        .diff-content {
            max-height: 600px;
            overflow-y: auto;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        .current-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        /* 版本预览模态框高度调整 */
        #versionModal .modal-dialog {
            height: 90vh;
            margin: 5vh auto;
        }
        #versionModal .modal-content {
            height: 100%;
        }
        #versionModal .modal-body {
            max-height: calc(90vh - 120px);
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- 侧边栏 -->
        <?php include '../sidebar.php'; ?>
        
        <div class="main-content">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3">文档历史记录</h1>
                        <p class="text-muted mb-0">文档: <?php echo htmlspecialchars($document['title']); ?></p>
                    </div>
                    <div>
                        <a href="edit.php?id=<?php echo $document_id; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-pencil"></i> 编辑文档
                        </a>
                        <a href="index.php" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> 返回列表
                        </a>
                    </div>
                </div>

                <!-- 消息提示 -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- 左侧：历史文档内容 -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-file-text"></i> 历史文档内容
                                    <span id="currentVersionLabel" class="badge bg-primary ms-2">最新版本</span>
                                </h5>
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showLatestVersion()" id="backToLatestBtn" style="display: none;">
                                    <i class="bi bi-arrow-left"></i> 返回最新版本
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="versionContentDisplay" class="markdown-content">
                                    <!-- 默认显示最新版本内容 -->
                                    <?php 
                                    require_once '../../Parsedown.php';
                                    $Parsedown = new Parsedown();
                                    echo $Parsedown->text($document['content']);
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 右侧：版本历史和编辑记录 -->
                    <div class="col-md-4">
                        <!-- 版本历史 -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history"></i> 版本历史
                                    <span class="badge bg-secondary ms-2"><?php echo count($versions); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($versions)): ?>
                                    <p class="text-muted text-center">暂无历史版本</p>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($versions as $index => $version): ?>
                                            <div class="list-group-item position-relative version-card">
                                                <?php if ($index === 0): ?>
                                                    <span class="badge bg-success current-badge">当前</span>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">版本 <?php echo $version['version_number']; ?></h6>
                                                        <small class="text-muted">
                                                            由 <?php echo htmlspecialchars($version['username']); ?> 创建于
                                                            <?php echo date('Y-m-d H:i', strtotime($version['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick='showVersionContent(<?php echo $version["version_number"]; ?>, <?php echo json_encode($version["title"]); ?>, <?php echo json_encode($version["content"]); ?>)'
                                                                title="查看内容">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($index > 0): ?>
                                                            <form method="post" class="d-inline" onsubmit="return confirm('确定要回滚到这个版本吗？当前内容将被保存为新版本。')">
                                                                <input type="hidden" name="rollback_version" value="<?php echo $version['version_number']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="回滚到此版本">
                                                                    <i class="bi bi-arrow-clockwise"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- 编辑记录 -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-list-ul"></i> 编辑记录
                                    <span class="badge bg-secondary ms-2"><?php echo count($edit_logs); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($edit_logs)): ?>
                                    <p class="text-muted text-center">暂无编辑记录</p>
                                <?php else: ?>
                                    <div class="timeline" style="max-height: 400px; overflow-y: auto;">
                                        <?php foreach ($edit_logs as $log): ?>
                                            <div class="log-item <?php echo htmlspecialchars($log['action']); ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                                        <span class="badge bg-<?php 
                                                            switch($log['action']) {
                                                                case 'create': echo 'success'; break;
                                                                case 'update': echo 'primary'; break;
                                                                case 'delete': echo 'danger'; break;
                                                                case 'rollback': echo 'warning'; break;
                                                                default: echo 'info';
                                                            }
                                                        ?> ms-2">
                                                            <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?>
                                                    </small>
                                                </div>
                                                
                                                <?php if ($log['action'] !== 'create' && $log['action'] !== 'delete'): ?>
                                                    <?php if ($log['old_title'] !== $log['new_title']): ?>
                                                        <div class="mt-2">
                                                            <small><strong>标题变更:</strong></small>
                                                            <div class="text-muted">
                                                                <del><?php echo htmlspecialchars(mb_substr($log['old_title'], 0, 30)); ?>...</del>
                                                                → 
                                                                <span><?php echo htmlspecialchars(mb_substr($log['new_title'], 0, 30)); ?>...</span>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
    <script>
        // 创建简单的Markdown解析器
        function parseMarkdown(text) {
            if (!text) return '<p>无内容</p>';
            
            // 处理代码块
            text = text.replace(/```(\w+)?\n([\s\S]*?)```/g, function(match, lang, code) {
                const language = lang || 'plaintext';
                return '<pre><code class="language-' + language + '">' + escapeHtml(code.trim()) + '</code></pre>';
            });
            
            // 处理行内代码
            text = text.replace(/`([^`]+)`/g, '<code>$1</code>');
            
            // 处理标题
            text = text.replace(/^### (.*$)/gim, '<h3>$1</h3>');
            text = text.replace(/^## (.*$)/gim, '<h2>$1</h2>');
            text = text.replace(/^# (.*$)/gim, '<h1>$1</h1>');
            
            // 处理粗体
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/__(.*?)__/g, '<strong>$1</strong>');
            
            // 处理斜体
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');
            text = text.replace(/_(.*?)_/g, '<em>$1</em>');
            
            // 处理链接
            text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank">$1</a>');
            
            // 处理段落
            text = text.replace(/\n\n/g, '</p><p>');
            text = '<p>' + text + '</p>';
            
            // 处理剩余的换行
            text = text.replace(/\n/g, '<br>');
            
            return text;
        }
        
        // HTML转义函数
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // 显示指定版本的内容
        function showVersionContent(versionNumber, title, content) {
            const contentDisplay = document.getElementById('versionContentDisplay');
            const versionLabel = document.getElementById('currentVersionLabel');
            const backButton = document.getElementById('backToLatestBtn');
            
            // 更新版本标签
            versionLabel.textContent = `版本 ${versionNumber}`;
            versionLabel.className = 'badge bg-info ms-2';
            
            // 显示返回按钮
            backButton.style.display = 'inline-block';
            
            // 更新内容
            contentDisplay.innerHTML = parseMarkdown(content);
            
            // 重新应用代码高亮
            setTimeout(() => {
                if (typeof Prism !== 'undefined') {
                    Prism.highlightAll();
                }
            }, 100);
            
            // 保持页面位置不变，不滚动
        }
        
        // 显示最新版本内容
        function showLatestVersion() {
            // 重新加载页面以显示最新版本
            window.location.href = window.location.pathname + window.location.search;
        }
    </script>
</body>
</html>