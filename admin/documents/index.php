<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/DocumentTree.php';

Auth::requireLogin();

$documentTree = new DocumentTree();
$documents = $documentTree->getAllDocuments();

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
                                        <th>分类</th>
                                        <th>作者</th>
                                        <th>更新时间</th>
                                        <th>状态</th>
                                        <th style="width: 120px;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): 
                                        $status = $doc['is_public'] ? '公开' : '私有';
                                        $status_class = $doc['is_public'] ? 'success' : 'secondary';
                                        $category_name = $doc['category_name'] ?? '未分类';
                                    ?>
                                    <tr>
                                        <td><?php echo $doc['id']; ?></td>
                                        <td>
                                            <a href="view.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($doc['title']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($category_name); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['username']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($doc['updated_at'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-pencil"></i> 编辑
                                                </a>
                                                <a href="delete.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('确定要删除该文档吗？')">
                                                    <i class="bi bi-trash"></i> 删除
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