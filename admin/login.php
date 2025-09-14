<?php
require_once __DIR__ . '/../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    if (empty($username)) {
        $errors[] = '请输入用户名';
    }
    
    if (empty($password)) {
        $errors[] = '请输入密码';
    }
    
    if (empty($errors)) {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            
            header('Location: dashboard.php');
            exit;
        } else {
            $errors[] = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 文档管理系统</title>
    <link href="../assets/css/static/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/static/bootstrap-icons.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-3">
        <div class="row justify-content-center align-items-center" style="min-height: 80vh;">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="text-center mb-0">用户登录</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <div><?php echo $error; ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">密码</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">登录</button>
                        </form>
                        

                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>