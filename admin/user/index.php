<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/Auth.php';
require_once __DIR__ . '/../../includes/DocumentTree.php';

// 检查是否为管理员，普通用户直接拒绝访问
if (!Auth::isAdmin()) {
    $_SESSION['error'] = '权限不足，需要管理员权限才能访问用户管理';
    header('Location: ../dashboard.php');
    exit();
}

$db = get_db();
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC");

// 获取会话消息
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

$title = '用户管理';
include __DIR__ . '/../sidebar.php';
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
    <style>
        .table td, .table th {
            vertical-align: middle;
        }
        
        /* 用户操作按钮样式 - 参考回收站设计 */
        .user-actions .btn-group {
            gap: 2px;
        }
        
        .user-actions .btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            border: none;
        }
        
        .user-actions .btn i {
            font-size: 14px;
            margin: 0;
        }
        
        /* 编辑按钮 - 蓝色系 */
        .btn-edit {
            background-color: #2196f3 !important;
            color: white !important;
            border: none !important;
        }
        .btn-edit:hover {
            background-color: #1976d2 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(33, 150, 243, 0.3);
        }
        
        /* 删除按钮 - 红色系 */
        .btn-delete {
            background-color: #f44336 !important;
            color: white !important;
            border: none !important;
        }
        .btn-delete:hover {
            background-color: #d32f2f !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(244, 67, 54, 0.3);
        }
        
        /* 禁用状态 */
        .btn-disabled {
            background-color: #bdbdbd !important;
            color: white !important;
            border: none !important;
            opacity: 0.65;
            cursor: not-allowed;
        }
        .btn-disabled:hover {
            background-color: #bdbdbd !important;
            transform: none;
            box-shadow: none;
        }
        
        /* 纯CSS悬停提示样式 */
        [data-tooltip] {
            position: relative;
        }
        
        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
            pointer-events: none;
        }
        
        [data-tooltip]:hover::before {
            content: "";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            margin-bottom: 1px;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-people"></i> 用户管理</h1>
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

            <?php $userList = $users->fetchAll(PDO::FETCH_ASSOC); ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people"></i> 用户列表</h5>
                    <span class="badge bg-secondary">共 <?php echo count($userList); ?> 个用户</span>
                </div>
                <div class="card-body">
                    <?php
                    // 获取管理员数量用于判断是否可以删除最后一个管理员
                    $adminCountStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                    $adminCount = $adminCountStmt->fetchColumn();
                    $currentUserId = $_SESSION['user_id'] ?? 0;
                    ?>
                    <?php if (empty($userList)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                            <h4 class="text-muted mt-3">暂无用户</h4>
                            <p class="text-muted">当前系统中没有任何用户</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 60px;">ID</th>
                                        <th scope="col">用户名</th>
                                        <th scope="col">邮箱</th>
                                        <th scope="col">角色</th>
                                        <th scope="col">创建时间</th>
                                        <th scope="col" style="width: 100px; text-align: center;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userList as $user): ?>
                                    <tr>
                                        <td><strong><?php echo $user['id']; ?></strong></td>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($user['username']); ?></div>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'secondary'; ?>" style="font-size: 0.75rem;">
                                                <?php echo $user['role'] === 'admin' ? '管理员' : '普通用户'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i>
                                                <?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td class="user-actions text-center">
                                            <div class="btn-group" role="group">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-edit" 
                                                   data-tooltip="编辑用户信息">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <?php
                                                $canDelete = true;
                                                $tooltipText = '';
                                                if ($user['role'] === 'admin' && $adminCount <= 1) {
                                                    $canDelete = false;
                                                    $tooltipText = '无法删除最后一个管理员';
                                                }
                                                if ($user['id'] == $currentUserId) {
                                                    $canDelete = false;
                                                    $tooltipText = '无法删除当前登录账户';
                                                }
                                                ?>
                                                <?php if ($canDelete): ?>
                                                    <a href="delete_user.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-delete" 
                                                       data-tooltip="删除用户"
                                                       onclick="return confirm('确定要删除用户 "<?php echo htmlspecialchars($user['username']); ?>" 吗？')">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-disabled" 
                                                            disabled 
                                                            data-tooltip="<?php echo $tooltipText; ?>">
                                                        <i class="bi bi-trash"></i>
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