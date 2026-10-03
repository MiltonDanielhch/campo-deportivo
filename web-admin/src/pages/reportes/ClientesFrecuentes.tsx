import { useState, useEffect } from 'react';
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
  const [nota, setNota] = useState('');

  const cargarClientes = async () => {
    try {
      setCargando(true);
      setError(null);
      const response = await reportesService.clientesFrecuentes(desde, hasta);
      setClientes(response.data);
      setTotalClientes(response.meta.total_clientes);
      setNota(response.meta.nota);
    } catch (err: any) {
      const mensaje =
        err.response?.status === 403
          ? 'No tenés permisos para ver clientes frecuentes'
          : err.response?.data?.message || 'Error al cargar clientes';
      setError(mensaje);
      toast.error(mensaje);
    } finally {
      setCargando(false);
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

  if (error && error.includes('permisos')) {
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
              <label className="text-sm font-medium">Desde</label>
              <Input
                type="date"
                value={desde}
                onChange={(e) => setDesde(e.target.value)}
                className="w-auto"
              />
            </div>
            <div className="space-y-2">
              <label className="text-sm font-medium">Hasta</label>
              <Input
                type="date"
                value={hasta}
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
              Top {clientes.length} clientes
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
