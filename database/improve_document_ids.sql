-- 改进文档ID方案：使用友好的业务ID
-- 方案1：添加业务ID字段（保持现有自增ID，新增业务ID）

-- 1. 添加业务ID字段
ALTER TABLE documents ADD COLUMN business_id TEXT UNIQUE;

-- 2. 创建索引优化查询
CREATE INDEX idx_documents_business_id ON documents(business_id);

-- 3. 生成现有数据的业务ID（可选）
-- UPDATE documents SET business_id = 'DOC' || printf('%06d', id) WHERE business_id IS NULL;

-- 方案2：完全替换为UUID（需要更多代码修改）
-- 这个方案需要修改PHP代码中的插入逻辑

-- 方案3：使用雪花算法风格的ID（需要PHP实现）
-- 这个方案需要在PHP中实现ID生成器

-- 推荐方案：方案1 - 添加业务ID字段
-- 优点：
-- 1. 保持现有ID不变，兼容性最好
-- 2. 业务ID可以自定义格式
-- 3. 可以逐步迁移，不影响现有功能