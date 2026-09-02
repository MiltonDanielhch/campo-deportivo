import { useCallback, useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { asignacionesService } from '@/services/asignacionesService';
import { camposService } from '@/services/camposService';
import { funcionariosService } from '@/services/funcionariosService';
import type { CampoDeportivo } from '@/types/parametricas';
import type { Funcionario, Rol } from '@/types/usuarios';

const ROL_CONTROL = 'funcionario_control';

export default function Asignaciones() {
  const [cargando, setCargando] = useState(true);
  const [roles, setRoles] = useState<Rol[]>([]);
  const [funcionariosControl, setFuncionariosControl] = useState<Funcionario[]>([]);
  const [campos, setCampos] = useState<CampoDeportivo[]>([]);

  const [funcionarioId, setFuncionarioId] = useState<string>('');
  const [asignadosIds, setAsignadosIds] = useState<Set<string>>(new Set());
  const [cargandoAsignaciones, setCargandoAsignaciones] = useState(false);
  const [operandoCampoId, setOperandoCampoId] = useState<string | null>(null);

  // Rol funcionario_control (para filtrar)
  const rolControl = useMemo(
    () => roles.find((r) => r.nombre === ROL_CONTROL),
    [roles],
  );

  // ─── Carga inicial: roles + campos ─────────────────────────────────────
  useEffect(() => {
    (async () => {
      setCargando(true);
      try {
        const [rolesData, camposData] = await Promise.all([
          funcionariosService.listarRoles(),
          camposService.listarTodos(),
        ]);
        setRoles(rolesData);
        setCampos(camposData);
      } catch {
        toast.error('No se pudieron cargar los datos iniciales');
      } finally {
        setCargando(false);
      }
    })();
  }, []);

  // ─── Cargar funcionarios_control cuando se conoce el rol ───────────────
  useEffect(() => {
    if (!rolControl) return;
    funcionariosService
      .listar({ rol_id: rolControl.id, estado: 'activo', page: 1 })
      .then((resp) => setFuncionariosControl(resp.data))
      .catch(() => toast.error('No se pudieron cargar los funcionarios de control'));
  }, [rolControl]);

  // ─── Al cambiar de funcionario, cargar sus asignaciones ────────────────
  const cargarAsignaciones = useCallback(async (id: string) => {
    setCargandoAsignaciones(true);
    try {
      const asignados = await asignacionesService.porFuncionario(id);
      setAsignadosIds(new Set(asignados.map((c) => c.id)));
    } catch {
      toast.error('No se pudieron cargar las asignaciones del funcionario');
    } finally {
      setCargandoAsignaciones(false);
    }
  }, []);

  const seleccionarFuncionario = (id: string) => {
    setFuncionarioId(id);
    if (id) cargarAsignaciones(id);
    else setAsignadosIds(new Set());
  };

  // ─── Toggle de asignación (optimista + rollback en error) ──────────────
  const toggleAsignacion = async (campo: CampoDeportivo) => {
    if (!funcionarioId || operandoCampoId) return;

    const estabaAsignado = asignadosIds.has(campo.id);
    setOperandoCampoId(campo.id);

    try {
      if (estabaAsignado) {
        await asignacionesService.desasignar(funcionarioId, campo.id);
        setAsignadosIds((prev) => {
          const siguiente = new Set(prev);
          siguiente.delete(campo.id);
          return siguiente;
        });
        toast.success(`"${campo.nombre}" desasignado`);
      } else {
        await asignacionesService.asignar(funcionarioId, campo.id);
        setAsignadosIds((prev) => new Set(prev).add(campo.id));
        toast.success(`"${campo.nombre}" asignado`);
      }
    } catch (error: any) {
      if (error.response?.status === 422) {
        const mensaje =
          error.response.data.errors?.funcionario_id?.[0] ??
          'No se pudo completar la asignación';
        toast.error(mensaje);
      } else {
        toast.error('Error al cambiar la asignación');
      }
    } finally {
      setOperandoCampoId(null);
    }
  };

  if (cargando) {
    return (
      <div className="p-6 text-muted-foreground">Cargando datos…</div>
    );
  }

  if (!rolControl) {
    return (
      <div className="p-6">
        <p className="text-destructive">
          No existe el rol "{ROL_CONTROL}" en el sistema. Verifica que haya sido
          creado en la migración inicial.
        </p>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight">Asignación de Control</h1>
        <p className="text-sm text-muted-foreground">
          Define qué campos deportivos supervisa cada funcionario de control
        </p>
      </div>

      {/* Selector de funcionario */}
      <div className="max-w-md space-y-2">
        <Label>Funcionario de control</Label>
        <Select value={funcionarioId} onValueChange={seleccionarFuncionario}>
          <SelectTrigger>
            <SelectValue placeholder="Selecciona un funcionario" />
          </SelectTrigger>
          <SelectContent>
            {funcionariosControl.map((f) => (
              <SelectItem key={f.id} value={f.id}>
                {f.nombre_completo}{' '}
                <span className="text-muted-foreground">· {f.usuario}</span>
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* Lista de campos a asignar */}
      {funcionarioId && (
        <Card>
          <CardHeader>
            <CardTitle>Campos supervisados</CardTitle>
            <CardDescription>
              {cargandoAsignaciones
                ? 'Cargando asignaciones…'
                : `${asignadosIds.size} campo(s) asignado(s) de ${campos.length} disponibles`}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {campos.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                No hay campos deportivos activos para asignar.
              </p>
            ) : (
              <div className="space-y-3">
                {campos.map((campo) => {
                  const asignado = asignadosIds.has(campo.id);
                  const operando = operandoCampoId === campo.id;
                  return (
                    <div
                      key={campo.id}
                      className="flex items-center gap-3 rounded-md border p-3"
                    >
                      <Checkbox
                        id={`campo-${campo.id}`}
                        checked={asignado}
                        disabled={operando || cargandoAsignaciones}
                        onCheckedChange={() => toggleAsignacion(campo)}
                      />
                      <Label
                        htmlFor={`campo-${campo.id}`}
                        className="flex flex-1 cursor-pointer items-center justify-between"
                      >
                        <div>
                          <span className="font-medium">{campo.nombre}</span>
                          <span className="ml-2 font-mono text-xs text-muted-foreground">
                            {campo.codigo}
                          </span>
                          <p className="text-xs text-muted-foreground">
                            {campo.direccion}
                          </p>
                        </div>
                        <Badge variant="secondary">
                          {campo.tipo_campo?.nombre ?? '—'}
                        </Badge>
                      </Label>
                    </div>
                  );
                })}
              </div>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  );
}
