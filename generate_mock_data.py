"""
生成模拟数据并插入MySQL数据库的脚本
"""
import mysql.connector
from mysql.connector import Error
import hashlib
import random
import datetime
import bcrypt

def connect_mysql():
    """连接到MySQL数据库"""
    try:
        conn = mysql.connector.connect(
            host='192.168.100.193',
            port=33310,
            database='web_doc',
            user='web_doc',
            password='YjA60cdN9'
        )
        return conn
    except Error as e:
        print(f"连接MySQL数据库时出错: {e}")
        return None

def create_default_admin(mysql_conn):
    """创建默认管理员账户 admin/admin123"""
    cursor = mysql_conn.cursor()
    
    # 使用bcrypt对密码进行哈希，与PHP的password_hash()兼容
    password_hash = bcrypt.hashpw('admin123'.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
    
    # 插入默认管理员账户
    try:
        cursor.execute("""
            INSERT INTO users (id, username, email, password, role, created_at) 
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
            username=VALUES(username), email=VALUES(email), password=VALUES(password), 
            role=VALUES(role), created_at=VALUES(created_at)
        """, (1, 'admin', 'admin@example.com', password_hash, 'admin', datetime.datetime.now()))
        
        mysql_conn.commit()
        print("默认管理员账户创建成功: admin/admin123")
    except Error as e:
        print(f"创建默认管理员账户时出错: {e}")
    finally:
        cursor.close()

def generate_mock_users(mysql_conn, count=10):
    """生成模拟用户数据"""
    cursor = mysql_conn.cursor()
    
    roles = ['admin', 'editor', 'viewer']
    
    for i in range(2, count + 2):  # 从ID 2开始，因为ID 1是管理员
        username = f"user{i}"
        email = f"user{i}@example.com"
        # 使用bcrypt对密码进行哈希
        password_hash = bcrypt.hashpw(f"password{i}".encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
        role = random.choice(roles)
        
        try:
            cursor.execute("""
                INSERT INTO users (id, username, email, password, role, created_at) 
                VALUES (%s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                username=VALUES(username), email=VALUES(email), password=VALUES(password), 
                role=VALUES(role), created_at=VALUES(created_at)
            """, (i, username, email, password_hash, role, datetime.datetime.now()))
        except Error as e:
            print(f"插入用户 {username} 时出错: {e}")
    
    mysql_conn.commit()
    cursor.close()
    print(f"成功生成 {count} 个模拟用户")

def generate_mock_documents(mysql_conn, count=20):
    """生成模拟文档数据"""
    cursor = mysql_conn.cursor()
    
    # 获取用户ID列表
    cursor.execute("SELECT id FROM users")
    user_ids = [row[0] for row in cursor.fetchall()]
    
    # 文档标题模板
    titles = [
        "技术文档", "用户手册", "开发指南", "API文档", "设计规范",
        "操作说明", "安装教程", "配置指南", "故障排除", "最佳实践",
        "系统架构", "数据库设计", "接口规范", "测试方案", "部署手册"
    ]
    
    # 文档内容模板
    content_template = """
# {title}

## 概述

这是关于 {title} 的详细文档。

## 内容

- 第一节：基本概念
- 第二节：使用方法
- 第三节：注意事项
- 第四节：常见问题

## 总结

本文档提供了 {title} 的全面介绍和使用指导。
"""
    
    for i in range(1, count + 1):
        title = f"{random.choice(titles)} {i}"
        content = content_template.format(title=title)
        parent_id = None if i <= 5 else random.randint(1, min(i-1, 5))  # 前5个为根文档，后面的可能有父文档
        sort_order = i * 10
        user_id = random.choice(user_ids)
        is_public = random.choice([0, 1])
        tags = f"tag{i},tag{i+1},tag{i+2}"
        view_count = random.randint(0, 1000)
        created_at = datetime.datetime.now() - datetime.timedelta(days=random.randint(1, 365))
        updated_at = created_at + datetime.timedelta(days=random.randint(1, 30))
        
        try:
            cursor.execute("""
                INSERT INTO documents (id, title, content, parent_id, sort_order, user_id, 
                                      is_public, tags, view_count, created_at, updated_at, 
                                      del_status, deleted_at, is_formal, update_code, document_id) 
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                title=VALUES(title), content=VALUES(content), parent_id=VALUES(parent_id), 
                sort_order=VALUES(sort_order), user_id=VALUES(user_id), is_public=VALUES(is_public), 
                tags=VALUES(tags), view_count=VALUES(view_count), created_at=VALUES(created_at), 
                updated_at=VALUES(updated_at), del_status=VALUES(del_status), deleted_at=VALUES(deleted_at), 
                is_formal=VALUES(is_formal), update_code=VALUES(update_code), document_id=VALUES(document_id)
            """, (i, title, content, parent_id, sort_order, user_id, is_public, tags, view_count, 
                 created_at, updated_at, 0, None, 1, f"code{i}", i))
        except Error as e:
            print(f"插入文档 {title} 时出错: {e}")
    
    mysql_conn.commit()
    cursor.close()
    print(f"成功生成 {count} 个模拟文档")

def main():
    """主函数"""
    # 连接数据库
    mysql_conn = connect_mysql()
    
    if mysql_conn:
        # 创建默认管理员账户
        create_default_admin(mysql_conn)
        
        # 生成模拟用户数据
        generate_mock_users(mysql_conn, 10)
        
        # 生成模拟文档数据
        generate_mock_documents(mysql_conn, 20)
        
        # 关闭数据库连接
        mysql_conn.close()
        
        print("所有模拟数据生成完成")
    else:
        print("数据库连接失败，无法生成模拟数据")

if __name__ == "__main__":
    main()