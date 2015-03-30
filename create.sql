CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE ON CONFLICT FAIL NOT NULL CHECK (length(username) > 0 AND instr(username, ":") = 0),
  password TEXT NOT NULL CHECK (length(password) > 0)
);

CREATE TRIGGER IF NOT EXISTS user_delete DELETE ON users BEGIN
DELETE FROM messages WHERE from_id = OLD.id OR to_id = OLD.id;
END;

CREATE TABLE IF NOT EXISTS messages (
  from_id INTEGER REFERENCES users(id),
  to_id INTEGER REFERENCES users(id),
  message TEXT,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS messages_index ON messages (from_id, to_id);