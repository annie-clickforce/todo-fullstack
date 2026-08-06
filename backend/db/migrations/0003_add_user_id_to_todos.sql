ALTER TABLE todos
  ADD COLUMN user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE;

CREATE INDEX idx_todos_user_completed_created
  ON todos (user_id, completed, created_at DESC);
