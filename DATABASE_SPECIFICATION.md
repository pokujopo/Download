# Once Post Anywhere — Database Specification

**Version:** 1.0
**Status:** Core Database Design
**Database:** MySQL

---

# 1. Database Principles

The database must separate:

```text
User
 ↓
Social Account
 ↓
Content
 ↓
Media
 ↓
Destination
 ↓
Publishing Result
```

A social account represents a connected external account.

A destination represents where a specific piece of content should be published.

These are NOT the same thing.

---

# 2. Entity Relationship

```text
users
 │
 ├────────────── social_accounts
 │
 └────────────── contents
                    │
                    ├──────── content_media
                    │
                    └──────── content_destinations
                                      │
                                      ▼
                               social_accounts
```

---

# 3. users

Laravel's authentication user table.

```text
users
├── id
├── name
├── email
├── password
├── created_at
└── updated_at
```

Authentication implementation can evolve independently from social publishing.

---

# 4. social_accounts

Stores external social accounts connected by a user.

```text
social_accounts
├── id
├── user_id
├── provider
├── provider_account_id
├── username
├── display_name
├── access_token
├── refresh_token
├── token_expires_at
├── scopes
├── status
├── metadata
├── created_at
└── updated_at
```

### Rules

`provider_account_id` identifies the account on the external platform.

Example:

```text
provider = instagram
provider_account_id = 28780557518298053
username = jboycreater
```

Tokens MUST be encrypted.

`metadata` stores provider-specific information that does not justify additional columns.

---

# 5. contents

Represents the user's canonical content.

```text
contents
├── id
├── user_id
├── caption
├── status
├── scheduled_at
├── published_at
├── created_at
└── updated_at
```

The content is platform-independent.

Example:

```text
caption:
"New product available 🚀"
```

The same content can have multiple destinations.

---

# 6. content_media

Stores media attached to content.

```text
content_media
├── id
├── content_id
├── type
├── storage_disk
├── storage_path
├── public_url
├── metadata
├── created_at
└── updated_at
```

Supported initial types:

```text
image
video
```

Future types:

```text
audio
document
thumbnail
```

---

# 7. content_destinations

Represents an intended publication target.

```text
content_destinations
├── id
├── content_id
├── social_account_id
├── provider
├── status
├── scheduled_at
├── external_media_id
├── error_code
├── error_message
├── provider_response
├── attempts
├── last_attempt_at
├── published_at
├── created_at
└── updated_at
```

Example:

```text
Content #25
│
├── Instagram Account A → published
├── Instagram Account B → failed
└── Facebook Page A      → queued
```

Each destination has an independent publishing lifecycle.

---

# 8. Statuses

## Content

```text
draft
scheduled
publishing
partially_published
published
failed
```

## Social Account

```text
active
reauthorization_required
disconnected
disabled
```

## Destination

```text
pending
queued
publishing
published
failed
cancelled
```

---

# 9. Relationships

```text
User
 ├── hasMany SocialAccounts
 └── hasMany Contents

SocialAccount
 ├── belongsTo User
 └── hasMany ContentDestinations

Content
 ├── belongsTo User
 ├── hasMany ContentMedia
 └── hasMany ContentDestinations

ContentMedia
 └── belongsTo Content

ContentDestination
 ├── belongsTo Content
 └── belongsTo SocialAccount
```

---

# 10. Indexes

Required indexes:

```text
social_accounts:
(user_id, provider)
(user_id, provider, provider_account_id)

contents:
(user_id, status)
(user_id, scheduled_at)

content_destinations:
(content_id, status)
(social_account_id, status)
(status, scheduled_at)
```

Unique external account constraint should prevent the same external account from being connected multiple times for the same user/provider.

---

# 11. Provider Response

Provider responses may be stored for debugging and auditing.

However:

```text
NEVER store:
- access tokens
- refresh tokens
- client secrets
- authorization codes
- sensitive credentials
```

Provider response storage must be sanitized.

---

# 12. Future Billing Tables

Billing is intentionally separated from publishing.

Future:

```text
plans
subscriptions
subscription_items
usage_records
payments
```

Publishing MUST NOT directly depend on a specific payment provider.

---

# 13. Future Analytics Tables

Analytics should also be separate:

```text
content_metrics
account_metrics
metric_snapshots
```

The publishing system only records publication identity/status.

Analytics owns performance data.

---

# 14. Core Database Rule

```text
Content
    ≠
Destination
    ≠
Social Account
```

This separation is mandatory because one content item can be published to many destinations while one social account can receive many content items.
