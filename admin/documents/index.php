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
                                        <th style="width: 200px;">操作</th>
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
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="edit.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary" title="编辑">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <a href="add.php?parent_id=<?php echo $documentTree->getParentId($doc['id']); ?>&sort_order=<?php echo $next_sort_order; ?>" 
                                                   class="btn btn-outline-success" title="添加同级文档">
                                                    <i class="bi bi-plus-circle"></i>
                                                </a>
                                                <a href="add.php?parent_id=<?php echo $doc['id']; ?>&sort_order=<?php echo $next_child_sort; ?>" 
                                                   class="btn btn-outline-info" title="添加下级文档">
                                                    <i class="bi bi-plus-square"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-danger" title="删除" onclick="return confirm('确定要删除该文档吗？')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
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
</body>
</html>