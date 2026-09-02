import { useCallback, useEffect, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { camposService } from '@/services/camposService';
import { tarifasService } from '@/services/tarifasService';
import type {
  CampoDeportivo,
  HistorialTarifas,
  TarifaCampo,
} from '@/types/parametricas';

const formatearPrecio = (precio: string) =>
  `Bs ${parseFloat(precio).toFixed(2)}/hora`;

const formatearFecha = (iso: string) =>
  new Date(iso).toLocaleString('es-BO', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });

const nombreCreador = (tarifa: TarifaCampo): string =>
  typeof tarifa.creado_por === 'object'
    ? tarifa.creado_por.nombre_completo
    : '—';

export default function Tarifas() {
  const { campoId } = useParams<{ campoId: string }>();
  const navigate = useNavigate();

  const [campo, setCampo] = useState<CampoDeportivo | null>(null);
  const [historial, setHistorial] = useState<HistorialTarifas | null>(null);
  const [cargando, setCargando] = useState(true);

  const [precio, setPrecio] = useState('');
  const [guardando, setGuardando] = useState(false);
  const [errorPrecio, setErrorPrecio] = useState<string | null>(null);

  const cargar = useCallback(async () => {
    if (!campoId) return;
    setCargando(true);
    try {
      const [campoData, tarifasData] = await Promise.all([
        camposService.obtener(campoId),
        tarifasService.historial(campoId),
      ]);
      setCampo(campoData);
      setHistorial(tarifasData);
    } catch {
      toast.error('No se pudieron cargar las tarifas del campo');
    } finally {
      setCargando(false);
    }
  }, [campoId]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorPrecio(null);

    const valor = parseFloat(precio);
    if (isNaN(valor) || valor <= 0) {
      setErrorPrecio('Ingresa un precio mayor a cero');
      return;
    }

    setGuardando(true);
    try {
      await tarifasService.crear(campoId!, { precio_por_hora: valor });
      toast.success('Nueva tarifa fijada. La anterior quedó cerrada en el historial.');
      setPrecio('');
      cargar();
    } catch (error: any) {
      if (error.response?.status === 422) {
        setErrorPrecio(
          error.response.data.errors?.precio_por_hora?.[0] ?? 'Precio inválido',
        );
      } else {
        toast.error('No se pudo fijar la nueva tarifa');
      }
    } finally {
      setGuardando(false);
    }
  };

  return (
    <div className="space-y-6 p-6">
      {/* Encabezado con volver */}
      <div className="flex items-center gap-3">
        <Button
          variant="outline"
          size="sm"
          onClick={() => navigate('/panel/parametricas/campos')}
        >
          <ArrowLeft className="h-4 w-4" /> Volver
        </Button>
        <div>
          <h1 className="text-2xl font-bold tracking-tight">
            Tarifas — {campo?.nombre ?? '…'}
          </h1>
          <p className="text-sm text-muted-foreground">
            Código {campo?.codigo ?? '—'} · historial de precios por hora
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        {/* Formulario de nueva tarifa */}
        <Card>
          <CardHeader>
            <CardTitle>Fijar nueva tarifa</CardTitle>
            <CardDescription>
              Al guardar, la tarifa activa se cierra automáticamente y la nueva
              queda vigente desde este momento. El historial completo se conserva.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="precio">Precio por hora (Bs)</Label>
                <Input
                  id="precio"
                  type="number"
                  step="0.01"
                  min="0.01"
                  placeholder="Ej: 150.00"
                  value={precio}
                  onChange={(e) => setPrecio(e.target.value)}
                  required
                />
                {errorPrecio && (
                  <p className="text-sm text-destructive">{errorPrecio}</p>
                )}
              </div>

              {/* Aviso claro del cierre automático */}
              <div className="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
                {historial?.activa
                  ? `Tarifa activa actual: ${formatearPrecio(
                      historial.activa.precio_por_hora,
                    )}. Al guardar, pasará al historial con fecha de cierre de hoy.`
                  : 'Este campo aún no tiene tarifas: la que fijes será la primera del historial.'}
              </div>

              <Button type="submit" disabled={guardando} className="w-full">
                {guardando ? 'Guardando…' : 'Fijar nueva tarifa'}
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Línea de tiempo de tarifas */}
        <Card>
          <CardHeader>
            <CardTitle>Línea de tiempo</CardTitle>
            <CardDescription>
              {historial?.historial.length ?? 0} tarifa(s) — la más reciente primero
            </CardDescription>
          </CardHeader>
          <CardContent>
            {cargando ? (
              <p className="text-center text-muted-foreground">Cargando…</p>
            ) : !historial || historial.historial.length === 0 ? (
              <p className="text-center text-muted-foreground">
                Este campo todavía no tiene tarifas registradas
              </p>
            ) : (
              <ol className="relative space-y-6 border-l pl-6">
                {historial.historial.map((tarifa) => {
                  const activa = tarifa.vigente_hasta === null;
                  return (
                    <li key={tarifa.id} className="relative">
                      <span
                        className={`absolute -left-[31px] top-1.5 h-2.5 w-2.5 rounded-full ${
                          activa ? 'bg-emerald-600' : 'bg-muted-foreground/40'
                        }`}
                      />
                      <div className="flex items-center gap-2">
                        <span className="text-lg font-semibold">
                          {formatearPrecio(tarifa.precio_por_hora)}
                        </span>
                        <Badge variant={activa ? 'default' : 'secondary'}>
                          {activa ? 'activa' : 'cerrada'}
                        </Badge>
                      </div>
                      <p className="text-sm text-muted-foreground">
                        Desde {formatearFecha(tarifa.vigente_desde)}
                        {tarifa.vigente_hasta &&
                          ` hasta ${formatearFecha(tarifa.vigente_hasta)}`}
                        {' · '}Fijada por {nombreCreador(tarifa)}
                      </p>
                    </li>
                  );
                })}
              </ol>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}