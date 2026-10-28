# Once Post Anywhere — Publishing Flow

**Version:** 1.0
**Status:** Core Publishing Flow

---

# 1. Objective

Provide one unified publishing pipeline capable of publishing the same content to multiple social platforms.

The publishing core must remain platform-independent.

---

# 2. High-Level Flow

```text
USER
 │
 ▼
Create Content
 │
 ▼
Attach Media
 │
 ▼
Select Destinations
 │
 ▼
Create Content Destinations
 │
 ▼
Publish Now / Schedule
 │
 ▼
Queue Job
 │
 ▼
SocialPublisher
 │
 ▼
Provider Resolver
 │
 ├──── InstagramProvider
 ├──── FacebookProvider
 ├──── LinkedInProvider
 └──── TikTokProvider
 │
 ▼
External Platform API
 │
 ▼
Publish Result
 │
 ▼
Update Destination
 │
 ▼
Update Content Status
 │
 ▼
Dashboard
```

---

# 3. Publish Now

```text
POST /content/{content}/publish
          │
          ▼
Validate ownership
          │
          ▼
Validate content
          │
          ▼
Validate destinations
          │
          ▼
Create publishing records
          │
          ▼
Dispatch jobs
          │
          ▼
Return queued response
```

The HTTP request must NOT wait for external social API completion.

---

# 4. Queue Flow

```text
PublishSocialContent
        │
        ▼
Load ContentDestination
        │
        ▼
Check destination status
        │
        ├── cancelled → stop
        ├── published → stop
        └── pending/queued → continue
        │
        ▼
SocialPublisher
        │
        ▼
Resolve Provider
        │
        ▼
Provider.publish()
```

---

# 5. Provider Contract

The core should depend on a provider contract.

Conceptually:

```text
SocialProvider
├── validate()
├── publish()
├── getStatus()
└── disconnect()
```

Provider-specific APIs are hidden behind this contract.

---

# 6. Instagram Publishing

The verified Instagram flow is:

```text
InstagramProvider
       │
       ▼
Validate account
       │
       ▼
Get encrypted access token
       │
       ▼
Get public media URL
       │
       ▼
POST /{IG_ID}/media
       │
       ▼
Media Container ID
       │
       ▼
Check container status
       │
       ├── IN_PROGRESS
       │       ↓
       │     retry/check
       │
       ├── ERROR
       │       ↓
       │     failed
       │
       └── FINISHED
               │
               ▼
POST /{IG_ID}/media_publish
               │
               ▼
Instagram Media ID
               │
               ▼
Save result
```

---

# 7. Instagram Result

Successful result:

```text
external_media_id
status = published
published_at = now
```

Failed result:

```text
status = failed
error_code
error_message
provider_response
attempts
last_attempt_at
```

---

# 8. Retry Strategy

Not every error should be retried.

### Retryable

Examples:

```text
temporary network failure
provider timeout
temporary API failure
rate limiting
temporary processing state
```

### Non-retryable

Examples:

```text
invalid access token
invalid media
missing permission
invalid destination
unsupported media format
permanent API validation error
```

Authentication errors should move the social account to:

```text
reauthorization_required
```

rather than repeatedly retrying.

---

# 9. Idempotency

Publishing must protect against duplicate posts.

Before publishing:

```text
Check ContentDestination
        │
        ├── published → do nothing
        │
        └── not published → continue
```

A retry must not blindly create another publication.

Provider-specific idempotency strategy may be implemented inside the provider.

---

# 10. Scheduling

Scheduled publishing:

```text
User
 ↓
scheduled_at
 ↓
Destination status = scheduled
 ↓
Laravel Scheduler
 ↓
Find due destinations
 ↓
Dispatch PublishSocialContent
 ↓
Queue
 ↓
Provider
```

The scheduler should only dispatch work.

It should NOT contain provider API logic.

---

# 11. Multi-platform Publishing

Example:

```text
One Content
│
├── Instagram
│      ↓
│   InstagramProvider
│
├── Facebook
│      ↓
│   FacebookProvider
│
└── LinkedIn
       ↓
    LinkedInProvider
```

Each destination gets an independent result.

Example:

```text
Instagram → published
Facebook  → published
LinkedIn  → failed
```

The original Content must preserve these independent results.

---

# 12. Content Status Calculation

Content status is derived from its destinations.

Example:

```text
All published
    ↓
published

Some published
Some failed
    ↓
partially_published

All failed
    ↓
failed

Any active publishing
    ↓
publishing
```

The Content status must not erase individual destination results.

---

# 13. Media Requirement

External social providers may require publicly accessible media.

Therefore:

```text
User Upload
    ↓
Media Storage
    ↓
Public/Signed URL
    ↓
Provider
    ↓
External Platform
```

The provider must receive a usable media URL.

Storage implementation must remain replaceable.

---

# 14. Security

Publishing jobs MUST NOT contain raw access tokens.

Instead:

```text
Job
 ↓
destination_id
 ↓
social_account_id
 ↓
load account
 ↓
decrypt token
 ↓
API request
```

Logs must sanitize:

```text
Authorization
access_token
refresh_token
client_secret
authorization_code
```

---

# 15. Webhooks

Webhook architecture is separate from the initial publishing request.

```text
External Platform
       ↓
Webhook Endpoint
       ↓
Verify Signature
       ↓
Normalize Event
       ↓
Domain Event
       ↓
Update Database
```

Provider-specific webhook implementation belongs inside Infrastructure.

---

# 16. Failure Flow

```text
Provider
   │
   ▼
External API
   │
   ├── success
   │      ↓
   │   published
   │
   ├── temporary failure
   │      ↓
   │   retry
   │
   ├── rate limit
   │      ↓
   │   delayed retry
   │
   ├── auth failure
   │      ↓
   │   reauthorization_required
   │
   └── permanent failure
          ↓
        failed
```

---

# 17. Observability

Every publishing attempt should have enough information to answer:

```text
Who published?
What content?
Which destination?
Which provider?
When?
Attempt number?
External ID?
Success/failure?
Provider error?
```

Never log credentials.

---

# 18. Final Publishing Principle

```text
Content
   ↓
Destination
   ↓
Queue
   ↓
Provider
   ↓
External API
   ↓
Result
   ↓
Database
```

The provider is replaceable.

The publishing core is not provider-specific.

This architecture allows new social platforms to be added without rebuilding the publishing system.
