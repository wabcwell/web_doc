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
$recentCreatedCount = $documentTree->getRecentCreatedCount(14);
$recentDeletedCount = $documentTree->getRecentDeletedCount(14);


$recentlyCreatedDocuments = $documentTree->getRecentlyCreatedDocuments(10);
$recentlyDeletedDocuments = $documentTree->getRecentlyDeletedDocuments(10);
$documentsWithMostVersions = $documentTree->getDocumentsWithMostVersions(10);
$documentsWithMostOperations = $documentTree->getDocumentsWithMostOperations(10);

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
            <h1><i class="bi bi-speedometer2"></i> 仪表盘</h1>

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
            
            <!-- 统计卡片 - 高级莫兰迪配色 -->
            <div class="row g-3 mb-4">
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="card stat-card border-0 shadow-sm" style="background: linear-gradient(135deg, #a8b5db 0%, #c7c7c7 100%); color: #2c3e50;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-white bg-opacity-70 text-muted rounded-circle d-flex align-items-center justify-content-center" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        <i class="bi bi-file-earmark-text fs-4"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title mb-1 text-secondary fw-normal">总文档数</h6>
                                    <h3 class="mb-0 fw-bold" style="color: #2c3e50;"><?php echo $totalDocuments; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="card stat-card border-0 shadow-sm" style="background: linear-gradient(135deg, #b8c6db 0%, #f5f7fa 100%); color: #2c3e50;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-white bg-opacity-70 text-muted rounded-circle d-flex align-items-center justify-content-center" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        <i class="bi bi-people fs-4"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title mb-1 text-secondary fw-normal">总用户数</h6>
                                    <h3 class="mb-0 fw-bold" style="color: #2c3e50;"><?php echo $totalUsers; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="card stat-card border-0 shadow-sm" style="background: linear-gradient(135deg, #d4e4f7 0%, #a9c9e8 100%); color: #2c3e50;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-white bg-opacity-70 text-muted rounded-circle d-flex align-items-center justify-content-center" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        <i class="bi bi-file-earmark-plus fs-4"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title mb-1 text-secondary fw-normal">最近创建</h6>
                                    <h3 class="mb-0 fw-bold" style="color: #2c3e50;"><?php echo $recentCreatedCount; ?></h3>
                                    <small class="text-muted fs-7">最近14天</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                    <div class="card stat-card border-0 shadow-sm" style="background: linear-gradient(135deg, #f8d7da 0%, #e2b4bd 100%); color: #2c3e50;">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="stat-icon bg-white bg-opacity-70 text-muted rounded-circle d-flex align-items-center justify-content-center" style="box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        <i class="bi bi-file-earmark-x fs-4"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="card-title mb-1 text-secondary fw-normal">最近删除</h6>
                                    <h3 class="mb-0 fw-bold" style="color: #2c3e50;"><?php echo $recentDeletedCount; ?></h3>
                                    <small class="text-muted fs-7">最近14天</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>

            <!-- 第一行：最近创建和最近删除 -->
            <div class="row g-3 mb-3">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">最近创建</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentlyCreatedDocuments)): ?>
                                <p class="text-muted">暂无创建</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($recentlyCreatedDocuments as $doc): ?>
                                        <a href="documents/view.php?id=<?php echo $doc['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="flex-grow-1 text-truncate">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($doc['title']); ?></span>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <small class="text-muted"><?php echo htmlspecialchars($doc['username']); ?></small>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <small class="text-muted"><?php echo date('m-d H:i', strtotime($doc['created_at'])); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">最近删除</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentlyDeletedDocuments)): ?>
                                <p class="text-muted">暂无删除</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($recentlyDeletedDocuments as $doc): ?>
                                        <a href="doc_recycle/view.php?id=<?php echo $doc['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="flex-grow-1 text-truncate">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($doc['title']); ?></span>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <small class="text-muted"><?php echo htmlspecialchars($doc['username']); ?></small>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <small class="text-muted"><?php echo date('m-d H:i', strtotime($doc['deleted_at'])); ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 第二行：版本最多和更新最多 -->
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">版本最多文档</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($documentsWithMostVersions)): ?>
                                <p class="text-muted">暂无数据</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($documentsWithMostVersions as $doc): ?>
                                        <a href="documents/view_his.php?id=<?php echo $doc['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="flex-grow-1 text-truncate">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($doc['title']); ?></span>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <small class="text-muted"><?php echo htmlspecialchars($doc['username']); ?></small>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <span class="badge bg-primary rounded-pill"><?php echo $doc['version_count']; ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">更新最多文档</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($documentsWithMostOperations)): ?>
                                <p class="text-muted">暂无数据</p>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($documentsWithMostOperations as $doc): ?>
                                        <a href="documents/edit_log.php?id=<?php echo $doc['id']; ?>" class="list-group-item list-group-item-action">
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="flex-grow-1 text-truncate">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($doc['title']); ?></span>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <small class="text-muted"><?php echo htmlspecialchars($doc['username']); ?></small>
                                                </div>
                                                <div class="text-nowrap" style="width: 100px;">
                                                    <span class="badge bg-success rounded-pill"><?php echo $doc['operation_count']; ?></span>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>