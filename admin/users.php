<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/DocumentTree.php';

Auth::requireAdmin();

$db = get_db();
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC");

$title = '用户管理';
include 'sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>用户管理</h1>
                <a href="add_user.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> 添加用户
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">用户列表</h5>
                </div>
                <div class="card-body">
                    <?php if ($users->rowCount() === 0): ?>
                        <p class="text-muted">暂无用户</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>用户名</th>
                                        <th>邮箱</th>
                                        <th>角色</th>
                                        <th>创建时间</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'secondary'; ?>">
                                                <?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-pencil"></i> 编辑
                                                </a>
                                                <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('确定要删除该用户吗？')">
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