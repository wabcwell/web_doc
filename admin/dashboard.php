<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/DocumentTree.php';

Auth::requireLogin();

// 获取会话消息
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$documentTree = new DocumentTree();
$totalDocuments = $documentTree->getTotalDocuments();
$totalUsers = $documentTree->getTotalUsers();
$recentDocuments = $documentTree->getRecentDocuments(5);

$title = '仪表盘';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h1>仪表盘</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- 统计卡片 -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">总文档数</h5>
                            <h2 class="text-primary"><?php echo $totalDocuments; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">总用户数</h5>
                            <h2 class="text-success"><?php echo $totalUsers; ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 最近更新 -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">最近更新</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentDocuments)): ?>
                        <p class="text-muted">暂无更新</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>标题</th>
                                        <th>作者</th>
                                        <th>更新时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentDocuments as $doc): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($doc['title']); ?></td>
                                        <td><?php echo htmlspecialchars($doc['username']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($doc['updated_at'])); ?></td>
                                        <td>
                                            <a href="documents/edit.php?id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
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