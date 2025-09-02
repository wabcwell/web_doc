import mysql.connector
import hashlib

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
    
    # 查询admin用户数据
    cursor.execute('SELECT id, username, password FROM users WHERE username = %s', ('admin',))
    result = cursor.fetchone()
    
    if result:
        print(f'User: {result[1]}')
        print(f'Stored password hash: {result[2]}')
        
        # 验证密码
        entered_password = 'admin123'
        # 看起来数据库中的密码是MD5哈希
        entered_hash = hashlib.md5(entered_password.encode()).hexdigest()
        print(f'Entered password hash: {entered_hash}')
        
        if entered_hash == result[2]:
            print('Password matches!')
        else:
            print('Password does not match!')
    else:
        print('User not found')
    
    # 关闭连接
    cursor.close()
    conn.close()
    
except mysql.connector.Error as err:
    print(f'数据库错误: {err}')
except Exception as e:
    print(f'其他错误: {e}')