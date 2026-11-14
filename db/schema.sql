CREATE TABLE IF NOT EXISTS members (
  id {{PK}},
  member_no VARCHAR(30) NOT NULL UNIQUE,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  birth_date VARCHAR(10) NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(40) NULL,
  street VARCHAR(120) NULL,
  postal_code VARCHAR(10) NULL,
  city VARCHAR(80) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE','INACTIVE')),
  created_at VARCHAR(19) NOT NULL,
  status_reason VARCHAR(60) NULL,
  status_date VARCHAR(10) NULL,
  emergency_name VARCHAR(120) NULL,
  emergency_phone VARCHAR(40) NULL,
  medical_note VARCHAR(500) NULL,
  photo_consent TINYINT NOT NULL DEFAULT 0
){{ENGINE}};

CREATE TABLE IF NOT EXISTS users (
  id {{PK}},
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  member_id INT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at VARCHAR(19) NOT NULL,
  ical_token VARCHAR(40) NULL,
  totp_secret VARCHAR(64) NULL,
  totp_enabled TINYINT NOT NULL DEFAULT 0,
  FOREIGN KEY (member_id) REFERENCES members(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS user_tokens (
  id {{PK}},
  user_id INT NOT NULL,
  token_hash VARCHAR(64) NOT NULL UNIQUE,
  purpose VARCHAR(10) NOT NULL,
  created_at VARCHAR(19) NOT NULL,
  expires_at VARCHAR(19) NOT NULL,
  used_at VARCHAR(19) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  CHECK (purpose IN ('INVITE','RESET'))
){{ENGINE}};

CREATE TABLE IF NOT EXISTS roles (
  id {{PK}},
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS permissions (
  id {{PK}},
  code VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (role_id) REFERENCES roles(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id),
  FOREIGN KEY (permission_id) REFERENCES permissions(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS guardian_links (
  id {{PK}},
  user_id INT NOT NULL,
  member_id INT NOT NULL,
  relationship VARCHAR(40) NULL,
  UNIQUE (user_id, member_id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (member_id) REFERENCES members(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS `groups` (
  id {{PK}},
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  category VARCHAR(20) NOT NULL DEFAULT 'SWIM_SCHOOL',
  is_active TINYINT NOT NULL DEFAULT 1,
  goals TEXT NULL,
  color INT NULL,
  capacity INT NULL,
  fee_cents INT NULL,
  next_group_id INT NULL,
  min_trainers INT NULL,
  share_token VARCHAR(40) NULL,
  CHECK (category IN ('SWIM_SCHOOL','COMPETITION'))
){{ENGINE}};

CREATE TABLE IF NOT EXISTS group_memberships (
  id {{PK}},
  member_id INT NOT NULL,
  group_id INT NOT NULL,
  start_date VARCHAR(10) NOT NULL,
  end_date VARCHAR(10) NULL,
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (group_id) REFERENCES `groups`(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS group_trainers (
  id {{PK}},
  user_id INT NOT NULL,
  group_id INT NOT NULL,
  start_date VARCHAR(10) NOT NULL,
  end_date VARCHAR(10) NULL,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (group_id) REFERENCES `groups`(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activity_types (
  id {{PK}},
  code VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activities (
  id {{PK}},
  type_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  location VARCHAR(150) NULL,
  starts_at VARCHAR(19) NOT NULL,
  ends_at VARCHAR(19) NOT NULL,
  audience VARCHAR(10) NOT NULL CHECK (audience IN ('CLUB','GROUPS')),
  status VARCHAR(10) NOT NULL DEFAULT 'PLANNED' CHECK (status IN ('PLANNED','CANCELLED')),
  created_by INT NULL,
  created_at VARCHAR(19) NOT NULL,
  series_id INT NULL,
  is_custom TINYINT NOT NULL DEFAULT 0,
  status_note VARCHAR(200) NULL,
  is_public TINYINT NOT NULL DEFAULT 0,
  signup_capacity INT NULL,
  min_trainers INT NULL,
  FOREIGN KEY (type_id) REFERENCES activity_types(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activity_groups (
  activity_id INT NOT NULL,
  group_id INT NOT NULL,
  PRIMARY KEY (activity_id, group_id),
  FOREIGN KEY (activity_id) REFERENCES activities(id),
  FOREIGN KEY (group_id) REFERENCES `groups`(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS attendance (
  id {{PK}},
  activity_id INT NOT NULL,
  member_id INT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'UNKNOWN' CHECK (status IN ('UNKNOWN','PRESENT','ABSENT')),
  updated_by INT NULL,
  updated_at VARCHAR(19) NOT NULL,
  UNIQUE (activity_id, member_id),
  FOREIGN KEY (activity_id) REFERENCES activities(id),
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (updated_by) REFERENCES users(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS audit_log (
  id {{PK}},
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,
  entity VARCHAR(40) NOT NULL,
  entity_id INT NULL,
  detail VARCHAR(255) NULL,
  created_at VARCHAR(19) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activity_series (
  id {{PK}},
  type_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT NULL,
  location VARCHAR(150) NULL,
  start_date VARCHAR(10) NOT NULL,
  end_date VARCHAR(10) NOT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  is_public TINYINT NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at VARCHAR(19) NOT NULL,
  capacity INT NULL,
  FOREIGN KEY (type_id) REFERENCES activity_types(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activity_series_slots (
  id {{PK}},
  series_id INT NOT NULL,
  weekday INT NOT NULL,
  start_time VARCHAR(5) NOT NULL,
  end_time VARCHAR(5) NOT NULL,
  location VARCHAR(150) NULL,
  UNIQUE (series_id, weekday),
  FOREIGN KEY (series_id) REFERENCES activity_series(id),
  CHECK (weekday BETWEEN 1 AND 7)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activity_series_groups (
  series_id INT NOT NULL,
  group_id INT NOT NULL,
  PRIMARY KEY (series_id, group_id),
  FOREIGN KEY (series_id) REFERENCES activity_series(id),
  FOREIGN KEY (group_id) REFERENCES `groups`(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS member_series (
  member_id INT NOT NULL,
  series_id INT NOT NULL,
  weekday INT NOT NULL DEFAULT 0,
  PRIMARY KEY (member_id, series_id, weekday),
  FOREIGN KEY (member_id) REFERENCES members(id),
  FOREIGN KEY (series_id) REFERENCES activity_series(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activity_series_skips (
  series_id INT NOT NULL,
  day VARCHAR(10) NOT NULL,
  PRIMARY KEY (series_id, day),
  FOREIGN KEY (series_id) REFERENCES activity_series(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS login_attempts (
  id {{PK}},
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at VARCHAR(19) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS absence_reports (
  id {{PK}},
  activity_id INT NOT NULL,
  member_id INT NOT NULL,
  reported_by INT NULL,
  reason VARCHAR(200) NULL,
  created_at VARCHAR(19) NOT NULL,
  UNIQUE (activity_id, member_id),
  FOREIGN KEY (activity_id) REFERENCES activities(id),
  FOREIGN KEY (member_id) REFERENCES members(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS broadcasts (
  id {{PK}},
  kind VARCHAR(12) NOT NULL,
  sender_id INT NULL,
  subject VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  audience VARCHAR(190) NOT NULL,
  recipient_count INT NOT NULL DEFAULT 0,
  created_at VARCHAR(19) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS series_trainers (
  series_id INT NOT NULL,
  weekday INT NOT NULL,
  user_id INT NOT NULL,
  PRIMARY KEY (series_id, weekday, user_id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS activity_trainers (
  id {{PK}},
  activity_id INT NOT NULL,
  user_id INT NOT NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'ASSIGNED' CHECK (status IN ('ASSIGNED','ABSENT')),
  note VARCHAR(200) NULL,
  updated_by INT NULL,
  updated_at VARCHAR(19) NOT NULL,
  UNIQUE (activity_id, user_id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS coverage_alerts (
  activity_id INT NOT NULL PRIMARY KEY,
  missing INT NOT NULL,
  sent_at VARCHAR(19) NOT NULL
){{ENGINE}};
