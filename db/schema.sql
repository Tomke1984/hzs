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
  created_at VARCHAR(19) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS users (
  id {{PK}},
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  member_id INT NULL,
  is_active TINYINT NOT NULL DEFAULT 1,
  created_at VARCHAR(19) NOT NULL,
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

CREATE TABLE IF NOT EXISTS registrations (
  id {{PK}},
  status VARCHAR(12) NOT NULL DEFAULT 'UNCONFIRMED',
  applicant_type VARCHAR(10) NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(40) NOT NULL,
  relationship VARCHAR(40) NULL,
  street VARCHAR(120) NOT NULL,
  postal_code VARCHAR(10) NOT NULL,
  city VARCHAR(80) NOT NULL,
  remarks VARCHAR(500) NULL,
  consent_at VARCHAR(19) NOT NULL,
  confirm_token_hash VARCHAR(64) NULL,
  created_at VARCHAR(19) NOT NULL,
  confirmed_at VARCHAR(19) NULL,
  decided_by INT NULL,
  decided_at VARCHAR(19) NULL,
  reject_reason VARCHAR(500) NULL,
  CHECK (status IN ('UNCONFIRMED','PENDING','APPROVED','REJECTED')),
  CHECK (applicant_type IN ('PARENT','SELF'))
){{ENGINE}};

CREATE TABLE IF NOT EXISTS registration_persons (
  id {{PK}},
  registration_id INT NOT NULL,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  birth_date VARCHAR(10) NOT NULL,
  preferred_category VARCHAR(20) NULL,
  member_id INT NULL,
  FOREIGN KEY (registration_id) REFERENCES registrations(id),
  FOREIGN KEY (member_id) REFERENCES members(id)
){{ENGINE}};

CREATE TABLE IF NOT EXISTS swimtest_requests (
  id {{PK}},
  status VARCHAR(12) NOT NULL DEFAULT 'UNCONFIRMED',
  child_first_name VARCHAR(80) NOT NULL,
  child_last_name VARCHAR(80) NOT NULL,
  child_birth_date VARCHAR(10) NOT NULL,
  diploma VARCHAR(10) NOT NULL DEFAULT 'NONE',
  experience VARCHAR(300) NULL,
  availability VARCHAR(500) NULL,
  contact_name VARCHAR(120) NOT NULL,
  contact_email VARCHAR(190) NOT NULL,
  contact_phone VARCHAR(40) NOT NULL,
  relationship VARCHAR(40) NULL,
  street VARCHAR(120) NOT NULL,
  postal_code VARCHAR(10) NOT NULL,
  city VARCHAR(80) NOT NULL,
  remarks VARCHAR(500) NULL,
  consent_at VARCHAR(19) NOT NULL,
  confirm_token_hash VARCHAR(64) NULL,
  created_at VARCHAR(19) NOT NULL,
  confirmed_at VARCHAR(19) NULL,
  status_changed_at VARCHAR(19) NULL,
  status_changed_by INT NULL,
  invite_info VARCHAR(300) NULL,
  decline_reason VARCHAR(500) NULL,
  internal_note VARCHAR(500) NULL,
  registration_id INT NULL,
  CHECK (status IN ('UNCONFIRMED','WAITING','INVITED','TESTED','ENROLLED','DECLINED'))
){{ENGINE}};

CREATE TABLE IF NOT EXISTS contact_messages (
  id {{PK}},
  topic VARCHAR(12) NOT NULL DEFAULT 'OTHER',
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL,
  message TEXT NOT NULL,
  created_at VARCHAR(19) NOT NULL,
  handled_at VARCHAR(19) NULL,
  handled_by INT NULL,
  mail_sent TINYINT NOT NULL DEFAULT 0,
  CHECK (topic IN ('SWIM_SCHOOL','COMPETITION','OTHER'))
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

CREATE TABLE IF NOT EXISTS posts (
  id {{PK}},
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  summary VARCHAR(300) NULL,
  body TEXT NULL,
  image VARCHAR(120) NULL,
  status VARCHAR(10) NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT','PUBLISHED')),
  published_at VARCHAR(19) NULL,
  author_id INT NULL,
  created_at VARCHAR(19) NOT NULL,
  updated_at VARCHAR(19) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS pages (
  id {{PK}},
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  body TEXT NULL,
  parent_id INT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status VARCHAR(10) NOT NULL DEFAULT 'DRAFT' CHECK (status IN ('DRAFT','PUBLISHED')),
  in_menu TINYINT NOT NULL DEFAULT 1,
  updated_at VARCHAR(19) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS media (
  id {{PK}},
  path VARCHAR(120) NOT NULL UNIQUE,
  original_name VARCHAR(190) NOT NULL,
  uploaded_by INT NULL,
  created_at VARCHAR(19) NOT NULL
){{ENGINE}};

CREATE TABLE IF NOT EXISTS login_attempts (
  id {{PK}},
  email VARCHAR(190) NOT NULL,
  ip VARCHAR(45) NOT NULL,
  created_at VARCHAR(19) NOT NULL
){{ENGINE}};
