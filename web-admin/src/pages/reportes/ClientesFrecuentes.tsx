import { useState, useEffect, useRef } from 'react';
import { Calendar, Users } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { reportesService } from '@/services/reportesService';
import type { ClienteFrecuente } from '@/types/reportes';
import { format, subDays } from 'date-fns';
import { toast } from 'sonner';

export default function ClientesFrecuentes() {
  const [desde, setDesde] = useState(format(subDays(new Date(), 30), 'yyyy-MM-dd'));
  const [hasta, setHasta] = useState(format(new Date(), 'yyyy-MM-dd'));
  const [clientes, setClientes] = useState<ClienteFrecuente[]>([]);
  const [totalClientes, setTotalClientes] = useState(0);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [sinPermisos, setSinPermisos] = useState(false);
  const [nota, setNota] = useState('');
  const peticionRef = useRef(0);

  const cargarClientes = async () => {
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

      const response = await reportesService.clientesFrecuentes(desde, hasta);

      if (id !== peticionRef.current) return;

      setClientes(response.data);
      setTotalClientes(response.meta.total_clientes);
      setNota(response.meta.nota);
    } catch (err: any) {
      if (id !== peticionRef.current) return;

      const es403 = err.response?.status === 403;
      const mensaje = es403
        ? 'No tenés permisos para ver clientes frecuentes'
        : err.response?.data?.message || 'Error al cargar clientes';

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
    cargarClientes();
  }, [desde, hasta]);

  const formatMonto = (monto: number): string => {
    return new Intl.NumberFormat('es-BO', {
      style: 'currency',
      currency: 'BOB',
      minimumFractionDigits: 2,
    }).format(monto);
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
        <h1 className="text-2xl font-bold tracking-tight">Clientes Frecuentes</h1>
        <p className="text-sm text-muted-foreground">
          Contribuyentes con más reservas en el período
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
              <label htmlFor="clientes-desde" className="text-sm font-medium">
                Desde
              </label>
              <Input
                id="clientes-desde"
                type="date"
                value={desde}
                max={hasta}
                onChange={(e) => setDesde(e.target.value)}
                className="w-auto"
              />
            </div>
            <div className="space-y-2">
              <label htmlFor="clientes-hasta" className="text-sm font-medium">
                Hasta
              </label>
              <Input
                id="clientes-hasta"
                type="date"
                value={hasta}
                min={desde}
                onChange={(e) => setHasta(e.target.value)}
                className="w-auto"
              />
            </div>
            <Button onClick={cargarClientes} disabled={cargando}>
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

      {/* Tabla de clientes frecuentes */}
      <Card>
        <CardHeader>
          <CardTitle className="flex items-center justify-between text-lg">
            <div className="flex items-center gap-2">
              <Users className="size-5" />
              {clientes.length > 0 ? `Top ${clientes.length} clientes` : 'Clientes frecuentes'}
            </div>
            <span className="text-sm font-normal text-muted-foreground">
              Total: {totalClientes} clientes únicos
            </span>
          </CardTitle>
        </CardHeader>
        <CardContent>
          {cargando ? (
            <div className="flex h-[300px] items-center justify-center text-muted-foreground">
              Cargando...
            </div>
          ) : clientes.length === 0 ? (
            <div className="flex h-[300px] items-center justify-center text-muted-foreground">
              Sin datos para el período seleccionado
            </div>
          ) : (
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-[60px]">#</TableHead>
                    <TableHead>CI/NIT o Teléfono</TableHead>
                    <TableHead>Nombre más reciente</TableHead>
                    <TableHead className="text-right">Total reservas</TableHead>
                    <TableHead className="text-right">Monto total</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {clientes.map((cliente, idx) => (
                    <TableRow key={cliente.clave_agrupacion}>
                      <TableCell className="font-medium">{idx + 1}</TableCell>
                      <TableCell className="font-mono text-sm">
                        {cliente.clave_agrupacion}
                      </TableCell>
                      <TableCell>{cliente.nombre_mas_reciente}</TableCell>
                      <TableCell className="text-right font-semibold">
                        {cliente.total_reservas}
                      </TableCell>
                      <TableCell className="text-right font-semibold">
                        {formatMonto(cliente.monto_total_gastado)}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
