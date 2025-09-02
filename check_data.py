import mysql.connector

# 数据库配置
MYSQL_CONFIG = {
    'host': '192.168.100.193',
    'database': 'web_doc',
    'user': 'web_doc',
    'password': 'YjA60cdN9',
    'charset': 'utf8mb4',
    'port': 33310,
}

# 连接到MySQL数据库
conn = mysql.connector.connect(**MYSQL_CONFIG)
cursor = conn.cursor()

# 查询用户数量
cursor.execute('SELECT COUNT(*) FROM users')
users_count = cursor.fetchone()[0]
print(f'Users count: {users_count}')

# 查询文档数量
cursor.execute('SELECT COUNT(*) FROM documents')
documents_count = cursor.fetchone()[0]
print(f'Documents count: {documents_count}')

# 查询默认管理员是否存在
cursor.execute("SELECT * FROM users WHERE username = 'admin'")
admin_user = cursor.fetchone()
if admin_user:
    print('默认管理员账户存在: admin')
else:
    print('默认管理员账户不存在')

# 关闭数据库连接
cursor.close()
conn.close()