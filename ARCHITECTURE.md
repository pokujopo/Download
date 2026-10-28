# Once Post Anywhere — System Architecture

**Version:** 1.0
**Status:** Core Architecture
**Backend:** Laravel 13
**Database:** MySQL
**Queue/Cache:** Redis

---

## 1. System Purpose

Once Post Anywhere is a SaaS platform that allows a user to create content once and publish it to multiple social platforms through a unified backend.

Initial platform:

* Instagram

Planned providers:

* Facebook
* LinkedIn
* TikTok
* YouTube
* Other providers added through the provider architecture

The core system MUST NOT depend directly on a specific social platform.

---

## 2. Core Architecture

```text
                         CLIENT
                           │
                           ▼
                    Laravel API
                           │
                           ▼
                Application / Domain
                           │
          ┌────────────────┼────────────────┐
          ▼                ▼                ▼
       Content         Social Accounts    Publishing
          │                │                │
          └────────────────┼────────────────┘
                           ▼
                    Provider Contract
                           │
             ┌─────────────┼─────────────┐
             ▼             ▼             ▼
        Instagram       Facebook       LinkedIn
        Provider        Provider        Provider
             │             │             │
             ▼             ▼             ▼
        External APIs / OAuth / Webhooks
```

---

## 3. Architectural Rules

### Rule 1 — Controllers are thin

Controllers handle:

* HTTP request
* authorization
* validation
* calling application/domain services
* returning API resources

Controllers MUST NOT contain social-platform API logic.

---

### Rule 2 — Domain does not know provider implementation

The core system should ask:

```text
Publish this destination.
```

It should NOT ask:

```text
Call Instagram /media endpoint.
```

Provider-specific implementation belongs inside Infrastructure.

---

### Rule 3 — Every social platform uses an adapter/provider

```text
SocialProvider
    │
    ├── InstagramProvider
    ├── FacebookProvider
    ├── LinkedInProvider
    └── TikTokProvider
```

Adding a new platform must not require rewriting the Content or Publishing core.

---

### Rule 4 — Publishing is asynchronous

Publishing must use Laravel Queue.

```text
API Request
    ↓
Create Publish Request
    ↓
Queue Job
    ↓
Provider
    ↓
External API
    ↓
Persist Result
```

The API request should not wait for a long-running external publishing process.

---

### Rule 5 — Credentials are sensitive

Social access tokens must:

* be encrypted at rest
* never appear in normal logs
* never be returned by normal API responses
* never be stored in job payloads when avoidable
* be decrypted only when required by the provider

---

## 4. Application Layers

```text
HTTP
 │
 ▼
Application / Domain
 │
 ▼
Contracts
 │
 ▼
Infrastructure
 │
 ▼
External Services
```

### HTTP

Contains:

```text
Controllers
Requests
Resources
```

### Domain

Contains:

```text
Models
DTOs
Enums
Business Rules
Contracts
Services
```

### Infrastructure

Contains:

```text
Social API clients
OAuth implementations
Storage implementations
External service integrations
```

---

## 5. Folder Structure

```text
app/
│
├── Domain/
│   │
│   ├── Content/
│   │   ├── Models/
│   │   ├── DTOs/
│   │   ├── Enums/
│   │   ├── Services/
│   │   └── Contracts/
│   │
│   ├── Social/
│   │   ├── Models/
│   │   ├── DTOs/
│   │   ├── Enums/
│   │   ├── Contracts/
│   │   └── Services/
│   │
│   ├── Publishing/
│   │   ├── DTOs/
│   │   ├── Enums/
│   │   ├── Jobs/
│   │   ├── Services/
│   │   └── Contracts/
│   │
│   ├── Scheduling/
│   │   ├── Models/
│   │   ├── Services/
│   │   └── Jobs/
│   │
│   ├── Media/
│   │   ├── Models/
│   │   ├── DTOs/
│   │   ├── Services/
│   │   └── Contracts/
│   │
│   └── Billing/
│       ├── Models/
│       ├── Services/
│       └── Contracts/
│
├── Infrastructure/
│   │
│   ├── Social/
│   │   ├── Instagram/
│   │   │   ├── InstagramProvider.php
│   │   │   ├── InstagramApiClient.php
│   │   │   └── InstagramOAuth.php
│   │   │
│   │   ├── Facebook/
│   │   ├── LinkedIn/
│   │   └── TikTok/
│   │
│   ├── Storage/
│   ├── OAuth/
│   └── External/
│
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
│
├── Jobs/
├── Console/
└── Support/
```

---

## 6. Dependency Direction

Dependencies should move inward:

```text
HTTP
 ↓
Application / Domain
 ↓
Contracts
 ↑
Infrastructure implements Contracts
```

The Domain MUST NOT depend directly on:

```text
Instagram SDK
Facebook SDK
Meta API
LinkedIn SDK
specific storage provider
```

---

## 7. Core Publishing Flow

```text
User
 ↓
Create Content
 ↓
Attach Media
 ↓
Select Social Accounts
 ↓
Create Destinations
 ↓
Publish / Schedule
 ↓
Queue
 ↓
SocialPublisher
 ↓
Provider
 ↓
External API
 ↓
Provider Result
 ↓
Destination Status
 ↓
Dashboard
```

---

## 8. Future Expansion

The following can be added without changing the core publishing architecture:

* Facebook Pages
* LinkedIn Pages
* TikTok
* YouTube
* Multiple Instagram accounts
* Multiple Facebook Pages
* Scheduling
* Analytics
* Billing
* Team members
* Workspaces
* Approval workflows
* AI content adaptation

---

## 9. Core Principle

> Create once. Destinate many. Publish through providers.

The Content system owns content.

The Social system owns connected accounts.

The Publishing system owns publishing orchestration.

Infrastructure owns external platform implementation.
