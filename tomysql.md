# SQLite到MySQL数据库迁移指南

本文档详细说明了如何将文档管理系统从SQLite数据库迁移到MySQL数据库。

## 为什么需要迁移到MySQL

虽然SQLite是一个轻量级的嵌入式数据库，非常适合小型项目和原型开发，但在以下情况下，您可能需要迁移到MySQL：

1. **更高的并发性能**：MySQL在处理高并发读写操作时表现更佳
2. **更大的数据量**：当数据量增长时，MySQL提供了更好的扩展性
3. **更丰富的功能**：MySQL支持更多高级数据库特性，如存储过程、触发器等
4. **更好的管理工具**：MySQL有更多成熟的数据库管理工具
5. **生产环境需求**：在生产环境中，MySQL通常被认为是更稳定和可靠的选择

## 迁移前准备

### 1. 安装MySQL

确保您的服务器上已安装MySQL数据库。如果尚未安装，请参考MySQL官方文档进行安装：https://dev.mysql.com/doc/refman/8.0/en/installing.html

### 2. 创建数据库

在MySQL中创建一个新的数据库用于迁移：

```sql
CREATE DATABASE web_doc;
```

### 3. 安装Python依赖

迁移脚本使用Python编写，需要安装`mysql-connector-python`库：

```bash
pip install mysql-connector-python
```

### 4. 备份SQLite数据库

在进行迁移之前，请务必备份当前的SQLite数据库文件：

```bash
cp database/docs.db database/docs.db.backup
```

## 迁移步骤

####### 1. 配置迁移脚本

`migrate_sqlite_to_mysql.py` 脚本已预配置了MySQL连接参数，如需修改，请根据实际情况调整：

```python
# 请根据实际情况修改数据库连接参数
conn = mysql.connector.connect(
    host='192.168.100.193',
    port=33310,
    database='web_doc',
    user='web_doc',
    password='YjA60cdN9'
)
```

建议将配置信息单独提取出来，便于维护和修改。

### 2. 运行迁移脚本

在项目根目录下运行迁移脚本：

```bash
python migrate_sqlite_to_mysql.py
```

脚本将自动完成以下操作：
1. 连接到SQLite和MySQL数据库
2. 在MySQL中创建所需的表结构
3. 逐表迁移数据
4. 处理可能的数据类型差异
5. 关闭数据库连接

### 3. 验证数据迁移

迁移完成后，建议验证数据是否正确迁移：

1. 检查MySQL数据库中的表是否都已创建
2. 验证各表中的记录数量是否与SQLite数据库一致
3. 随机抽查几条记录，确认数据完整性

## 更新项目配置

迁移完成后，需要更新项目的数据库配置文件以使用MySQL：

### 1. 修改数据库连接配置

编辑`config.php`文件，更新MySQL数据库连接参数：

```php
// 数据库配置
$db_host = 'localhost';  // MySQL服务器地址
$db_name = 'web_doc';    // 数据库名称
$db_user = 'your_username';  // 用户名
$db_pass = 'your_password';  // 密码
$db_charset = 'utf8mb4';     // 字符集
$db_port = '3306';           // 端口号
```

项目已经实现了MySQL数据库连接，无需修改`includes/init.php`文件中的数据库连接代码。系统会自动根据`config.php`中的配置连接到MySQL数据库。

### 2. 测试应用功能

完成配置更改后，全面测试应用的各项功能：

1. 用户登录和权限验证
2. 文档的增删改查操作
3. 文件上传和管理
4. 文档版本历史
5. 搜索功能
6. 导出功能

## 迁移后的优化建议

迁移到MySQL后，可以考虑以下优化措施：

### 1. 数据库索引优化

根据实际查询需求，为常用查询字段添加索引：

```sql
CREATE INDEX idx_documents_title ON documents(title);
CREATE INDEX idx_documents_created_at ON documents(created_at);
```

### 2. 查询优化

分析慢查询日志，优化执行效率较低的SQL语句。

### 3. 连接池配置

在生产环境中，建议配置数据库连接池以提高性能。

### 4. 定期维护

设置定期维护任务，如优化表、更新统计信息等。

## 故障排除

### 常见问题及解决方案

1. **连接失败**：检查MySQL服务是否启动，用户名密码是否正确
2. **字符编码问题**：确保MySQL数据库使用utf8mb4字符集
3. **数据类型不匹配**：检查并调整不兼容的数据类型
4. **外键约束错误**：确保数据迁移顺序正确，先迁移被引用表

### 数据回滚

如果迁移过程中出现问题，可以使用之前备份的SQLite数据库文件进行回滚：

```bash
cp database/docs.db.backup database/docs.db
```

然后恢复项目配置文件中SQLite的配置。

## 总结

通过以上步骤，您可以成功将文档管理系统从SQLite迁移到MySQL数据库。迁移后，系统将具备更好的并发处理能力和扩展性，更适合在生产环境中使用。

如果在迁移过程中遇到任何问题，请参考相关文档或寻求技术支持。