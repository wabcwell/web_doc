<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/Auth.php';
require_once __DIR__ . '/../includes/DocumentTree.php';

// 检查是否为管理员，普通用户直接拒绝访问
if (!Auth::isAdmin()) {
    $_SESSION['error'] = '权限不足，需要管理员权限才能访问用户管理';
    header('Location: dashboard.php');
    exit();
}

$db = get_db();
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC");

// 获取会话消息
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

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
    <link rel="stylesheet" href="../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .table td, .table th {
            vertical-align: middle;
        }
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
    </style>
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

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">用户列表</h5>
                </div>
                <div class="card-body">
                    <?php $userList = $users->fetchAll(PDO::FETCH_ASSOC); ?>
                    <?php
                    // 获取管理员数量用于判断是否可以删除最后一个管理员
                    $adminCountStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                    $adminCount = $adminCountStmt->fetchColumn();
                    $currentUserId = $_SESSION['user_id'] ?? 0;
                    ?>
                    <?php if (empty($userList)): ?>
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
                                    <?php foreach ($userList as $user): ?>
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
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-primary btn-sm d-flex align-items-center justify-content-center" style="min-width: 70px; height: 28px;">
                                                    <i class="bi bi-pencil me-1"></i>编辑
                                                </a>
                                                <?php
                                                $canDelete = true;
                                                if ($user['role'] === 'admin' && $adminCount <= 1) {
                                                    $canDelete = false;
                                                }
                                                if ($user['id'] == $currentUserId) {
                                                    $canDelete = false;
                                                }
                                                ?>
                                                <?php if ($canDelete): ?>
                                                    <a href="delete_user.php?id=<?php echo $user['id']; ?>" class="btn btn-outline-danger btn-sm d-flex align-items-center justify-content-center" style="min-width: 70px; height: 28px;" onclick="return confirm('确定要删除该用户吗？')">
                                                        <i class="bi bi-trash me-1"></i>删除
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center justify-content-center" style="min-width: 70px; height: 28px;" disabled title="<?php echo ($user['role'] === 'admin' && $adminCount <= 1) ? '无法删除最后一个管理员' : '无法删除当前登录账户'; ?>">
                                                        <i class="bi bi-trash me-1"></i>删除
                                                    </button>
                                                <?php endif; ?>
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