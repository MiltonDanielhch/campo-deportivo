import { Outlet, useLocation } from 'react-router-dom';
import { AppSidebar } from '@/components/app-sidebar';
import { ThemeToggle } from '@/components/ui/theme-toggle';
import { useBreadcrumbContext } from '@/context/BreadcrumbContext';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Separator } from '@/components/ui/separator';
import {
  SidebarInset,
  SidebarProvider,
  SidebarTrigger,
} from '@/components/ui/sidebar';

const RUTA_LABELS: Record<string, string> = {
  '/panel': 'Dashboard',
  '/panel/parametricas': 'Paramétricas',
  '/panel/parametricas/tipos-campo': 'Tipos de Campo',
  '/panel/parametricas/campos': 'Campos Deportivos',
  '/panel/reservas': 'Reservas',
  '/panel/funcionarios': 'Funcionarios',
  '/panel/asignaciones': 'Asignaciones',
};

function buildBreadcrumbs(pathname: string, overrides: Record<string, string>) {
  const partes = pathname.split('/').filter(Boolean);

  // Si la ruta es solo /panel, no hay crumbs adicionales (ya está GAD Beni)
  if (partes.length === 1 && partes[0] === 'panel') return [];

  // Omitimos "panel" del inicio (ya está representado por "GAD Beni" fijo)
  const partesVisibles = partes[0] === 'panel' ? partes.slice(1) : partes;

  const crumbs: { label: string; href: string; esFinal: boolean }[] = [];
  let rutaAcumulada = '/panel';

  partesVisibles.forEach((parte, i) => {
    rutaAcumulada += '/' + parte;
    // Prioridad: 1) override del contexto, 2) mapa estático, 3) segmento tal cual
    const label = overrides[rutaAcumulada] ?? RUTA_LABELS[rutaAcumulada] ?? parte;
    const esFinal = i === partesVisibles.length - 1;
    crumbs.push({ label, href: rutaAcumulada, esFinal });
  });

  return crumbs;
}

function BreadcrumbBar() {
  const location = useLocation();
  const { overrides } = useBreadcrumbContext();
  const crumbs = buildBreadcrumbs(location.pathname, overrides);

  return (
    <header className="flex h-14 shrink-0 items-center gap-2 border-b bg-background/80 backdrop-blur-sm px-4 sticky top-0 z-30">
      <SidebarTrigger className="-ml-1" />
      <Separator orientation="vertical" className="mr-2 h-4" />
      <Breadcrumb className="flex-1">
        <BreadcrumbList>
          <BreadcrumbItem>
            <BreadcrumbLink href="/panel">GAD Beni</BreadcrumbLink>
          </BreadcrumbItem>
          {crumbs.map((crumb) => (
            <span key={crumb.href} className="contents">
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                {crumb.esFinal ? (
                  <BreadcrumbPage className="font-medium text-foreground">
                    {crumb.label}
                  </BreadcrumbPage>
                ) : (
                  <BreadcrumbLink href={crumb.href}>{crumb.label}</BreadcrumbLink>
                )}
              </BreadcrumbItem>
            </span>
          ))}
        </BreadcrumbList>
      </Breadcrumb>
      <ThemeToggle />
    </header>
  );
}

export default function AppLayout() {
  return (
    <SidebarProvider>
      <AppSidebar />
      <SidebarInset>
        <BreadcrumbBar />
        <main className="flex-1 overflow-auto">
          <Outlet />
        </main>
      </SidebarInset>
    </SidebarProvider>
  );
}
