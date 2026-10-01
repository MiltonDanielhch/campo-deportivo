import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Eye, Search, Filter } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { solicitudesReservaService } from '@/services/solicitudesReservaService';
import type { SolicitudReservaAdmin, EstadoReserva } from '@/types/reservas';
import DialogDetalleReserva from '@/components/reservas/DialogDetalleReserva';

const coloresEstado: Record<EstadoReserva, string> = {
  pendiente: 'bg-yellow-100 text-yellow-800 border-yellow-200',
  confirmada: 'bg-green-100 text-green-800 border-green-200',
  expirada: 'bg-red-100 text-red-800 border-red-200',
  cancelada: 'bg-slate-100 text-slate-800 border-slate-200',
  rechazada: 'bg-orange-100 text-orange-800 border-orange-200',
};

export default function Reservas() {
  const [solicitudes, setSolicitudes] = useState<SolicitudReservaAdmin[]>([]);
  const [cargando, setCargando] = useState(true);
  const [filtroEstado, setFiltroEstado] = useState<string>('todos');
  const [filtroCodigo, setFiltroCodigo] = useState('');
  const [paginaActual, setPaginaActual] = useState(1);
  const [totalPaginas, setTotalPaginas] = useState(1);
  const [detalleId, setDetalleId] = useState<string | null>(null);
  const [detalleOpen, setDetalleOpen] = useState(false);

  const cargarSolicitudes = async () => {
    setCargando(true);
    try {
      const params: any = { page: paginaActual, per_page: 20 };
      if (filtroEstado !== 'todos') params.estado = filtroEstado;
      if (filtroCodigo.trim()) params.codigo = filtroCodigo.trim();

      const response = await solicitudesReservaService.listar(params);
      setSolicitudes(response.data);
      setTotalPaginas(response.last_page);
    } catch (error: any) {
      toast.error('Error al cargar solicitudes: ' + (error.response?.data?.message || error.message));
    } finally {
      setCargando(false);
    }
  };

  useEffect(() => {
    cargarSolicitudes();
  }, [paginaActual, filtroEstado]);

  const handleBuscar = () => {
    setPaginaActual(1);
    cargarSolicitudes();
  };

  const abrirDetalle = (id: string) => {
    setDetalleId(id);
    setDetalleOpen(true);
  };

  const handleRefrescar = () => {
    cargarSolicitudes();
  };

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Reservas</h1>
        <p className="text-sm text-muted-foreground">
          Gestión operativa de solicitudes de reserva e integración con SIREB
        </p>
      </div>

      {/* Filtros */}
      <div className="flex flex-col md:flex-row gap-3 bg-white p-4 rounded-lg border">
        <div className="flex-1 flex gap-2">
          <Input
            placeholder="Buscar por código (RES-...)"
            value={filtroCodigo}
            onChange={(e) => setFiltroCodigo(e.target.value.toUpperCase())}
            onKeyDown={(e) => e.key === 'Enter' && handleBuscar()}
            className="font-mono uppercase"
          />
          <Button variant="outline" onClick={handleBuscar}>
            <Search className="w-4 h-4" />
          </Button>
        </div>
        <Select value={filtroEstado} onValueChange={setFiltroEstado}>
          <SelectTrigger className="w-full md:w-48">
            <Filter className="w-4 h-4 mr-2" />
            <SelectValue placeholder="Estado" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="todos">Todos los estados</SelectItem>
            <SelectItem value="pendiente">Pendiente</SelectItem>
            <SelectItem value="confirmada">Confirmada</SelectItem>
            <SelectItem value="expirada">Expirada</SelectItem>
            <SelectItem value="cancelada">Cancelada</SelectItem>
            <SelectItem value="rechazada">Rechazada</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Tabla */}
      <div className="bg-white rounded-lg border overflow-hidden">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Código</TableHead>
              <TableHead>Pagador</TableHead>
              <TableHead>Monto</TableHead>
              <TableHead>Estado</TableHead>
              <TableHead>SIREB</TableHead>
              <TableHead>Creado</TableHead>
              <TableHead className="text-right">Acciones</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {cargando ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-8">
                  Cargando...
                </TableCell>
              </TableRow>
            ) : solicitudes.length === 0 ? (
              <TableRow>
                <TableCell colSpan={7} className="text-center py-8 text-muted-foreground">
                  No se encontraron solicitudes
                </TableCell>
              </TableRow>
            ) : (
              solicitudes.map((s) => (
                <TableRow key={s.id} className="hover:bg-slate-50">
                  <TableCell className="font-mono text-xs">
                    {s.codigo_seguimiento}
                  </TableCell>
                  <TableCell>
                    <p className="text-sm">{s.nombre_pagador}</p>
                    <p className="text-xs text-muted-foreground">
                      {s.ci_nit_pagador || '—'}
                    </p>
                  </TableCell>
                  <TableCell className="tabular-nums font-semibold">
                    Bs {Number(s.monto_total).toFixed(2)}
                  </TableCell>
                  <TableCell>
                    <Badge className={coloresEstado[s.estado]}>
                      {s.estado}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {s.referencia_recaudaciones ? (
                      <code className="text-xs">{s.referencia_recaudaciones}</code>
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground">
                    {new Date(s.creado_en).toLocaleDateString('es-BO')}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button
                      variant="ghost"
                      size="sm"
                      onClick={() => abrirDetalle(s.id)}
                    >
                      <Eye className="w-4 h-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>

        {/* Paginación */}
        {totalPaginas > 1 && (
          <div className="flex items-center justify-between p-4 border-t">
            <p className="text-sm text-muted-foreground">
              Página {paginaActual} de {totalPaginas}
            </p>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={paginaActual === 1}
                onClick={() => setPaginaActual((p) => p - 1)}
              >
                Anterior
              </Button>
              <Button
                variant="outline"
                size="sm"
                disabled={paginaActual === totalPaginas}
                onClick={() => setPaginaActual((p) => p + 1)}
              >
                Siguiente
              </Button>
            </div>
          </div>
        )}
      </div>

      <DialogDetalleReserva
        solicitudId={detalleId}
        open={detalleOpen}
        onOpenChange={setDetalleOpen}
        onRefresh={handleRefrescar}
      />
    </div>
  );
}
