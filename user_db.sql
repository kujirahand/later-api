-- data/users/user-N.db に保存されるSQLite用のユーザー情報テーブル定義
-- イベント記録テーブル
CREATE TABLE events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    task_id TEXT NOT NULL,
    json_str TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
