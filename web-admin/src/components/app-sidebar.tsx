import * as React from "react"
import {
  CommandIcon,
  HomeIcon,
  LogOutIcon,
  MapIcon,
  MapPinIcon,
  UsersIcon,
} from "lucide-react"
import { Link, useLocation, useNavigate } from "react-router-dom"

import { Avatar, AvatarFallback } from "@/components/ui/avatar"
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
} from "@/components/ui/sidebar"
import { useAuth } from "@/context/AuthContext"

const navItems = [
  { title: "Dashboard", url: "/panel", icon: HomeIcon, permiso: null },
  {
    title: "Campos",
    url: "/panel/parametricas/campos",
    icon: MapIcon,
    permiso: "gestionar-campos",
  },
  {
    title: "Tipos de Campo",
    url: "/panel/parametricas/tipos-campo",
    icon: MapIcon,
    permiso: "gestionar-tipos-campo",
  },
  { title: "Reservas", url: "/panel/reservas", icon: MapPinIcon, permiso: "ver-reservas" },
  {
    title: "Funcionarios",
    url: "/panel/funcionarios",
    icon: UsersIcon,
    permiso: "gestionar-funcionarios",
  },

  {
    title: "Asignaciones",
    url: "/panel/asignaciones",
    icon: MapPinIcon, // o cualquier otro ícono de lucide-react
    permiso: "gestionar-funcionarios",
  },
]

export function AppSidebar({ ...props }: React.ComponentProps<typeof Sidebar>) {
  const { funcionario, tienePermiso, logout } = useAuth()
  const location = useLocation()
  const navigate = useNavigate()

  const items = navItems.filter((i) => !i.permiso || tienePermiso(i.permiso))

  const handleLogout = async () => {
    await logout()
    navigate("/login")
  }

  const initials = (funcionario?.nombre_completo ?? "U")
    .split(" ")
    .map((p) => p[0])
    .slice(0, 2)
    .join("")
    .toUpperCase()

  return (
    <Sidebar collapsible="offcanvas" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              className="data-[slot=sidebar-menu-button]:p-1.5!"
              render={<Link to="/panel" />}
            >
              <CommandIcon className="size-5!" />
              <span className="text-base font-semibold">GAD Beni</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Administración</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {items.map((item) => (
                <SidebarMenuItem key={item.url}>
                  <SidebarMenuButton
                    isActive={location.pathname === item.url}
                    tooltip={item.title}
                    render={<Link to={item.url} />}
                  >
                    <item.icon />
                    <span>{item.title}</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg">
              <Avatar className="h-8 w-8 rounded-lg">
                <AvatarFallback className="rounded-lg">{initials}</AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">
                  {funcionario?.nombre_completo}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                  {funcionario?.rol?.nombre}
                </span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
          <SidebarMenuItem>
            <SidebarMenuButton onClick={handleLogout} tooltip="Cerrar sesión">
              <LogOutIcon />
              <span>Cerrar sesión</span>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  )
}
