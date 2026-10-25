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
