import type { NavItem } from '@/components/Shell'

export const BUSINESS_NAV: NavItem[] = [
  { label: 'Dashboard', to: '/business' },
  { label: 'Team', to: '/business/team', permission: 'team' },
  { label: 'Knowledge Base', to: '/business/kb', permission: 'knowledge_base' },
  { label: 'Leads', to: '/leads', permission: 'leads' },
  { label: 'Roles & Permissions', to: '/business/roles', section: 'Configuration', permission: 'team' },
]

export const PLATFORM_NAV: NavItem[] = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tenants', to: '/admin/tenants' },
  { label: 'Users', to: '/admin/users' },
  { label: 'AI Providers', to: '/admin/ai-providers', section: 'Configuration' },
]
