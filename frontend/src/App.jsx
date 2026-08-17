import { Routes, Route, Navigate } from 'react-router-dom'
import { useAuth } from './hooks/useAuth.jsx'
import Layout from './components/Layout.jsx'
import ProtectedRoute from './components/ProtectedRoute.jsx'
import LoginPage from './pages/LoginPage.jsx'
import RegisterPage from './pages/RegisterPage.jsx'
import DashboardPage from './pages/DashboardPage.jsx'
import ApplicationsPage from './pages/ApplicationsPage.jsx'
import ApplicationPage from './pages/ApplicationPage.jsx'
import ReviewPage from './pages/ReviewPage.jsx'
import UsersPage from './pages/UsersPage.jsx'
import CeoPage from './pages/CeoPage.jsx'
import NotFoundPage from './pages/NotFoundPage.jsx'

export default function App() {
  const { user, loading } = useAuth()

  if (loading) {
    return (
      <div className="boot">
        <div className="boot-spinner" aria-label="Loading" />
        <p className="muted">KYC Verify</p>
      </div>
    )
  }

  return (
    <Routes>
      <Route path="/" element={user ? <Navigate to="/dashboard" replace /> : <Navigate to="/login" replace />} />
      <Route path="/login" element={user ? <Navigate to="/dashboard" replace /> : <LoginPage />} />
      <Route path="/register" element={user ? <Navigate to="/dashboard" replace /> : <RegisterPage />} />

      <Route element={<ProtectedRoute />}>
        <Route element={<Layout />}>
          <Route path="/dashboard" element={<DashboardPage />} />
          <Route path="/applications" element={<ApplicationsPage />} />
          <Route path="/application/:id" element={<ApplicationPage />} />
          <Route path="/review" element={<ReviewPage />} />
          <Route path="/users" element={<UsersPage />} />
          <Route path="/ceo" element={<CeoPage />} />
        </Route>
      </Route>

      <Route path="*" element={<NotFoundPage />} />
    </Routes>
  )
}
