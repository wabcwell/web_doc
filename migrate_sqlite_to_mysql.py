"""
SQLite到MySQL数据迁移脚本
"""
import sqlite3
import mysql.connector
from mysql.connector import Error
import json
import re
import datetime

def connect_sqlite(db_file):
    """连接到SQLite数据库"""
    try:
        conn = sqlite3.connect(db_file)
        return conn
    except Error as e:
        print(f"连接SQLite数据库时出错: {e}")
        return None

def connect_mysql():
    """连接到MySQL数据库"""
    try:
        # 请根据实际情况修改数据库连接参数
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

def create_mysql_tables(mysql_conn):
    """在MySQL中创建表结构"""
    cursor = mysql_conn.cursor()
    
    # 创建users表
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'editor',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    """)
    
    # 创建documents表
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title TEXT NOT NULL,
            content LONGTEXT,
            parent_id INT DEFAULT NULL,
            sort_order INT DEFAULT 0,
            user_id INT DEFAULT 1,
            is_public INT DEFAULT 1,
            tags TEXT,
            view_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            del_status INT DEFAULT 0,
            deleted_at DATETIME DEFAULT NULL,
            is_formal INT DEFAULT 0,
            update_code VARCHAR(255),
            document_id INT,
            FOREIGN KEY (parent_id) REFERENCES documents(id) ON DELETE SET NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    """)
    
    # 创建documents_version表
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS documents_version (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            title TEXT NOT NULL,
            content LONGTEXT,
            version_number INT NOT NULL,
            created_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            tags TEXT,
            update_code VARCHAR(255),
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )
    """)
    
    # 创建edit_log表
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS edit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            document_id INT NOT NULL,
            user_id INT NOT NULL,
            action VARCHAR(50) NOT NULL,
            op_title INT DEFAULT 0,
            op_content INT DEFAULT 0,
            op_tags INT DEFAULT 0,
            op_parent INT DEFAULT 0,
            op_corder INT DEFAULT 0,
            op_public INT DEFAULT 0,
            op_formal INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            temp_action TEXT,
            update_code VARCHAR(255),
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    """)
    
    # 创建file_upload表
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS file_upload (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_type VARCHAR(50) NOT NULL,
            file_format VARCHAR(50) NOT NULL,
            file_size INT NOT NULL,
            file_path TEXT NOT NULL,
            alias TEXT,
            document_id INT,
            description TEXT,
            notes TEXT,
            uploaded_by INT NOT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            del_status INT DEFAULT 0,
            deleted_at DATETIME DEFAULT NULL,
            image_width INT,
            image_height INT,
            FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )
    """)
    
    # 创建document_id_apportion表
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS document_id_apportion (
            document_id INT PRIMARY KEY AUTO_INCREMENT,
            usage_status INT DEFAULT 0,
            created_by INT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
        )
    """)
    
    mysql_conn.commit()
    cursor.close()

def process_row_for_mysql(row):
    """处理行数据，将None值转换为MySQL可接受的值"""
    processed_row = []
    for v in row:
        if v is None:
            processed_row.append(None)  # MySQL中的NULL值
        else:
            # 特殊处理日期时间字符串
            if isinstance(v, str) and v == '':
                processed_row.append(None)  # 空字符串也转换为NULL
            else:
                processed_row.append(v)
    return tuple(processed_row)

def migrate_users(sqlite_conn, mysql_conn):
    """迁移users表数据"""
    sqlite_cursor = sqlite_conn.cursor()
    mysql_cursor = mysql_conn.cursor()
    
    # 从SQLite读取数据
    sqlite_cursor.execute("SELECT id, username, email, password, role, created_at FROM users")
    rows = sqlite_cursor.fetchall()
    
    # 插入到MySQL
    for row in rows:
        processed_row = process_row_for_mysql(row)
        mysql_cursor.execute("""
            INSERT INTO users (id, username, email, password, role, created_at) 
            VALUES (%s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
            username=VALUES(username), email=VALUES(email), password=VALUES(password), 
            role=VALUES(role), created_at=VALUES(created_at)
        """, processed_row)
    
    mysql_conn.commit()
    sqlite_cursor.close()
    mysql_cursor.close()

def migrate_documents(sqlite_conn, mysql_conn):
    """迁移documents表数据"""
    sqlite_cursor = sqlite_conn.cursor()
    mysql_cursor = mysql_conn.cursor()
    
    # 禁用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    
    # 从SQLite读取数据
    sqlite_cursor.execute("""
        SELECT id, title, content, parent_id, sort_order, user_id, is_public, 
               tags, view_count, created_at, updated_at, del_status, deleted_at, 
               is_formal, update_code, document_id 
        FROM documents
    """)
    rows = sqlite_cursor.fetchall()
    
    # 插入到MySQL
    for row in rows:
        processed_row = process_row_for_mysql(row)
        mysql_cursor.execute("""
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
        """, processed_row)
    
    mysql_conn.commit()
    
    # 重新启用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    
    sqlite_cursor.close()
    mysql_cursor.close()

def migrate_documents_version(sqlite_conn, mysql_conn):
    """迁移documents_version表数据"""
    sqlite_cursor = sqlite_conn.cursor()
    mysql_cursor = mysql_conn.cursor()
    
    # 禁用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    
    # 从SQLite读取数据
    sqlite_cursor.execute("""
        SELECT id, document_id, title, content, version_number, created_by, created_at, tags, update_code 
        FROM documents_version
    """)
    rows = sqlite_cursor.fetchall()
    
    # 插入到MySQL
    for row in rows:
        processed_row = process_row_for_mysql(row)
        mysql_cursor.execute("""
            INSERT INTO documents_version (id, document_id, title, content, version_number, 
                                          created_by, created_at, tags, update_code) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
            document_id=VALUES(document_id), title=VALUES(title), content=VALUES(content), 
            version_number=VALUES(version_number), created_by=VALUES(created_by), 
            created_at=VALUES(created_at), tags=VALUES(tags), update_code=VALUES(update_code)
        """, processed_row)
    
    mysql_conn.commit()
    
    # 重新启用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    
    sqlite_cursor.close()
    mysql_cursor.close()

def migrate_edit_log(sqlite_conn, mysql_conn):
    """迁移edit_log表数据"""
    sqlite_cursor = sqlite_conn.cursor()
    mysql_cursor = mysql_conn.cursor()
    
    # 禁用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    
    # 从SQLite读取数据
    sqlite_cursor.execute("""
        SELECT id, document_id, user_id, action, op_title, op_content, op_tags, 
               op_parent, op_corder, op_public, op_formal, created_at, temp_action, update_code 
        FROM edit_log
    """)
    rows = sqlite_cursor.fetchall()
    
    # 插入到MySQL
    for row in rows:
        processed_row = process_row_for_mysql(row)
        mysql_cursor.execute("""
            INSERT INTO edit_log (id, document_id, user_id, action, op_title, op_content, 
                                 op_tags, op_parent, op_corder, op_public, op_formal, 
                                 created_at, temp_action, update_code) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
            document_id=VALUES(document_id), user_id=VALUES(user_id), action=VALUES(action), 
            op_title=VALUES(op_title), op_content=VALUES(op_content), op_tags=VALUES(op_tags), 
            op_parent=VALUES(op_parent), op_corder=VALUES(op_corder), op_public=VALUES(op_public), 
            op_formal=VALUES(op_formal), created_at=VALUES(created_at), temp_action=VALUES(temp_action), 
            update_code=VALUES(update_code)
        """, processed_row)
    
    mysql_conn.commit()
    
    # 重新启用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    
    sqlite_cursor.close()
    mysql_cursor.close()

def migrate_file_upload(sqlite_conn, mysql_conn):
    """迁移file_upload表数据"""
    sqlite_cursor = sqlite_conn.cursor()
    mysql_cursor = mysql_conn.cursor()
    
    # 禁用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    
    # 从SQLite读取数据
    sqlite_cursor.execute("""
        SELECT id, file_type, file_format, file_size, file_path, alias, document_id, 
               description, notes, uploaded_by, uploaded_at, updated_at, del_status, 
               deleted_at, image_width, image_height 
        FROM file_upload
    """)
    rows = sqlite_cursor.fetchall()
    
    # 插入到MySQL
    for row in rows:
        processed_row = process_row_for_mysql(row)
        mysql_cursor.execute("""
            INSERT INTO file_upload (id, file_type, file_format, file_size, file_path, 
                                    alias, document_id, description, notes, uploaded_by, 
                                    uploaded_at, updated_at, del_status, deleted_at, 
                                    image_width, image_height) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
            file_type=VALUES(file_type), file_format=VALUES(file_format), file_size=VALUES(file_size), 
            file_path=VALUES(file_path), alias=VALUES(alias), document_id=VALUES(document_id), 
            description=VALUES(description), notes=VALUES(notes), uploaded_by=VALUES(uploaded_by), 
            uploaded_at=VALUES(uploaded_at), updated_at=VALUES(updated_at), del_status=VALUES(del_status), 
            deleted_at=VALUES(deleted_at), image_width=VALUES(image_width), image_height=VALUES(image_height)
        """, processed_row)
    
    mysql_conn.commit()
    
    # 重新启用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    
    sqlite_cursor.close()
    mysql_cursor.close()

def migrate_document_id_apportion(sqlite_conn, mysql_conn):
    """迁移document_id_apportion表数据"""
    sqlite_cursor = sqlite_conn.cursor()
    mysql_cursor = mysql_conn.cursor()
    
    # 禁用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 0")
    
    # 从SQLite读取数据
    sqlite_cursor.execute("""
        SELECT document_id, usage_status, created_by, created_at, updated_at 
        FROM document_id_apportion
    """)
    rows = sqlite_cursor.fetchall()
    
    # 插入到MySQL
    for row in rows:
        processed_row = process_row_for_mysql(row)
        mysql_cursor.execute("""
            INSERT INTO document_id_apportion (document_id, usage_status, created_by, created_at, updated_at) 
            VALUES (%s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE 
            usage_status=VALUES(usage_status), created_by=VALUES(created_by), 
            created_at=VALUES(created_at), updated_at=VALUES(updated_at)
        """, processed_row)
    
    mysql_conn.commit()
    
    # 重新启用外键约束检查
    mysql_cursor.execute("SET FOREIGN_KEY_CHECKS = 1")
    
    sqlite_cursor.close()
    mysql_cursor.close()

def main():
    """主函数"""
    # 连接数据库
    sqlite_conn = connect_sqlite('e:/Trae/web_doc_mysql/database/docs.db')
    mysql_conn = connect_mysql()
    
    if sqlite_conn and mysql_conn:
        # 创建MySQL表结构
        create_mysql_tables(mysql_conn)
        
        # 迁移数据
        print("开始迁移users表数据...")
        migrate_users(sqlite_conn, mysql_conn)
        print("users表数据迁移完成")
        
        print("开始迁移documents表数据...")
        migrate_documents(sqlite_conn, mysql_conn)
        print("documents表数据迁移完成")
        
        print("开始迁移documents_version表数据...")
        migrate_documents_version(sqlite_conn, mysql_conn)
        print("documents_version表数据迁移完成")
        
        print("开始迁移edit_log表数据...")
        migrate_edit_log(sqlite_conn, mysql_conn)
        print("edit_log表数据迁移完成")
        
        print("开始迁移file_upload表数据...")
        migrate_file_upload(sqlite_conn, mysql_conn)
        print("file_upload表数据迁移完成")
        
        print("开始迁移document_id_apportion表数据...")
        migrate_document_id_apportion(sqlite_conn, mysql_conn)
        print("document_id_apportion表数据迁移完成")
        
        # 关闭数据库连接
        sqlite_conn.close()
        mysql_conn.close()
        
        print("所有数据迁移完成")
    else:
        print("数据库连接失败，无法进行数据迁移")

if __name__ == "__main__":
    main()