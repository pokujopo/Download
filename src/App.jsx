import { Routes, Route, Navigate } from 'react-router-dom'
import MainLayout      from '@/layouts/MainLayout'
import AuthLayout      from '@/layouts/AuthLayout'
import ProtectedRoute  from '@/components/ProtectedRoute'
import DashboardPage   from '@/pages/DashboardPage'
import CreatePostPage  from '@/pages/CreatePostPage'
import AccountsPage    from '@/pages/AccountsPage'
import LoginPage       from '@/pages/auth/LoginPage'
import RegisterPage    from '@/pages/auth/RegisterPage'
import FacebookPageSelectPage from '@/pages/FacebookPageSelectPage'

export default function App() {
  return (
    <Routes>
      {/* Public auth routes */}
      <Route element={<AuthLayout />}>
        <Route path="/login"    element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />
      </Route>

      {/* Protected app routes */}
      <Route element={<ProtectedRoute />}>
        <Route element={<MainLayout />}>
          <Route index element={<Navigate to="/dashboard" replace />} />
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/create"    element={<CreatePostPage />} />
          <Route path="/accounts"  element={<AccountsPage />} />
        </Route>
      </Route>

      {/* Catch-all */}
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
      <Route
  path="/settings/social-accounts/facebook/select"
  element={<FacebookPageSelectPage />}
/>
    </Routes>
  )
}
