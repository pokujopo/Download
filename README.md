# ZynkPost — Full-Stack Integration

React (Vite + Tailwind) frontend connected to a Django REST Framework backend with JWT authentication.

---

## Quick Start

### 1. Backend

```bash
cd backend
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt

# Configure
cp .env.example .env    # fill in SECRET_KEY and DB_* values

# MySQL
mysql -u root -p -e "
  CREATE DATABASE zynkpost_db CHARACTER SET utf8mb4;
  CREATE USER 'zynkpost_user'@'localhost' IDENTIFIED BY 'your_password';
  GRANT ALL PRIVILEGES ON zynkpost_db.* TO 'zynkpost_user'@'localhost';
"

# Migrate & run
python manage.py migrate
python manage.py createsuperuser   # optional
python manage.py runserver         # → http://localhost:8000
```

### 2. Frontend

```bash
# From the project root (zynkpost_integrated/)
cp .env.example .env               # VITE_API_BASE_URL=http://localhost:8000
npm install
npm run dev                        # → http://localhost:5173
```

Open **http://localhost:5173** — you'll be redirected to `/login`.

---

## Integration Architecture

```
Frontend (Vite :5173)
  │
  ├── src/context/AuthContext.jsx      ← global auth state, bootstrap on load
  ├── src/services/apiClient.js        ← Axios instance, JWT interceptors, silent refresh
  ├── src/services/authService.js      ← register / login / logout / getMe
  ├── src/services/postService.js      ← createPost / getPosts / getPost / updatePost / deletePost
  ├── src/hooks/usePosts.js            ← real API fetch, replaces mock data
  ├── src/hooks/useUpload.js           ← real multipart upload, Axios onUploadProgress
  ├── src/components/ProtectedRoute    ← redirect to /login if no token
  └── src/layouts/AuthLayout.jsx       ← redirect to /dashboard if already authed
  │
  │   (Vite proxy: /api/* → localhost:8000)
  │
Backend (Django :8000)
  ├── /api/auth/register/     POST  — returns { access_token, refresh_token, user }
  ├── /api/auth/login/        POST  — returns { access_token, refresh_token, user }
  ├── /api/auth/token/refresh/ POST — silent token refresh
  ├── /api/auth/logout/       POST  — blacklists refresh token
  ├── /api/auth/me/           GET   — current user profile
  ├── /api/posts/create/      POST  — multipart upload
  ├── /api/posts/             GET   — user's posts (paginated)
  └── /api/posts/<id>/        GET/PATCH/DELETE
```

## Token Flow

1. Login → Django returns `access_token` + `refresh_token` → stored in `localStorage`
2. Every Axios request → `Authorization: Bearer <access_token>` added automatically
3. On 401 → Axios interceptor silently calls `/api/auth/token/refresh/` → retries original request
4. If refresh also fails → localStorage cleared → redirect to `/login`
5. Logout → server blacklists refresh token → localStorage cleared → redirect to `/login`

## New / Modified Files

### Frontend (new)
| File | Purpose |
|------|---------|
| `src/context/AuthContext.jsx` | Global auth state + bootstrap |
| `src/services/apiClient.js` | Axios + JWT interceptors |
| `src/services/authService.js` | Auth API calls |
| `src/services/postService.js` | Post API calls |
| `src/components/ProtectedRoute.jsx` | Route guard |
| `src/layouts/AuthLayout.jsx` | Login/register shell |
| `src/pages/auth/LoginPage.jsx` | Login form |
| `src/pages/auth/RegisterPage.jsx` | Register form |
| `src/components/ui/AlertBanner.jsx` | Error/success UI |

### Frontend (modified)
| File | Change |
|------|--------|
| `package.json` | Added `axios` |
| `vite.config.js` | Added `/api` proxy |
| `main.jsx` | Wrapped in `<AuthProvider>` |
| `App.jsx` | Added auth routes + ProtectedRoute |
| `layouts/MainLayout.jsx` | Uses real `usePosts` |
| `hooks/usePosts.js` | Real API, no mock data |
| `hooks/useUpload.js` | Real upload, Axios progress |
| `components/Sidebar.jsx` | Shows real user info |
| `components/Topbar.jsx` | Real logout button |
| `components/RecentPosts.jsx` | Handles API field names |
| `components/charts/ActivityChart.jsx` | Derived from real posts |
| `components/charts/PlatformReach.jsx` | Derived from real posts |
| `pages/DashboardPage.jsx` | Loading + error states |
| `pages/CreatePostPage.jsx` | Real upload progress |
| `components/ProgressBar.jsx` | Single real-progress bar |

### Backend (modified)
| File | Change |
|------|--------|
| `zynkpost/settings.py` | CORS updated for Vite origins |
| `posts/serializers.py` | Parses JSON `platforms` from FormData |
