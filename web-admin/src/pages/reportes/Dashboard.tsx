import { useState, useEffect, useRef } from 'react';
import {
  BarChart,
  Bar,
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  Legend,
} from 'recharts';
import { Calendar, TrendingUp, Clock, DollarSign } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { reportesService } from '@/services/reportesService';
import type { IngresoPorCampo, HoraPico } from '@/types/reportes';
import { haceNDiasISO, hoyISO } from '@/lib/fecha';
import { formatMontoBoB } from '@/lib/moneda';
import { toast } from 'sonner';

export default function Dashboard() {
  const [desde, setDesde] = useState(haceNDiasISO(30));
  const [hasta, setHasta] = useState(hoyISO());
  const [ingresos, setIngresos] = useState<IngresoPorCampo[]>([]);
  const [horasPico, setHorasPico] = useState<HoraPico[]>([]);
  const [totalGeneral, setTotalGeneral] = useState(0);
  const [totalReservas, setTotalReservas] = useState(0);
  const [horaPico, setHoraPico] = useState<number | null>(null);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sinPermisos, setSinPermisos] = useState(false);
  const [nota, setNota] = useState('');
  const peticionRef = useRef(0);

  const cargarReportes = async () => {
    if (desde > hasta) {
      setCargando(false);
      setError('El rango de fechas no es válido: Desde no puede ser mayor que Hasta.');
      return;
    }

    const id = ++peticionRef.current;

    try {
      setCargando(true);
      setError(null);
      setSinPermisos(false);

      const [ingresosRes, horasPicoRes] = await Promise.all([
        reportesService.ingresos(desde, hasta),
        reportesService.horasPico(desde, hasta),
      ]);

      if (id !== peticionRef.current) return;

      setIngresos(ingresosRes.data);
      setTotalGeneral(ingresosRes.meta.total_general);
      setNota(ingresosRes.meta.nota);

      setHorasPico(horasPicoRes.data);
      setTotalReservas(horasPicoRes.meta.total_reservas);
      setHoraPico(horasPicoRes.meta.hora_pico);
    } catch (err: any) {
      if (id !== peticionRef.current) return;

      const es403 = err.response?.status === 403;
      const mensaje = es403
        ? 'No tenés permisos para ver reportes'
        : err.response?.data?.message || 'Error al cargar reportes';

      setSinPermisos(es403);
      setError(mensaje);
      toast.error(mensaje);
    } finally {
      if (id === peticionRef.current) {
        setCargando(false);
      }
    }
  };

  useEffect(() => {
    cargarReportes();
  }, [desde, hasta]);

  const formatHora = (hora: number): string => {
    return `${hora.toString().padStart(2, '0')}:00`;
  };

  if (sinPermisos) {
    return (
      <div className="p-6">
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
        <p className="text-sm text-muted-foreground">
          Reportes gerenciales del sistema
        </p>
      </div>

      {/* Nota de vista operativa */}
      {nota && (
        <Alert className="border-blue-200 bg-blue-50">
          <AlertDescription className="text-sm text-blue-900">
            ⚠️ {nota}
          </AlertDescription>
        </Alert>
      )}

      {/* Selector de rango de fechas */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2 text-lg">
            <Calendar className="size-5" />
            Rango de fechas
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex flex-wrap items-end gap-4">
            <div className="space-y-2">
              <label htmlFor="dashboard-desde" className="text-sm font-medium">
                Desde
              </label>
              <Input
                id="dashboard-desde"
                type="date"
                value={desde}
                max={hasta}
                onChange={(e) => setDesde(e.target.value)}
                className="w-auto"
              />
            </div>
            <div className="space-y-2">
              <label htmlFor="dashboard-hasta" className="text-sm font-medium">
                Hasta
              </label>
              <Input
                id="dashboard-hasta"
                type="date"
                value={hasta}
                min={desde}
                onChange={(e) => setHasta(e.target.value)}
                className="w-auto"
              />
            </div>
            <Button onClick={cargarReportes} disabled={cargando}>
              {cargando ? 'Cargando...' : 'Actualizar'}
            </Button>
          </div>
        </CardContent>
      </Card>

      {error && (
        <Alert variant="destructive">
          <AlertDescription>{error}</AlertDescription>
        </Alert>
      )}

      {/* Métricas clave */}
      <div className="grid gap-4 md:grid-cols-3">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Ingresos totales</CardTitle>
            <DollarSign className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{formatMontoBoB(totalGeneral)}</div>
            <p className="text-xs text-muted-foreground">
              Período seleccionado
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Total reservas</CardTitle>
            <TrendingUp className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{totalReservas}</div>
            <p className="text-xs text-muted-foreground">
              Confirmadas en el período
            </p>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-sm font-medium">Hora pico</CardTitle>
            <Clock className="size-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">
              {horaPico !== null ? formatHora(horaPico) : '—'}
            </div>
            <p className="text-xs text-muted-foreground">
              Hora con más reservas
            </p>
          </CardContent>
        </Card>
      </div>

      {/* Gráfico de ingresos por campo */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Ingresos por campo</CardTitle>
        </CardHeader>
        <CardContent>
          {cargando ? (
            <div className="flex h-[300px] items-center justify-center text-muted-foreground">
              Cargando...
            </div>
          ) : ingresos.length === 0 ? (
            <div className="flex h-[300px] items-center justify-center text-muted-foreground">
              Sin datos para el período seleccionado
            </div>
          ) : (
            <ResponsiveContainer width="100%" height={300}>
              <BarChart data={ingresos}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis dataKey="campo_nombre" angle={-45} textAnchor="end" height={80} />
                <YAxis />
                <Tooltip
                  formatter={(value) => formatMontoBoB(Array.isArray(value) ? value[0] : value)}
                  labelFormatter={(label) => `Campo: ${label}`}
                />
                <Bar dataKey="total_ingresos" fill="#14b8a6" name="Ingresos" />
              </BarChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>

      {/* Gráfico de horas pico */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg">Distribución por hora del día</CardTitle>
        </CardHeader>
        <CardContent>
          {cargando ? (
            <div className="flex h-[300px] items-center justify-center text-muted-foreground">
              Cargando...
            </div>
          ) : (
            <ResponsiveContainer width="100%" height={300}>
              <LineChart data={horasPico}>
                <CartesianGrid strokeDasharray="3 3" />
                <XAxis
                  dataKey="hora"
                  tickFormatter={(hora) => formatHora(hora)}
                />
                <YAxis />
                <Tooltip
                  formatter={(value) => [`${value} reservas`, 'Total']}
                  labelFormatter={(hora) => `Hora: ${formatHora(Number(hora))}`}
                />
                <Legend />
                <Line
                  type="monotone"
                  dataKey="total_reservas"
                  stroke="#14b8a6"
                  strokeWidth={2}
                  name="Reservas"
                />
              </LineChart>
            </ResponsiveContainer>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
