import * as React from 'react';
import {
  HomeIcon,
  LogOutIcon,
  MapIcon,
  MapPinIcon,
  UsersIcon,
  CalendarRangeIcon,
  CalendarCheck,
  LayoutDashboard,
  Link2Icon,
  Building2 as Building2Icon,
  Shapes,
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
  SidebarSeparator,
} from '@/components/ui/sidebar';
import { useAuth } from '@/context/AuthContext';
import { configDeRol } from '@/lib/roles';

type NavItem = {
  title: string;
  url: string;
  icon: React.ElementType;
  permiso?: string | null;
  roles?: string[];
};

type NavGroup = {
  label: string;
  items: NavItem[];
};

// ─── Agrupación por lógica de dominio ───
// Operación = lo diario · Paramétricas = catálogo/configuración ·
// Acceso = personas y asignación · Análisis = visión gerencial.
const navGroups: NavGroup[] = [
  {
    label: 'Operación',
    items: [
      { title: 'Dashboard', url: '/panel', icon: HomeIcon, permiso: null },
      {
        title: 'Ocupación',
        url: '/panel/ocupacion',
        icon: CalendarCheck,
        roles: ['admin_parametricas', 'admin_reservas', 'funcionario_control'],
      },
      {
        title: 'Reservas',
        url: '/panel/reservas',
        icon: CalendarRangeIcon,
        roles: ['admin_parametricas', 'admin_reservas', 'funcionario_control'],
      },
    ],
  },
  {
    label: 'Paramétricas',
    items: [
      {
        title: 'Campos',
        url: '/panel/parametricas/campos',
        icon: MapPinIcon,
        permiso: 'gestionar-campos',
      },
      {
        title: 'Tipos de Campo',
        url: '/panel/parametricas/tipos-campo',
        icon: Shapes,
        permiso: 'gestionar-tipos-campo',
      },
      {
        title: 'Asociaciones',
        url: '/panel/parametricas/asociaciones',
        icon: Building2Icon,
        roles: ['admin_parametricas'],
      },
    ],
  },
  {
    label: 'Acceso',
    items: [
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
    ],
  },
  {
    label: 'Análisis',
    items: [
      {
        title: 'Mapa Global',
        url: '/panel/ocupacion/mapa',
        icon: MapIcon,
        roles: ['admin_parametricas', 'gerencia'],
      },
      {
        title: 'Reportes',
        url: '/panel/reportes',
        icon: LayoutDashboard,
        roles: ['admin_parametricas', 'gerencia'],
      },
    ],
  },
];

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { funcionario, tienePermiso, logout } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  const pasaItem = (item: NavItem): boolean => {
    if (item.roles) {
      const rol = (funcionario as any)?.rol?.nombre as string | undefined;
      const esGerencia = (funcionario as any)?.rol?.permisos?.includes('*');
      return (!!rol && item.roles.includes(rol)) || esGerencia;
    }
    return !item.permiso || tienePermiso(item.permiso);
  };

  // Filtro por grupo; los grupos que quedan vacíos no se renderizan.
  const gruposFiltrados = navGroups
    .map((grupo) => ({ ...grupo, items: grupo.items.filter(pasaItem) }))
    .filter((grupo) => grupo.items.length > 0);

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
    <Sidebar collapsible="icon" className="border-r border-sidebar-border" {...props}>
      {/* ─── Header con branding (texto oculto en rail) ─── */}
      <SidebarHeader className="border-b border-sidebar-border py-4">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              className="data-[slot=sidebar-menu-button]:p-1.5 group-data-[collapsible=icon]:p-0!"
              render={<Link to="/panel" />}
            >
              <div className="flex size-8 items-center justify-center rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600 text-white group-data-[collapsible=icon]:size-5">
                <MapIcon className="size-4" />
              </div>
              <div className="flex flex-col text-left leading-tight group-data-[collapsible=icon]:hidden">
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
        {gruposFiltrados.map((grupo, i) => (
          <React.Fragment key={grupo.label}>
            {i > 0 && <SidebarSeparator />}
            <SidebarGroup>
              <SidebarGroupLabel className="px-3 text-[10px] uppercase tracking-wider text-sidebar-foreground/50">
                {grupo.label}
              </SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu className="gap-1 px-2">
                  {grupo.items.map((item) => {
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
                            <span className="ml-auto size-1.5 rounded-full bg-teal-400 group-data-[collapsible=icon]:hidden" />
                          )}
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  })}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </React.Fragment>
        ))}
      </SidebarContent>

      {/* ─── Footer con usuario (texto oculto en rail) ─── */}
      <SidebarFooter className="border-t border-sidebar-border pt-3">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              className="rounded-lg px-2 data-[slot=sidebar-menu-button]:p-2"
            >
              <Avatar className="size-9 rounded-lg bg-gradient-to-br from-teal-400 to-emerald-600 group-data-[collapsible=icon]:size-5">
                <AvatarFallback className="rounded-lg bg-transparent text-white font-semibold text-xs">
                  {initials}
                </AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-left text-sm leading-tight min-w-0 group-data-[collapsible=icon]:hidden">
                <span className="truncate font-semibold text-sidebar-foreground">
                  {funcionario?.nombre_completo}
                </span>
                <span className="truncate text-xs text-sidebar-foreground/60">
                  {configDeRol((funcionario as any)?.rol).label}
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
              <span className="group-data-[collapsible=icon]:hidden">Cerrar sesión</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  );
}
