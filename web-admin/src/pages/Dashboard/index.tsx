import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
import {
  Calendar,
  MapPin,
  DollarSign,
  Map,
  Users,
  TrendingUp,
  Clock,
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { dashboardService } from '@/services/dashboardService';
import type { DashboardResumen } from '@/services/dashboardService';
import { toast } from 'sonner';

const ACCESOS_CONFIG: Record<string, { label: string; icon: any; href: string; color: string }> = {
  ocupacion: {
    label: 'Ocupación',
    icon: Calendar,
    href: '/panel/ocupacion',
    color: 'bg-blue-500',
  },
  reservas: {
    label: 'Reservas',
    icon: Users,
    href: '/panel/reservas',
    color: 'bg-teal-500',
  },
  reportes: {
    label: 'Reportes',
    icon: TrendingUp,
    href: '/panel/reportes',
    color: 'bg-emerald-500',
  },
  mapa_global: {
    label: 'Mapa Global',
    icon: Map,
    href: '/panel/ocupacion/mapa',
    color: 'bg-purple-500',
  },
};

export default function Dashboard() {
  const [resumen, setResumen] = useState<DashboardResumen | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    cargarResumen();
  }, []);

  const cargarResumen = async () => {
    try {
      setCargando(true);
      setError(null);
      const response = await dashboardService.resumen();
      setResumen(response.data);
    } catch (err: any) {
      const mensaje = err.response?.data?.message || 'Error al cargar el resumen';
      setError(mensaje);
      toast.error(mensaje);
    } finally {
      setCargando(false);
    }
  };

  const formatMonto = (monto: number): string => {
    return new Intl.NumberFormat('es-BO', {
      style: 'currency',
      currency: 'BOB',
      minimumFractionDigits: 2,
    }).format(monto);
  };

  const formatHora = (hora: number): string => {
    return `${hora.toString().padStart(2, '0')}:00`;
  };

  if (cargando) {
    return (
      <div className="flex h-[60vh] items-center justify-center">
        <p className="text-muted-foreground">Cargando resumen...</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      </div>
    );
  }

  if (!resumen) return null;

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
        <p className="text-sm text-muted-foreground">
          Resumen del día — {new Date(resumen.fecha).toLocaleDateString('es-BO', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
          })}
        </p>
      </div>

      {/* KPIs del día */}
      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Reservas hoy</CardTitle>
            <Calendar className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{resumen.reservas_hoy}</div>
            <p className="text-xs text-muted-foreground">Confirmadas</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Ocupados ahora</CardTitle>
            <MapPin className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {resumen.campos_ocupados_ahora} / {resumen.total_campos_activos}
            </div>
            <p className="text-xs text-muted-foreground">Campos activos</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Ingresos hoy</CardTitle>
            <DollarSign className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatMonto(resumen.ingresos_hoy)}</div>
            <p className="text-xs text-muted-foreground">Monto confirmado</p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Campos activos</CardTitle>
            <Map className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{resumen.total_campos_activos}</div>
            <p className="text-xs text-muted-foreground">En el sistema</p>
          </CardContent>
        </Card>
      </div>

      {/* Accesos rápidos */}
      {resumen.accesos_rapidos.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="text-lg">Accesos rápidos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
              {resumen.accesos_rapidos.map((acceso) => {
                const config = ACCESOS_CONFIG[acceso];
                if (!config) return null;

                const Icon = config.icon;

                return (
                  <Link
                    key={acceso}
                    to={config.href}
                    className="flex items-center gap-3 rounded-lg border p-4 transition-colors hover:bg-accent"
                  >
                    <div className={`flex size-10 items-center justify-center rounded-lg ${config.color} text-white`}>
                      <Icon className="size-5" />
                    </div>
                    <span className="font-medium">{config.label}</span>
                  </Link>
                );
              })}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Gráfico de distribución horaria */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <Clock className="size-5" />
            Distribución de reservas de hoy
          </CardTitle>
        </CardHeader>
        <CardContent>
          {resumen.distribucion_hoy.every((d) => d.total === 0) ? (
            <div className="flex h-[300px] items-center justify-center text-muted-foreground">
              Sin reservas confirmadas hoy
            </div>
          ) : (
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={resumen.distribucion_hoy}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis
                  dataKey="hora"
                  tickFormatter={(hora) => formatHora(hora)}
                />
                <YAxis allowDecimals={false} />
                <Tooltip
                  formatter={(value) => [`${value} reservas`, 'Total']}
                  labelFormatter={(hora) => `Hora: ${formatHora(Number(hora))}`}
                />
                <Bar dataKey="total" fill="#14b8a6" />
              </BarChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
