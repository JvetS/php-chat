INSERT INTO users (username, password) VALUES ("john", "password");
INSERT INTO users (username, password) VALUES ("jane", "password");
INSERT INTO users (username, password) VALUES ("jimmy", "password");
INSERT INTO users (username, password) VALUES ("janet", "password");

INSERT INTO messages (from_id, to_id, message) VALUES (1,2,"hello");
INSERT INTO messages (from_id, to_id, message) VALUES (2,1,"world");
INSERT INTO messages (from_id, to_id, message) VALUES (1,3,"foo");
INSERT INTO messages (from_id, to_id, message) VALUES (2,3,"bar");
INSERT INTO messages (from_id, to_id, message) VALUES (3,2,"baz");
INSERT INTO messages (from_id, to_id, message) VALUES (3,4,"qux");
INSERT INTO messages (from_id, to_id, message) VALUES (4,1,"bux");