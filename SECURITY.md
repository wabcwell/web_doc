# 安全配置指南

## 数据库文件保护

本项目已实施多层安全措施保护数据库文件：

### 已实施的保护措施

1. **Apache (.htaccess) 保护**
   - `database/.htaccess` - 阻止直接访问数据库目录
   - 根目录 `.htaccess` - 全局敏感文件保护

2. **IIS (Web.config) 保护**
   - `database/Web.config` - IIS 服务器数据库保护
   - 根目录 `Web.config` - 全局配置

3. **PHP 内置服务器保护**
   - `database/index.php` - 返回 403 禁止访问
   - `database/router.php` - 路由保护文件

4. **Git 忽略配置**
   - `.gitignore` - 防止安全配置被意外提交

### 推荐的生产环境配置

#### 对于 Apache
```apache
# 在 Apache 配置中
<Directory "/path/to/your/project/database">
    Require all denied
</Directory>
```

#### 对于 Nginx
```nginx
# 在 Nginx 配置中
location ~* \.(db|sqlite|sqlite3)$ {
    deny all;
    return 403;
}

location /database/ {
    deny all;
    return 403;
}
```

#### 对于 PHP 内置服务器
使用自定义路由文件：
```bash
php -S localhost:8000 database/router.php
```

### 文件权限设置

在 Linux/Unix 系统中：
```bash
chmod 600 database/docs.db
chmod 755 database/
```

在 Windows 系统中：
- 确保数据库文件不在 Web 根目录下
- 使用 NTFS 权限限制访问

### 额外安全建议

1. **数据库加密**：考虑使用 SQLCipher 进行数据库加密
2. **定期备份**：创建加密备份并存储在安全位置
3. **监控访问**：记录和监控对数据库的访问尝试
4. **更新维护**：定期更新系统和依赖项

### 测试保护

您可以通过以下方式测试保护是否生效：
```bash
curl -I http://localhost:8000/database/docs.db
# 应该返回 403 Forbidden
```