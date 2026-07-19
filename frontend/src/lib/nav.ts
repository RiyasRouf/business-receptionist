import type { NavItem } from '@/components/Shell'

export const BUSINESS_NAV: NavItem[] = [
  { label: 'Dashboard', to: '/business' },
  { label: 'Team', to: '/business/team', permission: 'team' },
  { label: 'Knowledge Base', to: '/business/kb', permission: 'knowledge_base' },
  { label: 'Leads', to: '/leads', permission: 'leads' },
  { label: 'Roles & Permissions', to: '/business/roles', section: 'Configuration', permission: 'team' },
  { label: 'Voice & WhatsApp', to: '/business/voice', section: 'Configuration', permission: 'team' },
  { label: 'Branding', to: '/business/brand', section: 'Configuration', permission: 'team' },
]

export const PLATFORM_NAV: NavItem[] = [
  { label: 'Dashboard', to: '/admin' },
  { label: 'Tenants', to: '/admin/tenants' },
  { label: 'Users', to: '/admin/users' },
  { label: 'AI Providers', to: '/admin/ai-providers', section: 'Configuration' },
  { label: 'Voice Configuration', to: '/admin/voice-config', section: 'Configuration' },
  { label: 'Voice & WhatsApp', to: '/admin/voice', section: 'Configuration' },
  { label: 'Branding', to: '/admin/branding', section: 'Configuration' },
  { label: 'Health Monitor', to: '/admin/health', section: 'Configuration' },
  { label: 'Audit Logs', to: '/admin/audit-logs', section: 'Configuration' },
]
