import type { CSSProperties, ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { authStore } from '@/lib/auth-store'
import { useAuth } from '@/hooks/use-auth'
import { api, type ApiSuccess } from '@/lib/api'

// Tenant brand endpoint returns brand_name/brand_color; platform brand
// endpoint returns name/color — normalized below since Shell serves both.
interface BrandData {
  name?: string
  color?: string
  brand_name?: string | null
  brand_color?: string | null
  logo_url: string | null
}

async function fetchBrand(role: 'platform' | 'business'): Promise<BrandData> {
  const url = role === 'platform' ? '/admin/branding' : '/branding'

  return (await api.get<ApiSuccess<BrandData>>(url)).data.data
}

export interface NavItem {
  label: string
  to: string
  section?: string
  // Omit for items any tenant_admin can see (e.g. Dashboard). Set to
  // gate the item for permission-limited members — unrestricted
  // admins (permissions === null) always see everything regardless.
  permission?: string
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
  const { data: brand } = useQuery({
    queryKey: role === 'platform' ? ['admin', 'branding'] : ['branding'],
    queryFn: () => fetchBrand(role),
  })

  const brandName = brand?.brand_name ?? brand?.name
  const accentColor = brand?.brand_color ?? brand?.color

  const initials = (user?.name ?? '?')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()

  const visibleItems = navItems.filter(
    (item) => !item.permission || user?.permissions === null || user?.permissions?.includes(item.permission),
  )

  let lastSection: string | undefined

  return (
    <div className="ds-root" data-role={role} style={accentColor ? ({ '--p': accentColor } as CSSProperties) : undefined}>
      <div className="shell">
        <aside className="sb">
          <div className="sb-logo-wrap">
            <div className="sb-logo-img">
              {brand?.logo_url ? <img src={brand.logo_url} alt="" style={{ width: '100%', height: '100%', objectFit: 'contain' }} /> : logo}
            </div>
            <div className="sb-logo-name">{brandName ?? (logo === 'B' ? 'Business AI' : logo)}</div>
          </div>
          <div className="sb-role">{roleLabel}</div>
          {visibleItems.map((item) => {
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
