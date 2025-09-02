import mysql.connector

# 数据库连接配置
config = {
    'host': '192.168.100.193',
    'port': 33310,
    'database': 'web_doc',
    'user': 'web_doc',
    'password': 'YjA60cdN9',
    'charset': 'utf8mb4'
}

try:
    # 连接数据库
    conn = mysql.connector.connect(**config)
    cursor = conn.cursor()
    
    # 查询用户数据
    cursor.execute('SELECT id, username, password, role FROM users')
    results = cursor.fetchall()
    
    print('Users in database:')
    for row in results:
        print(f'ID: {row[0]}, Username: {row[1]}, Password: {row[2]}, Role: {row[3]}')
    
    # 关闭连接
    cursor.close()
    conn.close()
    
except mysql.connector.Error as err:
    print(f'数据库错误: {err}')
except Exception as e:
    print(f'其他错误: {e}')