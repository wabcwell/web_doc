-- 创建文件上传记录表
-- 用于记录文档相关的上传文件信息

CREATE TABLE IF NOT EXISTS file_upload (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    
    -- 业务字段
    file_type TEXT NOT NULL CHECK (file_type IN ('图片', '视频', '音频', '文档', '压缩包', '其他')),
    file_format TEXT NOT NULL,
    file_size INTEGER NOT NULL,
    file_path TEXT NOT NULL,
    document_id INTEGER,
    
    -- 描述信息
    description TEXT,
    notes TEXT,
    
    -- 上传者信息
    uploaded_by INTEGER NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- 外键约束
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- 创建索引优化查询性能
CREATE INDEX IF NOT EXISTS idx_file_upload_document_id ON file_upload(document_id);
CREATE INDEX IF NOT EXISTS idx_file_upload_uploaded_by ON file_upload(uploaded_by);
CREATE INDEX IF NOT EXISTS idx_file_upload_file_type ON file_upload(file_type);
CREATE INDEX IF NOT EXISTS idx_file_upload_uploaded_at ON file_upload(uploaded_at);

-- 添加触发器，在文档删除时清理相关文件记录
CREATE TRIGGER IF NOT EXISTS cleanup_files_on_document_delete
AFTER DELETE ON documents
BEGIN
    UPDATE file_upload 
    SET document_id = NULL 
    WHERE document_id = OLD.id;
END;