import type { ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { authStore } from '@/lib/auth-store'
import { useAuth } from '@/hooks/use-auth'

export interface NavItem {
  label: string
  to: string
  section?: string
}

interface ShellProps {
  role: 'platform' | 'business'
  logo: string
  roleLabel: string
  navItems: NavItem[]
  activePath: string
  title: string
  subtitle?: string
  topbarActions?: ReactNode
  children: ReactNode
}

export function Shell({ role, logo, roleLabel, navItems, activePath, title, subtitle, topbarActions, children }: ShellProps) {
  const { user } = useAuth()
  const navigate = useNavigate()

  const initials = (user?.name ?? '?')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  let lastSection: string | undefined

  return (
    <div className="ds-root" data-role={role}>
      <div className="shell">
        <aside className="sb">
          <div className="sb-logo-wrap">
            <div className="sb-logo-img">{logo}</div>
            <div className="sb-logo-name">{logo === 'B' ? 'Business AI' : logo}</div>
          </div>
          <div className="sb-role">{roleLabel}</div>
          {navItems.map((item) => {
            const showSection = item.section && item.section !== lastSection
            lastSection = item.section

            return (
              <div key={item.to}>
                {showSection && <div className="sb-sec">{item.section}</div>}
                <div
                  className={`si ${activePath === item.to ? 'on' : ''}`}
                  onClick={() => navigate(item.to)}
                >
                  <span className="dot" />
                  {item.label}
                </div>
              </div>
            )
          })}
          <div className="sb-foot">
            <div className="av-row">
              <div className="av">{initials}</div>
              <div>
                <div className="av-n">{user?.name}</div>
                <div className="av-r" style={{ cursor: 'pointer' }} onClick={() => authStore.clear()}>
                  Sign out
                </div>
              </div>
            </div>
          </div>
        </aside>
        <div className="main">
          <div className="topbar">
            <div>
              <span className="tb-t">{title}</span>
              {subtitle && <span className="tb-s">{subtitle}</span>}
            </div>
            {topbarActions && <div className="tb-a">{topbarActions}</div>}
          </div>
          <div className="content">{children}</div>
        </div>
      </div>
    </div>
  )
}
