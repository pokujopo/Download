/**
 * layouts/MainLayout.jsx
 *
 * App shell — lifts posts state (real API) and passes it via Outlet context.
 */

import { useState } from 'react'
import { Outlet } from 'react-router-dom'
import Sidebar from '@/components/Sidebar'
import Topbar  from '@/components/Topbar'
import { usePosts } from '@/hooks/usePosts'

export default function MainLayout() {
  const [sidebarOpen, setSidebarOpen] = useState(false)
  const postsState = usePosts()

  return (
    <div className="flex min-h-screen bg-surface-bg font-body">
      {/* Mobile overlay */}
      {sidebarOpen && (
        <div
          className="fixed inset-0 z-[99] bg-black/60 backdrop-blur-sm lg:hidden"
          onClick={() => setSidebarOpen(false)}
        />
      )}

      <Sidebar open={sidebarOpen} onClose={() => setSidebarOpen(false)} />

      <div className="flex flex-1 flex-col lg:ml-[240px]">
        <Topbar onMenuClick={() => setSidebarOpen((v) => !v)} />

        <main className="flex-1 p-5 pt-[84px] lg:p-8 lg:pt-[88px]">
          <Outlet context={postsState} />
        </main>
      </div>
    </div>
  )
}
