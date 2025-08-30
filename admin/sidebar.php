<!-- 管理员侧边栏 -->
<div class="sidebar">
    <a href="/admin/dashboard.php" class="sidebar-brand">
        <i class="bi bi-journal-text"></i> 管理后台
    </a>
    
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/dashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i> 管理面板
            </a>
        </li>
        <li>
            <a href="/admin/documents/index.php" class="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/documents/') !== false && strpos($_SERVER['PHP_SELF'], '/admin/doc_recycle/') === false && strpos($_SERVER['PHP_SELF'], '/admin/files/') === false) ? 'active' : ''; ?>">
                <i class="bi bi-file-text"></i> 文档管理
            </a>
        </li>
        <li>
            <a href="/admin/doc_recycle/index.php" class="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/doc_recycle/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-recycle"></i> 回收站
            </a>
        </li>
        <li>
            <a href="/admin/files/index.php" class="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/files/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-folder2-open"></i> 文件管理
            </a>
        </li>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
        <li>
            <a href="/admin/user/" class="<?php echo (strpos($_SERVER['PHP_SELF'], '/admin/user/') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i> 用户管理
            </a>
        </li>
        <li>
            <a href="/admin/settings.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'settings.php') ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i> 系统设置
            </a>
        </li>
        <?php endif; ?>
    </ul>
    
    <div class="sidebar-footer">
        <a href="/index.php" target="_blank" class="logout-btn">
            <i class="bi bi-house"></i> 前台首页
        </a>
        <a href="/admin/logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i> 退出登录
        </a>
    </div>
</div>