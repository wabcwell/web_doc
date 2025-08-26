<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/DocumentTree.php';

// 检查用户权限
Auth::requireLogin();

// 获取文档ID
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: index.php?error=未指定文档ID');
    exit;
}

$db = get_db();
$tree = new DocumentTree($db);

// 获取文档详情
$stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.id = ?");
$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: index.php?error=文档不存在');
    exit;
}

// 获取面包屑导航
$breadcrumbs = $tree->getBreadcrumbs($id);

$title = '查看文档 - ' . htmlspecialchars($document['title'] ?? '未知文档');
include '../sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- 页面标题 -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><?php echo htmlspecialchars($document['title'] ?? '未知文档'); ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">文档管理</a></li>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['title']); ?></a></li>
                            <?php endforeach; ?>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($document['title'] ?? '未知文档'); ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-pencil"></i> 编辑
                    </a>
                    <a href="../../document.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-success">
                        <i class="bi bi-eye"></i> 前台查看
                    </a>
                </div>
            </div>

            <!-- 文档信息卡片 -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">文档内容</h5>
                        </div>
                        <div class="card-body">
                            <div class="markdown-content">
                                <?php 
                                require_once '../../Parsedown.php';
                                $Parsedown = new Parsedown();
                                echo $Parsedown->text($document['content']);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- 文档信息 -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">文档信息</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">ID</dt>
                                <dd class="col-sm-8"><?php echo $document['id']; ?></dd>
                                
                                <dt class="col-sm-4">作者</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($document['username'] ?? '未知用户'); ?></dd>
                                
                                <dt class="col-sm-4">状态</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-<?php echo $document['is_public'] ? 'success' : 'secondary'; ?>">
                                        <?php echo $document['is_public'] ? '公开' : '私有'; ?>
                                    </span>
                                </dd>
                                
                                <dt class="col-sm-4">排序权重</dt>
                                <dd class="col-sm-8"><?php echo $document['sort_order']; ?></dd>
                                
                                <dt class="col-sm-4">创建时间</dt>
                                <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['created_at'])); ?></dd>
                                
                                <dt class="col-sm-4">更新时间</dt>
                                <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['updated_at'])); ?></dd>
                                
                                <?php if ($document['tags']): ?>
                                <dt class="col-sm-4">标签</dt>
                                <dd class="col-sm-8">
                                    <?php 
                                    $tags = array_map('trim', explode(',', $document['tags']));
                                    foreach ($tags as $tag): 
                                        if (trim($tag)): 
                                    ?>
                                        <span class="badge bg-info me-1"><?php echo htmlspecialchars(trim($tag) ?? ''); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <!-- 操作按钮 -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">操作</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> 编辑文档
                                </a>
                                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $id; ?>, '<?php echo addslashes(htmlspecialchars($document['title'] ?? '未知文档')); ?>')">
                                    <i class="bi bi-trash"></i> 删除文档
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> 返回列表
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
    
    <!-- 删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">确认删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要删除文档 <strong id="deleteDocumentTitle"></strong> 吗？</p>
                    <p class="text-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        此操作不可撤销，文档将被永久删除。
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">确认删除</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 删除确认函数
        function confirmDelete(id, title) {
            document.getElementById('deleteDocumentTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
            
            // 显示模态框
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
<?php 
// 更新浏览次数
$stmt = $db->prepare("UPDATE documents SET view_count = view_count + 1 WHERE id = ?");
$stmt->execute([$id]);
?>