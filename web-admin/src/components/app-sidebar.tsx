import * as React from 'react';
import {
  HomeIcon,
  LogOutIcon,
  MapIcon,
  MapPinIcon,
  UsersIcon,
  CalendarRangeIcon,
  Link2Icon,
} from 'lucide-react';
import { Link, useLocation, useNavigate } from 'react-router-dom';

import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useAuth } from '@/context/AuthContext';

type NavItem = {
  title: string;
  url: string;
  icon: React.ElementType;
  permiso?: string | null;
  roles?: string[];
};

const navItems: NavItem[] = [
  { title: 'Dashboard', url: '/panel', icon: HomeIcon, permiso: null },
  {
    title: 'Campos',
    url: '/panel/parametricas/campos',
    icon: MapIcon,
    permiso: 'gestionar-campos',
  },
  {
    title: 'Tipos de Campo',
    url: '/panel/parametricas/tipos-campo',
    icon: MapIcon,
    permiso: 'gestionar-tipos-campo',
  },
  {
    title: 'Reservas',
    url: '/panel/reservas',
    icon: CalendarRangeIcon,
    roles: ['admin_parametricas', 'admin_reservas', 'funcionario_control'],
  },
  {
    title: 'Funcionarios',
    url: '/panel/funcionarios',
    icon: UsersIcon,
    permiso: 'gestionar-funcionarios',
  },
  {
    title: 'Asignaciones',
    url: '/panel/asignaciones',
    icon: Link2Icon,
    permiso: 'gestionar-funcionarios',
  },
];

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { funcionario, tienePermiso, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  const items = navItems.filter((item) => {
    if (item.roles) {
      const rol = (funcionario as any)?.rol?.nombre as string | undefined;
      return !!rol && item.roles.includes(rol);
    }

    return !item.permiso || tienePermiso(item.permiso);
  });

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const initials = (funcionario?.nombre_completo ?? 'U')
    .split(' ')
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase();

  return (
    <Sidebar collapsible="offcanvas" className="border-r border-sidebar-border" {...props}>
      {/* ─── Header con branding ─── */}
      <SidebarHeader className="border-b border-sidebar-border py-4">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              className="data-[slot=sidebar-menu-button]:p-1.5!"
              render={<Link to="/panel" />}
            >
              <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600 text-white">
                <MapIcon className="size-4" />
              </div>
              <div className="flex flex-col text-left leading-tight">
                <span className="text-sm font-bold">GAD Beni</span>
                <span className="text-[10px] text-sidebar-foreground/60 uppercase tracking-wider">
                  Canchas deportivas
                </span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent className="py-3">
        <SidebarGroup>
          <SidebarGroupLabel className="px-3 text-[10px] uppercase tracking-wider text-sidebar-foreground/50">
            Administración
          </SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu className="gap-1 px-2">
              {items.map((item) => {
                const activo =
                  location.pathname === item.url ||
                  (item.url !== '/panel' && location.pathname.startsWith(item.url));
                return (
                  <SidebarMenuItem key={item.url}>
                    <SidebarMenuButton
                      isActive={activo}
                      tooltip={item.title}
                      render={<Link to={item.url} />}
                      className={`rounded-lg px-3 py-2 transition-colors ${
                        activo
                          ? 'bg-teal-500/15 text-teal-300 data-[active=true]:bg-teal-500/15 data-[active=true]:text-teal-300'
                          : 'text-sidebar-foreground/80 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'
                      }`}
                    >
                      <item.icon className={activo ? 'text-teal-400' : ''} />
                      <span className="font-medium">{item.title}</span>
                      {activo && (
                        <span className="ml-auto size-1.5 rounded-full bg-teal-400" />
                      )}
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                );
              })}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      {/* ─── Footer con usuario ─── */}
      <SidebarFooter className="border-t border-sidebar-border pt-3">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              className="rounded-lg px-2 data-[slot=sidebar-menu-button]:p-2"
            >
              <Avatar className="size-9 rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600">
                <AvatarFallback className="rounded-lg bg-transparent text-white font-semibold text-xs">
                  {initials}
                </AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-left text-sm leading-tight min-w-0">
                <span className="truncate font-semibold text-sidebar-foreground">
                  {funcionario?.nombre_completo}
                </span>
                <span className="truncate text-xs text-sidebar-foreground/60">
                  {funcionario?.rol?.nombre}
                </span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton
              onClick={handleLogout}
              tooltip="Cerrar sesión"
              className="rounded-lg text-sidebar-foreground/70 hover:bg-red-500/10 hover:text-red-300"
            >
              <LogOutIcon className="size-4" />
              <span>Cerrar sesión</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  );
}
