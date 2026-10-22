import { useState, useEffect, useCallback } from 'react';
import { useParams } from 'react-router-dom';
import { Plus, Trash2, Edit, Clock, Users, Copy } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { piscinaService } from '@/services/piscinaService';
import type { PiscinaTurno } from '@/types/piscina';
import CrearTurnoDialog from '@/components/piscina/CrearTurnoDialog';
import CrearTurnosLoteDialog from '@/components/piscina/CrearTurnosLoteDialog';

const iso = (d: Date) => d.toISOString().substring(0, 10);
const addDays = (base: string, n: number) => {
  const d = new Date(base + 'T00:00:00');
  d.setDate(d.getDate() + n);
  return iso(d);
};
const hoy = () => iso(new Date());

export default function TurnosPiscina() {
  const { campoId } = useParams<{ campoId: string }>();
  const [turnos, setTurnos] = useState<PiscinaTurno[]>([]);
  const [cargando, setCargando] = useState(true);
  const [dialogAbierto, setDialogAbierto] = useState(false);
  const [loteAbierto, setLoteAbierto] = useState(false);

  // ⟦LOTE⟧ sin rango, un lote de 60 días × N turnos satura la lista. Filtro por
  // defecto hoy..hoy+30 (el service ya acepta fecha_desde/fecha_hasta).
  const [desde, setDesde] = useState(hoy());
  const [hasta, setHasta] = useState(addDays(hoy(), 30));

  const cargarTurnos = useCallback(async () => {
    if (!campoId) return;
    setCargando(true);
    try {
      const data = await piscinaService.obtenerTurnos(campoId, {
        fecha_desde: desde,
        fecha_hasta: hasta,
      });
      setTurnos(data);
    } catch (error) {
      console.error('Error al cargar turnos:', error);
    } finally {
      setCargando(false);
    }
  }, [campoId, desde, hasta]);

  useEffect(() => {
    cargarTurnos();
  }, [cargarTurnos]);

  const handleEliminar = async (turnoId: string) => {
    if (!confirm('¿Estás seguro de eliminar este turno?')) return;
    try {
      await piscinaService.eliminarTurno(turnoId);
      cargarTurnos();
    } catch (error) {
      console.error('Error al eliminar turno:', error);
    }
  };

  if (cargando) {
    return <div className="p-6">Cargando turnos...</div>;
  }

  return (
    <div className="p-6">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h1 className="text-2xl font-bold">Turnos de Piscina</h1>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => setLoteAbierto(true)}>
            <Copy className="mr-2 w-4 h-4" />
            Generar en lote
          </Button>
          <Button onClick={() => setDialogAbierto(true)}>
            <Plus className="w-4 h-4 mr-2" />
            Crear Turno
          </Button>
        </div>
      </div>

      {/* Filtro de rango (necesario con lote) */}
      <div className="mb-4 flex flex-wrap items-end gap-3 rounded-lg border bg-card p-3">
        <div className="space-y-1">
          <Label className="text-xs">Desde</Label>
          <Input type="date" value={desde} onChange={(e) => setDesde(e.target.value)} className="w-40" />
        </div>
        <div className="space-y-1">
          <Label className="text-xs">Hasta</Label>
          <Input type="date" value={hasta} min={desde} onChange={(e) => setHasta(e.target.value)} className="w-40" />
        </div>
        <Button variant="outline" size="sm" onClick={cargarTurnos}>Aplicar</Button>
      </div>

      <CrearTurnoDialog
        abierto={dialogAbierto}
        onCerrar={() => setDialogAbierto(false)}
        campoId={campoId || ''}
        onCreado={cargarTurnos}
      />

      <CrearTurnosLoteDialog
        abierto={loteAbierto}
        onCerrar={() => setLoteAbierto(false)}
        campoId={campoId || ''}
        onCreado={(rango) => { setDesde(rango.desde); setHasta(rango.hasta); }}
      />

      {turnos.length === 0 ? (
        <Card>
          <CardContent className="p-8 text-center">
            <p className="text-slate-500">
              No hay turnos en este rango ({desde} – {hasta}). Usá «Generar en lote»
              para clonar un día de referencia, o «Crear Turno» para uno puntual.
            </p>
          </CardContent>
        </Card>
      ) : (
        <div className="space-y-4">
          {turnos.map((turno) => (
            <Card key={turno.id}>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <Clock className="w-5 h-5 text-teal-600" />
                    <CardTitle>{turno.hora_inicio} - {turno.hora_fin}</CardTitle>
                    <Badge variant={turno.estado === 'activo' ? 'default' : 'secondary'}>
                      {turno.estado}
                    </Badge>
                  </div>
                  <div className="flex gap-2">
                    {/* ⟦Fix 3 gate PO⟧ edición de perfiles pendiente: deshabilitado
                        con título honesto en vez de botón muerto que no hace nada. */}
                    <Button variant="outline" size="sm" disabled title="Edición de perfiles pendiente (Fix 3)">
                      <Edit className="w-4 h-4" />
                    </Button>
                    <Button variant="outline" size="sm" onClick={() => handleEliminar(turno.id)}>
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-2">
                  <p className="text-sm text-slate-600">Fecha: {turno.fecha}</p>
                  {turno.cupo_total && (
                    <p className="text-sm text-slate-600">Cupo total: {turno.cupo_total}</p>
                  )}
                  <div className="mt-4">
                    <p className="text-sm font-medium mb-2">Perfiles:</p>
                    <div className="space-y-2">
                      {turno.perfiles.map((perfil) => (
                        <div key={perfil.id} className="flex items-center justify-between p-2 bg-slate-50 rounded">
                          <div className="flex items-center gap-2">
                            <Users className="w-4 h-4 text-slate-400" />
                            <span className="text-sm">
                              {perfil.perfil === 'mayor_12' && 'Mayores de 12'}
                              {perfil.perfil === 'menor_12' && 'Menores de 12'}
                              {perfil.perfil === 'institucion' && 'Institución'}
                            </span>
                          </div>
                          <div className="text-sm">
                            <span className="text-slate-600">{perfil.reservado}/{perfil.cupo}</span>
                            <span className="ml-2 text-slate-400">Bs {Number(perfil.precio_unitario).toFixed(2)}</span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
