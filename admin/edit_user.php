<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/Auth.php';

Auth::requireAdmin();

$db = get_db();
$error = '';
$success = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = (int)$_GET['id'];

// 获取用户信息
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $password = $_POST['password'] ?? '';

        // 验证输入
        if (empty($username) || empty($email)) {
            $error = '请填写所有必填字段';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '请输入有效的邮箱地址';
        } elseif (!empty($password) && strlen($password) < 6) {
            $error = '密码长度至少6位';
        } else {
            // 检查是否尝试将最后一个管理员降级
            if ($user['role'] === 'admin' && $role === 'user') {
                $adminCountStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?");
                $adminCountStmt->execute([$user_id]);
                $otherAdminCount = $adminCountStmt->fetchColumn();
                
                if ($otherAdminCount == 0) {
                    $error = '无法将最后一个管理员降级为普通用户';
                }
            }
        // 检查用户名和邮箱是否已被其他用户使用
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $user_id]);
        
        if ($stmt->fetchColumn() > 0) {
            $error = '用户名或邮箱已存在';
        } else {
            // 更新用户信息
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
                $result = $stmt->execute([$username, $email, $role, $hashed_password, $user_id]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                $result = $stmt->execute([$username, $email, $role, $user_id]);
            }
            
            if ($result) {
                $success = '用户信息更新成功！';
                // 重新获取更新后的用户信息
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = '更新用户信息失败，请重试';
            }
        }
    }
}

$title = '编辑用户 - ' . htmlspecialchars($user['username']);
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
                <h1>编辑用户</h1>
                <a href="users.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> 返回列表
                </a>
            </div>

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

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">用户信息</h5>
                </div>
                <div class="card-body">
                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="username" class="form-label">用户名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            <div class="invalid-feedback">请输入用户名</div>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">邮箱 <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            <div class="invalid-feedback">请输入有效的邮箱地址</div>
                        </div>

                        <?php
                        // 检查是否为最后一个管理员
                        $adminCountStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != ?");
                        $adminCountStmt->execute([$user_id]);
                        $otherAdminCount = $adminCountStmt->fetchColumn();
                        $isLastAdmin = ($user['role'] === 'admin' && $otherAdminCount == 0);
                        ?>
                        <div class="mb-3">
                            <label for="role" class="form-label">角色</label>
                            <select class="form-select" id="role" name="role" <?php echo $isLastAdmin ? 'disabled' : ''; ?>>
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>普通用户</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>管理员</option>
                            </select>
                            <?php if ($isLastAdmin): ?>
                                <small class="form-text text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> 这是系统中唯一的管理员账户，无法降级为普通用户
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">新密码</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="form-text text-muted">留空表示不修改密码</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <?php if ($isLastAdmin): ?>
                                <input type="hidden" name="role" value="admin">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary" <?php echo $isLastAdmin ? 'disabled' : ''; ?>>
                                <i class="bi bi-save"></i> 保存修改
                            </button>
                            <a href="users.php" class="btn btn-secondary">取消</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">用户详情</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-3">用户ID</dt>
                        <dd class="col-sm-9"><?php echo $user['id']; ?></dd>
                        
                        <dt class="col-sm-3">创建时间</dt>
                        <dd class="col-sm-9"><?php echo date('Y-m-d H:i:s', strtotime($user['created_at'])); ?></dd>
                        
                        <dt class="col-sm-3">最后登录</dt>
                        <dd class="col-sm-9"><?php echo isset($user['last_login']) && $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : '从未登录'; ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 表单验证
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();
    </script>
</body>
</html>