<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/DocumentTree.php';

Auth::requireLogin();

$documentTree = new DocumentTree();
$documents = $documentTree->getAllDocumentsByHierarchy();

// 获取参数用于添加按钮
$parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : null;
$sort_order = isset($_GET['sort_order']) ? intval($_GET['sort_order']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

$title = '文档管理';
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
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>文档管理</h1>
                <a href="add.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> 添加文档
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">文档列表</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">暂无文档，开始创建你的第一篇文档吧！</p>
                            <a href="add.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> 创建文档
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th>标题</th>
                                        <th>作者</th>
                                        <th>更新时间</th>
                                        <th>状态</th>
                                        <th style="width: 140px; text-align: center;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): 
                                        $status = $doc['is_public'] ? '公开' : '私有';
                                        $status_class = $doc['is_public'] ? 'success' : 'secondary';
                                        
                                        // 计算同级文档的下一个排序值
                                        $next_sort_order = $doc['sort_order'] + 1;
                                        
                                        // 计算下级文档的下一个排序值
                                        $max_child_sort = $documentTree->getMaxChildSortOrder($doc['id']);
                                        $next_child_sort = $max_child_sort + 1;
                                    ?>
                                    <tr>
                                        <td><?php echo $doc['id']; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <?php if (isset($doc['level']) && $doc['level'] > 0): ?>
                                                    <span style="margin-right: 5px;">
                                                        <?php echo str_repeat('│&nbsp;&nbsp;', $doc['level']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (isset($doc['level']) && $doc['level'] > 0): ?>
                                                    └─
                                                <?php endif; ?>
                                                <a href="view.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['username']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($doc['updated_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td style="width: 120px;">
                                            <div style="display: flex; gap: 2px; align-items: center; justify-content: center; margin: 0 auto;">
                                                <a href="edit.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0;" title="编辑">
                                                    <i class="bi bi-pencil" style="font-size: 14px; margin: 0 auto;"></i>
                                                </a>
                                                <a href="add.php?parent_id=<?php echo $documentTree->getParentId($doc['id']); ?>&sort_order=<?php echo $next_sort_order; ?>" 
                                                   class="btn btn-outline-warning btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0;" title="添加同级文档">
                                                    <i class="bi bi-plus-circle" style="font-size: 14px; margin: 0 auto;"></i>
                                                </a>
                                                <a href="add.php?parent_id=<?php echo $doc['id']; ?>&sort_order=<?php echo $next_child_sort; ?>" 
                                                   class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0;" title="添加下级文档">
                                                    <i class="bi bi-arrow-return-right" style="font-size: 14px; margin: 0 auto;"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-danger btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0;" title="删除" 
                                                        onclick="confirmDelete(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(addslashes($doc['title'])); ?>')">
                                                    <i class="bi bi-trash" style="font-size: 14px; margin: 0 auto;"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 删除确认函数
        function confirmDelete(id, title) {
            document.getElementById('deleteDocumentTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
            
            // 显示模态框
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // 页面加载完成后的处理
        document.addEventListener('DOMContentLoaded', function() {
            // 处理URL参数显示提示消息
            const urlParams = new URLSearchParams(window.location.search);
            const success = urlParams.get('success');
            const error = urlParams.get('error');

            if (success) {
                // 显示成功提示（可选）
                console.log('Success:', success);
            }
            if (error) {
                // 显示错误提示（可选）
                console.error('Error:', error);
            }
        });
    </script>
</body>
</html>