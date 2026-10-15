import { useState, useEffect } from 'react';
import { useParams } from 'react-router-dom';
import { Plus, Trash2, Edit, Clock, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { piscinaService } from '@/services/piscinaService';
import type { PiscinaTurno } from '@/types/piscina';
import CrearTurnoDialog from '@/components/piscina/CrearTurnoDialog';

export default function TurnosPiscina() {
  const { campoId } = useParams<{ campoId: string }>();
  const [turnos, setTurnos] = useState<PiscinaTurno[]>([]);
  const [cargando, setCargando] = useState(true);
  const [dialogAbierto, setDialogAbierto] = useState(false);

  useEffect(() => {
    if (!campoId) return;
    cargarTurnos();
  }, [campoId]);

  const cargarTurnos = async () => {
    if (!campoId) return;
    try {
      const data = await piscinaService.obtenerTurnos(campoId);
      setTurnos(data);
    } catch (error) {
      console.error('Error al cargar turnos:', error);
    } finally {
      setCargando(false);
    }
  };

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
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-2xl font-bold">Turnos de Piscina</h1>
        <Button onClick={() => setDialogAbierto(true)}>
          <Plus className="w-4 h-4 mr-2" />
          Crear Turno
        </Button>
      </div>

      <CrearTurnoDialog
        abierto={dialogAbierto}
        onCerrar={() => setDialogAbierto(false)}
        campoId={campoId || ''}
        onCreado={cargarTurnos}
      />

      {turnos.length === 0 ? (
        <Card>
          <CardContent className="p-8 text-center">
            <p className="text-slate-500">No hay turnos configurados para este campo.</p>
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
                    <CardTitle>
                      {turno.hora_inicio} - {turno.hora_fin}
                    </CardTitle>
                    <Badge variant={turno.estado === 'activo' ? 'default' : 'secondary'}>
                      {turno.estado}
                    </Badge>
                  </div>
                  <div className="flex gap-2">
                    <Button variant="outline" size="sm">
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
                    <p className="text-sm text-slate-600">
                      Cupo total: {turno.cupo_total}
                    </p>
                  )}
                  <div className="mt-4">
                    <p className="text-sm font-medium mb-2">Perfiles:</p>
                    <div className="space-y-2">
                      {turno.perfiles.map((perfil) => (
                        <div
                          key={perfil.id}
                          className="flex items-center justify-between p-2 bg-slate-50 rounded"
                        >
                          <div className="flex items-center gap-2">
                            <Users className="w-4 h-4 text-slate-400" />
                            <span className="text-sm">
                              {perfil.perfil === 'mayor_12' && 'Mayores de 12'}
                              {perfil.perfil === 'menor_12' && 'Menores de 12'}
                              {perfil.perfil === 'institucion' && 'Institución'}
                            </span>
                          </div>
                          <div className="text-sm">
                            <span className="text-slate-600">
                              {perfil.reservado}/{perfil.cupo}
                            </span>
                            <span className="ml-2 text-slate-400">
                              Bs {Number(perfil.precio_unitario).toFixed(2)}
                            </span>
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
